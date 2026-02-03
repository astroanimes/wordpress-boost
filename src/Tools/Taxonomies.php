<?php

declare(strict_types=1);

namespace WordPressBoost\Tools;

/**
 * Taxonomies Tool
 *
 * Provides introspection into registered WordPress taxonomies.
 */
class Taxonomies extends BaseTool
{
    public function getToolDefinitions(): array
    {
        return [
            $this->createToolDefinition(
                'list_taxonomies',
                'List all registered taxonomies with their configurations',
                [
                    'public' => [
                        'type' => 'boolean',
                        'description' => 'Filter by public status. Leave empty for all.',
                    ],
                    'show_in_rest' => [
                        'type' => 'boolean',
                        'description' => 'Filter by REST API availability',
                    ],
                    'builtin' => [
                        'type' => 'boolean',
                        'description' => 'Include built-in taxonomies (category, post_tag). Default: true',
                    ],
                    'object_type' => [
                        'type' => 'string',
                        'description' => 'Filter by associated post type',
                    ],
                ]
            ),
            $this->createToolDefinition(
                'get_taxonomy',
                'Get detailed configuration for a specific taxonomy',
                [
                    'taxonomy' => [
                        'type' => 'string',
                        'description' => 'The taxonomy slug',
                    ],
                ],
                ['taxonomy']
            ),
            $this->createToolDefinition(
                'list_terms',
                'List terms in a taxonomy',
                [
                    'taxonomy' => [
                        'type' => 'string',
                        'description' => 'The taxonomy slug',
                    ],
                    'hide_empty' => [
                        'type' => 'boolean',
                        'description' => 'Hide empty terms (default: false)',
                    ],
                    'parent' => [
                        'type' => 'integer',
                        'description' => 'Get children of this term ID (0 for top-level)',
                    ],
                    'limit' => [
                        'type' => 'integer',
                        'description' => 'Maximum terms to return (default: 100)',
                    ],
                ],
                ['taxonomy']
            ),
        ];
    }

    public function handles(string $name): bool
    {
        return in_array($name, ['list_taxonomies', 'get_taxonomy', 'list_terms']);
    }

    public function execute(string $name, array $arguments): mixed
    {
        return match ($name) {
            'list_taxonomies' => $this->listTaxonomies(
                $arguments['public'] ?? null,
                $arguments['show_in_rest'] ?? null,
                $arguments['builtin'] ?? true,
                $arguments['object_type'] ?? null
            ),
            'get_taxonomy' => $this->getTaxonomy($arguments['taxonomy']),
            'list_terms' => $this->listTerms(
                $arguments['taxonomy'],
                $arguments['hide_empty'] ?? false,
                $arguments['parent'] ?? null,
                $arguments['limit'] ?? 100
            ),
            default => throw new \RuntimeException("Unknown tool: {$name}"),
        };
    }

    private function listTaxonomies(?bool $public = null, ?bool $showInRest = null, bool $builtin = true, ?string $objectType = null): array
    {
        $args = [];

        if ($public !== null) {
            $args['public'] = $public;
        }

        if ($showInRest !== null) {
            $args['show_in_rest'] = $showInRest;
        }

        if ($objectType !== null) {
            $args['object_type'] = [$objectType];
        }

        $taxonomies = get_taxonomies($args, 'objects');

        $result = [];
        foreach ($taxonomies as $taxonomy) {
            // Filter built-in types if requested
            if (!$builtin && $taxonomy->_builtin) {
                continue;
            }

            $result[] = [
                'name' => $taxonomy->name,
                'label' => $taxonomy->label,
                'singular_label' => $taxonomy->labels->singular_name,
                'description' => $taxonomy->description,
                'public' => $taxonomy->public,
                'hierarchical' => $taxonomy->hierarchical,
                'show_ui' => $taxonomy->show_ui,
                'show_in_menu' => $taxonomy->show_in_menu,
                'show_in_rest' => $taxonomy->show_in_rest,
                'rest_base' => $taxonomy->rest_base,
                'object_types' => $taxonomy->object_type,
                'builtin' => $taxonomy->_builtin,
                'rewrite' => $taxonomy->rewrite,
            ];
        }

        return [
            'count' => count($result),
            'taxonomies' => $result,
        ];
    }

    private function getTaxonomy(string $taxonomyName): array
    {
        $taxonomy = get_taxonomy($taxonomyName);

        if (!$taxonomy) {
            return [
                'error' => "Taxonomy not found: {$taxonomyName}",
                'available_taxonomies' => array_keys(get_taxonomies()),
            ];
        }

        // Get term count
        $termCount = wp_count_terms(['taxonomy' => $taxonomyName, 'hide_empty' => false]);

        return [
            'name' => $taxonomy->name,
            'label' => $taxonomy->label,
            'labels' => (array) $taxonomy->labels,
            'description' => $taxonomy->description,
            'public' => $taxonomy->public,
            'publicly_queryable' => $taxonomy->publicly_queryable,
            'hierarchical' => $taxonomy->hierarchical,
            'show_ui' => $taxonomy->show_ui,
            'show_in_menu' => $taxonomy->show_in_menu,
            'show_in_nav_menus' => $taxonomy->show_in_nav_menus,
            'show_in_rest' => $taxonomy->show_in_rest,
            'rest_base' => $taxonomy->rest_base,
            'rest_controller_class' => $taxonomy->rest_controller_class,
            'rest_namespace' => $taxonomy->rest_namespace ?? 'wp/v2',
            'show_tagcloud' => $taxonomy->show_tagcloud,
            'show_in_quick_edit' => $taxonomy->show_in_quick_edit,
            'show_admin_column' => $taxonomy->show_admin_column,
            'object_types' => $taxonomy->object_type,
            'builtin' => $taxonomy->_builtin,
            'rewrite' => $taxonomy->rewrite,
            'query_var' => $taxonomy->query_var,
            'capabilities' => (array) $taxonomy->cap,
            'meta_box_cb' => is_callable($taxonomy->meta_box_cb) ? 'callable' : $taxonomy->meta_box_cb,
            'meta_box_sanitize_cb' => is_callable($taxonomy->meta_box_sanitize_cb) ? 'callable' : $taxonomy->meta_box_sanitize_cb,
            'default_term' => $taxonomy->default_term,
            'term_count' => is_wp_error($termCount) ? 0 : (int) $termCount,
        ];
    }

    private function listTerms(string $taxonomyName, bool $hideEmpty = false, ?int $parent = null, int $limit = 100): array
    {
        $taxonomy = get_taxonomy($taxonomyName);

        if (!$taxonomy) {
            return [
                'error' => "Taxonomy not found: {$taxonomyName}",
                'available_taxonomies' => array_keys(get_taxonomies()),
            ];
        }

        $args = [
            'taxonomy' => $taxonomyName,
            'hide_empty' => $hideEmpty,
            'number' => $limit,
        ];

        if ($parent !== null) {
            $args['parent'] = $parent;
        }

        $terms = get_terms($args);

        if (is_wp_error($terms)) {
            return [
                'error' => $terms->get_error_message(),
            ];
        }

        $result = [];
        foreach ($terms as $term) {
            $result[] = [
                'term_id' => $term->term_id,
                'name' => $term->name,
                'slug' => $term->slug,
                'description' => $term->description,
                'parent' => $term->parent,
                'count' => $term->count,
                'term_group' => $term->term_group,
                'term_taxonomy_id' => $term->term_taxonomy_id,
            ];
        }

        return [
            'taxonomy' => $taxonomyName,
            'count' => count($result),
            'limit' => $limit,
            'hide_empty' => $hideEmpty,
            'terms' => $result,
        ];
    }
}
