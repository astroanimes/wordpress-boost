# WordPress Plugin Development Guidelines

## Plugin Structure

```
my-plugin/
├── my-plugin.php              # Main plugin file
├── uninstall.php              # Cleanup on uninstall
├── readme.txt                 # WordPress.org readme
├── includes/
│   ├── class-my-plugin.php    # Main plugin class
│   ├── class-loader.php       # Hook loader
│   ├── class-activator.php    # Activation logic
│   ├── class-deactivator.php  # Deactivation logic
│   └── functions.php          # Helper functions
├── admin/
│   ├── class-admin.php        # Admin functionality
│   ├── partials/              # Admin templates
│   ├── css/
│   └── js/
├── public/
│   ├── class-public.php       # Public functionality
│   ├── partials/              # Public templates
│   ├── css/
│   └── js/
├── languages/                 # Translation files
├── templates/                 # Template overrides
└── vendor/                    # Dependencies (if any)
```

## Plugin Header

```php
<?php
/**
 * Plugin Name:       My Plugin
 * Plugin URI:        https://example.com/my-plugin
 * Description:       A description of what this plugin does.
 * Version:           1.0.0
 * Requires at least: 6.0
 * Requires PHP:      7.4
 * Author:            Your Name
 * Author URI:        https://example.com
 * License:           GPL v2 or later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       my-plugin
 * Domain Path:       /languages
 * Network:           false
 */

// Prevent direct access
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}
```

## Plugin Constants

```php
// Plugin version
define( 'MY_PLUGIN_VERSION', '1.0.0' );

// Plugin paths
define( 'MY_PLUGIN_FILE', __FILE__ );
define( 'MY_PLUGIN_PATH', plugin_dir_path( __FILE__ ) );
define( 'MY_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'MY_PLUGIN_BASENAME', plugin_basename( __FILE__ ) );
```

## Main Plugin Class (OOP Approach)

```php
<?php
namespace MyPlugin;

class Plugin {
    /**
     * Plugin instance
     */
    private static $instance = null;

    /**
     * Plugin version
     */
    const VERSION = '1.0.0';

    /**
     * Get plugin instance
     */
    public static function get_instance() {
        if ( null === self::$instance ) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Constructor
     */
    private function __construct() {
        $this->define_constants();
        $this->load_dependencies();
        $this->set_locale();
        $this->define_admin_hooks();
        $this->define_public_hooks();
    }

    /**
     * Define plugin constants
     */
    private function define_constants() {
        define( 'MY_PLUGIN_VERSION', self::VERSION );
        define( 'MY_PLUGIN_PATH', plugin_dir_path( dirname( __FILE__ ) ) );
        define( 'MY_PLUGIN_URL', plugin_dir_url( dirname( __FILE__ ) ) );
    }

    /**
     * Load dependencies
     */
    private function load_dependencies() {
        require_once MY_PLUGIN_PATH . 'includes/class-loader.php';
        require_once MY_PLUGIN_PATH . 'includes/class-i18n.php';
        require_once MY_PLUGIN_PATH . 'admin/class-admin.php';
        require_once MY_PLUGIN_PATH . 'public/class-public.php';
    }

    /**
     * Set plugin text domain
     */
    private function set_locale() {
        add_action( 'plugins_loaded', function() {
            load_plugin_textdomain(
                'my-plugin',
                false,
                dirname( MY_PLUGIN_BASENAME ) . '/languages/'
            );
        } );
    }

    /**
     * Define admin hooks
     */
    private function define_admin_hooks() {
        $admin = new Admin();
        add_action( 'admin_menu', array( $admin, 'add_menu_pages' ) );
        add_action( 'admin_enqueue_scripts', array( $admin, 'enqueue_scripts' ) );
    }

    /**
     * Define public hooks
     */
    private function define_public_hooks() {
        $public = new Frontend();
        add_action( 'wp_enqueue_scripts', array( $public, 'enqueue_scripts' ) );
    }
}

// Initialize plugin
add_action( 'plugins_loaded', array( 'MyPlugin\Plugin', 'get_instance' ) );
```

## Activation & Deactivation

```php
// Activation hook
register_activation_hook( __FILE__, 'my_plugin_activate' );

function my_plugin_activate() {
    // Check WordPress version
    if ( version_compare( get_bloginfo( 'version' ), '6.0', '<' ) ) {
        deactivate_plugins( plugin_basename( __FILE__ ) );
        wp_die( 'This plugin requires WordPress 6.0 or higher.' );
    }

    // Check PHP version
    if ( version_compare( PHP_VERSION, '7.4', '<' ) ) {
        deactivate_plugins( plugin_basename( __FILE__ ) );
        wp_die( 'This plugin requires PHP 7.4 or higher.' );
    }

    // Create database tables
    my_plugin_create_tables();

    // Add default options
    add_option( 'my_plugin_version', MY_PLUGIN_VERSION );
    add_option( 'my_plugin_settings', array(
        'option1' => 'default',
        'option2' => true,
    ) );

    // Set activation flag
    set_transient( 'my_plugin_activated', true, 30 );

    // Flush rewrite rules
    flush_rewrite_rules();
}

// Deactivation hook
register_deactivation_hook( __FILE__, 'my_plugin_deactivate' );

function my_plugin_deactivate() {
    // Clear scheduled events
    wp_clear_scheduled_hook( 'my_plugin_cron_event' );

    // Flush rewrite rules
    flush_rewrite_rules();
}
```

## Uninstall (uninstall.php)

```php
<?php
// If uninstall not called from WordPress, exit
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
    exit;
}

// Delete options
delete_option( 'my_plugin_version' );
delete_option( 'my_plugin_settings' );

// Delete transients
delete_transient( 'my_plugin_cache' );

// Delete user meta (for all users)
delete_metadata( 'user', 0, 'my_plugin_user_meta', '', true );

// Drop custom tables
global $wpdb;
$wpdb->query( "DROP TABLE IF EXISTS {$wpdb->prefix}my_plugin_table" );

// Clear any cached data
wp_cache_flush();
```

## Admin Pages

```php
class Admin {
    /**
     * Add menu pages
     */
    public function add_menu_pages() {
        // Main menu
        add_menu_page(
            __( 'My Plugin', 'my-plugin' ),     // Page title
            __( 'My Plugin', 'my-plugin' ),     // Menu title
            'manage_options',                    // Capability
            'my-plugin',                         // Menu slug
            array( $this, 'render_main_page' ), // Callback
            'dashicons-admin-generic',           // Icon
            30                                   // Position
        );

        // Submenu
        add_submenu_page(
            'my-plugin',                              // Parent slug
            __( 'Settings', 'my-plugin' ),           // Page title
            __( 'Settings', 'my-plugin' ),           // Menu title
            'manage_options',                         // Capability
            'my-plugin-settings',                     // Menu slug
            array( $this, 'render_settings_page' )   // Callback
        );
    }

    /**
     * Render main page
     */
    public function render_main_page() {
        if ( ! current_user_can( 'manage_options' ) ) {
            return;
        }
        include MY_PLUGIN_PATH . 'admin/partials/main-page.php';
    }

    /**
     * Render settings page
     */
    public function render_settings_page() {
        if ( ! current_user_can( 'manage_options' ) ) {
            return;
        }
        include MY_PLUGIN_PATH . 'admin/partials/settings-page.php';
    }
}
```

## Settings API

```php
class Settings {
    /**
     * Register settings
     */
    public function register_settings() {
        register_setting(
            'my_plugin_settings_group',      // Option group
            'my_plugin_settings',            // Option name
            array( $this, 'sanitize_settings' )
        );

        // Add section
        add_settings_section(
            'my_plugin_general_section',
            __( 'General Settings', 'my-plugin' ),
            array( $this, 'render_section_description' ),
            'my-plugin-settings'
        );

        // Add field
        add_settings_field(
            'my_plugin_field_1',
            __( 'Field Label', 'my-plugin' ),
            array( $this, 'render_field_1' ),
            'my-plugin-settings',
            'my_plugin_general_section'
        );
    }

    /**
     * Sanitize settings
     */
    public function sanitize_settings( $input ) {
        $sanitized = array();

        if ( isset( $input['field_1'] ) ) {
            $sanitized['field_1'] = sanitize_text_field( $input['field_1'] );
        }

        if ( isset( $input['field_2'] ) ) {
            $sanitized['field_2'] = (bool) $input['field_2'];
        }

        return $sanitized;
    }

    /**
     * Render field
     */
    public function render_field_1() {
        $options = get_option( 'my_plugin_settings' );
        $value = isset( $options['field_1'] ) ? $options['field_1'] : '';
        ?>
        <input type="text"
               name="my_plugin_settings[field_1]"
               value="<?php echo esc_attr( $value ); ?>"
               class="regular-text">
        <?php
    }
}
```

## Custom Post Types

```php
function my_plugin_register_post_types() {
    register_post_type( 'my_cpt', array(
        'labels' => array(
            'name'               => __( 'Items', 'my-plugin' ),
            'singular_name'      => __( 'Item', 'my-plugin' ),
            'add_new'            => __( 'Add New', 'my-plugin' ),
            'add_new_item'       => __( 'Add New Item', 'my-plugin' ),
            'edit_item'          => __( 'Edit Item', 'my-plugin' ),
            'new_item'           => __( 'New Item', 'my-plugin' ),
            'view_item'          => __( 'View Item', 'my-plugin' ),
            'search_items'       => __( 'Search Items', 'my-plugin' ),
            'not_found'          => __( 'No items found', 'my-plugin' ),
            'not_found_in_trash' => __( 'No items found in trash', 'my-plugin' ),
        ),
        'public'             => true,
        'publicly_queryable' => true,
        'show_ui'            => true,
        'show_in_menu'       => true,
        'show_in_rest'       => true, // Enable Gutenberg
        'query_var'          => true,
        'rewrite'            => array( 'slug' => 'items' ),
        'capability_type'    => 'post',
        'has_archive'        => true,
        'hierarchical'       => false,
        'menu_position'      => 20,
        'menu_icon'          => 'dashicons-portfolio',
        'supports'           => array( 'title', 'editor', 'thumbnail', 'excerpt' ),
    ) );
}
add_action( 'init', 'my_plugin_register_post_types' );
```

## AJAX Handling

```php
// Register AJAX handlers
add_action( 'wp_ajax_my_plugin_action', 'my_plugin_ajax_handler' );
add_action( 'wp_ajax_nopriv_my_plugin_action', 'my_plugin_ajax_handler' ); // For non-logged users

function my_plugin_ajax_handler() {
    // Verify nonce
    if ( ! check_ajax_referer( 'my_plugin_nonce', 'nonce', false ) ) {
        wp_send_json_error( array( 'message' => 'Invalid nonce' ), 403 );
    }

    // Check capabilities if needed
    if ( ! current_user_can( 'edit_posts' ) ) {
        wp_send_json_error( array( 'message' => 'Unauthorized' ), 403 );
    }

    // Get and sanitize data
    $data = isset( $_POST['data'] ) ? sanitize_text_field( $_POST['data'] ) : '';

    // Process request
    $result = process_data( $data );

    if ( is_wp_error( $result ) ) {
        wp_send_json_error( array(
            'message' => $result->get_error_message(),
        ), 400 );
    }

    wp_send_json_success( array(
        'message' => 'Success',
        'data'    => $result,
    ) );
}

// Enqueue script with localized data
function my_plugin_enqueue_scripts() {
    wp_enqueue_script( 'my-plugin-script', MY_PLUGIN_URL . 'public/js/script.js', array( 'jquery' ), MY_PLUGIN_VERSION, true );

    wp_localize_script( 'my-plugin-script', 'myPluginAjax', array(
        'ajaxUrl' => admin_url( 'admin-ajax.php' ),
        'nonce'   => wp_create_nonce( 'my_plugin_nonce' ),
    ) );
}
```

## Shortcodes

```php
function my_plugin_shortcode( $atts, $content = null ) {
    // Parse attributes
    $atts = shortcode_atts( array(
        'id'    => 0,
        'class' => '',
        'limit' => 10,
    ), $atts, 'my_shortcode' );

    // Sanitize
    $id = absint( $atts['id'] );
    $class = sanitize_html_class( $atts['class'] );
    $limit = absint( $atts['limit'] );

    // Build output
    ob_start();
    include MY_PLUGIN_PATH . 'public/partials/shortcode-template.php';
    return ob_get_clean();
}
add_shortcode( 'my_shortcode', 'my_plugin_shortcode' );

// Usage: [my_shortcode id="123" class="custom" limit="5"]Content here[/my_shortcode]
```

## Cron Jobs

```php
// Schedule event on activation
function my_plugin_schedule_cron() {
    if ( ! wp_next_scheduled( 'my_plugin_daily_event' ) ) {
        wp_schedule_event( time(), 'daily', 'my_plugin_daily_event' );
    }
}
register_activation_hook( __FILE__, 'my_plugin_schedule_cron' );

// Handle event
add_action( 'my_plugin_daily_event', 'my_plugin_daily_task' );

function my_plugin_daily_task() {
    // Perform scheduled task
}

// Custom schedule
add_filter( 'cron_schedules', 'my_plugin_add_cron_interval' );

function my_plugin_add_cron_interval( $schedules ) {
    $schedules['five_minutes'] = array(
        'interval' => 300,
        'display'  => __( 'Every Five Minutes', 'my-plugin' ),
    );
    return $schedules;
}
```

## Database Tables

```php
function my_plugin_create_tables() {
    global $wpdb;

    $table_name = $wpdb->prefix . 'my_plugin_table';
    $charset_collate = $wpdb->get_charset_collate();

    $sql = "CREATE TABLE $table_name (
        id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
        user_id bigint(20) unsigned NOT NULL,
        title varchar(255) NOT NULL,
        content longtext NOT NULL,
        status varchar(20) NOT NULL DEFAULT 'active',
        created_at datetime DEFAULT CURRENT_TIMESTAMP,
        updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY (id),
        KEY user_id (user_id),
        KEY status (status)
    ) $charset_collate;";

    require_once ABSPATH . 'wp-admin/includes/upgrade.php';
    dbDelta( $sql );

    // Store DB version
    add_option( 'my_plugin_db_version', '1.0.0' );
}
```

## Best Practices

1. **Prefix Everything**: Functions, classes, hooks, options, database tables
2. **Use Nonces**: For all form submissions and AJAX requests
3. **Check Capabilities**: Before performing privileged actions
4. **Escape Output**: Use appropriate escaping functions
5. **Sanitize Input**: Validate and sanitize all user input
6. **Internationalize**: Use translation functions for all strings
7. **Use Hooks**: Make your plugin extensible
8. **Document Code**: Add PHPDoc blocks
9. **Follow Standards**: WordPress PHP Coding Standards
10. **Test Thoroughly**: Unit tests, integration tests
