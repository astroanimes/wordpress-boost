<?php

declare(strict_types=1);

namespace WordPressBoost\Tools;

/**
 * URL Tools
 *
 * Provides tools for generating absolute URLs and listing WordPress URL functions.
 */
class Urls extends BaseTool
{
    public function getToolDefinitions(): array
    {
        return [
            $this->createToolDefinition(
                'get_absolute_url',
                'Convert a relative path to an absolute WordPress URL using home_url, site_url, admin_url, content_url, plugins_url, themes_url, or uploads_url',
                [
                    'path' => [
                        'type' => 'string',
                        'description' => 'The relative path to convert (e.g., "/about", "/wp-admin/options.php")',
                    ],
                    'type' => [
                        'type' => 'string',
                        'description' => 'URL type: home (frontend), site (WordPress install), admin (wp-admin), content (wp-content), plugins, themes, or uploads',
                        'enum' => ['home', 'site', 'admin', 'content', 'plugins', 'themes', 'uploads'],
                    ],
                ],
                ['path']
            ),
            $this->createToolDefinition(
                'list_url_functions',
                'List all WordPress URL functions with descriptions and example outputs'
            ),
            $this->createToolDefinition(
                'get_all_urls',
                'Get all important WordPress URLs at once (home, site, admin, content, plugins, themes, uploads, ajax, rest)'
            ),
        ];
    }

    public function handles(string $name): bool
    {
        return in_array($name, ['get_absolute_url', 'list_url_functions', 'get_all_urls']);
    }

    public function execute(string $name, array $arguments): mixed
    {
        return match ($name) {
            'get_absolute_url' => $this->getAbsoluteUrl(
                $arguments['path'],
                $arguments['type'] ?? 'home'
            ),
            'list_url_functions' => $this->listUrlFunctions(),
            'get_all_urls' => $this->getAllUrls(),
            default => throw new \RuntimeException("Unknown tool: {$name}"),
        };
    }

    private function getAbsoluteUrl(string $path, string $type = 'home'): array
    {
        // Normalize path - ensure it starts with /
        $path = '/' . ltrim($path, '/');

        $url = match ($type) {
            'home' => home_url($path),
            'site' => site_url($path),
            'admin' => admin_url(ltrim($path, '/')),
            'content' => content_url($path),
            'plugins' => plugins_url($path),
            'themes' => get_theme_root_uri() . $path,
            'uploads' => $this->getUploadsUrl($path),
            default => home_url($path),
        };

        return [
            'path' => $path,
            'type' => $type,
            'url' => $url,
            'function_used' => $this->getFunctionName($type),
        ];
    }

    private function getUploadsUrl(string $path): string
    {
        $uploadDir = wp_upload_dir();
        return $uploadDir['baseurl'] . $path;
    }

    private function getFunctionName(string $type): string
    {
        return match ($type) {
            'home' => 'home_url()',
            'site' => 'site_url()',
            'admin' => 'admin_url()',
            'content' => 'content_url()',
            'plugins' => 'plugins_url()',
            'themes' => 'get_theme_root_uri()',
            'uploads' => 'wp_upload_dir()[baseurl]',
            default => 'home_url()',
        };
    }

    private function listUrlFunctions(): array
    {
        $uploadDir = wp_upload_dir();

        return [
            'description' => 'WordPress URL functions for generating absolute URLs',
            'functions' => [
                [
                    'name' => 'home_url()',
                    'description' => 'Returns the home URL for the current site (frontend URL)',
                    'example' => home_url(),
                    'with_path' => home_url('/about'),
                    'use_case' => 'Links to frontend pages, blog posts, archives',
                ],
                [
                    'name' => 'site_url()',
                    'description' => 'Returns the site URL where WordPress core files reside',
                    'example' => site_url(),
                    'with_path' => site_url('/wp-login.php'),
                    'use_case' => 'Links to WordPress core files, login page',
                ],
                [
                    'name' => 'admin_url()',
                    'description' => 'Returns the admin area URL (wp-admin)',
                    'example' => admin_url(),
                    'with_path' => admin_url('options-general.php'),
                    'use_case' => 'Links to admin pages, settings, tools',
                ],
                [
                    'name' => 'content_url()',
                    'description' => 'Returns the wp-content directory URL',
                    'example' => content_url(),
                    'with_path' => content_url('/uploads'),
                    'use_case' => 'Links to content directory, custom folders',
                ],
                [
                    'name' => 'plugins_url()',
                    'description' => 'Returns the plugins directory URL',
                    'example' => plugins_url(),
                    'with_path' => plugins_url('/my-plugin/assets/script.js'),
                    'use_case' => 'Enqueuing plugin assets, referencing plugin files',
                ],
                [
                    'name' => 'get_theme_root_uri()',
                    'description' => 'Returns the themes directory URL',
                    'example' => get_theme_root_uri(),
                    'use_case' => 'Referencing theme files across themes',
                ],
                [
                    'name' => 'get_template_directory_uri()',
                    'description' => 'Returns the current parent theme URL',
                    'example' => get_template_directory_uri(),
                    'use_case' => 'Theme assets in parent theme',
                ],
                [
                    'name' => 'get_stylesheet_directory_uri()',
                    'description' => 'Returns the current theme/child theme URL',
                    'example' => get_stylesheet_directory_uri(),
                    'use_case' => 'Theme assets in active theme (child or parent)',
                ],
                [
                    'name' => 'wp_upload_dir()',
                    'description' => 'Returns upload directory paths and URLs',
                    'example' => $uploadDir['baseurl'],
                    'current_month_url' => $uploadDir['url'],
                    'use_case' => 'Accessing uploaded media files',
                ],
                [
                    'name' => 'includes_url()',
                    'description' => 'Returns the wp-includes directory URL',
                    'example' => includes_url(),
                    'with_path' => includes_url('js/jquery/jquery.min.js'),
                    'use_case' => 'Referencing WordPress core JS/CSS libraries',
                ],
                [
                    'name' => 'network_home_url()',
                    'description' => 'Returns the main site URL in multisite',
                    'example' => function_exists('network_home_url') ? network_home_url() : 'N/A (not multisite)',
                    'use_case' => 'Cross-site links in multisite installations',
                ],
                [
                    'name' => 'get_rest_url()',
                    'description' => 'Returns the REST API root URL',
                    'example' => get_rest_url(),
                    'with_path' => get_rest_url(null, 'wp/v2/posts'),
                    'use_case' => 'Making REST API requests',
                ],
                [
                    'name' => 'admin_url("admin-ajax.php")',
                    'description' => 'Returns the AJAX handler URL',
                    'example' => admin_url('admin-ajax.php'),
                    'use_case' => 'AJAX requests to WordPress',
                ],
            ],
        ];
    }

    private function getAllUrls(): array
    {
        $uploadDir = wp_upload_dir();

        return [
            'home_url' => home_url(),
            'site_url' => site_url(),
            'admin_url' => admin_url(),
            'content_url' => content_url(),
            'plugins_url' => plugins_url(),
            'themes_url' => get_theme_root_uri(),
            'uploads_url' => $uploadDir['baseurl'],
            'uploads_current_month' => $uploadDir['url'],
            'includes_url' => includes_url(),
            'ajax_url' => admin_url('admin-ajax.php'),
            'rest_url' => get_rest_url(),
            'login_url' => wp_login_url(),
            'logout_url' => wp_logout_url(),
            'register_url' => wp_registration_url(),
            'template_directory' => get_template_directory_uri(),
            'stylesheet_directory' => get_stylesheet_directory_uri(),
            'is_multisite' => is_multisite(),
            'network_home_url' => is_multisite() ? network_home_url() : null,
            'network_site_url' => is_multisite() ? network_site_url() : null,
        ];
    }
}
