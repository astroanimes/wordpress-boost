# WordPress Development Guidelines

## Code Standards

Follow WordPress PHP Coding Standards:
- Use tabs for indentation (not spaces)
- Use lowercase letters in variable, action, filter, and function names
- Separate words with underscores
- Use Yoda conditions: `if ( true === $variable )`
- Always use braces for control structures
- Use meaningful names that describe purpose

## Security Best Practices

### Data Validation & Sanitization
- Always sanitize input: `sanitize_text_field()`, `sanitize_email()`, `sanitize_url()`
- Always escape output: `esc_html()`, `esc_attr()`, `esc_url()`, `wp_kses()`
- Use nonces for form verification: `wp_nonce_field()`, `wp_verify_nonce()`
- Check user capabilities: `current_user_can()`

### Database Security
- Use `$wpdb->prepare()` for all database queries with variables
- Never concatenate user input directly into SQL queries
- Use WordPress functions over direct SQL when possible

## Hooks Best Practices

### Actions
```php
// Register at the right priority
add_action( 'init', 'my_register_post_type', 10 );
add_action( 'wp_enqueue_scripts', 'my_enqueue_scripts', 20 );
```

### Filters
```php
// Always return the filtered value
add_filter( 'the_content', function( $content ) {
    // Modify $content
    return $content;
} );
```

### When to Use Each
- **Actions**: When you want to DO something (send email, save data, add output)
- **Filters**: When you want to MODIFY something (change text, alter data)

## Post Types & Taxonomies

### Registration
- Register on `init` hook
- Use descriptive labels
- Consider REST API support (`show_in_rest`)
- Set appropriate capabilities

### Queries
- Use `WP_Query` for complex queries
- Use `get_posts()` for simple retrieval
- Use `pre_get_posts` to modify the main query
- Always reset post data after custom loops: `wp_reset_postdata()`

## REST API

### Custom Endpoints
```php
add_action( 'rest_api_init', function() {
    register_rest_route( 'myplugin/v1', '/items', array(
        'methods'             => 'GET',
        'callback'            => 'my_get_items',
        'permission_callback' => function() {
            return current_user_can( 'read' );
        },
    ) );
} );
```

### Always include:
- Permission callbacks
- Schema definitions
- Proper sanitization

## Performance

### Database
- Use transients for expensive queries
- Index custom tables appropriately
- Avoid queries in loops

### Assets
- Enqueue scripts in footer when possible
- Use wp_enqueue_script/style, not direct output
- Minify and combine assets in production

### Caching
- Use Object Cache when available
- Use transients for API responses
- Clear caches on relevant actions

## Plugin Development

### File Organization
```
my-plugin/
├── my-plugin.php          # Main plugin file
├── includes/
│   ├── class-*.php        # Classes
│   └── functions-*.php    # Function files
├── admin/                 # Admin-specific code
├── public/                # Frontend code
├── assets/
│   ├── css/
│   └── js/
└── templates/             # Template files
```

### Plugin Header
```php
/**
 * Plugin Name: My Plugin
 * Plugin URI:  https://example.com
 * Description: What the plugin does
 * Version:     1.0.0
 * Author:      Your Name
 * Author URI:  https://example.com
 * License:     GPL v2 or later
 * Text Domain: my-plugin
 */
```

## Theme Development

### Template Hierarchy
Understand the template hierarchy for proper theme development:
- `single-{post-type}.php` → `single.php` → `singular.php` → `index.php`
- `archive-{post-type}.php` → `archive.php` → `index.php`
- `page-{slug}.php` → `page-{id}.php` → `page.php` → `singular.php`

### Child Themes
- Always use child themes for customization
- Use `get_template_directory()` for parent theme files
- Use `get_stylesheet_directory()` for child theme files

## Common Patterns

### Singleton Pattern
```php
class My_Class {
    private static $instance = null;

    public static function get_instance() {
        if ( null === self::$instance ) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        // Initialize
    }
}
```

### AJAX Handler
```php
// Register
add_action( 'wp_ajax_my_action', 'my_ajax_handler' );
add_action( 'wp_ajax_nopriv_my_action', 'my_ajax_handler' );

function my_ajax_handler() {
    check_ajax_referer( 'my_nonce', 'nonce' );

    // Process request

    wp_send_json_success( $data );
}
```

## Debugging

### Enable Debug Mode
```php
define( 'WP_DEBUG', true );
define( 'WP_DEBUG_LOG', true );
define( 'WP_DEBUG_DISPLAY', false );
define( 'SCRIPT_DEBUG', true );
```

### Debug Functions
- `error_log()` - Write to debug.log
- `var_dump()` / `print_r()` - Inspect variables
- `wp_debug_backtrace_summary()` - Get backtrace
