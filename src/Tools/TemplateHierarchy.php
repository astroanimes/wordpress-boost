<?php

declare(strict_types=1);

namespace WordPressBoost\Tools;

/**
 * Template Hierarchy Tool
 *
 * Provides information about WordPress template hierarchy and resolution.
 */
class TemplateHierarchy extends BaseTool
{
    public function getToolDefinitions(): array
    {
        return [
            $this->createToolDefinition(
                'template_hierarchy',
                'Get template hierarchy information for different page types',
                [
                    'type' => [
                        'type' => 'string',
                        'description' => 'Page type: home, single, page, archive, category, tag, author, date, search, 404, attachment, taxonomy, or all',
                    ],
                    'context' => [
                        'type' => 'string',
                        'description' => 'Additional context (e.g., post type for single, taxonomy for archive)',
                    ],
                ]
            ),
            $this->createToolDefinition(
                'list_theme_templates',
                'List all template files in the active theme',
                [
                    'type' => [
                        'type' => 'string',
                        'description' => 'Filter by template type: page, post, archive, etc.',
                    ],
                ]
            ),
            $this->createToolDefinition(
                'get_template_parts',
                'List all template parts used by the theme'
            ),
        ];
    }

    public function handles(string $name): bool
    {
        return in_array($name, ['template_hierarchy', 'list_theme_templates', 'get_template_parts']);
    }

    public function execute(string $name, array $arguments): mixed
    {
        return match ($name) {
            'template_hierarchy' => $this->getTemplateHierarchy(
                $arguments['type'] ?? 'all',
                $arguments['context'] ?? null
            ),
            'list_theme_templates' => $this->listThemeTemplates($arguments['type'] ?? null),
            'get_template_parts' => $this->getTemplateParts(),
            default => throw new \RuntimeException("Unknown tool: {$name}"),
        };
    }

    private function getTemplateHierarchy(string $type = 'all', ?string $context = null): array
    {
        $hierarchies = [
            'home' => $this->getHomeHierarchy(),
            'front_page' => $this->getFrontPageHierarchy(),
            'single' => $this->getSingleHierarchy($context),
            'page' => $this->getPageHierarchy($context),
            'archive' => $this->getArchiveHierarchy($context),
            'category' => $this->getCategoryHierarchy($context),
            'tag' => $this->getTagHierarchy($context),
            'author' => $this->getAuthorHierarchy(),
            'date' => $this->getDateHierarchy(),
            'search' => $this->getSearchHierarchy(),
            '404' => $this->get404Hierarchy(),
            'attachment' => $this->getAttachmentHierarchy($context),
            'taxonomy' => $this->getTaxonomyHierarchy($context),
        ];

        if ($type === 'all') {
            return [
                'theme' => wp_get_theme()->get_stylesheet(),
                'template_directory' => get_template_directory(),
                'stylesheet_directory' => get_stylesheet_directory(),
                'is_child_theme' => is_child_theme(),
                'hierarchies' => $hierarchies,
            ];
        }

        if (!isset($hierarchies[$type])) {
            return [
                'error' => "Unknown template type: {$type}",
                'available_types' => array_keys($hierarchies),
            ];
        }

        return [
            'type' => $type,
            'context' => $context,
            'hierarchy' => $hierarchies[$type],
        ];
    }

    private function getHomeHierarchy(): array
    {
        return [
            'description' => 'Blog posts index (when front page displays latest posts)',
            'templates' => [
                'home.php',
                'index.php',
            ],
        ];
    }

    private function getFrontPageHierarchy(): array
    {
        return [
            'description' => 'Static front page',
            'templates' => [
                'front-page.php',
                'page.php (if using a page)',
                'index.php',
            ],
        ];
    }

    private function getSingleHierarchy(?string $postType): array
    {
        $postType = $postType ?: 'post';
        $templates = [];

        if ($postType !== 'post') {
            $templates[] = "single-{$postType}-{slug}.php";
            $templates[] = "single-{$postType}.php";
        } else {
            $templates[] = 'single-post-{slug}.php';
        }

        $templates[] = 'single.php';
        $templates[] = 'singular.php';
        $templates[] = 'index.php';

        return [
            'description' => "Single post/custom post type display (post_type: {$postType})",
            'templates' => $templates,
        ];
    }

    private function getPageHierarchy(?string $slug): array
    {
        $templates = [];

        if ($slug) {
            $templates[] = "page-{$slug}.php";
        }

        $templates[] = 'page-{slug}.php';
        $templates[] = 'page-{id}.php';
        $templates[] = 'page.php';
        $templates[] = 'singular.php';
        $templates[] = 'index.php';

        return [
            'description' => 'Static page display',
            'templates' => $templates,
        ];
    }

    private function getArchiveHierarchy(?string $postType): array
    {
        $templates = [];

        if ($postType) {
            $templates[] = "archive-{$postType}.php";
        }

        $templates[] = 'archive-{post_type}.php';
        $templates[] = 'archive.php';
        $templates[] = 'index.php';

        return [
            'description' => 'Post type archive display',
            'templates' => $templates,
        ];
    }

    private function getCategoryHierarchy(?string $slug): array
    {
        $templates = [];

        if ($slug) {
            $templates[] = "category-{$slug}.php";
        }

        $templates[] = 'category-{slug}.php';
        $templates[] = 'category-{id}.php';
        $templates[] = 'category.php';
        $templates[] = 'archive.php';
        $templates[] = 'index.php';

        return [
            'description' => 'Category archive display',
            'templates' => $templates,
        ];
    }

    private function getTagHierarchy(?string $slug): array
    {
        $templates = [];

        if ($slug) {
            $templates[] = "tag-{$slug}.php";
        }

        $templates[] = 'tag-{slug}.php';
        $templates[] = 'tag-{id}.php';
        $templates[] = 'tag.php';
        $templates[] = 'archive.php';
        $templates[] = 'index.php';

        return [
            'description' => 'Tag archive display',
            'templates' => $templates,
        ];
    }

    private function getAuthorHierarchy(): array
    {
        return [
            'description' => 'Author archive display',
            'templates' => [
                'author-{nicename}.php',
                'author-{id}.php',
                'author.php',
                'archive.php',
                'index.php',
            ],
        ];
    }

    private function getDateHierarchy(): array
    {
        return [
            'description' => 'Date-based archive display',
            'templates' => [
                'date.php',
                'archive.php',
                'index.php',
            ],
        ];
    }

    private function getSearchHierarchy(): array
    {
        return [
            'description' => 'Search results display',
            'templates' => [
                'search.php',
                'index.php',
            ],
        ];
    }

    private function get404Hierarchy(): array
    {
        return [
            'description' => '404 error page display',
            'templates' => [
                '404.php',
                'index.php',
            ],
        ];
    }

    private function getAttachmentHierarchy(?string $mimeType): array
    {
        $templates = [];

        if ($mimeType) {
            $parts = explode('/', $mimeType);
            $templates[] = "{$parts[0]}-{$parts[1]}.php";
            $templates[] = "{$parts[1]}.php";
            $templates[] = "{$parts[0]}.php";
        }

        $templates[] = '{mimetype}.php';
        $templates[] = '{subtype}.php';
        $templates[] = '{type}.php';
        $templates[] = 'attachment.php';
        $templates[] = 'single.php';
        $templates[] = 'singular.php';
        $templates[] = 'index.php';

        return [
            'description' => 'Attachment page display',
            'templates' => $templates,
        ];
    }

    private function getTaxonomyHierarchy(?string $taxonomy): array
    {
        $templates = [];

        if ($taxonomy) {
            $templates[] = "taxonomy-{$taxonomy}-{term}.php";
            $templates[] = "taxonomy-{$taxonomy}.php";
        }

        $templates[] = 'taxonomy-{taxonomy}-{term}.php';
        $templates[] = 'taxonomy-{taxonomy}.php';
        $templates[] = 'taxonomy.php';
        $templates[] = 'archive.php';
        $templates[] = 'index.php';

        return [
            'description' => 'Custom taxonomy archive display',
            'templates' => $templates,
        ];
    }

    private function listThemeTemplates(?string $type = null): array
    {
        $theme = wp_get_theme();
        $templateDir = get_template_directory();
        $stylesheetDir = get_stylesheet_directory();

        // Get all PHP files
        $templates = [];

        // Scan template directory
        $files = $this->scanDirectory($templateDir, 'php');

        // If child theme, also scan stylesheet directory
        if ($templateDir !== $stylesheetDir) {
            $childFiles = $this->scanDirectory($stylesheetDir, 'php');
            $files = array_merge($files, $childFiles);
        }

        foreach ($files as $file) {
            $relativePath = str_replace([$templateDir . '/', $stylesheetDir . '/'], '', $file);
            $templateType = $this->identifyTemplateType($relativePath);

            // Filter by type
            if ($type !== null && $templateType !== $type) {
                continue;
            }

            $templates[] = [
                'file' => $relativePath,
                'path' => $file,
                'type' => $templateType,
                'is_child_theme' => strpos($file, $stylesheetDir) === 0 && $templateDir !== $stylesheetDir,
            ];
        }

        // Sort by file name
        usort($templates, fn($a, $b) => strcmp($a['file'], $b['file']));

        return [
            'theme' => $theme->get('Name'),
            'count' => count($templates),
            'templates' => $templates,
        ];
    }

    private function getTemplateParts(): array
    {
        $templateDir = get_template_directory();
        $stylesheetDir = get_stylesheet_directory();

        $parts = [];

        // Look for template parts directories
        $partsDir = $templateDir . '/template-parts';
        $partialsDir = $templateDir . '/partials';

        if (is_dir($partsDir)) {
            $files = $this->scanDirectory($partsDir, 'php');
            foreach ($files as $file) {
                $parts[] = [
                    'file' => str_replace($templateDir . '/', '', $file),
                    'name' => basename($file, '.php'),
                    'usage' => "get_template_part('template-parts/" . basename($file, '.php') . "')",
                ];
            }
        }

        if (is_dir($partialsDir)) {
            $files = $this->scanDirectory($partialsDir, 'php');
            foreach ($files as $file) {
                $parts[] = [
                    'file' => str_replace($templateDir . '/', '', $file),
                    'name' => basename($file, '.php'),
                    'usage' => "get_template_part('partials/" . basename($file, '.php') . "')",
                ];
            }
        }

        // Check child theme
        if ($templateDir !== $stylesheetDir) {
            $childPartsDir = $stylesheetDir . '/template-parts';
            if (is_dir($childPartsDir)) {
                $files = $this->scanDirectory($childPartsDir, 'php');
                foreach ($files as $file) {
                    $parts[] = [
                        'file' => str_replace($stylesheetDir . '/', '', $file),
                        'name' => basename($file, '.php'),
                        'is_child_theme' => true,
                        'usage' => "get_template_part('template-parts/" . basename($file, '.php') . "')",
                    ];
                }
            }
        }

        return [
            'count' => count($parts),
            'parts' => $parts,
        ];
    }

    private function scanDirectory(string $directory, string $extension): array
    {
        $files = [];

        if (!is_dir($directory)) {
            return $files;
        }

        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($directory, \RecursiveDirectoryIterator::SKIP_DOTS)
        );

        foreach ($iterator as $file) {
            if ($file->isFile() && $file->getExtension() === $extension) {
                $files[] = $file->getPathname();
            }
        }

        return $files;
    }

    private function identifyTemplateType(string $file): string
    {
        $basename = basename($file, '.php');

        $patterns = [
            'single' => '/^single(-|$)/',
            'page' => '/^page(-|$)/',
            'archive' => '/^archive(-|$)/',
            'category' => '/^category(-|$)/',
            'tag' => '/^tag(-|$)/',
            'taxonomy' => '/^taxonomy(-|$)/',
            'author' => '/^author(-|$)/',
            'search' => '/^search/',
            '404' => '/^404/',
            'home' => '/^home/',
            'front-page' => '/^front-page/',
            'header' => '/^header(-|$)/',
            'footer' => '/^footer(-|$)/',
            'sidebar' => '/^sidebar(-|$)/',
            'comments' => '/^comments/',
            'template-part' => '/template-parts\//',
        ];

        foreach ($patterns as $type => $pattern) {
            if (preg_match($pattern, $basename) || preg_match($pattern, $file)) {
                return $type;
            }
        }

        return 'other';
    }
}
