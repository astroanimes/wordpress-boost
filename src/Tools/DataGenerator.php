<?php

declare(strict_types=1);

namespace WordPressBoost\Tools;

/**
 * Data Generator Tool
 *
 * Generates dummy/test data for WordPress development using Faker.
 */
class DataGenerator extends BaseTool
{
    private $faker;

    public function __construct()
    {
        // Try to load Faker if available
        if (class_exists('\Faker\Factory')) {
            $this->faker = \Faker\Factory::create();
        }
    }

    public function getToolDefinitions(): array
    {
        return [
            $this->createToolDefinition(
                'create_posts',
                'Generate test posts with random content',
                [
                    'count' => [
                        'type' => 'integer',
                        'description' => 'Number of posts to create (default: 10, max: 100)',
                    ],
                    'post_type' => [
                        'type' => 'string',
                        'description' => 'Post type to create (default: post)',
                    ],
                    'status' => [
                        'type' => 'string',
                        'description' => 'Post status (default: draft)',
                        'enum' => ['draft', 'publish', 'pending', 'private'],
                    ],
                    'author' => [
                        'type' => 'integer',
                        'description' => 'Author user ID (default: current user)',
                    ],
                    'categories' => [
                        'type' => 'array',
                        'description' => 'Category IDs to assign',
                    ],
                    'tags' => [
                        'type' => 'array',
                        'description' => 'Tags to assign',
                    ],
                ]
            ),
            $this->createToolDefinition(
                'create_pages',
                'Generate test pages with random content',
                [
                    'count' => [
                        'type' => 'integer',
                        'description' => 'Number of pages to create (default: 5, max: 50)',
                    ],
                    'status' => [
                        'type' => 'string',
                        'description' => 'Page status (default: draft)',
                    ],
                    'parent' => [
                        'type' => 'integer',
                        'description' => 'Parent page ID for hierarchical pages',
                    ],
                ]
            ),
            $this->createToolDefinition(
                'create_users',
                'Generate test users',
                [
                    'count' => [
                        'type' => 'integer',
                        'description' => 'Number of users to create (default: 5, max: 50)',
                    ],
                    'role' => [
                        'type' => 'string',
                        'description' => 'User role (default: subscriber)',
                        'enum' => ['administrator', 'editor', 'author', 'contributor', 'subscriber', 'customer'],
                    ],
                ]
            ),
            $this->createToolDefinition(
                'create_terms',
                'Generate terms for a taxonomy',
                [
                    'taxonomy' => [
                        'type' => 'string',
                        'description' => 'Taxonomy to add terms to',
                    ],
                    'count' => [
                        'type' => 'integer',
                        'description' => 'Number of terms to create (default: 10, max: 100)',
                    ],
                    'parent' => [
                        'type' => 'integer',
                        'description' => 'Parent term ID for hierarchical taxonomies',
                    ],
                ],
                ['taxonomy']
            ),
            $this->createToolDefinition(
                'create_products',
                'Generate WooCommerce products (requires WooCommerce)',
                [
                    'count' => [
                        'type' => 'integer',
                        'description' => 'Number of products to create (default: 10, max: 100)',
                    ],
                    'type' => [
                        'type' => 'string',
                        'description' => 'Product type',
                        'enum' => ['simple', 'variable', 'grouped', 'external'],
                    ],
                    'status' => [
                        'type' => 'string',
                        'description' => 'Product status (default: draft)',
                    ],
                    'categories' => [
                        'type' => 'array',
                        'description' => 'Product category IDs',
                    ],
                    'price_min' => [
                        'type' => 'number',
                        'description' => 'Minimum price (default: 10)',
                    ],
                    'price_max' => [
                        'type' => 'number',
                        'description' => 'Maximum price (default: 500)',
                    ],
                ]
            ),
            $this->createToolDefinition(
                'populate_acf',
                'Populate ACF fields with test data (requires ACF)',
                [
                    'post_id' => [
                        'type' => 'integer',
                        'description' => 'Post ID to populate fields for',
                    ],
                    'field_group' => [
                        'type' => 'string',
                        'description' => 'Specific field group key to populate (optional)',
                    ],
                ],
                ['post_id']
            ),
            $this->createToolDefinition(
                'create_comments',
                'Generate test comments on posts',
                [
                    'post_id' => [
                        'type' => 'integer',
                        'description' => 'Post ID to add comments to (random if not specified)',
                    ],
                    'count' => [
                        'type' => 'integer',
                        'description' => 'Number of comments to create (default: 10, max: 100)',
                    ],
                    'status' => [
                        'type' => 'string',
                        'description' => 'Comment status',
                        'enum' => ['approve', 'hold', 'spam', 'trash'],
                    ],
                ]
            ),
        ];
    }

    public function handles(string $name): bool
    {
        return in_array($name, [
            'create_posts', 'create_pages', 'create_users',
            'create_terms', 'create_products', 'populate_acf',
            'create_comments'
        ]);
    }

    public function execute(string $name, array $arguments): mixed
    {
        if (!$this->faker) {
            return [
                'error' => 'Faker library is not installed.',
                'suggestion' => 'Run: composer require fakerphp/faker --dev',
            ];
        }

        return match ($name) {
            'create_posts' => $this->createPosts($arguments),
            'create_pages' => $this->createPages($arguments),
            'create_users' => $this->createUsers($arguments),
            'create_terms' => $this->createTerms($arguments),
            'create_products' => $this->createProducts($arguments),
            'populate_acf' => $this->populateAcf($arguments),
            'create_comments' => $this->createComments($arguments),
            default => throw new \RuntimeException("Unknown tool: {$name}"),
        };
    }

    private function createPosts(array $args): array
    {
        $count = min($args['count'] ?? 10, 100);
        $postType = $args['post_type'] ?? 'post';
        $status = $args['status'] ?? 'draft';
        $author = $args['author'] ?? get_current_user_id();
        $categories = $args['categories'] ?? [];
        $tags = $args['tags'] ?? [];

        // Verify post type exists
        if (!post_type_exists($postType)) {
            return [
                'error' => "Post type does not exist: {$postType}",
                'available_types' => array_keys(get_post_types(['public' => true])),
            ];
        }

        $created = [];

        for ($i = 0; $i < $count; $i++) {
            $title = $this->faker->sentence(rand(4, 8));
            $content = $this->generateContent();

            $postData = [
                'post_title' => $title,
                'post_content' => $content,
                'post_status' => $status,
                'post_type' => $postType,
                'post_author' => $author,
                'post_excerpt' => $this->faker->paragraph(),
            ];

            $postId = wp_insert_post($postData);

            if (is_wp_error($postId)) {
                continue;
            }

            // Assign categories
            if (!empty($categories) && $postType === 'post') {
                wp_set_post_categories($postId, $categories);
            }

            // Assign tags
            if (!empty($tags) && $postType === 'post') {
                wp_set_post_tags($postId, $tags);
            }

            $created[] = [
                'ID' => $postId,
                'title' => $title,
                'url' => get_permalink($postId),
            ];
        }

        return [
            'created' => count($created),
            'post_type' => $postType,
            'status' => $status,
            'posts' => $created,
        ];
    }

    private function createPages(array $args): array
    {
        $count = min($args['count'] ?? 5, 50);
        $status = $args['status'] ?? 'draft';
        $parent = $args['parent'] ?? 0;

        $created = [];

        for ($i = 0; $i < $count; $i++) {
            $title = $this->faker->words(rand(2, 5), true);
            $content = $this->generateContent();

            $postData = [
                'post_title' => ucfirst($title),
                'post_content' => $content,
                'post_status' => $status,
                'post_type' => 'page',
                'post_parent' => $parent,
            ];

            $postId = wp_insert_post($postData);

            if (is_wp_error($postId)) {
                continue;
            }

            $created[] = [
                'ID' => $postId,
                'title' => ucfirst($title),
                'url' => get_permalink($postId),
            ];
        }

        return [
            'created' => count($created),
            'status' => $status,
            'pages' => $created,
        ];
    }

    private function createUsers(array $args): array
    {
        $count = min($args['count'] ?? 5, 50);
        $role = $args['role'] ?? 'subscriber';

        // Verify role exists
        $wpRoles = wp_roles();
        if (!isset($wpRoles->roles[$role])) {
            return [
                'error' => "Role does not exist: {$role}",
                'available_roles' => array_keys($wpRoles->roles),
            ];
        }

        $created = [];

        for ($i = 0; $i < $count; $i++) {
            $firstName = $this->faker->firstName();
            $lastName = $this->faker->lastName();
            $username = strtolower($firstName . '.' . $lastName . rand(1, 999));
            $email = $this->faker->unique()->safeEmail();

            $userData = [
                'user_login' => $username,
                'user_email' => $email,
                'user_pass' => wp_generate_password(12, true),
                'first_name' => $firstName,
                'last_name' => $lastName,
                'display_name' => "{$firstName} {$lastName}",
                'role' => $role,
            ];

            $userId = wp_insert_user($userData);

            if (is_wp_error($userId)) {
                continue;
            }

            $created[] = [
                'ID' => $userId,
                'username' => $username,
                'email' => $email,
                'display_name' => "{$firstName} {$lastName}",
            ];
        }

        return [
            'created' => count($created),
            'role' => $role,
            'users' => $created,
        ];
    }

    private function createTerms(array $args): array
    {
        $taxonomy = $args['taxonomy'];
        $count = min($args['count'] ?? 10, 100);
        $parent = $args['parent'] ?? 0;

        // Verify taxonomy exists
        if (!taxonomy_exists($taxonomy)) {
            return [
                'error' => "Taxonomy does not exist: {$taxonomy}",
                'available_taxonomies' => array_keys(get_taxonomies(['public' => true])),
            ];
        }

        $created = [];

        for ($i = 0; $i < $count; $i++) {
            $name = $this->faker->words(rand(1, 3), true);

            $termArgs = [];
            if ($parent > 0 && is_taxonomy_hierarchical($taxonomy)) {
                $termArgs['parent'] = $parent;
            }

            $term = wp_insert_term(ucfirst($name), $taxonomy, $termArgs);

            if (is_wp_error($term)) {
                continue;
            }

            $created[] = [
                'term_id' => $term['term_id'],
                'name' => ucfirst($name),
                'slug' => sanitize_title($name),
            ];
        }

        return [
            'created' => count($created),
            'taxonomy' => $taxonomy,
            'terms' => $created,
        ];
    }

    private function createProducts(array $args): array
    {
        // Check if WooCommerce is active
        if (!class_exists('WooCommerce')) {
            return [
                'error' => 'WooCommerce is not active.',
                'suggestion' => 'Install and activate WooCommerce to use this tool.',
            ];
        }

        $count = min($args['count'] ?? 10, 100);
        $type = $args['type'] ?? 'simple';
        $status = $args['status'] ?? 'draft';
        $categories = $args['categories'] ?? [];
        $priceMin = $args['price_min'] ?? 10;
        $priceMax = $args['price_max'] ?? 500;

        $created = [];

        for ($i = 0; $i < $count; $i++) {
            $title = $this->faker->words(rand(2, 5), true);
            $price = $this->faker->randomFloat(2, $priceMin, $priceMax);

            // Create product based on type
            switch ($type) {
                case 'variable':
                    $product = new \WC_Product_Variable();
                    break;
                case 'grouped':
                    $product = new \WC_Product_Grouped();
                    break;
                case 'external':
                    $product = new \WC_Product_External();
                    break;
                default:
                    $product = new \WC_Product_Simple();
            }

            $product->set_name(ucfirst($title));
            $product->set_status($status);
            $product->set_description($this->generateContent());
            $product->set_short_description($this->faker->paragraph());
            $product->set_sku('SKU-' . strtoupper($this->faker->lexify('??????')));

            if ($type === 'simple') {
                $product->set_regular_price($price);

                // Random sale price
                if ($this->faker->boolean(30)) {
                    $salePrice = $price * (1 - rand(10, 40) / 100);
                    $product->set_sale_price(round($salePrice, 2));
                }

                // Stock management
                if ($this->faker->boolean(70)) {
                    $product->set_manage_stock(true);
                    $product->set_stock_quantity(rand(0, 100));
                }
            }

            // Virtual and downloadable
            if ($this->faker->boolean(20)) {
                $product->set_virtual(true);
            }

            if ($this->faker->boolean(15)) {
                $product->set_downloadable(true);
            }

            // Weight and dimensions for physical products
            if (!$product->get_virtual()) {
                $product->set_weight($this->faker->randomFloat(2, 0.1, 10));
                $product->set_length($this->faker->randomFloat(1, 1, 50));
                $product->set_width($this->faker->randomFloat(1, 1, 50));
                $product->set_height($this->faker->randomFloat(1, 1, 30));
            }

            $productId = $product->save();

            // Assign categories
            if (!empty($categories)) {
                wp_set_object_terms($productId, $categories, 'product_cat');
            }

            $created[] = [
                'ID' => $productId,
                'name' => ucfirst($title),
                'type' => $type,
                'price' => $type === 'simple' ? $price : null,
                'url' => get_permalink($productId),
            ];
        }

        return [
            'created' => count($created),
            'type' => $type,
            'status' => $status,
            'products' => $created,
        ];
    }

    private function populateAcf(array $args): array
    {
        // Check if ACF is active
        if (!function_exists('acf_get_field_groups')) {
            return [
                'error' => 'ACF is not active.',
                'suggestion' => 'Install and activate ACF to use this tool.',
            ];
        }

        $postId = $args['post_id'];
        $fieldGroupKey = $args['field_group'] ?? null;

        // Verify post exists
        $post = get_post($postId);
        if (!$post) {
            return [
                'error' => "Post not found: {$postId}",
            ];
        }

        // Get field groups for this post
        $fieldGroups = acf_get_field_groups(['post_id' => $postId]);

        if (empty($fieldGroups)) {
            return [
                'error' => 'No ACF field groups found for this post.',
                'post_type' => $post->post_type,
            ];
        }

        $populated = [];

        foreach ($fieldGroups as $group) {
            // Filter by specific group if requested
            if ($fieldGroupKey !== null && $group['key'] !== $fieldGroupKey) {
                continue;
            }

            $fields = acf_get_fields($group['key']);

            if (empty($fields)) {
                continue;
            }

            foreach ($fields as $field) {
                $value = $this->generateAcfValue($field);

                if ($value !== null) {
                    update_field($field['key'], $value, $postId);

                    $populated[] = [
                        'field' => $field['name'],
                        'type' => $field['type'],
                        'value_preview' => $this->previewValue($value),
                    ];
                }
            }
        }

        return [
            'post_id' => $postId,
            'populated' => count($populated),
            'fields' => $populated,
        ];
    }

    private function createComments(array $args): array
    {
        $postId = $args['post_id'] ?? null;
        $count = min($args['count'] ?? 10, 100);
        $status = $args['status'] ?? 'approve';

        // If no post specified, get a random post
        if ($postId === null) {
            $posts = get_posts([
                'post_type' => 'post',
                'post_status' => 'publish',
                'numberposts' => 1,
                'orderby' => 'rand',
            ]);

            if (empty($posts)) {
                return [
                    'error' => 'No published posts found to add comments to.',
                ];
            }

            $postId = $posts[0]->ID;
        }

        // Verify post exists
        $post = get_post($postId);
        if (!$post) {
            return [
                'error' => "Post not found: {$postId}",
            ];
        }

        $statusMap = [
            'approve' => 1,
            'hold' => 0,
            'spam' => 'spam',
            'trash' => 'trash',
        ];

        $created = [];

        for ($i = 0; $i < $count; $i++) {
            $commentData = [
                'comment_post_ID' => $postId,
                'comment_author' => $this->faker->name(),
                'comment_author_email' => $this->faker->safeEmail(),
                'comment_author_url' => $this->faker->boolean(30) ? $this->faker->url() : '',
                'comment_content' => $this->faker->paragraph(rand(1, 3)),
                'comment_approved' => $statusMap[$status] ?? 1,
            ];

            $commentId = wp_insert_comment($commentData);

            if ($commentId) {
                $created[] = [
                    'comment_ID' => $commentId,
                    'author' => $commentData['comment_author'],
                    'content_preview' => substr($commentData['comment_content'], 0, 50) . '...',
                ];
            }
        }

        return [
            'created' => count($created),
            'post_id' => $postId,
            'post_title' => $post->post_title,
            'status' => $status,
            'comments' => $created,
        ];
    }

    private function generateContent(): string
    {
        $paragraphs = rand(3, 7);
        $content = '';

        for ($i = 0; $i < $paragraphs; $i++) {
            // Occasionally add a heading
            if ($i > 0 && $this->faker->boolean(30)) {
                $level = rand(2, 4);
                $content .= "<!-- wp:heading {\"level\":{$level}} -->\n";
                $content .= "<h{$level}>" . $this->faker->sentence(rand(3, 6)) . "</h{$level}>\n";
                $content .= "<!-- /wp:heading -->\n\n";
            }

            $content .= "<!-- wp:paragraph -->\n";
            $content .= "<p>" . $this->faker->paragraph(rand(3, 6)) . "</p>\n";
            $content .= "<!-- /wp:paragraph -->\n\n";

            // Occasionally add a list
            if ($this->faker->boolean(20)) {
                $items = rand(3, 6);
                $content .= "<!-- wp:list -->\n<ul>\n";
                for ($j = 0; $j < $items; $j++) {
                    $content .= "<li>" . $this->faker->sentence() . "</li>\n";
                }
                $content .= "</ul>\n<!-- /wp:list -->\n\n";
            }
        }

        return $content;
    }

    private function generateAcfValue(array $field)
    {
        switch ($field['type']) {
            case 'text':
                return $this->faker->sentence();

            case 'textarea':
                return $this->faker->paragraph();

            case 'number':
                $min = $field['min'] ?? 0;
                $max = $field['max'] ?? 1000;
                return rand($min, $max);

            case 'range':
                $min = $field['min'] ?? 0;
                $max = $field['max'] ?? 100;
                return rand($min, $max);

            case 'email':
                return $this->faker->email();

            case 'url':
                return $this->faker->url();

            case 'password':
                return $this->faker->password();

            case 'wysiwyg':
                return $this->faker->paragraphs(rand(2, 4), true);

            case 'select':
            case 'radio':
            case 'button_group':
                if (!empty($field['choices'])) {
                    $choices = array_keys($field['choices']);
                    return $choices[array_rand($choices)];
                }
                return null;

            case 'checkbox':
                if (!empty($field['choices'])) {
                    $choices = array_keys($field['choices']);
                    shuffle($choices);
                    return array_slice($choices, 0, rand(1, min(3, count($choices))));
                }
                return [];

            case 'true_false':
                return $this->faker->boolean();

            case 'date_picker':
                return $this->faker->date('Ymd');

            case 'date_time_picker':
                return $this->faker->date('Y-m-d H:i:s');

            case 'time_picker':
                return $this->faker->time('H:i:s');

            case 'color_picker':
                return $this->faker->hexColor();

            default:
                return null;
        }
    }

    private function previewValue($value): string
    {
        if (is_array($value)) {
            return '[' . implode(', ', array_slice($value, 0, 3)) . (count($value) > 3 ? '...' : '') . ']';
        }

        if (is_bool($value)) {
            return $value ? 'true' : 'false';
        }

        if (is_string($value) && strlen($value) > 50) {
            return substr($value, 0, 50) . '...';
        }

        return (string) $value;
    }
}
