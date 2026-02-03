# WooCommerce Development Guidelines

## Product Management

### Creating Products Programmatically
```php
$product = new WC_Product_Simple();
$product->set_name( 'Product Name' );
$product->set_regular_price( '19.99' );
$product->set_description( 'Description' );
$product->set_short_description( 'Short description' );
$product->set_status( 'publish' );
$product->save();
```

### Product Types
- **Simple**: Single product without variations
- **Variable**: Product with variations (size, color, etc.)
- **Grouped**: Collection of related products
- **External/Affiliate**: Products sold elsewhere

### Variable Products
```php
$product = new WC_Product_Variable();
$product->set_name( 'Variable Product' );
$product->save();

// Create attributes
$attribute = new WC_Product_Attribute();
$attribute->set_name( 'Size' );
$attribute->set_options( array( 'Small', 'Medium', 'Large' ) );
$attribute->set_variation( true );
$product->set_attributes( array( $attribute ) );
$product->save();

// Create variation
$variation = new WC_Product_Variation();
$variation->set_parent_id( $product->get_id() );
$variation->set_attributes( array( 'size' => 'Small' ) );
$variation->set_regular_price( '19.99' );
$variation->save();
```

## Cart & Checkout

### Modifying Cart
```php
// Add item to cart
WC()->cart->add_to_cart( $product_id, $quantity );

// Get cart contents
$cart_items = WC()->cart->get_cart();

// Get cart total
$total = WC()->cart->get_total();
```

### Checkout Fields
```php
// Add custom field
add_filter( 'woocommerce_checkout_fields', function( $fields ) {
    $fields['billing']['billing_custom'] = array(
        'label'       => 'Custom Field',
        'required'    => true,
        'type'        => 'text',
        'priority'    => 25,
    );
    return $fields;
} );

// Save custom field
add_action( 'woocommerce_checkout_update_order_meta', function( $order_id ) {
    if ( ! empty( $_POST['billing_custom'] ) ) {
        update_post_meta( $order_id, '_billing_custom', sanitize_text_field( $_POST['billing_custom'] ) );
    }
} );
```

## Orders

### Creating Orders
```php
$order = wc_create_order();
$order->add_product( wc_get_product( $product_id ), $quantity );
$order->set_address( $address, 'billing' );
$order->set_address( $address, 'shipping' );
$order->calculate_totals();
$order->update_status( 'processing' );
```

### Order Statuses
- `pending` - Awaiting payment
- `processing` - Payment received, awaiting fulfillment
- `on-hold` - Awaiting payment confirmation
- `completed` - Order fulfilled
- `cancelled` - Cancelled by admin or customer
- `refunded` - Order refunded
- `failed` - Payment failed

### Custom Order Status
```php
add_action( 'init', function() {
    register_post_status( 'wc-custom-status', array(
        'label'                     => 'Custom Status',
        'public'                    => true,
        'show_in_admin_status_list' => true,
        'show_in_admin_all_list'    => true,
        'exclude_from_search'       => false,
        'label_count'               => _n_noop( 'Custom <span class="count">(%s)</span>', 'Custom <span class="count">(%s)</span>' ),
    ) );
} );

add_filter( 'wc_order_statuses', function( $statuses ) {
    $statuses['wc-custom-status'] = 'Custom Status';
    return $statuses;
} );
```

## Important Hooks

### Actions
```php
// Before/after cart
do_action( 'woocommerce_before_cart' );
do_action( 'woocommerce_after_cart' );

// Checkout process
do_action( 'woocommerce_checkout_process' );
do_action( 'woocommerce_checkout_order_processed', $order_id, $posted_data, $order );

// Order status changes
do_action( 'woocommerce_order_status_changed', $order_id, $old_status, $new_status, $order );
do_action( 'woocommerce_order_status_completed', $order_id );

// Product page
do_action( 'woocommerce_before_single_product' );
do_action( 'woocommerce_single_product_summary' );
```

### Filters
```php
// Modify price display
add_filter( 'woocommerce_get_price_html', function( $price, $product ) {
    return $price;
}, 10, 2 );

// Modify cart item data
add_filter( 'woocommerce_add_cart_item_data', function( $cart_item_data, $product_id ) {
    return $cart_item_data;
}, 10, 2 );

// Modify checkout fields
add_filter( 'woocommerce_checkout_fields', function( $fields ) {
    return $fields;
} );
```

## Payment Gateways

### Custom Gateway
```php
class My_Payment_Gateway extends WC_Payment_Gateway {
    public function __construct() {
        $this->id                 = 'my_gateway';
        $this->method_title       = 'My Gateway';
        $this->method_description = 'Description';
        $this->has_fields         = true;
        $this->supports           = array( 'products' );

        $this->init_form_fields();
        $this->init_settings();

        add_action( 'woocommerce_update_options_payment_gateways_' . $this->id, array( $this, 'process_admin_options' ) );
    }

    public function process_payment( $order_id ) {
        $order = wc_get_order( $order_id );

        // Process payment logic

        return array(
            'result'   => 'success',
            'redirect' => $this->get_return_url( $order ),
        );
    }
}

add_filter( 'woocommerce_payment_gateways', function( $gateways ) {
    $gateways[] = 'My_Payment_Gateway';
    return $gateways;
} );
```

## Email Customization

### Custom Email
```php
class My_Custom_Email extends WC_Email {
    public function __construct() {
        $this->id             = 'my_custom_email';
        $this->title          = 'My Custom Email';
        $this->description    = 'Description';
        $this->template_html  = 'emails/my-custom-email.php';
        $this->template_plain = 'emails/plain/my-custom-email.php';
        $this->placeholders   = array(
            '{order_date}' => '',
        );

        parent::__construct();
    }

    public function trigger( $order_id ) {
        $this->setup_locale();

        if ( $order_id ) {
            $this->object = wc_get_order( $order_id );
            $this->recipient = $this->object->get_billing_email();
            $this->placeholders['{order_date}'] = wc_format_datetime( $this->object->get_date_created() );
        }

        if ( $this->is_enabled() && $this->get_recipient() ) {
            $this->send( $this->get_recipient(), $this->get_subject(), $this->get_content(), $this->get_headers(), $this->get_attachments() );
        }

        $this->restore_locale();
    }
}
```

## REST API

### Custom Endpoints
```php
add_action( 'rest_api_init', function() {
    register_rest_route( 'my-store/v1', '/custom', array(
        'methods'             => 'GET',
        'callback'            => 'my_custom_endpoint',
        'permission_callback' => function() {
            return current_user_can( 'manage_woocommerce' );
        },
    ) );
} );
```

### Using WC REST API
The WooCommerce REST API provides endpoints under `/wp-json/wc/v3/`:
- `/products` - Product management
- `/orders` - Order management
- `/customers` - Customer management
- `/coupons` - Coupon management

## HPOS (High-Performance Order Storage)

WooCommerce is migrating to HPOS. Write compatible code:

```php
// Check if HPOS is enabled
if ( class_exists( '\Automattic\WooCommerce\Utilities\OrderUtil' ) ) {
    $hpos_enabled = \Automattic\WooCommerce\Utilities\OrderUtil::custom_orders_table_usage_is_enabled();
}

// Use CRUD methods instead of direct meta access
$order->get_meta( '_custom_key' );    // Instead of get_post_meta()
$order->update_meta_data( '_custom_key', 'value' );
$order->save();
```

## Best Practices

1. **Use CRUD methods** - Don't access post meta directly for orders/products
2. **Check for WooCommerce** - `class_exists( 'WooCommerce' )`
3. **Use WooCommerce hooks** - Not generic WordPress hooks for WC functionality
4. **Follow WC coding standards** - Similar to WordPress standards
5. **Test with HPOS** - Ensure compatibility with new order storage
6. **Use template overrides** - Copy to theme's `woocommerce/` folder
