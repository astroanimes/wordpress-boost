<?php

declare(strict_types=1);

namespace WordPressBoost\Tools;

/**
 * WP Shell Tool
 *
 * Execute PHP code in WordPress context (requires WP_DEBUG mode).
 */
class WpShell extends BaseTool
{
    public function getToolDefinitions(): array
    {
        return [
            $this->createToolDefinition(
                'wp_shell',
                'Execute PHP code in WordPress context. REQUIRES WP_DEBUG to be enabled. Use with caution.',
                [
                    'code' => [
                        'type' => 'string',
                        'description' => 'PHP code to execute. Do not include <?php tags.',
                    ],
                ],
                ['code']
            ),
        ];
    }

    public function handles(string $name): bool
    {
        return $name === 'wp_shell';
    }

    public function execute(string $name, array $arguments): mixed
    {
        if ($name !== 'wp_shell') {
            throw new \RuntimeException("Unknown tool: {$name}");
        }

        return $this->executeCode($arguments['code']);
    }

    private function executeCode(string $code): array
    {
        // Security check: require WP_DEBUG
        if (!defined('WP_DEBUG') || !WP_DEBUG) {
            return [
                'error' => 'wp_shell requires WP_DEBUG to be enabled for security reasons.',
                'suggestion' => 'Add define("WP_DEBUG", true); to wp-config.php',
            ];
        }

        // Prevent dangerous operations
        $dangerous = [
            'exec', 'shell_exec', 'system', 'passthru', 'popen', 'proc_open',
            'eval', 'create_function', 'unlink', 'rmdir', 'file_put_contents',
            'fwrite', 'chmod', 'chown', 'chgrp', 'mail', 'header',
        ];

        foreach ($dangerous as $func) {
            if (preg_match('/\b' . preg_quote($func, '/') . '\s*\(/i', $code)) {
                return [
                    'error' => "Dangerous function '{$func}' is not allowed.",
                    'code' => $code,
                ];
            }
        }

        // Capture output
        ob_start();
        $result = null;
        $error = null;

        try {
            // Execute the code
            $result = eval($code);
        } catch (\Throwable $e) {
            $error = [
                'type' => get_class($e),
                'message' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ];
        }

        $output = ob_get_clean();

        $response = [
            'code' => $code,
        ];

        if ($error !== null) {
            $response['error'] = $error;
        } else {
            if ($output !== '') {
                $response['output'] = $output;
            }

            if ($result !== null) {
                $response['return'] = $this->formatResult($result);
                $response['type'] = gettype($result);
            }
        }

        return $response;
    }

    private function formatResult($result): mixed
    {
        if (is_object($result)) {
            // Convert common WordPress objects to arrays
            if ($result instanceof \WP_Post) {
                return [
                    'ID' => $result->ID,
                    'post_title' => $result->post_title,
                    'post_type' => $result->post_type,
                    'post_status' => $result->post_status,
                    'post_date' => $result->post_date,
                    'post_content' => substr($result->post_content, 0, 500) . (strlen($result->post_content) > 500 ? '...' : ''),
                ];
            }

            if ($result instanceof \WP_User) {
                return [
                    'ID' => $result->ID,
                    'user_login' => $result->user_login,
                    'user_email' => $result->user_email,
                    'display_name' => $result->display_name,
                    'roles' => $result->roles,
                ];
            }

            if ($result instanceof \WP_Term) {
                return [
                    'term_id' => $result->term_id,
                    'name' => $result->name,
                    'slug' => $result->slug,
                    'taxonomy' => $result->taxonomy,
                    'count' => $result->count,
                ];
            }

            if ($result instanceof \WP_Error) {
                return [
                    'is_error' => true,
                    'codes' => $result->get_error_codes(),
                    'messages' => $result->get_error_messages(),
                ];
            }

            // Generic object
            return '[object ' . get_class($result) . ']';
        }

        if (is_array($result)) {
            // Limit array size
            $count = count($result);
            if ($count > 100) {
                $result = array_slice($result, 0, 100);
                $result['_truncated'] = "Showing 100 of {$count} items";
            }

            return array_map([$this, 'formatResult'], $result);
        }

        return $result;
    }
}
