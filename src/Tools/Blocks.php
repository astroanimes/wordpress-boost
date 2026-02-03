<?php

declare(strict_types=1);

namespace WordPressBoost\Tools;

/**
 * Gutenberg Blocks Tool
 *
 * Provides introspection into registered Gutenberg block types and patterns.
 */
class Blocks extends BaseTool
{
    public function getToolDefinitions(): array
    {
        return [
            $this->createToolDefinition(
                'list_block_types',
                'List all registered Gutenberg block types',
                [
                    'namespace' => [
                        'type' => 'string',
                        'description' => 'Filter by namespace (e.g., core, woocommerce, acf)',
                    ],
                    'search' => [
                        'type' => 'string',
                        'description' => 'Search blocks by name or title',
                    ],
                ]
            ),
            $this->createToolDefinition(
                'get_block_type',
                'Get detailed information about a specific block type',
                [
                    'name' => [
                        'type' => 'string',
                        'description' => 'The block name (e.g., core/paragraph, woocommerce/product-grid)',
                    ],
                ],
                ['name']
            ),
            $this->createToolDefinition(
                'list_block_patterns',
                'List all registered block patterns',
                [
                    'category' => [
                        'type' => 'string',
                        'description' => 'Filter by category slug',
                    ],
                ]
            ),
            $this->createToolDefinition(
                'list_block_categories',
                'List all registered block categories'
            ),
            $this->createToolDefinition(
                'list_block_styles',
                'List registered block styles for a specific block',
                [
                    'block' => [
                        'type' => 'string',
                        'description' => 'The block name to get styles for',
                    ],
                ],
                ['block']
            ),
        ];
    }

    public function handles(string $name): bool
    {
        return in_array($name, [
            'list_block_types', 'get_block_type', 'list_block_patterns',
            'list_block_categories', 'list_block_styles'
        ]);
    }

    public function execute(string $name, array $arguments): mixed
    {
        return match ($name) {
            'list_block_types' => $this->listBlockTypes(
                $arguments['namespace'] ?? null,
                $arguments['search'] ?? null
            ),
            'get_block_type' => $this->getBlockType($arguments['name']),
            'list_block_patterns' => $this->listBlockPatterns($arguments['category'] ?? null),
            'list_block_categories' => $this->listBlockCategories(),
            'list_block_styles' => $this->listBlockStyles($arguments['block']),
            default => throw new \RuntimeException("Unknown tool: {$name}"),
        };
    }

    private function listBlockTypes(?string $namespace = null, ?string $search = null): array
    {
        $registry = \WP_Block_Type_Registry::get_instance();
        $blockTypes = $registry->get_all_registered();

        $result = [];
        $namespaces = [];

        foreach ($blockTypes as $name => $block) {
            // Extract namespace
            $parts = explode('/', $name);
            $blockNamespace = $parts[0];
            $namespaces[$blockNamespace] = true;

            // Filter by namespace
            if ($namespace !== null && $blockNamespace !== $namespace) {
                continue;
            }

            // Filter by search
            if ($search !== null) {
                $searchLower = strtolower($search);
                $title = $block->title ?? '';
                if (stripos($name, $searchLower) === false && stripos($title, $searchLower) === false) {
                    continue;
                }
            }

            $result[] = [
                'name' => $name,
                'title' => $block->title ?? '',
                'description' => $block->description ?? '',
                'category' => $block->category ?? '',
                'icon' => is_string($block->icon) ? $block->icon : (isset($block->icon['src']) ? $block->icon['src'] : 'block-default'),
                'keywords' => $block->keywords ?? [],
                'supports' => $this->formatSupports($block->supports ?? []),
                'is_dynamic' => $block->is_dynamic(),
            ];
        }

        // Sort by name
        usort($result, fn($a, $b) => strcmp($a['name'], $b['name']));

        return [
            'count' => count($result),
            'namespaces' => array_keys($namespaces),
            'blocks' => $result,
        ];
    }

    private function getBlockType(string $name): array
    {
        $registry = \WP_Block_Type_Registry::get_instance();
        $block = $registry->get_registered($name);

        if (!$block) {
            return [
                'error' => "Block type not found: {$name}",
                'suggestion' => 'Use list_block_types to see available blocks',
            ];
        }

        $result = [
            'name' => $block->name,
            'title' => $block->title ?? '',
            'description' => $block->description ?? '',
            'category' => $block->category ?? '',
            'keywords' => $block->keywords ?? [],
            'parent' => $block->parent,
            'ancestor' => $block->ancestor ?? [],
            'icon' => $block->icon,
            'api_version' => $block->api_version,
            'supports' => $this->formatSupports($block->supports ?? []),
            'is_dynamic' => $block->is_dynamic(),
            'uses_context' => $block->uses_context ?? [],
            'provides_context' => $block->provides_context ?? [],
        ];

        // Get attributes schema
        if (!empty($block->attributes)) {
            $result['attributes'] = $this->formatAttributes($block->attributes);
        }

        // Get styles
        if (!empty($block->styles)) {
            $result['styles'] = $block->styles;
        }

        // Get variations
        if (!empty($block->variations)) {
            $result['variations'] = array_map(function ($variation) {
                return [
                    'name' => $variation['name'] ?? '',
                    'title' => $variation['title'] ?? '',
                    'description' => $variation['description'] ?? '',
                    'icon' => $variation['icon'] ?? null,
                    'isDefault' => $variation['isDefault'] ?? false,
                    'scope' => $variation['scope'] ?? [],
                ];
            }, $block->variations);
        }

        // Get example (for preview)
        if (!empty($block->example)) {
            $result['example'] = $block->example;
        }

        // Get editor/render scripts and styles
        $result['scripts'] = [
            'editor_script' => $block->editor_script ?? null,
            'script' => $block->script ?? null,
            'view_script' => $block->view_script ?? null,
        ];

        $result['styles_files'] = [
            'editor_style' => $block->editor_style ?? null,
            'style' => $block->style ?? null,
        ];

        // Check for render callback
        if ($block->render_callback) {
            $result['render_callback'] = $this->getCallbackInfo($block->render_callback);
        }

        return $result;
    }

    private function listBlockPatterns(?string $category = null): array
    {
        $registry = \WP_Block_Patterns_Registry::get_instance();
        $patterns = $registry->get_all_registered();

        $result = [];
        $categories = [];

        foreach ($patterns as $name => $pattern) {
            // Collect categories
            $patternCategories = $pattern['categories'] ?? [];
            foreach ($patternCategories as $cat) {
                $categories[$cat] = true;
            }

            // Filter by category
            if ($category !== null && !in_array($category, $patternCategories)) {
                continue;
            }

            $result[] = [
                'name' => $name,
                'title' => $pattern['title'] ?? '',
                'description' => $pattern['description'] ?? '',
                'categories' => $patternCategories,
                'keywords' => $pattern['keywords'] ?? [],
                'viewportWidth' => $pattern['viewportWidth'] ?? null,
                'blockTypes' => $pattern['blockTypes'] ?? [],
                'content_preview' => isset($pattern['content'])
                    ? substr($pattern['content'], 0, 200) . (strlen($pattern['content']) > 200 ? '...' : '')
                    : '',
            ];
        }

        // Sort by name
        usort($result, fn($a, $b) => strcmp($a['name'], $b['name']));

        return [
            'count' => count($result),
            'categories' => array_keys($categories),
            'patterns' => $result,
        ];
    }

    private function listBlockCategories(): array
    {
        $categories = [];

        // Get block categories
        if (function_exists('get_block_categories')) {
            // WordPress 5.8+
            $post = new \WP_Post((object) ['ID' => 0, 'post_type' => 'page']);
            $categories = get_block_categories($post);
        } elseif (function_exists('get_default_block_categories')) {
            $categories = get_default_block_categories();
        }

        $result = array_map(function ($category) {
            return [
                'slug' => $category['slug'],
                'title' => $category['title'],
                'icon' => $category['icon'] ?? null,
            ];
        }, $categories);

        // Get pattern categories
        $patternCategories = [];
        if (class_exists('\WP_Block_Pattern_Categories_Registry')) {
            $patternRegistry = \WP_Block_Pattern_Categories_Registry::get_instance();
            $patternCats = $patternRegistry->get_all_registered();

            foreach ($patternCats as $name => $category) {
                $patternCategories[] = [
                    'slug' => $name,
                    'label' => $category['label'] ?? '',
                    'description' => $category['description'] ?? '',
                ];
            }
        }

        return [
            'block_categories' => $result,
            'pattern_categories' => $patternCategories,
        ];
    }

    private function listBlockStyles(string $blockName): array
    {
        $registry = \WP_Block_Styles_Registry::get_instance();

        // Get all registered styles for this block
        $styles = $registry->get_registered_styles_for_block($blockName);

        if (empty($styles)) {
            return [
                'block' => $blockName,
                'count' => 0,
                'styles' => [],
                'note' => 'No custom styles registered for this block',
            ];
        }

        $result = [];
        foreach ($styles as $name => $style) {
            $result[] = [
                'name' => $name,
                'label' => $style['label'] ?? '',
                'is_default' => $style['is_default'] ?? false,
                'inline_style' => $style['inline_style'] ?? null,
                'style_handle' => $style['style_handle'] ?? null,
            ];
        }

        return [
            'block' => $blockName,
            'count' => count($result),
            'styles' => $result,
        ];
    }

    private function formatSupports(array $supports): array
    {
        // Common supports with descriptions
        $supportDescriptions = [
            'align' => 'Block alignment (left, center, right, wide, full)',
            'alignWide' => 'Wide and full width alignments',
            'anchor' => 'HTML anchor/ID',
            'className' => 'Additional CSS class',
            'color' => 'Color controls (text, background, gradients)',
            'customClassName' => 'Custom CSS class name',
            'html' => 'HTML editing mode',
            'inserter' => 'Show in block inserter',
            'multiple' => 'Allow multiple instances',
            'reusable' => 'Can be converted to reusable block',
            'spacing' => 'Spacing controls (margin, padding)',
            'typography' => 'Typography controls (font size, line height)',
        ];

        $result = [];

        foreach ($supports as $key => $value) {
            $support = [
                'name' => $key,
                'value' => $value,
            ];

            if (isset($supportDescriptions[$key])) {
                $support['description'] = $supportDescriptions[$key];
            }

            $result[] = $support;
        }

        return $result;
    }

    private function formatAttributes(array $attributes): array
    {
        $result = [];

        foreach ($attributes as $name => $config) {
            $attr = [
                'name' => $name,
                'type' => $config['type'] ?? 'string',
            ];

            if (isset($config['default'])) {
                $attr['default'] = $config['default'];
            }

            if (isset($config['enum'])) {
                $attr['enum'] = $config['enum'];
            }

            if (isset($config['source'])) {
                $attr['source'] = $config['source'];
            }

            if (isset($config['selector'])) {
                $attr['selector'] = $config['selector'];
            }

            if (isset($config['attribute'])) {
                $attr['attribute'] = $config['attribute'];
            }

            $result[] = $attr;
        }

        return $result;
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
            $className = is_object($callback[0]) ? get_class($callback[0]) : $callback[0];
            return [
                'type' => is_object($callback[0]) ? 'method' : 'static_method',
                'class' => $className,
                'method' => $callback[1],
            ];
        }

        if ($callback instanceof \Closure) {
            try {
                $reflection = new \ReflectionFunction($callback);
                return [
                    'type' => 'closure',
                    'file' => $reflection->getFileName(),
                    'line' => $reflection->getStartLine(),
                ];
            } catch (\Exception $e) {
                return ['type' => 'closure'];
            }
        }

        return ['type' => 'unknown'];
    }
}
