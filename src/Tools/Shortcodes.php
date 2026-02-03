<?php

declare(strict_types=1);

namespace WordPressBoost\Tools;

/**
 * Shortcodes Tool
 *
 * Provides introspection into registered WordPress shortcodes.
 */
class Shortcodes extends BaseTool
{
    public function getToolDefinitions(): array
    {
        return [
            $this->createToolDefinition(
                'list_shortcodes',
                'List all registered shortcodes with their callbacks',
                [
                    'search' => [
                        'type' => 'string',
                        'description' => 'Search shortcodes by name pattern',
                    ],
                ]
            ),
            $this->createToolDefinition(
                'get_shortcode',
                'Get detailed information about a specific shortcode',
                [
                    'tag' => [
                        'type' => 'string',
                        'description' => 'The shortcode tag name',
                    ],
                ],
                ['tag']
            ),
        ];
    }

    public function handles(string $name): bool
    {
        return in_array($name, ['list_shortcodes', 'get_shortcode']);
    }

    public function execute(string $name, array $arguments): mixed
    {
        return match ($name) {
            'list_shortcodes' => $this->listShortcodes($arguments['search'] ?? null),
            'get_shortcode' => $this->getShortcode($arguments['tag']),
            default => throw new \RuntimeException("Unknown tool: {$name}"),
        };
    }

    private function listShortcodes(?string $search = null): array
    {
        global $shortcode_tags;

        $result = [];

        foreach ($shortcode_tags as $tag => $callback) {
            // Filter by search pattern
            if ($search !== null && stripos($tag, $search) === false) {
                continue;
            }

            $result[] = [
                'tag' => $tag,
                'callback' => $this->getCallbackInfo($callback),
            ];
        }

        // Sort by tag name
        usort($result, fn($a, $b) => strcmp($a['tag'], $b['tag']));

        return [
            'count' => count($result),
            'shortcodes' => $result,
        ];
    }

    private function getShortcode(string $tag): array
    {
        global $shortcode_tags;

        if (!isset($shortcode_tags[$tag])) {
            return [
                'error' => "Shortcode not found: {$tag}",
                'available_shortcodes' => array_keys($shortcode_tags),
            ];
        }

        $callback = $shortcode_tags[$tag];
        $callbackInfo = $this->getCallbackInfo($callback);

        // Try to extract parameters from callback
        $parameters = $this->extractParameters($callback);

        return [
            'tag' => $tag,
            'usage' => "[{$tag}]",
            'callback' => $callbackInfo,
            'parameters' => $parameters,
        ];
    }

    private function getCallbackInfo($callback): array
    {
        if (is_string($callback)) {
            $info = [
                'type' => 'function',
                'name' => $callback,
            ];

            if (function_exists($callback)) {
                try {
                    $reflection = new \ReflectionFunction($callback);
                    $info['file'] = $reflection->getFileName();
                    $info['line'] = $reflection->getStartLine();
                } catch (\ReflectionException $e) {
                    // Ignore
                }
            }

            return $info;
        }

        if (is_array($callback)) {
            $className = is_object($callback[0]) ? get_class($callback[0]) : $callback[0];
            $methodName = $callback[1];

            $info = [
                'type' => is_object($callback[0]) ? 'method' : 'static_method',
                'class' => $className,
                'method' => $methodName,
            ];

            try {
                $reflection = new \ReflectionMethod($className, $methodName);
                $info['file'] = $reflection->getFileName();
                $info['line'] = $reflection->getStartLine();
            } catch (\ReflectionException $e) {
                // Ignore
            }

            return $info;
        }

        if ($callback instanceof \Closure) {
            $reflection = new \ReflectionFunction($callback);
            return [
                'type' => 'closure',
                'file' => $reflection->getFileName(),
                'line' => $reflection->getStartLine(),
            ];
        }

        return ['type' => 'unknown'];
    }

    /**
     * Try to extract shortcode parameters by inspecting the callback's first parameter
     */
    private function extractParameters($callback): array
    {
        try {
            if (is_string($callback) && function_exists($callback)) {
                $reflection = new \ReflectionFunction($callback);
            } elseif (is_array($callback)) {
                $className = is_object($callback[0]) ? get_class($callback[0]) : $callback[0];
                $reflection = new \ReflectionMethod($className, $callback[1]);
            } elseif ($callback instanceof \Closure) {
                $reflection = new \ReflectionFunction($callback);
            } else {
                return [];
            }

            // Get the first parameter which should be $atts
            $params = $reflection->getParameters();

            if (empty($params)) {
                return [];
            }

            // Try to read the function source to find shortcode_atts call
            $file = $reflection->getFileName();
            $startLine = $reflection->getStartLine();
            $endLine = $reflection->getEndLine();

            if ($file && is_readable($file)) {
                $source = file_get_contents($file);
                $lines = explode("\n", $source);
                $functionSource = implode("\n", array_slice($lines, $startLine - 1, $endLine - $startLine + 1));

                // Look for shortcode_atts pattern
                if (preg_match('/shortcode_atts\s*\(\s*\[([^\]]+)\]/s', $functionSource, $matches)) {
                    $attsString = $matches[1];
                    $parameters = [];

                    // Parse the array syntax
                    preg_match_all("/['\"]([^'\"]+)['\"]\s*=>\s*([^,\]]+)/", $attsString, $attsMatches, PREG_SET_ORDER);

                    foreach ($attsMatches as $match) {
                        $parameters[] = [
                            'name' => $match[1],
                            'default' => trim($match[2], "' \""),
                        ];
                    }

                    return $parameters;
                }
            }
        } catch (\Exception $e) {
            // Ignore errors during parameter extraction
        }

        return [];
    }
}
