# Skill: WordPress Plugin Development

## Description
Expert guidance for creating WordPress plugins with proper architecture, security, and WordPress coding standards.

## When to Use
- User asks to create a new plugin
- User needs to add custom functionality
- User wants to create admin pages
- User needs to integrate with WordPress hooks
- User wants to add custom post types or taxonomies
- User needs to create shortcodes or widgets

## WordPress Boost Tools to Use
```
- list_plugins: See installed plugins
- list_hooks: Find relevant hooks
- list_post_types: Check existing post types
- list_taxonomies: Check existing taxonomies
- wp_shell: Test plugin code
- database_schema: Check database tables
```

## Key Concepts

### Plugin Structure
```
my-plugin/
├── my-plugin.php       # Main file with header
├── uninstall.php       # Cleanup on uninstall
├── includes/           # PHP classes
│   ├── class-plugin.php
│   └── class-admin.php
├── admin/              # Admin assets
│   ├── css/
│   └── js/
├── public/             # Frontend assets
│   ├── css/
│   └── js/
└── languages/          # Translations
```

### Required Plugin Header
```php
<?php
/**
 * Plugin Name: My Plugin
 * Plugin URI: https://example.com
 * Description: What the plugin does
 * Version: 1.0.0
 * Requires at least: 6.0
 * Requires PHP: 7.4
 * Author: Your Name
 * License: GPL v2 or later
 * Text Domain: my-plugin
 */

if ( ! defined( 'ABSPATH' ) ) exit;
```

### Plugin Constants
```php
define( 'MY_PLUGIN_VERSION', '1.0.0' );
define( 'MY_PLUGIN_PATH', plugin_dir_path( __FILE__ ) );
define( 'MY_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
```

## Common Tasks

### 1. Register Custom Post Type
```php
add_action( 'init', function() {
    register_post_type( 'book', array(
        'labels' => array(
            'name' => 'Books',
            'singular_name' => 'Book',
        ),
        'public' => true,
        'show_in_rest' => true,
        'supports' => array( 'title', 'editor', 'thumbnail' ),
        'menu_icon' => 'dashicons-book',
        'has_archive' => true,
    ) );
});
```

### 2. Add Admin Menu Page
```php
add_action( 'admin_menu', function() {
    add_menu_page(
        'My Plugin Settings',
        'My Plugin',
        'manage_options',
        'my-plugin',
        'my_plugin_settings_page',
        'dashicons-admin-generic',
        30
    );
});

function my_plugin_settings_page() {
    if ( ! current_user_can( 'manage_options' ) ) return;
    ?>
    <div class="wrap">
        <h1><?php echo esc_html( get_admin_page_title() ); ?></h1>
        <form method="post" action="options.php">
            <?php
            settings_fields( 'my_plugin_options' );
            do_settings_sections( 'my-plugin' );
            submit_button();
            ?>
        </form>
    </div>
    <?php
}
```

### 3. Register Settings
```php
add_action( 'admin_init', function() {
    register_setting( 'my_plugin_options', 'my_plugin_setting', array(
        'sanitize_callback' => 'sanitize_text_field',
    ) );

    add_settings_section( 'general', 'General Settings', null, 'my-plugin' );

    add_settings_field( 'api_key', 'API Key', function() {
        $value = get_option( 'my_plugin_setting' );
        echo '<input type="text" name="my_plugin_setting" value="' . esc_attr( $value ) . '" class="regular-text">';
    }, 'my-plugin', 'general' );
});
```

### 4. Create Shortcode
```php
add_shortcode( 'my_shortcode', function( $atts ) {
    $atts = shortcode_atts( array(
        'id' => 0,
        'class' => '',
    ), $atts );

    ob_start();
    // Output content
    return ob_get_clean();
});
```

### 5. Add AJAX Handler
```php
// Register
add_action( 'wp_ajax_my_action', 'my_ajax_handler' );
add_action( 'wp_ajax_nopriv_my_action', 'my_ajax_handler' );

function my_ajax_handler() {
    check_ajax_referer( 'my_nonce', 'nonce' );

    $data = sanitize_text_field( $_POST['data'] );
    // Process...

    wp_send_json_success( array( 'result' => $data ) );
}

// Enqueue with nonce
wp_localize_script( 'my-script', 'myPlugin', array(
    'ajaxUrl' => admin_url( 'admin-ajax.php' ),
    'nonce' => wp_create_nonce( 'my_nonce' ),
));
```

### 6. Activation/Deactivation
```php
register_activation_hook( __FILE__, function() {
    // Create tables, set options
    add_option( 'my_plugin_version', '1.0.0' );
    flush_rewrite_rules();
});

register_deactivation_hook( __FILE__, function() {
    // Cleanup temporary data
    flush_rewrite_rules();
});
```

### 7. Uninstall (uninstall.php)
```php
<?php
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) exit;

delete_option( 'my_plugin_setting' );
// Delete custom tables if any
```

## Security Checklist
- [ ] Use nonces for forms
- [ ] Check capabilities (current_user_can)
- [ ] Sanitize all input
- [ ] Escape all output
- [ ] Use $wpdb->prepare() for queries
- [ ] Validate data types
- [ ] Prefix all functions/classes/hooks

## Best Practices
- [ ] Use OOP structure for complex plugins
- [ ] Follow WordPress Coding Standards
- [ ] Make plugin translatable
- [ ] Check plugin/theme conflicts
- [ ] Provide uninstall cleanup
- [ ] Use hooks for extensibility
