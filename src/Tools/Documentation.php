<?php

declare(strict_types=1);

namespace WordPressBoost\Tools;

/**
 * Documentation Tool
 *
 * Search WordPress developer documentation.
 */
class Documentation extends BaseTool
{
    private const DOCS_SEARCH_URL = 'https://developer.wordpress.org/wp-json/wp/v2/search';
    private const CODEX_URL = 'https://codex.wordpress.org/api.php';

    public function getToolDefinitions(): array
    {
        return [
            $this->createToolDefinition(
                'search_docs',
                'Search WordPress developer documentation (developer.wordpress.org)',
                [
                    'query' => [
                        'type' => 'string',
                        'description' => 'Search query (e.g., "wp_query", "custom post type", "rest api")',
                    ],
                    'type' => [
                        'type' => 'string',
                        'description' => 'Filter by documentation type',
                        'enum' => ['all', 'functions', 'hooks', 'classes', 'methods'],
                    ],
                    'limit' => [
                        'type' => 'integer',
                        'description' => 'Maximum results to return (default: 10)',
                    ],
                ],
                ['query']
            ),
            $this->createToolDefinition(
                'get_function_reference',
                'Get reference documentation for a WordPress function',
                [
                    'function' => [
                        'type' => 'string',
                        'description' => 'Function name (e.g., "wp_insert_post", "get_posts")',
                    ],
                ],
                ['function']
            ),
            $this->createToolDefinition(
                'get_hook_reference',
                'Get reference documentation for a WordPress hook',
                [
                    'hook' => [
                        'type' => 'string',
                        'description' => 'Hook name (e.g., "init", "the_content")',
                    ],
                ],
                ['hook']
            ),
            $this->createToolDefinition(
                'list_function_parameters',
                'Get detailed parameter information for a WordPress function',
                [
                    'function' => [
                        'type' => 'string',
                        'description' => 'Function name',
                    ],
                ],
                ['function']
            ),
        ];
    }

    public function handles(string $name): bool
    {
        return in_array($name, [
            'search_docs', 'get_function_reference',
            'get_hook_reference', 'list_function_parameters'
        ]);
    }

    public function execute(string $name, array $arguments): mixed
    {
        return match ($name) {
            'search_docs' => $this->searchDocs(
                $arguments['query'],
                $arguments['type'] ?? 'all',
                $arguments['limit'] ?? 10
            ),
            'get_function_reference' => $this->getFunctionReference($arguments['function']),
            'get_hook_reference' => $this->getHookReference($arguments['hook']),
            'list_function_parameters' => $this->listFunctionParameters($arguments['function']),
            default => throw new \RuntimeException("Unknown tool: {$name}"),
        };
    }

    private function searchDocs(string $query, string $type = 'all', int $limit = 10): array
    {
        // First, try to get local function info if it looks like a function name
        if (preg_match('/^[a-z_]+$/i', $query)) {
            $localInfo = $this->getLocalFunctionInfo($query);
            if ($localInfo !== null) {
                return [
                    'query' => $query,
                    'source' => 'local',
                    'results' => [$localInfo],
                ];
            }
        }

        // Search online documentation
        $results = $this->searchOnlineDocs($query, $type, $limit);

        return [
            'query' => $query,
            'type' => $type,
            'count' => count($results),
            'results' => $results,
        ];
    }

    private function getFunctionReference(string $functionName): array
    {
        // Check if function exists locally
        if (function_exists($functionName)) {
            $localInfo = $this->getLocalFunctionInfo($functionName);

            if ($localInfo !== null) {
                return $localInfo;
            }
        }

        // Get common WordPress function info
        $wpFunctions = $this->getWordPressFunctionDatabase();

        if (isset($wpFunctions[$functionName])) {
            return $wpFunctions[$functionName];
        }

        // Try online search
        $results = $this->searchOnlineDocs($functionName, 'functions', 1);

        if (!empty($results)) {
            return [
                'function' => $functionName,
                'found' => true,
                'source' => 'online',
                'result' => $results[0],
            ];
        }

        return [
            'function' => $functionName,
            'found' => false,
            'suggestion' => 'Function may not be a core WordPress function, or may be provided by a plugin/theme.',
            'docs_url' => "https://developer.wordpress.org/reference/functions/{$functionName}/",
        ];
    }

    private function getHookReference(string $hookName): array
    {
        global $wp_filter, $wp_actions;

        // Check if hook is registered locally
        $isRegistered = isset($wp_filter[$hookName]);
        $isAction = isset($wp_actions[$hookName]);

        $result = [
            'hook' => $hookName,
            'type' => $isAction ? 'action' : 'filter',
            'registered_locally' => $isRegistered,
        ];

        if ($isRegistered) {
            $callbacks = $wp_filter[$hookName]->callbacks ?? [];
            $callbackCount = 0;
            foreach ($callbacks as $priority => $funcs) {
                $callbackCount += count($funcs);
            }
            $result['callback_count'] = $callbackCount;
        }

        // Get common hook info
        $commonHooks = $this->getCommonHooksDatabase();

        if (isset($commonHooks[$hookName])) {
            $result = array_merge($result, $commonHooks[$hookName]);
            $result['found'] = true;
        } else {
            $result['found'] = $isRegistered;
        }

        $result['docs_url'] = "https://developer.wordpress.org/reference/hooks/{$hookName}/";

        return $result;
    }

    private function listFunctionParameters(string $functionName): array
    {
        if (!function_exists($functionName)) {
            return [
                'error' => "Function not found: {$functionName}",
                'suggestion' => 'Function may not be loaded or may not exist.',
            ];
        }

        try {
            $reflection = new \ReflectionFunction($functionName);
            $parameters = [];

            foreach ($reflection->getParameters() as $param) {
                $paramInfo = [
                    'name' => $param->getName(),
                    'position' => $param->getPosition(),
                    'optional' => $param->isOptional(),
                ];

                if ($param->hasType()) {
                    $type = $param->getType();
                    if ($type instanceof \ReflectionNamedType) {
                        $paramInfo['type'] = $type->getName();
                        $paramInfo['nullable'] = $type->allowsNull();
                    }
                }

                if ($param->isDefaultValueAvailable()) {
                    try {
                        $default = $param->getDefaultValue();
                        $paramInfo['default'] = $default;
                    } catch (\ReflectionException $e) {
                        $paramInfo['default'] = '(unavailable)';
                    }
                }

                if ($param->isVariadic()) {
                    $paramInfo['variadic'] = true;
                }

                if ($param->isPassedByReference()) {
                    $paramInfo['by_reference'] = true;
                }

                $parameters[] = $paramInfo;
            }

            // Get return type
            $returnType = null;
            if ($reflection->hasReturnType()) {
                $type = $reflection->getReturnType();
                if ($type instanceof \ReflectionNamedType) {
                    $returnType = [
                        'type' => $type->getName(),
                        'nullable' => $type->allowsNull(),
                    ];
                }
            }

            return [
                'function' => $functionName,
                'parameter_count' => count($parameters),
                'required_count' => $reflection->getNumberOfRequiredParameters(),
                'parameters' => $parameters,
                'return_type' => $returnType,
                'file' => $reflection->getFileName(),
                'line' => $reflection->getStartLine(),
            ];
        } catch (\ReflectionException $e) {
            return [
                'error' => "Could not reflect function: " . $e->getMessage(),
            ];
        }
    }

    private function getLocalFunctionInfo(string $functionName): ?array
    {
        if (!function_exists($functionName)) {
            return null;
        }

        try {
            $reflection = new \ReflectionFunction($functionName);

            $params = [];
            foreach ($reflection->getParameters() as $param) {
                $paramStr = '';

                if ($param->hasType()) {
                    $type = $param->getType();
                    if ($type instanceof \ReflectionNamedType) {
                        $paramStr .= $type->getName() . ' ';
                    }
                }

                $paramStr .= '$' . $param->getName();

                if ($param->isOptional() && $param->isDefaultValueAvailable()) {
                    try {
                        $default = $param->getDefaultValue();
                        $paramStr .= ' = ' . var_export($default, true);
                    } catch (\Exception $e) {
                        $paramStr .= ' = ...';
                    }
                }

                $params[] = $paramStr;
            }

            $signature = $functionName . '(' . implode(', ', $params) . ')';

            // Try to get docblock
            $docComment = $reflection->getDocComment();
            $description = '';

            if ($docComment) {
                // Extract first line of description from docblock
                if (preg_match('/\*\s+([^@\n]+)/', $docComment, $matches)) {
                    $description = trim($matches[1]);
                }
            }

            return [
                'function' => $functionName,
                'signature' => $signature,
                'description' => $description,
                'file' => $reflection->getFileName(),
                'line' => $reflection->getStartLine(),
                'internal' => $reflection->isInternal(),
                'docs_url' => "https://developer.wordpress.org/reference/functions/{$functionName}/",
            ];
        } catch (\ReflectionException $e) {
            return null;
        }
    }

    private function searchOnlineDocs(string $query, string $type, int $limit): array
    {
        // Build search parameters
        $params = [
            'search' => $query,
            'per_page' => $limit,
            'type' => 'post',
        ];

        // Map type filter to post types
        $subtypes = [];
        switch ($type) {
            case 'functions':
                $subtypes = ['wp-parser-function'];
                break;
            case 'hooks':
                $subtypes = ['wp-parser-hook'];
                break;
            case 'classes':
                $subtypes = ['wp-parser-class'];
                break;
            case 'methods':
                $subtypes = ['wp-parser-method'];
                break;
        }

        if (!empty($subtypes)) {
            $params['subtype'] = implode(',', $subtypes);
        }

        $url = self::DOCS_SEARCH_URL . '?' . http_build_query($params);

        // Make request using WordPress HTTP API
        $response = wp_remote_get($url, [
            'timeout' => 10,
            'headers' => [
                'Accept' => 'application/json',
            ],
        ]);

        if (is_wp_error($response)) {
            return [];
        }

        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);

        if (!is_array($data)) {
            return [];
        }

        $results = [];
        foreach ($data as $item) {
            $results[] = [
                'title' => html_entity_decode($item['title'] ?? ''),
                'type' => $item['subtype'] ?? 'unknown',
                'url' => $item['url'] ?? '',
            ];
        }

        return $results;
    }

    private function getWordPressFunctionDatabase(): array
    {
        return [
            'wp_insert_post' => [
                'function' => 'wp_insert_post',
                'description' => 'Insert or update a post.',
                'parameters' => [
                    'postarr' => 'array - An array of elements that make up a post.',
                    'wp_error' => 'bool - Whether to return a WP_Error on failure. Default false.',
                    'fire_after_hooks' => 'bool - Whether to fire the after insert hooks. Default true.',
                ],
                'returns' => 'int|WP_Error - The post ID on success, WP_Error on failure.',
                'docs_url' => 'https://developer.wordpress.org/reference/functions/wp_insert_post/',
            ],
            'get_posts' => [
                'function' => 'get_posts',
                'description' => 'Retrieves an array of the latest posts, or posts matching the given criteria.',
                'parameters' => [
                    'args' => 'array - Arguments to retrieve posts.',
                ],
                'returns' => 'WP_Post[]|int[] - Array of post objects or post IDs.',
                'docs_url' => 'https://developer.wordpress.org/reference/functions/get_posts/',
            ],
            'wp_query' => [
                'function' => 'WP_Query',
                'description' => 'The WordPress Query class. Used to query posts from the database.',
                'docs_url' => 'https://developer.wordpress.org/reference/classes/wp_query/',
            ],
            'add_action' => [
                'function' => 'add_action',
                'description' => 'Hooks a function on to a specific action.',
                'parameters' => [
                    'hook_name' => 'string - The name of the action to add the callback to.',
                    'callback' => 'callable - The callback to be run when the action is called.',
                    'priority' => 'int - Used to specify the order. Default 10.',
                    'accepted_args' => 'int - The number of arguments the callback accepts. Default 1.',
                ],
                'returns' => 'true - Always returns true.',
                'docs_url' => 'https://developer.wordpress.org/reference/functions/add_action/',
            ],
            'add_filter' => [
                'function' => 'add_filter',
                'description' => 'Hooks a function to a specific filter action.',
                'parameters' => [
                    'hook_name' => 'string - The name of the filter to add the callback to.',
                    'callback' => 'callable - The callback to be run when the filter is applied.',
                    'priority' => 'int - Used to specify the order. Default 10.',
                    'accepted_args' => 'int - The number of arguments the callback accepts. Default 1.',
                ],
                'returns' => 'true - Always returns true.',
                'docs_url' => 'https://developer.wordpress.org/reference/functions/add_filter/',
            ],
            'register_post_type' => [
                'function' => 'register_post_type',
                'description' => 'Registers a post type.',
                'parameters' => [
                    'post_type' => 'string - Post type key. Must not exceed 20 characters.',
                    'args' => 'array|string - Array or string of arguments for registering a post type.',
                ],
                'returns' => 'WP_Post_Type|WP_Error - The registered post type object or error.',
                'docs_url' => 'https://developer.wordpress.org/reference/functions/register_post_type/',
            ],
            'register_taxonomy' => [
                'function' => 'register_taxonomy',
                'description' => 'Creates or modifies a taxonomy object.',
                'parameters' => [
                    'taxonomy' => 'string - Taxonomy key. Must not exceed 32 characters.',
                    'object_type' => 'array|string - Object type or array of object types with which the taxonomy should be associated.',
                    'args' => 'array|string - Array or query string of arguments for registering a taxonomy.',
                ],
                'returns' => 'WP_Taxonomy|WP_Error - The registered taxonomy object or error.',
                'docs_url' => 'https://developer.wordpress.org/reference/functions/register_taxonomy/',
            ],
            'get_option' => [
                'function' => 'get_option',
                'description' => 'Retrieves an option value based on an option name.',
                'parameters' => [
                    'option' => 'string - Name of the option to retrieve.',
                    'default' => 'mixed - Default value to return if option does not exist.',
                ],
                'returns' => 'mixed - Value of the option or default.',
                'docs_url' => 'https://developer.wordpress.org/reference/functions/get_option/',
            ],
            'update_option' => [
                'function' => 'update_option',
                'description' => 'Updates the value of an option that was already added.',
                'parameters' => [
                    'option' => 'string - Name of the option to update.',
                    'value' => 'mixed - Option value.',
                    'autoload' => "string|bool - Whether to load the option when WordPress starts. Default 'yes'.",
                ],
                'returns' => 'bool - True if value was updated, false otherwise.',
                'docs_url' => 'https://developer.wordpress.org/reference/functions/update_option/',
            ],
        ];
    }

    private function getCommonHooksDatabase(): array
    {
        return [
            'init' => [
                'description' => 'Fires after WordPress has finished loading but before any headers are sent.',
                'type' => 'action',
                'parameters' => 'None',
                'common_uses' => 'Register post types, taxonomies, and shortcodes.',
            ],
            'wp_enqueue_scripts' => [
                'description' => 'Fires when scripts and styles are enqueued for the frontend.',
                'type' => 'action',
                'parameters' => 'None',
                'common_uses' => 'Enqueue styles and scripts for the theme.',
            ],
            'admin_enqueue_scripts' => [
                'description' => 'Fires when scripts and styles are enqueued for all admin pages.',
                'type' => 'action',
                'parameters' => '$hook_suffix - The current admin page.',
                'common_uses' => 'Enqueue styles and scripts for admin pages.',
            ],
            'the_content' => [
                'description' => 'Filters the post content.',
                'type' => 'filter',
                'parameters' => '$content - The post content.',
                'common_uses' => 'Modify post content before display.',
            ],
            'the_title' => [
                'description' => 'Filters the post title.',
                'type' => 'filter',
                'parameters' => '$title, $id - The post title and post ID.',
                'common_uses' => 'Modify post title before display.',
            ],
            'wp_head' => [
                'description' => 'Fires in the head section of the page.',
                'type' => 'action',
                'parameters' => 'None',
                'common_uses' => 'Add meta tags, styles, or scripts to head.',
            ],
            'wp_footer' => [
                'description' => 'Fires before the closing body tag.',
                'type' => 'action',
                'parameters' => 'None',
                'common_uses' => 'Add scripts before closing body tag.',
            ],
            'save_post' => [
                'description' => 'Fires once a post has been saved.',
                'type' => 'action',
                'parameters' => '$post_ID, $post, $update - Post ID, post object, whether this is an update.',
                'common_uses' => 'Perform actions when post is saved.',
            ],
            'pre_get_posts' => [
                'description' => 'Fires after the query variable object is created, but before the actual query is run.',
                'type' => 'action',
                'parameters' => '$query - The WP_Query instance.',
                'common_uses' => 'Modify the main query or custom queries.',
            ],
            'template_redirect' => [
                'description' => 'Fires before determining which template to load.',
                'type' => 'action',
                'parameters' => 'None',
                'common_uses' => 'Redirect based on conditions, output custom content.',
            ],
        ];
    }
}
