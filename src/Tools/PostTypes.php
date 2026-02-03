<?php

declare(strict_types=1);

namespace WordPressBoost\Tools;

/**
 * Post Types Tool
 *
 * Provides introspection into registered WordPress post types.
 */
class PostTypes extends BaseTool
{
    public function getToolDefinitions(): array
    {
        return [
            $this->createToolDefinition(
                'list_post_types',
                'List all registered post types with their configurations',
                [
                    'public' => [
                        'type' => 'boolean',
                        'description' => 'Filter by public status (true/false). Leave empty for all.',
                    ],
                    'show_in_rest' => [
                        'type' => 'boolean',
                        'description' => 'Filter by REST API availability',
                    ],
                    'builtin' => [
                        'type' => 'boolean',
                        'description' => 'Include built-in post types (post, page, attachment). Default: true',
                    ],
                ]
            ),
            $this->createToolDefinition(
                'get_post_type',
                'Get detailed configuration for a specific post type',
                [
                    'post_type' => [
                        'type' => 'string',
                        'description' => 'The post type slug',
                    ],
                ],
                ['post_type']
            ),
        ];
    }

    public function handles(string $name): bool
    {
        return in_array($name, ['list_post_types', 'get_post_type']);
    }

    public function execute(string $name, array $arguments): mixed
    {
        return match ($name) {
            'list_post_types' => $this->listPostTypes(
                $arguments['public'] ?? null,
                $arguments['show_in_rest'] ?? null,
                $arguments['builtin'] ?? true
            ),
            'get_post_type' => $this->getPostType($arguments['post_type']),
            default => throw new \RuntimeException("Unknown tool: {$name}"),
        };
    }

    private function listPostTypes(?bool $public = null, ?bool $showInRest = null, bool $builtin = true): array
    {
        $args = [];

        if ($public !== null) {
            $args['public'] = $public;
        }

        if ($showInRest !== null) {
            $args['show_in_rest'] = $showInRest;
        }

        $postTypes = get_post_types($args, 'objects');

        $result = [];
        foreach ($postTypes as $postType) {
            // Filter built-in types if requested
            if (!$builtin && $postType->_builtin) {
                continue;
            }

            $result[] = [
                'name' => $postType->name,
                'label' => $postType->label,
                'singular_label' => $postType->labels->singular_name,
                'description' => $postType->description,
                'public' => $postType->public,
                'hierarchical' => $postType->hierarchical,
                'show_ui' => $postType->show_ui,
                'show_in_menu' => $postType->show_in_menu,
                'show_in_rest' => $postType->show_in_rest,
                'rest_base' => $postType->rest_base,
                'has_archive' => $postType->has_archive,
                'supports' => get_all_post_type_supports($postType->name),
                'taxonomies' => get_object_taxonomies($postType->name),
                'builtin' => $postType->_builtin,
                'rewrite' => $postType->rewrite,
                'capability_type' => $postType->capability_type,
                'menu_icon' => $postType->menu_icon,
            ];
        }

        return [
            'count' => count($result),
            'post_types' => $result,
        ];
    }

    private function getPostType(string $postTypeName): array
    {
        $postType = get_post_type_object($postTypeName);

        if (!$postType) {
            return [
                'error' => "Post type not found: {$postTypeName}",
                'available_post_types' => array_keys(get_post_types()),
            ];
        }

        // Get counts
        $counts = wp_count_posts($postTypeName);

        return [
            'name' => $postType->name,
            'label' => $postType->label,
            'labels' => (array) $postType->labels,
            'description' => $postType->description,
            'public' => $postType->public,
            'publicly_queryable' => $postType->publicly_queryable,
            'hierarchical' => $postType->hierarchical,
            'exclude_from_search' => $postType->exclude_from_search,
            'show_ui' => $postType->show_ui,
            'show_in_menu' => $postType->show_in_menu,
            'show_in_nav_menus' => $postType->show_in_nav_menus,
            'show_in_admin_bar' => $postType->show_in_admin_bar,
            'show_in_rest' => $postType->show_in_rest,
            'rest_base' => $postType->rest_base,
            'rest_controller_class' => $postType->rest_controller_class,
            'rest_namespace' => $postType->rest_namespace ?? 'wp/v2',
            'has_archive' => $postType->has_archive,
            'supports' => get_all_post_type_supports($postTypeName),
            'taxonomies' => get_object_taxonomies($postTypeName),
            'builtin' => $postType->_builtin,
            'rewrite' => $postType->rewrite,
            'query_var' => $postType->query_var,
            'capability_type' => $postType->capability_type,
            'capabilities' => (array) $postType->cap,
            'map_meta_cap' => $postType->map_meta_cap,
            'menu_position' => $postType->menu_position,
            'menu_icon' => $postType->menu_icon,
            'template' => $postType->template ?? [],
            'template_lock' => $postType->template_lock ?? false,
            'counts' => (array) $counts,
        ];
    }
}
