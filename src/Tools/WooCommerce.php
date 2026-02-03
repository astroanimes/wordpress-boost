<?php

declare(strict_types=1);

namespace WordPressBoost\Tools;

/**
 * WooCommerce Tool
 *
 * Provides introspection into WooCommerce data and configuration.
 */
class WooCommerce extends BaseTool
{
    public function getToolDefinitions(): array
    {
        return [
            $this->createToolDefinition(
                'woo_info',
                'Get WooCommerce installation information and settings overview'
            ),
            $this->createToolDefinition(
                'list_product_types',
                'List all registered WooCommerce product types'
            ),
            $this->createToolDefinition(
                'woo_schema',
                'Get WooCommerce database table structures',
                [
                    'table' => [
                        'type' => 'string',
                        'description' => 'Specific table to inspect (without prefix): orders, products, customers, subscriptions, etc.',
                    ],
                ]
            ),
            $this->createToolDefinition(
                'list_payment_gateways',
                'List all registered payment gateways',
                [
                    'enabled' => [
                        'type' => 'boolean',
                        'description' => 'Filter by enabled status',
                    ],
                ]
            ),
            $this->createToolDefinition(
                'list_shipping_methods',
                'List all registered shipping methods'
            ),
            $this->createToolDefinition(
                'get_woo_settings',
                'Get WooCommerce settings for a specific tab',
                [
                    'tab' => [
                        'type' => 'string',
                        'description' => 'Settings tab: general, products, shipping, payments, accounts, emails, integration, advanced',
                    ],
                ]
            ),
            $this->createToolDefinition(
                'list_woo_hooks',
                'List important WooCommerce action and filter hooks',
                [
                    'category' => [
                        'type' => 'string',
                        'description' => 'Filter by category: cart, checkout, product, order, account, email, or all',
                        'enum' => ['cart', 'checkout', 'product', 'order', 'account', 'email', 'all'],
                    ],
                ]
            ),
        ];
    }

    public function handles(string $name): bool
    {
        return in_array($name, [
            'woo_info', 'list_product_types', 'woo_schema',
            'list_payment_gateways', 'list_shipping_methods',
            'get_woo_settings', 'list_woo_hooks'
        ]);
    }

    public function execute(string $name, array $arguments): mixed
    {
        // Check if WooCommerce is active
        if (!$this->isWooCommerceActive()) {
            return [
                'error' => 'WooCommerce is not active.',
                'suggestion' => 'Install and activate WooCommerce to use these tools.',
            ];
        }

        return match ($name) {
            'woo_info' => $this->getWooInfo(),
            'list_product_types' => $this->listProductTypes(),
            'woo_schema' => $this->getSchema($arguments['table'] ?? null),
            'list_payment_gateways' => $this->listPaymentGateways($arguments['enabled'] ?? null),
            'list_shipping_methods' => $this->listShippingMethods(),
            'get_woo_settings' => $this->getSettings($arguments['tab'] ?? 'general'),
            'list_woo_hooks' => $this->listWooHooks($arguments['category'] ?? 'all'),
            default => throw new \RuntimeException("Unknown tool: {$name}"),
        };
    }

    private function isWooCommerceActive(): bool
    {
        return class_exists('WooCommerce');
    }

    private function getWooInfo(): array
    {
        $wc = WC();

        // Get store info
        $storeAddress = [
            'address' => get_option('woocommerce_store_address'),
            'address_2' => get_option('woocommerce_store_address_2'),
            'city' => get_option('woocommerce_store_city'),
            'postcode' => get_option('woocommerce_store_postcode'),
            'country' => get_option('woocommerce_default_country'),
        ];

        // Get currency info
        $currency = [
            'code' => get_woocommerce_currency(),
            'symbol' => get_woocommerce_currency_symbol(),
            'position' => get_option('woocommerce_currency_pos'),
            'decimals' => wc_get_price_decimals(),
            'thousand_separator' => wc_get_price_thousand_separator(),
            'decimal_separator' => wc_get_price_decimal_separator(),
        ];

        // Get counts
        $counts = [
            'products' => (int) wp_count_posts('product')->publish,
            'orders' => (int) wp_count_posts('shop_order')->{'wc-completed'} ?? 0,
            'customers' => $this->getCustomerCount(),
            'coupons' => (int) wp_count_posts('shop_coupon')->publish,
        ];

        // Get page info
        $pages = [
            'shop' => get_option('woocommerce_shop_page_id'),
            'cart' => get_option('woocommerce_cart_page_id'),
            'checkout' => get_option('woocommerce_checkout_page_id'),
            'myaccount' => get_option('woocommerce_myaccount_page_id'),
            'terms' => get_option('woocommerce_terms_page_id'),
        ];

        // Check for common extensions
        $extensions = [
            'subscriptions' => class_exists('WC_Subscriptions'),
            'memberships' => class_exists('WC_Memberships'),
            'bookings' => class_exists('WC_Bookings'),
            'product_bundles' => class_exists('WC_Bundles'),
            'composite_products' => class_exists('WC_Composite_Products'),
        ];

        return [
            'version' => $wc->version,
            'database_version' => get_option('woocommerce_db_version'),
            'store_address' => $storeAddress,
            'currency' => $currency,
            'counts' => $counts,
            'pages' => $pages,
            'extensions' => $extensions,
            'tax_enabled' => wc_tax_enabled(),
            'shipping_enabled' => wc_shipping_enabled(),
            'coupons_enabled' => wc_coupons_enabled(),
            'guest_checkout' => get_option('woocommerce_enable_guest_checkout') === 'yes',
            'hpos_enabled' => $this->isHposEnabled(),
        ];
    }

    private function listProductTypes(): array
    {
        $types = wc_get_product_types();

        $result = [];
        foreach ($types as $slug => $label) {
            $result[] = [
                'slug' => $slug,
                'label' => $label,
                'class' => $this->getProductTypeClass($slug),
            ];
        }

        // Add virtual and downloadable info
        $attributes = [
            [
                'attribute' => 'virtual',
                'description' => 'Product is virtual (no shipping)',
            ],
            [
                'attribute' => 'downloadable',
                'description' => 'Product is downloadable',
            ],
        ];

        return [
            'count' => count($result),
            'types' => $result,
            'product_attributes' => $attributes,
        ];
    }

    private function getSchema(?string $table = null): array
    {
        global $wpdb;

        // WooCommerce tables
        $wooTables = [
            'wc_orders' => 'Orders (HPOS)',
            'wc_orders_meta' => 'Order meta (HPOS)',
            'wc_order_addresses' => 'Order addresses (HPOS)',
            'wc_order_operational_data' => 'Order operational data (HPOS)',
            'wc_order_product_lookup' => 'Order product lookup',
            'wc_order_tax_lookup' => 'Order tax lookup',
            'wc_order_coupon_lookup' => 'Order coupon lookup',
            'wc_order_stats' => 'Order statistics',
            'wc_customer_lookup' => 'Customer lookup',
            'wc_category_lookup' => 'Category lookup',
            'wc_product_meta_lookup' => 'Product meta lookup',
            'wc_webhooks' => 'Webhooks',
            'wc_download_log' => 'Download logs',
            'woocommerce_sessions' => 'Sessions',
            'woocommerce_api_keys' => 'API keys',
            'woocommerce_attribute_taxonomies' => 'Product attributes',
            'woocommerce_downloadable_product_permissions' => 'Download permissions',
            'woocommerce_order_items' => 'Order items',
            'woocommerce_order_itemmeta' => 'Order item meta',
            'woocommerce_tax_rates' => 'Tax rates',
            'woocommerce_tax_rate_locations' => 'Tax rate locations',
            'woocommerce_shipping_zones' => 'Shipping zones',
            'woocommerce_shipping_zone_locations' => 'Shipping zone locations',
            'woocommerce_shipping_zone_methods' => 'Shipping zone methods',
            'woocommerce_payment_tokens' => 'Payment tokens',
            'woocommerce_payment_tokenmeta' => 'Payment token meta',
            'woocommerce_log' => 'Logs',
        ];

        if ($table !== null) {
            // Check specific table
            $fullTableName = $wpdb->prefix . $table;

            // Also try with wc_ prefix
            if (!$this->tableExists($fullTableName)) {
                $fullTableName = $wpdb->prefix . 'wc_' . $table;
            }

            if (!$this->tableExists($fullTableName)) {
                $fullTableName = $wpdb->prefix . 'woocommerce_' . $table;
            }

            if (!$this->tableExists($fullTableName)) {
                return [
                    'error' => "Table not found: {$table}",
                    'available_tables' => array_keys($wooTables),
                ];
            }

            return $this->getTableSchema($fullTableName);
        }

        // Get all WooCommerce tables that exist
        $result = [];
        foreach ($wooTables as $tableName => $description) {
            $fullName = $wpdb->prefix . $tableName;
            if ($this->tableExists($fullName)) {
                $result[] = [
                    'table' => $tableName,
                    'full_name' => $fullName,
                    'description' => $description,
                    'exists' => true,
                ];
            }
        }

        return [
            'count' => count($result),
            'prefix' => $wpdb->prefix,
            'tables' => $result,
        ];
    }

    private function listPaymentGateways(?bool $enabled = null): array
    {
        $gateways = WC()->payment_gateways()->payment_gateways();

        $result = [];
        foreach ($gateways as $gateway) {
            $isEnabled = $gateway->enabled === 'yes';

            if ($enabled !== null && $enabled !== $isEnabled) {
                continue;
            }

            $result[] = [
                'id' => $gateway->id,
                'title' => $gateway->get_title(),
                'description' => $gateway->get_description(),
                'method_title' => $gateway->get_method_title(),
                'method_description' => $gateway->get_method_description(),
                'enabled' => $isEnabled,
                'supports' => $gateway->supports,
                'has_fields' => $gateway->has_fields,
            ];
        }

        return [
            'count' => count($result),
            'gateways' => $result,
        ];
    }

    private function listShippingMethods(): array
    {
        $methods = WC()->shipping()->get_shipping_methods();

        $result = [];
        foreach ($methods as $method) {
            $result[] = [
                'id' => $method->id,
                'title' => $method->get_method_title(),
                'description' => $method->get_method_description(),
                'supports' => $method->supports,
                'enabled' => $method->enabled,
            ];
        }

        // Get shipping zones
        $zones = \WC_Shipping_Zones::get_zones();
        $zoneInfo = [];

        foreach ($zones as $zone) {
            $zoneMethods = [];
            foreach ($zone['shipping_methods'] as $method) {
                $zoneMethods[] = [
                    'id' => $method->id,
                    'title' => $method->get_title(),
                    'enabled' => $method->enabled,
                ];
            }

            $zoneInfo[] = [
                'id' => $zone['id'],
                'name' => $zone['zone_name'],
                'locations' => count($zone['zone_locations']),
                'methods' => $zoneMethods,
            ];
        }

        return [
            'method_count' => count($result),
            'methods' => $result,
            'zone_count' => count($zoneInfo),
            'zones' => $zoneInfo,
        ];
    }

    private function getSettings(string $tab): array
    {
        // Get settings tabs
        $settings = [
            'general' => [
                'store_address' => get_option('woocommerce_store_address'),
                'store_city' => get_option('woocommerce_store_city'),
                'default_country' => get_option('woocommerce_default_country'),
                'currency' => get_option('woocommerce_currency'),
                'selling_locations' => get_option('woocommerce_allowed_countries'),
                'enable_coupons' => get_option('woocommerce_enable_coupons'),
                'calc_taxes' => get_option('woocommerce_calc_taxes'),
            ],
            'products' => [
                'shop_page_id' => get_option('woocommerce_shop_page_id'),
                'cart_redirect_after_add' => get_option('woocommerce_cart_redirect_after_add'),
                'enable_ajax_add_to_cart' => get_option('woocommerce_enable_ajax_add_to_cart'),
                'weight_unit' => get_option('woocommerce_weight_unit'),
                'dimension_unit' => get_option('woocommerce_dimension_unit'),
                'enable_reviews' => get_option('woocommerce_enable_reviews'),
                'manage_stock' => get_option('woocommerce_manage_stock'),
            ],
            'shipping' => [
                'enable_shipping_calc' => get_option('woocommerce_enable_shipping_calc'),
                'shipping_cost_requires_address' => get_option('woocommerce_shipping_cost_requires_address'),
                'ship_to_destination' => get_option('woocommerce_ship_to_destination'),
            ],
            'payments' => $this->listPaymentGateways(true),
            'accounts' => [
                'enable_guest_checkout' => get_option('woocommerce_enable_guest_checkout'),
                'enable_checkout_login_reminder' => get_option('woocommerce_enable_checkout_login_reminder'),
                'enable_signup_and_login_from_checkout' => get_option('woocommerce_enable_signup_and_login_from_checkout'),
                'enable_myaccount_registration' => get_option('woocommerce_enable_myaccount_registration'),
                'registration_generate_username' => get_option('woocommerce_registration_generate_username'),
                'registration_generate_password' => get_option('woocommerce_registration_generate_password'),
            ],
            'emails' => [
                'email_from_name' => get_option('woocommerce_email_from_name'),
                'email_from_address' => get_option('woocommerce_email_from_address'),
                'email_header_image' => get_option('woocommerce_email_header_image'),
                'email_footer_text' => get_option('woocommerce_email_footer_text'),
                'email_base_color' => get_option('woocommerce_email_base_color'),
            ],
            'advanced' => [
                'cart_page_id' => get_option('woocommerce_cart_page_id'),
                'checkout_page_id' => get_option('woocommerce_checkout_page_id'),
                'myaccount_page_id' => get_option('woocommerce_myaccount_page_id'),
                'terms_page_id' => get_option('woocommerce_terms_page_id'),
                'force_ssl_checkout' => get_option('woocommerce_force_ssl_checkout'),
                'api_enabled' => get_option('woocommerce_api_enabled'),
            ],
        ];

        if (!isset($settings[$tab])) {
            return [
                'error' => "Unknown settings tab: {$tab}",
                'available_tabs' => array_keys($settings),
            ];
        }

        return [
            'tab' => $tab,
            'settings' => $settings[$tab],
        ];
    }

    private function listWooHooks(string $category = 'all'): array
    {
        $hooks = [
            'cart' => [
                ['hook' => 'woocommerce_add_to_cart', 'type' => 'action', 'description' => 'Fires when item is added to cart'],
                ['hook' => 'woocommerce_cart_item_removed', 'type' => 'action', 'description' => 'Fires when item is removed from cart'],
                ['hook' => 'woocommerce_cart_updated', 'type' => 'action', 'description' => 'Fires when cart is updated'],
                ['hook' => 'woocommerce_before_cart', 'type' => 'action', 'description' => 'Before cart table'],
                ['hook' => 'woocommerce_after_cart', 'type' => 'action', 'description' => 'After cart table'],
                ['hook' => 'woocommerce_cart_totals_before_order_total', 'type' => 'action', 'description' => 'Before cart total row'],
                ['hook' => 'woocommerce_cart_subtotal', 'type' => 'filter', 'description' => 'Filter cart subtotal'],
                ['hook' => 'woocommerce_cart_item_price', 'type' => 'filter', 'description' => 'Filter cart item price'],
            ],
            'checkout' => [
                ['hook' => 'woocommerce_before_checkout_form', 'type' => 'action', 'description' => 'Before checkout form'],
                ['hook' => 'woocommerce_after_checkout_form', 'type' => 'action', 'description' => 'After checkout form'],
                ['hook' => 'woocommerce_checkout_before_customer_details', 'type' => 'action', 'description' => 'Before customer details'],
                ['hook' => 'woocommerce_checkout_after_customer_details', 'type' => 'action', 'description' => 'After customer details'],
                ['hook' => 'woocommerce_checkout_process', 'type' => 'action', 'description' => 'Process checkout validation'],
                ['hook' => 'woocommerce_checkout_order_processed', 'type' => 'action', 'description' => 'After order is processed'],
                ['hook' => 'woocommerce_checkout_fields', 'type' => 'filter', 'description' => 'Filter checkout fields'],
                ['hook' => 'woocommerce_checkout_posted_data', 'type' => 'filter', 'description' => 'Filter posted checkout data'],
            ],
            'product' => [
                ['hook' => 'woocommerce_before_single_product', 'type' => 'action', 'description' => 'Before single product'],
                ['hook' => 'woocommerce_after_single_product', 'type' => 'action', 'description' => 'After single product'],
                ['hook' => 'woocommerce_before_single_product_summary', 'type' => 'action', 'description' => 'Before product summary (images)'],
                ['hook' => 'woocommerce_single_product_summary', 'type' => 'action', 'description' => 'Product summary content'],
                ['hook' => 'woocommerce_after_single_product_summary', 'type' => 'action', 'description' => 'After product summary (tabs)'],
                ['hook' => 'woocommerce_product_tabs', 'type' => 'filter', 'description' => 'Filter product tabs'],
                ['hook' => 'woocommerce_get_price_html', 'type' => 'filter', 'description' => 'Filter price HTML'],
            ],
            'order' => [
                ['hook' => 'woocommerce_new_order', 'type' => 'action', 'description' => 'When a new order is created'],
                ['hook' => 'woocommerce_order_status_changed', 'type' => 'action', 'description' => 'When order status changes'],
                ['hook' => 'woocommerce_order_status_completed', 'type' => 'action', 'description' => 'When order is completed'],
                ['hook' => 'woocommerce_payment_complete', 'type' => 'action', 'description' => 'When payment is complete'],
                ['hook' => 'woocommerce_thankyou', 'type' => 'action', 'description' => 'Thank you page content'],
                ['hook' => 'woocommerce_order_item_meta_start', 'type' => 'action', 'description' => 'Before order item meta'],
                ['hook' => 'woocommerce_order_items_table', 'type' => 'filter', 'description' => 'Filter order items in emails'],
            ],
            'account' => [
                ['hook' => 'woocommerce_before_my_account', 'type' => 'action', 'description' => 'Before my account page'],
                ['hook' => 'woocommerce_account_dashboard', 'type' => 'action', 'description' => 'Account dashboard content'],
                ['hook' => 'woocommerce_before_account_orders', 'type' => 'action', 'description' => 'Before orders list'],
                ['hook' => 'woocommerce_register_form', 'type' => 'action', 'description' => 'Registration form'],
                ['hook' => 'woocommerce_login_form', 'type' => 'action', 'description' => 'Login form'],
                ['hook' => 'woocommerce_account_menu_items', 'type' => 'filter', 'description' => 'Filter account menu items'],
            ],
            'email' => [
                ['hook' => 'woocommerce_email_header', 'type' => 'action', 'description' => 'Email header'],
                ['hook' => 'woocommerce_email_footer', 'type' => 'action', 'description' => 'Email footer'],
                ['hook' => 'woocommerce_email_order_details', 'type' => 'action', 'description' => 'Email order details'],
                ['hook' => 'woocommerce_email_customer_details', 'type' => 'action', 'description' => 'Email customer details'],
                ['hook' => 'woocommerce_email_classes', 'type' => 'filter', 'description' => 'Filter email classes'],
                ['hook' => 'woocommerce_email_styles', 'type' => 'filter', 'description' => 'Filter email CSS styles'],
            ],
        ];

        if ($category === 'all') {
            $allHooks = [];
            foreach ($hooks as $cat => $catHooks) {
                foreach ($catHooks as $hook) {
                    $hook['category'] = $cat;
                    $allHooks[] = $hook;
                }
            }

            return [
                'count' => count($allHooks),
                'categories' => array_keys($hooks),
                'hooks' => $allHooks,
            ];
        }

        if (!isset($hooks[$category])) {
            return [
                'error' => "Unknown category: {$category}",
                'available_categories' => array_keys($hooks),
            ];
        }

        return [
            'category' => $category,
            'count' => count($hooks[$category]),
            'hooks' => $hooks[$category],
        ];
    }

    private function getCustomerCount(): int
    {
        $customer_query = new \WP_User_Query([
            'role' => 'customer',
            'count_total' => true,
            'number' => 0,
        ]);

        return $customer_query->get_total();
    }

    private function getProductTypeClass(string $type): string
    {
        $classes = [
            'simple' => 'WC_Product_Simple',
            'grouped' => 'WC_Product_Grouped',
            'external' => 'WC_Product_External',
            'variable' => 'WC_Product_Variable',
        ];

        return $classes[$type] ?? 'WC_Product';
    }

    private function tableExists(string $tableName): bool
    {
        global $wpdb;

        return $wpdb->get_var(
            $wpdb->prepare(
                "SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = %s AND table_name = %s",
                $wpdb->dbname,
                $tableName
            )
        ) > 0;
    }

    private function getTableSchema(string $tableName): array
    {
        global $wpdb;

        $columns = $wpdb->get_results("DESCRIBE `{$tableName}`", ARRAY_A);
        $indexes = $wpdb->get_results("SHOW INDEX FROM `{$tableName}`", ARRAY_A);

        return [
            'table' => $tableName,
            'columns' => array_map(function ($col) {
                return [
                    'name' => $col['Field'],
                    'type' => $col['Type'],
                    'null' => $col['Null'] === 'YES',
                    'key' => $col['Key'],
                    'default' => $col['Default'],
                    'extra' => $col['Extra'],
                ];
            }, $columns),
            'indexes' => $indexes,
        ];
    }

    private function isHposEnabled(): bool
    {
        if (!class_exists('\Automattic\WooCommerce\Utilities\OrderUtil')) {
            return false;
        }

        return \Automattic\WooCommerce\Utilities\OrderUtil::custom_orders_table_usage_is_enabled();
    }
}
