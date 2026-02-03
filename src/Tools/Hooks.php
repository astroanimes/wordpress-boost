<?php

declare(strict_types=1);

namespace WordPressBoost\Tools;

/**
 * Hooks Tool
 *
 * Provides introspection into WordPress actions and filters.
 */
class Hooks extends BaseTool
{
    public function getToolDefinitions(): array
    {
        return [
            $this->createToolDefinition(
                'list_hooks',
                'List all registered WordPress actions and filters',
                [
                    'type' => [
                        'type' => 'string',
                        'description' => 'Filter by type: action, filter, or all',
                        'enum' => ['action', 'filter', 'all'],
                    ],
                    'pattern' => [
                        'type' => 'string',
                        'description' => 'Filter hooks by pattern (supports wildcards like woocommerce_*)',
                    ],
                    'limit' => [
                        'type' => 'integer',
                        'description' => 'Maximum number of hooks to return (default: 100)',
                    ],
                ]
            ),
            $this->createToolDefinition(
                'get_hook_callbacks',
                'Get all callbacks attached to a specific hook with their priorities',
                [
                    'hook' => [
                        'type' => 'string',
                        'description' => 'The hook name to inspect',
                    ],
                ],
                ['hook']
            ),
            $this->createToolDefinition(
                'search_hooks',
                'Search for hooks by name pattern',
                [
                    'pattern' => [
                        'type' => 'string',
                        'description' => 'Search pattern (case-insensitive, supports wildcards)',
                    ],
                    'limit' => [
                        'type' => 'integer',
                        'description' => 'Maximum results to return (default: 50)',
                    ],
                ],
                ['pattern']
            ),
        ];
    }

    public function handles(string $name): bool
    {
        return in_array($name, ['list_hooks', 'get_hook_callbacks', 'search_hooks']);
    }

    public function execute(string $name, array $arguments): mixed
    {
        return match ($name) {
            'list_hooks' => $this->listHooks(
                $arguments['type'] ?? 'all',
                $arguments['pattern'] ?? null,
                $arguments['limit'] ?? 100
            ),
            'get_hook_callbacks' => $this->getHookCallbacks($arguments['hook']),
            'search_hooks' => $this->searchHooks(
                $arguments['pattern'],
                $arguments['limit'] ?? 50
            ),
            default => throw new \RuntimeException("Unknown tool: {$name}"),
        };
    }

    private function listHooks(string $type = 'all', ?string $pattern = null, int $limit = 100): array
    {
        global $wp_filter, $wp_actions;

        $hooks = [];
        $count = 0;

        foreach ($wp_filter as $hookName => $hookData) {
            if ($count >= $limit) {
                break;
            }

            // Check pattern match
            if ($pattern !== null && !$this->matchesPattern($hookName, $pattern)) {
                continue;
            }

            // Determine hook type
            $isAction = isset($wp_actions[$hookName]);
            $hookType = $isAction ? 'action' : 'filter';

            // Filter by type
            if ($type !== 'all' && $type !== $hookType) {
                continue;
            }

            $callbacks = $hookData->callbacks ?? [];
            $callbackCount = 0;
            $priorities = [];

            foreach ($callbacks as $priority => $funcs) {
                $callbackCount += count($funcs);
                $priorities[] = $priority;
            }

            $hooks[] = [
                'name' => $hookName,
                'type' => $hookType,
                'callback_count' => $callbackCount,
                'priorities' => array_unique($priorities),
            ];

            $count++;
        }

        // Sort by name
        usort($hooks, fn($a, $b) => strcmp($a['name'], $b['name']));

        return [
            'count' => count($hooks),
            'limit' => $limit,
            'hooks' => $hooks,
        ];
    }

    private function getHookCallbacks(string $hookName): array
    {
        global $wp_filter, $wp_actions;

        if (!isset($wp_filter[$hookName])) {
            return [
                'hook' => $hookName,
                'exists' => false,
                'callbacks' => [],
            ];
        }

        $hookData = $wp_filter[$hookName];
        $callbacks = $hookData->callbacks ?? [];
        $isAction = isset($wp_actions[$hookName]);

        $result = [];

        foreach ($callbacks as $priority => $funcs) {
            foreach ($funcs as $func) {
                $callback = $func['function'];
                $acceptedArgs = $func['accepted_args'];

                $callbackInfo = [
                    'priority' => $priority,
                    'accepted_args' => $acceptedArgs,
                ];

                // Resolve callback information
                if (is_string($callback)) {
                    $callbackInfo['type'] = 'function';
                    $callbackInfo['function'] = $callback;
                } elseif (is_array($callback)) {
                    if (is_object($callback[0])) {
                        $callbackInfo['type'] = 'method';
                        $callbackInfo['class'] = get_class($callback[0]);
                        $callbackInfo['method'] = $callback[1];
                    } else {
                        $callbackInfo['type'] = 'static_method';
                        $callbackInfo['class'] = $callback[0];
                        $callbackInfo['method'] = $callback[1];
                    }
                } elseif ($callback instanceof \Closure) {
                    $callbackInfo['type'] = 'closure';
                    $reflection = new \ReflectionFunction($callback);
                    $callbackInfo['file'] = $reflection->getFileName();
                    $callbackInfo['line'] = $reflection->getStartLine();
                } else {
                    $callbackInfo['type'] = 'unknown';
                }

                // Try to get source location for functions/methods
                if (isset($callbackInfo['function']) && function_exists($callbackInfo['function'])) {
                    try {
                        $reflection = new \ReflectionFunction($callbackInfo['function']);
                        $callbackInfo['file'] = $reflection->getFileName();
                        $callbackInfo['line'] = $reflection->getStartLine();
                    } catch (\ReflectionException $e) {
                        // Ignore reflection errors
                    }
                } elseif (isset($callbackInfo['class'], $callbackInfo['method'])) {
                    try {
                        $reflection = new \ReflectionMethod($callbackInfo['class'], $callbackInfo['method']);
                        $callbackInfo['file'] = $reflection->getFileName();
                        $callbackInfo['line'] = $reflection->getStartLine();
                    } catch (\ReflectionException $e) {
                        // Ignore reflection errors
                    }
                }

                $result[] = $callbackInfo;
            }
        }

        // Sort by priority
        usort($result, fn($a, $b) => $a['priority'] <=> $b['priority']);

        return [
            'hook' => $hookName,
            'type' => $isAction ? 'action' : 'filter',
            'exists' => true,
            'callback_count' => count($result),
            'callbacks' => $result,
        ];
    }

    private function searchHooks(string $pattern, int $limit = 50): array
    {
        global $wp_filter, $wp_actions;

        $matches = [];

        foreach ($wp_filter as $hookName => $hookData) {
            if (count($matches) >= $limit) {
                break;
            }

            if ($this->matchesPattern($hookName, $pattern)) {
                $isAction = isset($wp_actions[$hookName]);
                $callbacks = $hookData->callbacks ?? [];
                $callbackCount = 0;

                foreach ($callbacks as $funcs) {
                    $callbackCount += count($funcs);
                }

                $matches[] = [
                    'name' => $hookName,
                    'type' => $isAction ? 'action' : 'filter',
                    'callback_count' => $callbackCount,
                ];
            }
        }

        // Sort by name
        usort($matches, fn($a, $b) => strcmp($a['name'], $b['name']));

        return [
            'pattern' => $pattern,
            'count' => count($matches),
            'limit' => $limit,
            'hooks' => $matches,
        ];
    }

    /**
     * Check if a hook name matches a pattern (supports * wildcard)
     */
    private function matchesPattern(string $hookName, string $pattern): bool
    {
        // Convert wildcard pattern to regex
        $regex = '/^' . str_replace(
            ['*', '?'],
            ['.*', '.'],
            preg_quote($pattern, '/')
        ) . '$/i';

        return (bool) preg_match($regex, $hookName);
    }
}
