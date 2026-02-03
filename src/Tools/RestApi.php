<?php

declare(strict_types=1);

namespace WordPressBoost\Tools;

/**
 * REST API Tool
 *
 * Provides introspection into WordPress REST API endpoints.
 */
class RestApi extends BaseTool
{
    public function getToolDefinitions(): array
    {
        return [
            $this->createToolDefinition(
                'list_rest_endpoints',
                'List all registered REST API routes and endpoints',
                [
                    'namespace' => [
                        'type' => 'string',
                        'description' => 'Filter by namespace (e.g., wp/v2, wc/v3)',
                    ],
                    'search' => [
                        'type' => 'string',
                        'description' => 'Search routes by pattern',
                    ],
                ]
            ),
            $this->createToolDefinition(
                'get_rest_endpoint',
                'Get detailed information about a specific REST endpoint',
                [
                    'route' => [
                        'type' => 'string',
                        'description' => 'The REST route (e.g., /wp/v2/posts)',
                    ],
                ],
                ['route']
            ),
            $this->createToolDefinition(
                'list_rest_namespaces',
                'List all REST API namespaces'
            ),
        ];
    }

    public function handles(string $name): bool
    {
        return in_array($name, ['list_rest_endpoints', 'get_rest_endpoint', 'list_rest_namespaces']);
    }

    public function execute(string $name, array $arguments): mixed
    {
        return match ($name) {
            'list_rest_endpoints' => $this->listEndpoints(
                $arguments['namespace'] ?? null,
                $arguments['search'] ?? null
            ),
            'get_rest_endpoint' => $this->getEndpoint($arguments['route']),
            'list_rest_namespaces' => $this->listNamespaces(),
            default => throw new \RuntimeException("Unknown tool: {$name}"),
        };
    }

    private function listEndpoints(?string $namespace = null, ?string $search = null): array
    {
        $server = rest_get_server();
        $routes = $server->get_routes();
        $namespaces = $server->get_namespaces();

        $result = [];

        foreach ($routes as $route => $handlers) {
            // Filter by namespace
            if ($namespace !== null) {
                $routeNamespace = $this->getRouteNamespace($route, $namespaces);
                if ($routeNamespace !== $namespace) {
                    continue;
                }
            }

            // Filter by search pattern
            if ($search !== null && stripos($route, $search) === false) {
                continue;
            }

            $methods = [];
            foreach ($handlers as $handler) {
                if (isset($handler['methods'])) {
                    $methods = array_merge($methods, array_keys($handler['methods']));
                }
            }

            $result[] = [
                'route' => $route,
                'namespace' => $this->getRouteNamespace($route, $namespaces),
                'methods' => array_unique($methods),
                'endpoint_count' => count($handlers),
            ];
        }

        return [
            'count' => count($result),
            'routes' => $result,
        ];
    }

    private function getEndpoint(string $route): array
    {
        $server = rest_get_server();
        $routes = $server->get_routes();

        // Try exact match first
        if (!isset($routes[$route])) {
            // Try with leading slash
            $route = '/' . ltrim($route, '/');
        }

        if (!isset($routes[$route])) {
            return [
                'error' => "Route not found: {$route}",
                'suggestion' => 'Use list_rest_endpoints to see available routes',
            ];
        }

        $handlers = $routes[$route];
        $endpoints = [];

        foreach ($handlers as $handler) {
            $endpoint = [
                'methods' => isset($handler['methods']) ? array_keys($handler['methods']) : [],
                'accept_json' => $handler['accept_json'] ?? false,
                'accept_raw' => $handler['accept_raw'] ?? false,
                'show_in_index' => $handler['show_in_index'] ?? true,
            ];

            // Get callback info
            if (isset($handler['callback'])) {
                $endpoint['callback'] = $this->getCallbackInfo($handler['callback']);
            }

            // Get permission callback info
            if (isset($handler['permission_callback'])) {
                $endpoint['permission_callback'] = $this->getCallbackInfo($handler['permission_callback']);
            }

            // Get args schema
            if (isset($handler['args']) && !empty($handler['args'])) {
                $endpoint['args'] = $this->formatArgs($handler['args']);
            }

            $endpoints[] = $endpoint;
        }

        return [
            'route' => $route,
            'namespace' => $this->getRouteNamespace($route, $server->get_namespaces()),
            'endpoints' => $endpoints,
        ];
    }

    private function listNamespaces(): array
    {
        $server = rest_get_server();
        $namespaces = $server->get_namespaces();
        $routes = $server->get_routes();

        $result = [];

        foreach ($namespaces as $namespace) {
            $routeCount = 0;
            foreach ($routes as $route => $handlers) {
                if ($this->getRouteNamespace($route, $namespaces) === $namespace) {
                    $routeCount++;
                }
            }

            $result[] = [
                'namespace' => $namespace,
                'route_count' => $routeCount,
            ];
        }

        return [
            'count' => count($result),
            'namespaces' => $result,
        ];
    }

    private function getRouteNamespace(string $route, array $namespaces): string
    {
        foreach ($namespaces as $namespace) {
            if (strpos($route, '/' . $namespace) === 0) {
                return $namespace;
            }
        }
        return 'core';
    }

    private function getCallbackInfo($callback): array
    {
        if (is_string($callback)) {
            return [
                'type' => 'function',
                'name' => $callback,
            ];
        }

        if (is_array($callback)) {
            if (is_object($callback[0])) {
                return [
                    'type' => 'method',
                    'class' => get_class($callback[0]),
                    'method' => $callback[1],
                ];
            }
            return [
                'type' => 'static_method',
                'class' => $callback[0],
                'method' => $callback[1],
            ];
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

    private function formatArgs(array $args): array
    {
        $result = [];

        foreach ($args as $name => $config) {
            $arg = [
                'name' => $name,
                'required' => $config['required'] ?? false,
            ];

            if (isset($config['type'])) {
                $arg['type'] = $config['type'];
            }

            if (isset($config['description'])) {
                $arg['description'] = $config['description'];
            }

            if (isset($config['default'])) {
                $arg['default'] = $config['default'];
            }

            if (isset($config['enum'])) {
                $arg['enum'] = $config['enum'];
            }

            $result[] = $arg;
        }

        return $result;
    }
}
