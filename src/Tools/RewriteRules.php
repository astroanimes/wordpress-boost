<?php

declare(strict_types=1);

namespace WordPressBoost\Tools;

/**
 * Rewrite Rules Tool
 *
 * Provides introspection into WordPress URL rewrite rules.
 */
class RewriteRules extends BaseTool
{
    public function getToolDefinitions(): array
    {
        return [
            $this->createToolDefinition(
                'list_rewrite_rules',
                'List all URL rewrite rules',
                [
                    'search' => [
                        'type' => 'string',
                        'description' => 'Search rules by pattern or redirect',
                    ],
                    'limit' => [
                        'type' => 'integer',
                        'description' => 'Maximum rules to return (default: 100)',
                    ],
                ]
            ),
            $this->createToolDefinition(
                'test_rewrite',
                'Test which rewrite rule matches a given URL',
                [
                    'url' => [
                        'type' => 'string',
                        'description' => 'The URL path to test (e.g., /sample-post/)',
                    ],
                ],
                ['url']
            ),
            $this->createToolDefinition(
                'get_rewrite_tags',
                'List all registered rewrite tags'
            ),
        ];
    }

    public function handles(string $name): bool
    {
        return in_array($name, ['list_rewrite_rules', 'test_rewrite', 'get_rewrite_tags']);
    }

    public function execute(string $name, array $arguments): mixed
    {
        return match ($name) {
            'list_rewrite_rules' => $this->listRewriteRules(
                $arguments['search'] ?? null,
                $arguments['limit'] ?? 100
            ),
            'test_rewrite' => $this->testRewrite($arguments['url']),
            'get_rewrite_tags' => $this->getRewriteTags(),
            default => throw new \RuntimeException("Unknown tool: {$name}"),
        };
    }

    private function listRewriteRules(?string $search = null, int $limit = 100): array
    {
        global $wp_rewrite;

        $rules = get_option('rewrite_rules', []);

        if (empty($rules)) {
            return [
                'error' => 'No rewrite rules found. Run "flush_rewrite_rules()" to generate them.',
                'permalink_structure' => get_option('permalink_structure'),
            ];
        }

        $result = [];
        $count = 0;

        foreach ($rules as $pattern => $redirect) {
            if ($count >= $limit) {
                break;
            }

            // Filter by search pattern
            if ($search !== null) {
                if (stripos($pattern, $search) === false && stripos($redirect, $search) === false) {
                    continue;
                }
            }

            $result[] = [
                'pattern' => $pattern,
                'redirect' => $redirect,
                'regex' => $this->isRegex($pattern),
            ];

            $count++;
        }

        return [
            'count' => count($result),
            'total' => count($rules),
            'limit' => $limit,
            'permalink_structure' => get_option('permalink_structure'),
            'rules' => $result,
        ];
    }

    private function testRewrite(string $url): array
    {
        global $wp_rewrite;

        $rules = get_option('rewrite_rules', []);

        if (empty($rules)) {
            return [
                'error' => 'No rewrite rules found.',
            ];
        }

        // Clean the URL
        $url = trim($url, '/');
        $url = ltrim(parse_url($url, PHP_URL_PATH) ?? $url, '/');

        $matches = [];

        foreach ($rules as $pattern => $redirect) {
            if (preg_match('#^' . $pattern . '#', $url, $match)) {
                // Build the actual redirect URL
                $actualRedirect = $redirect;
                foreach ($match as $i => $value) {
                    if ($i > 0) {
                        $actualRedirect = str_replace('$matches[' . $i . ']', $value, $actualRedirect);
                    }
                }

                $matches[] = [
                    'pattern' => $pattern,
                    'redirect' => $redirect,
                    'actual_redirect' => $actualRedirect,
                    'captures' => array_slice($match, 1),
                ];
            }
        }

        if (empty($matches)) {
            return [
                'url' => $url,
                'matched' => false,
                'message' => 'No rewrite rules match this URL.',
            ];
        }

        // The first match is the one WordPress would use
        return [
            'url' => $url,
            'matched' => true,
            'active_rule' => $matches[0],
            'all_matches' => $matches,
        ];
    }

    private function getRewriteTags(): array
    {
        global $wp_rewrite;

        $tags = [];

        // Get registered rewrite tags
        if (!empty($wp_rewrite->rewritecode)) {
            foreach ($wp_rewrite->rewritecode as $i => $code) {
                $tags[] = [
                    'tag' => $code,
                    'regex' => $wp_rewrite->rewritereplace[$i] ?? '',
                    'query' => $wp_rewrite->queryreplace[$i] ?? '',
                ];
            }
        }

        // Add extra permastructs
        $extraPermastructs = [];
        if (!empty($wp_rewrite->extra_permastructs)) {
            foreach ($wp_rewrite->extra_permastructs as $name => $struct) {
                $extraPermastructs[] = [
                    'name' => $name,
                    'struct' => $struct['struct'] ?? '',
                    'with_front' => $struct['with_front'] ?? true,
                    'ep_mask' => $struct['ep_mask'] ?? EP_NONE,
                ];
            }
        }

        return [
            'tags' => $tags,
            'extra_permastructs' => $extraPermastructs,
            'front' => $wp_rewrite->front,
            'root' => $wp_rewrite->root,
            'permalink_structure' => $wp_rewrite->permalink_structure,
        ];
    }

    private function isRegex(string $pattern): bool
    {
        // Check if the pattern contains regex metacharacters
        return preg_match('/[()[\]{}.*+?^$|\\\\]/', $pattern) === 1;
    }
}
