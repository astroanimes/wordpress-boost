# WooCommerce Development Guidelines

## WooCommerce Hooks Overview

WooCommerce provides extensive hooks for customization. Key hook prefixes:
- `woocommerce_` - General WooCommerce hooks
- `wc_` - WooCommerce shorthand hooks

## Product Customization

### Adding Custom Product Fields
```php
// Add field to product edit page
add_action( 'woocommerce_product_options_general_product_data', 'add_custom_product_field' );

function add_custom_product_field() {
    woocommerce_wp_text_input( array(
        'id'          => '_custom_field',
        'label'       => __( 'Custom Field', 'my-plugin' ),
        'placeholder' => __( 'Enter value', 'my-plugin' ),
        'desc_tip'    => true,
        'description' => __( 'Description of field', 'my-plugin' ),
    ) );

    woocommerce_wp_textarea_input( array(
        'id'          => '_custom_textarea',
        'label'       => __( 'Custom Textarea', 'my-plugin' ),
        'desc_tip'    => true,
        'description' => __( 'Enter details', 'my-plugin' ),
    ) );

    woocommerce_wp_select( array(
        'id'      => '_custom_select',
        'label'   => __( 'Custom Select', 'my-plugin' ),
        'options' => array(
            ''       => __( 'Select option', 'my-plugin' ),
            'option1' => __( 'Option 1', 'my-plugin' ),
            'option2' => __( 'Option 2', 'my-plugin' ),
        ),
    ) );

    woocommerce_wp_checkbox( array(
        'id'          => '_custom_checkbox',
        'label'       => __( 'Custom Checkbox', 'my-plugin' ),
        'description' => __( 'Enable this option', 'my-plugin' ),
    ) );
}

// Save custom field
add_action( 'woocommerce_process_product_meta', 'save_custom_product_field' );

function save_custom_product_field( $post_id ) {
    $custom_field = isset( $_POST['_custom_field'] ) ? sanitize_text_field( $_POST['_custom_field'] ) : '';
    update_post_meta( $post_id, '_custom_field', $custom_field );
}
```

### Custom Product Tabs
```php
add_filter( 'woocommerce_product_tabs', 'add_custom_product_tab' );

function add_custom_product_tab( $tabs ) {
    $tabs['custom_tab'] = array(
        'title'    => __( 'Custom Tab', 'my-plugin' ),
        'priority' => 50,
        'callback' => 'custom_tab_content',
    );
    return $tabs;
}

function custom_tab_content() {
    global $product;
    $custom_field = get_post_meta( $product->get_id(), '_custom_field', true );
    echo '<h2>' . esc_html__( 'Custom Tab Title', 'my-plugin' ) . '</h2>';
    echo '<p>' . esc_html( $custom_field ) . '</p>';
}
```

## Cart & Checkout Customization

### Add Fields to Checkout
```php
// Add custom checkout field
add_action( 'woocommerce_after_order_notes', 'add_custom_checkout_field' );

function add_custom_checkout_field( $checkout ) {
    woocommerce_form_field( 'custom_field', array(
        'type'        => 'text',
        'class'       => array( 'form-row-wide' ),
        'label'       => __( 'Custom Field', 'my-plugin' ),
        'placeholder' => __( 'Enter value', 'my-plugin' ),
        'required'    => true,
    ), $checkout->get_value( 'custom_field' ) );
}

// Validate custom field
add_action( 'woocommerce_checkout_process', 'validate_custom_checkout_field' );

function validate_custom_checkout_field() {
    if ( empty( $_POST['custom_field'] ) ) {
        wc_add_notice( __( 'Please enter custom field value.', 'my-plugin' ), 'error' );
    }
}

// Save custom field to order
add_action( 'woocommerce_checkout_update_order_meta', 'save_custom_checkout_field' );

function save_custom_checkout_field( $order_id ) {
    if ( ! empty( $_POST['custom_field'] ) ) {
        update_post_meta( $order_id, '_custom_field', sanitize_text_field( $_POST['custom_field'] ) );
    }
}

// Display in admin order
add_action( 'woocommerce_admin_order_data_after_billing_address', 'display_custom_field_admin' );

function display_custom_field_admin( $order ) {
    $custom_field = get_post_meta( $order->get_id(), '_custom_field', true );
    if ( $custom_field ) {
        echo '<p><strong>' . esc_html__( 'Custom Field:', 'my-plugin' ) . '</strong> ' . esc_html( $custom_field ) . '</p>';
    }
}
```

### Modify Cart Item Data
```php
// Add custom data to cart item
add_filter( 'woocommerce_add_cart_item_data', 'add_custom_cart_item_data', 10, 3 );

function add_custom_cart_item_data( $cart_item_data, $product_id, $variation_id ) {
    if ( isset( $_POST['custom_option'] ) ) {
        $cart_item_data['custom_option'] = sanitize_text_field( $_POST['custom_option'] );
    }
    return $cart_item_data;
}

// Display custom data in cart
add_filter( 'woocommerce_get_item_data', 'display_custom_cart_item_data', 10, 2 );

function display_custom_cart_item_data( $item_data, $cart_item ) {
    if ( isset( $cart_item['custom_option'] ) ) {
        $item_data[] = array(
            'key'   => __( 'Custom Option', 'my-plugin' ),
            'value' => esc_html( $cart_item['custom_option'] ),
        );
    }
    return $item_data;
}

// Save custom data to order item
add_action( 'woocommerce_checkout_create_order_line_item', 'save_custom_order_item_data', 10, 4 );

function save_custom_order_item_data( $item, $cart_item_key, $values, $order ) {
    if ( isset( $values['custom_option'] ) ) {
        $item->add_meta_data( __( 'Custom Option', 'my-plugin' ), $values['custom_option'] );
    }
}
```

## Order Handling

### Working with Orders (HPOS Compatible)
```php
// Get order (works with HPOS)
$order = wc_get_order( $order_id );

// Order properties
$order_id = $order->get_id();
$status = $order->get_status();
$total = $order->get_total();
$currency = $order->get_currency();
$date = $order->get_date_created();

// Billing info
$billing_email = $order->get_billing_email();
$billing_phone = $order->get_billing_phone();
$billing_address = $order->get_formatted_billing_address();

// Order items
foreach ( $order->get_items() as $item_id => $item ) {
    $product_id = $item->get_product_id();
    $variation_id = $item->get_variation_id();
    $product = $item->get_product();
    $name = $item->get_name();
    $quantity = $item->get_quantity();
    $subtotal = $item->get_subtotal();
    $total = $item->get_total();
}

// Update order
$order->set_status( 'completed' );
$order->add_order_note( __( 'Order completed manually.', 'my-plugin' ) );
$order->save();

// Create order programmatically
$order = wc_create_order();
$order->add_product( wc_get_product( $product_id ), 1 );
$order->set_address( $address, 'billing' );
$order->set_address( $address, 'shipping' );
$order->calculate_totals();
$order->save();
```

### Custom Order Status
```php
// Register custom order status
add_action( 'init', 'register_custom_order_status' );

function register_custom_order_status() {
    register_post_status( 'wc-custom-status', array(
        'label'                     => _x( 'Custom Status', 'Order status', 'my-plugin' ),
        'public'                    => true,
        'exclude_from_search'       => false,
        'show_in_admin_all_list'    => true,
        'show_in_admin_status_list' => true,
        'label_count'               => _n_noop( 'Custom Status <span class="count">(%s)</span>', 'Custom Status <span class="count">(%s)</span>', 'my-plugin' ),
    ) );
}

// Add to order status list
add_filter( 'wc_order_statuses', 'add_custom_order_status' );

function add_custom_order_status( $order_statuses ) {
    $order_statuses['wc-custom-status'] = _x( 'Custom Status', 'Order status', 'my-plugin' );
    return $order_statuses;
}
```

## Payment Gateways

### Custom Payment Gateway
```php
add_filter( 'woocommerce_payment_gateways', 'add_custom_gateway' );

function add_custom_gateway( $gateways ) {
    $gateways[] = 'WC_Gateway_Custom';
    return $gateways;
}

add_action( 'plugins_loaded', 'init_custom_gateway' );

function init_custom_gateway() {
    class WC_Gateway_Custom extends WC_Payment_Gateway {
        public function __construct() {
            $this->id                 = 'custom_gateway';
            $this->icon               = '';
            $this->has_fields         = true;
            $this->method_title       = __( 'Custom Gateway', 'my-plugin' );
            $this->method_description = __( 'Custom payment gateway description.', 'my-plugin' );

            // Load settings
            $this->init_form_fields();
            $this->init_settings();

            $this->title       = $this->get_option( 'title' );
            $this->description = $this->get_option( 'description' );
            $this->enabled     = $this->get_option( 'enabled' );

            // Save settings
            add_action( 'woocommerce_update_options_payment_gateways_' . $this->id, array( $this, 'process_admin_options' ) );
        }

        public function init_form_fields() {
            $this->form_fields = array(
                'enabled' => array(
                    'title'   => __( 'Enable/Disable', 'my-plugin' ),
                    'type'    => 'checkbox',
                    'label'   => __( 'Enable Custom Gateway', 'my-plugin' ),
                    'default' => 'no',
                ),
                'title' => array(
                    'title'       => __( 'Title', 'my-plugin' ),
                    'type'        => 'text',
                    'description' => __( 'This controls the title displayed during checkout.', 'my-plugin' ),
                    'default'     => __( 'Custom Payment', 'my-plugin' ),
                ),
                'description' => array(
                    'title'       => __( 'Description', 'my-plugin' ),
                    'type'        => 'textarea',
                    'description' => __( 'This controls the description displayed during checkout.', 'my-plugin' ),
                    'default'     => __( 'Pay using custom gateway.', 'my-plugin' ),
                ),
            );
        }

        public function process_payment( $order_id ) {
            $order = wc_get_order( $order_id );

            // Process payment logic here

            // Mark as processing
            $order->update_status( 'processing', __( 'Payment received via Custom Gateway.', 'my-plugin' ) );

            // Reduce stock
            wc_reduce_stock_levels( $order_id );

            // Empty cart
            WC()->cart->empty_cart();

            // Return success
            return array(
                'result'   => 'success',
                'redirect' => $this->get_return_url( $order ),
            );
        }
    }
}
```

## Shipping Methods

### Custom Shipping Method
```php
add_filter( 'woocommerce_shipping_methods', 'add_custom_shipping_method' );

function add_custom_shipping_method( $methods ) {
    $methods['custom_shipping'] = 'WC_Shipping_Custom';
    return $methods;
}

add_action( 'woocommerce_shipping_init', 'init_custom_shipping_method' );

function init_custom_shipping_method() {
    class WC_Shipping_Custom extends WC_Shipping_Method {
        public function __construct( $instance_id = 0 ) {
            $this->id                 = 'custom_shipping';
            $this->instance_id        = absint( $instance_id );
            $this->method_title       = __( 'Custom Shipping', 'my-plugin' );
            $this->method_description = __( 'Custom shipping method.', 'my-plugin' );
            $this->supports           = array(
                'shipping-zones',
                'instance-settings',
            );

            $this->init();
        }

        public function init() {
            $this->init_form_fields();
            $this->init_settings();

            $this->title = $this->get_option( 'title' );
            $this->cost  = $this->get_option( 'cost' );

            add_action( 'woocommerce_update_options_shipping_' . $this->id, array( $this, 'process_admin_options' ) );
        }

        public function init_form_fields() {
            $this->instance_form_fields = array(
                'title' => array(
                    'title'   => __( 'Title', 'my-plugin' ),
                    'type'    => 'text',
                    'default' => __( 'Custom Shipping', 'my-plugin' ),
                ),
                'cost' => array(
                    'title'   => __( 'Cost', 'my-plugin' ),
                    'type'    => 'price',
                    'default' => '10',
                ),
            );
        }

        public function calculate_shipping( $package = array() ) {
            $this->add_rate( array(
                'id'    => $this->get_rate_id(),
                'label' => $this->title,
                'cost'  => $this->cost,
            ) );
        }
    }
}
```

## WooCommerce REST API

### Custom REST Endpoints
```php
add_action( 'rest_api_init', 'register_custom_wc_endpoints' );

function register_custom_wc_endpoints() {
    register_rest_route( 'my-plugin/v1', '/custom-products', array(
        'methods'             => 'GET',
        'callback'            => 'get_custom_products',
        'permission_callback' => function() {
            return current_user_can( 'read' );
        },
    ) );
}

function get_custom_products( $request ) {
    $args = array(
        'post_type'      => 'product',
        'posts_per_page' => 10,
        'meta_query'     => array(
            array(
                'key'     => '_custom_field',
                'compare' => 'EXISTS',
            ),
        ),
    );

    $products = wc_get_products( $args );
    $data = array();

    foreach ( $products as $product ) {
        $data[] = array(
            'id'           => $product->get_id(),
            'name'         => $product->get_name(),
            'price'        => $product->get_price(),
            'custom_field' => get_post_meta( $product->get_id(), '_custom_field', true ),
        );
    }

    return rest_ensure_response( $data );
}
```

## Common WooCommerce Hooks

### Template Hooks
```php
// Before/after shop loop
add_action( 'woocommerce_before_shop_loop', 'my_before_shop' );
add_action( 'woocommerce_after_shop_loop', 'my_after_shop' );

// Single product
add_action( 'woocommerce_before_single_product', 'my_before_product' );
add_action( 'woocommerce_after_single_product', 'my_after_product' );
add_action( 'woocommerce_single_product_summary', 'my_product_summary', 25 );

// Cart
add_action( 'woocommerce_before_cart', 'my_before_cart' );
add_action( 'woocommerce_after_cart', 'my_after_cart' );

// Checkout
add_action( 'woocommerce_before_checkout_form', 'my_before_checkout' );
add_action( 'woocommerce_after_checkout_form', 'my_after_checkout' );
```

### Price Filters
```php
// Modify product price display
add_filter( 'woocommerce_get_price_html', 'custom_price_html', 10, 2 );

function custom_price_html( $price, $product ) {
    if ( $product->is_on_sale() ) {
        $price .= ' <span class="sale-badge">' . __( 'Sale!', 'my-plugin' ) . '</span>';
    }
    return $price;
}

// Modify cart item price
add_filter( 'woocommerce_cart_item_price', 'custom_cart_item_price', 10, 3 );

function custom_cart_item_price( $price, $cart_item, $cart_item_key ) {
    // Modify price
    return $price;
}
```

## Best Practices

1. **Use WooCommerce Functions**: Use `wc_get_product()`, `wc_get_order()` instead of direct queries
2. **HPOS Compatibility**: Use order methods instead of post meta for orders
3. **Template Overrides**: Copy templates to theme's `woocommerce/` folder
4. **Use Hooks**: Prefer hooks over template overrides when possible
5. **Check WC Active**: `if ( class_exists( 'WooCommerce' ) )`
6. **Follow WC Standards**: Use WC form field functions, sanitization
7. **Test Edge Cases**: Variable products, virtual/downloadable, subscriptions
8. **Performance**: Cache expensive queries, use transients
