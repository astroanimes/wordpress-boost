<?php

declare(strict_types=1);

namespace WordPressBoost\Tools;

/**
 * Site Information Tool
 *
 * Provides information about the WordPress installation.
 */
class SiteInfo extends BaseTool
{
    public function getToolDefinitions(): array
    {
        return [
            $this->createToolDefinition(
                'site_info',
                'Get WordPress site information including version, PHP version, active theme, plugins, and multisite status'
            ),
            $this->createToolDefinition(
                'list_plugins',
                'List all installed plugins with versions, status, and update availability',
                [
                    'status' => [
                        'type' => 'string',
                        'description' => 'Filter by status: active, inactive, must-use, drop-in, or all',
                        'enum' => ['active', 'inactive', 'must-use', 'drop-in', 'all'],
                    ],
                ]
            ),
            $this->createToolDefinition(
                'list_themes',
                'List all available themes with versions, active status, and parent/child relationships'
            ),
        ];
    }

    public function handles(string $name): bool
    {
        return in_array($name, ['site_info', 'list_plugins', 'list_themes']);
    }

    public function execute(string $name, array $arguments): mixed
    {
        return match ($name) {
            'site_info' => $this->getSiteInfo(),
            'list_plugins' => $this->listPlugins($arguments['status'] ?? 'all'),
            'list_themes' => $this->listThemes(),
            default => throw new \RuntimeException("Unknown tool: {$name}"),
        };
    }

    private function getSiteInfo(): array
    {
        global $wp_version, $wpdb;

        if (!function_exists('get_plugins')) {
            require_once ABSPATH . 'wp-admin/includes/plugin.php';
        }

        $theme = wp_get_theme();
        $plugins = get_plugins();
        $activePlugins = get_option('active_plugins', []);

        return [
            'wordpress' => [
                'version' => $wp_version,
                'home_url' => home_url(),
                'site_url' => site_url(),
                'admin_email' => get_option('admin_email'),
                'language' => get_locale(),
                'timezone' => wp_timezone_string(),
                'is_multisite' => is_multisite(),
            ],
            'php' => [
                'version' => PHP_VERSION,
                'memory_limit' => ini_get('memory_limit'),
                'max_execution_time' => ini_get('max_execution_time'),
                'upload_max_filesize' => ini_get('upload_max_filesize'),
                'post_max_size' => ini_get('post_max_size'),
            ],
            'database' => [
                'server_version' => $wpdb->db_version(),
                'client_version' => $wpdb->db_server_info(),
                'prefix' => $wpdb->prefix,
                'charset' => $wpdb->charset,
                'collate' => $wpdb->collate,
            ],
            'theme' => [
                'name' => $theme->get('Name'),
                'version' => $theme->get('Version'),
                'template' => $theme->get_template(),
                'stylesheet' => $theme->get_stylesheet(),
                'is_child_theme' => $theme->parent() !== false,
                'parent_theme' => $theme->parent() ? $theme->parent()->get('Name') : null,
            ],
            'plugins' => [
                'total' => count($plugins),
                'active' => count($activePlugins),
            ],
            'debug' => [
                'wp_debug' => defined('WP_DEBUG') && WP_DEBUG,
                'wp_debug_log' => defined('WP_DEBUG_LOG') && WP_DEBUG_LOG,
                'wp_debug_display' => defined('WP_DEBUG_DISPLAY') && WP_DEBUG_DISPLAY,
                'script_debug' => defined('SCRIPT_DEBUG') && SCRIPT_DEBUG,
            ],
            'constants' => [
                'ABSPATH' => ABSPATH,
                'WP_CONTENT_DIR' => WP_CONTENT_DIR,
                'WP_PLUGIN_DIR' => WP_PLUGIN_DIR,
                'WPINC' => WPINC,
            ],
        ];
    }

    private function listPlugins(string $status = 'all'): array
    {
        if (!function_exists('get_plugins')) {
            require_once ABSPATH . 'wp-admin/includes/plugin.php';
        }

        $allPlugins = get_plugins();
        $activePlugins = get_option('active_plugins', []);
        $mustUsePlugins = get_mu_plugins();
        $dropins = get_dropins();

        $result = [];

        // Regular plugins
        foreach ($allPlugins as $pluginFile => $pluginData) {
            $isActive = in_array($pluginFile, $activePlugins);

            if ($status === 'all' ||
                ($status === 'active' && $isActive) ||
                ($status === 'inactive' && !$isActive)) {
                $result[] = [
                    'file' => $pluginFile,
                    'name' => $pluginData['Name'],
                    'version' => $pluginData['Version'],
                    'description' => $pluginData['Description'],
                    'author' => $pluginData['Author'],
                    'author_uri' => $pluginData['AuthorURI'],
                    'plugin_uri' => $pluginData['PluginURI'],
                    'text_domain' => $pluginData['TextDomain'],
                    'status' => $isActive ? 'active' : 'inactive',
                    'type' => 'regular',
                ];
            }
        }

        // Must-use plugins
        if ($status === 'all' || $status === 'must-use') {
            foreach ($mustUsePlugins as $pluginFile => $pluginData) {
                $result[] = [
                    'file' => $pluginFile,
                    'name' => $pluginData['Name'],
                    'version' => $pluginData['Version'],
                    'description' => $pluginData['Description'],
                    'author' => $pluginData['Author'],
                    'status' => 'must-use',
                    'type' => 'must-use',
                ];
            }
        }

        // Drop-ins
        if ($status === 'all' || $status === 'drop-in') {
            foreach ($dropins as $pluginFile => $pluginData) {
                $result[] = [
                    'file' => $pluginFile,
                    'name' => $pluginData['Name'],
                    'description' => $pluginData['Description'],
                    'status' => 'drop-in',
                    'type' => 'drop-in',
                ];
            }
        }

        return [
            'count' => count($result),
            'plugins' => $result,
        ];
    }

    private function listThemes(): array
    {
        $themes = wp_get_themes();
        $activeTheme = wp_get_theme();
        $result = [];

        foreach ($themes as $stylesheet => $theme) {
            $result[] = [
                'stylesheet' => $stylesheet,
                'name' => $theme->get('Name'),
                'version' => $theme->get('Version'),
                'description' => $theme->get('Description'),
                'author' => $theme->get('Author'),
                'author_uri' => $theme->get('AuthorURI'),
                'theme_uri' => $theme->get('ThemeURI'),
                'template' => $theme->get_template(),
                'status' => $activeTheme->get_stylesheet() === $stylesheet ? 'active' : 'inactive',
                'is_child_theme' => $theme->parent() !== false,
                'parent_theme' => $theme->parent() ? $theme->parent()->get('Name') : null,
                'screenshot' => $theme->get_screenshot(),
                'tags' => $theme->get('Tags'),
            ];
        }

        return [
            'count' => count($result),
            'active_theme' => $activeTheme->get_stylesheet(),
            'themes' => $result,
        ];
    }
}
