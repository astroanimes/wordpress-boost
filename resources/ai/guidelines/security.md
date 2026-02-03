# WordPress Security Guidelines

## Input Handling

### Sanitization Functions
Always sanitize user input before using it. Choose the appropriate function based on expected data type:

| Function | Use Case |
|----------|----------|
| `sanitize_text_field()` | Single-line strings (removes tags, newlines) |
| `sanitize_textarea_field()` | Multi-line text (preserves newlines) |
| `sanitize_email()` | Email addresses |
| `sanitize_file_name()` | File names |
| `sanitize_html_class()` | HTML class names |
| `sanitize_key()` | Lowercase alphanumeric with dashes/underscores |
| `sanitize_mime_type()` | MIME types |
| `sanitize_option()` | Option values based on option name |
| `sanitize_sql_orderby()` | SQL ORDER BY clauses |
| `sanitize_title()` | Titles (removes special chars) |
| `sanitize_title_with_dashes()` | Slug-friendly strings |
| `sanitize_user()` | Usernames |
| `sanitize_url()` | URLs for database storage |
| `absint()` | Positive integers |
| `intval()` | Integers (including negative) |
| `floatval()` | Floating point numbers |
| `wp_kses()` | HTML with allowed tags |
| `wp_kses_post()` | HTML like post content |
| `wp_kses_data()` | HTML with basic formatting |

### Validation Patterns
```php
// Validate email
if ( ! is_email( $email ) ) {
    return new WP_Error( 'invalid_email', 'Invalid email address' );
}

// Validate URL
if ( ! wp_http_validate_url( $url ) ) {
    return new WP_Error( 'invalid_url', 'Invalid URL' );
}

// Validate integer
if ( ! is_numeric( $value ) || $value < 0 ) {
    return new WP_Error( 'invalid_number', 'Must be a positive number' );
}

// Validate against allowed values
$allowed = array( 'option1', 'option2', 'option3' );
if ( ! in_array( $value, $allowed, true ) ) {
    return new WP_Error( 'invalid_option', 'Invalid option selected' );
}
```

### When to Use Which Function
- **Text input fields**: `sanitize_text_field()`
- **Textarea**: `sanitize_textarea_field()`
- **Rich text/HTML editor**: `wp_kses_post()` or `wp_kses()` with custom allowed tags
- **URLs**: `esc_url_raw()` for database, `esc_url()` for output
- **Email**: `sanitize_email()` then validate with `is_email()`
- **Numbers**: `absint()` for positive integers, `intval()` for any integer
- **File names**: `sanitize_file_name()`
- **Slugs/keys**: `sanitize_title_with_dashes()` or `sanitize_key()`

---

## Output Escaping

### Escaping Functions
Always escape output as late as possible, right before display:

| Function | Use Case |
|----------|----------|
| `esc_html()` | Plain text in HTML context |
| `esc_attr()` | HTML attribute values |
| `esc_url()` | URLs in href, src, etc. |
| `esc_js()` | Inline JavaScript strings |
| `esc_textarea()` | Content in textarea elements |
| `wp_kses()` | Allow specific HTML tags |
| `wp_kses_post()` | Allow post-like HTML |

### Context-Specific Escaping

```php
// HTML content
<p><?php echo esc_html( $text ); ?></p>

// HTML attributes
<input type="text" value="<?php echo esc_attr( $value ); ?>">

// URLs
<a href="<?php echo esc_url( $url ); ?>">Link</a>
<img src="<?php echo esc_url( $image_url ); ?>">

// JavaScript in HTML
<script>var name = '<?php echo esc_js( $name ); ?>';</script>

// JSON data in HTML attribute
<div data-config="<?php echo esc_attr( wp_json_encode( $config ) ); ?>">

// CSS values (no dedicated function - validate instead)
<div style="width: <?php echo absint( $width ); ?>px;">

// Textarea content
<textarea><?php echo esc_textarea( $content ); ?></textarea>

// Allow specific HTML
<?php echo wp_kses( $html, array(
    'a'      => array( 'href' => array(), 'title' => array() ),
    'strong' => array(),
    'em'     => array(),
) ); ?>
```

### Late Escaping Principle
Escape at the point of output, not at the point of storage:

```php
// BAD: Escaping before storage
update_post_meta( $post_id, 'my_field', esc_html( $value ) );
echo get_post_meta( $post_id, 'my_field', true ); // Double-escaped!

// GOOD: Escape at output
update_post_meta( $post_id, 'my_field', sanitize_text_field( $value ) );
echo esc_html( get_post_meta( $post_id, 'my_field', true ) );
```

### Translation with Escaping
```php
// Escape + translate
echo esc_html__( 'Text', 'text-domain' );
echo esc_attr__( 'Text', 'text-domain' );
esc_html_e( 'Text', 'text-domain' );
esc_attr_e( 'Text', 'text-domain' );

// With placeholders
printf( esc_html__( 'Hello, %s!', 'text-domain' ), esc_html( $name ) );
```

---

## Authentication & Authorization

### Nonce Usage Patterns

#### Forms
```php
// In form
<form method="post">
    <?php wp_nonce_field( 'my_action_name', 'my_nonce_name' ); ?>
    <!-- form fields -->
</form>

// Verification
if ( ! isset( $_POST['my_nonce_name'] ) ||
     ! wp_verify_nonce( $_POST['my_nonce_name'], 'my_action_name' ) ) {
    wp_die( 'Security check failed' );
}
```

#### AJAX
```php
// Enqueue script with nonce
wp_localize_script( 'my-script', 'myAjax', array(
    'ajax_url' => admin_url( 'admin-ajax.php' ),
    'nonce'    => wp_create_nonce( 'my_ajax_action' ),
) );

// JavaScript
jQuery.post( myAjax.ajax_url, {
    action: 'my_action',
    nonce: myAjax.nonce,
    // ... other data
} );

// Handler
add_action( 'wp_ajax_my_action', 'handle_my_action' );
function handle_my_action() {
    check_ajax_referer( 'my_ajax_action', 'nonce' );
    // Process request
    wp_send_json_success( $data );
}
```

#### REST API
```php
// In JavaScript
fetch( '/wp-json/my-plugin/v1/endpoint', {
    method: 'POST',
    headers: {
        'Content-Type': 'application/json',
        'X-WP-Nonce': wpApiSettings.nonce,
    },
    body: JSON.stringify( data ),
} );

// In REST route registration
register_rest_route( 'my-plugin/v1', '/endpoint', array(
    'methods'             => 'POST',
    'callback'            => 'my_endpoint_handler',
    'permission_callback' => function() {
        return current_user_can( 'edit_posts' );
    },
) );
```

### Capability Checks
```php
// Check capability
if ( ! current_user_can( 'manage_options' ) ) {
    wp_die( 'Unauthorized access' );
}

// Check capability for specific object
if ( ! current_user_can( 'edit_post', $post_id ) ) {
    wp_die( 'You cannot edit this post' );
}

// Common capabilities:
// - manage_options: Admin settings
// - edit_posts: Create/edit own posts
// - edit_others_posts: Edit any post
// - publish_posts: Publish posts
// - delete_posts: Delete own posts
// - upload_files: Upload media
// - edit_theme_options: Customize theme
// - activate_plugins: Manage plugins
```

### User Role Verification
```php
// Check user role
$user = wp_get_current_user();
if ( ! in_array( 'administrator', (array) $user->roles, true ) ) {
    wp_die( 'Admin access required' );
}

// Better: Check capability instead of role
if ( ! current_user_can( 'manage_options' ) ) {
    wp_die( 'Admin access required' );
}
```

### Login Security
```php
// Limit login attempts (use plugin or custom implementation)
// Rate limit authentication attempts
// Use strong password requirements
// Consider two-factor authentication

// Force SSL for login
define( 'FORCE_SSL_LOGIN', true );
define( 'FORCE_SSL_ADMIN', true );

// Change login URL (via plugin)
// Disable XML-RPC if not needed
add_filter( 'xmlrpc_enabled', '__return_false' );
```

---

## Database Security

### Prepared Statements
Always use `$wpdb->prepare()` when including variables in SQL:

```php
global $wpdb;

// SELECT with single variable
$result = $wpdb->get_var(
    $wpdb->prepare(
        "SELECT meta_value FROM {$wpdb->postmeta} WHERE post_id = %d AND meta_key = %s",
        $post_id,
        $meta_key
    )
);

// SELECT with multiple variables
$results = $wpdb->get_results(
    $wpdb->prepare(
        "SELECT * FROM {$wpdb->posts} WHERE post_type = %s AND post_status = %s LIMIT %d",
        $post_type,
        'publish',
        $limit
    )
);

// INSERT (prefer $wpdb->insert() but prepare() works too)
$wpdb->query(
    $wpdb->prepare(
        "INSERT INTO {$wpdb->prefix}custom_table (column1, column2) VALUES (%s, %d)",
        $string_value,
        $int_value
    )
);

// UPDATE
$wpdb->query(
    $wpdb->prepare(
        "UPDATE {$wpdb->prefix}custom_table SET column1 = %s WHERE id = %d",
        $new_value,
        $id
    )
);

// DELETE
$wpdb->query(
    $wpdb->prepare(
        "DELETE FROM {$wpdb->prefix}custom_table WHERE id = %d",
        $id
    )
);
```

### Table Prefix Usage
```php
// Always use $wpdb properties for core tables
$wpdb->posts       // wp_posts
$wpdb->postmeta    // wp_postmeta
$wpdb->users       // wp_users
$wpdb->usermeta    // wp_usermeta
$wpdb->options     // wp_options
$wpdb->terms       // wp_terms
$wpdb->term_taxonomy
$wpdb->term_relationships
$wpdb->comments
$wpdb->commentmeta

// Custom tables: use prefix property
$table_name = $wpdb->prefix . 'my_custom_table';
```

### Patterns to Avoid
```php
// NEVER do these:

// Direct variable interpolation
$wpdb->query( "SELECT * FROM {$wpdb->posts} WHERE ID = $id" ); // BAD!

// Concatenation without escaping
$wpdb->query( "SELECT * FROM {$wpdb->posts} WHERE post_title = '" . $title . "'" ); // BAD!

// User input in LIKE clause without prepare
$wpdb->query( "SELECT * FROM {$wpdb->posts} WHERE post_title LIKE '%{$search}%'" ); // BAD!

// CORRECT way for LIKE:
$wpdb->get_results(
    $wpdb->prepare(
        "SELECT * FROM {$wpdb->posts} WHERE post_title LIKE %s",
        '%' . $wpdb->esc_like( $search ) . '%'
    )
);
```

### Using Higher-Level APIs
When possible, use WordPress functions instead of direct queries:

```php
// Instead of SELECT queries:
$post = get_post( $id );
$meta = get_post_meta( $post_id, 'key', true );
$option = get_option( 'option_name' );
$user = get_user_by( 'email', $email );

// Instead of INSERT/UPDATE:
wp_insert_post( $args );
update_post_meta( $post_id, 'key', $value );
update_option( 'option_name', $value );
wp_insert_user( $userdata );
```

---

## File Security

### Upload Validation
```php
// Check file type
$allowed_types = array( 'image/jpeg', 'image/png', 'image/gif' );
$file_type = wp_check_filetype( $filename );

if ( ! in_array( $file_type['type'], $allowed_types, true ) ) {
    return new WP_Error( 'invalid_type', 'File type not allowed' );
}

// Validate uploaded file
$file = $_FILES['my_file'];
$upload = wp_handle_upload( $file, array(
    'test_form' => false,
    'mimes'     => array(
        'jpg|jpeg' => 'image/jpeg',
        'png'      => 'image/png',
        'gif'      => 'image/gif',
    ),
) );

if ( isset( $upload['error'] ) ) {
    return new WP_Error( 'upload_error', $upload['error'] );
}
```

### File Type Checking
```php
// Check MIME type properly
$finfo = finfo_open( FILEINFO_MIME_TYPE );
$mime_type = finfo_file( $finfo, $file_path );
finfo_close( $finfo );

// Don't trust file extension alone
$extension = pathinfo( $filename, PATHINFO_EXTENSION );
// Extension can be faked - always verify actual content type

// Use WordPress function
$check = wp_check_filetype_and_ext( $file_path, $filename );
if ( ! $check['type'] ) {
    // File type not allowed
}
```

### Path Traversal Prevention
```php
// Validate and sanitize file paths
$filename = sanitize_file_name( $user_input );

// Prevent directory traversal
if ( strpos( $filename, '..' ) !== false || strpos( $filename, '/' ) !== false ) {
    return new WP_Error( 'invalid_path', 'Invalid file path' );
}

// Use realpath() to resolve and validate
$base_path = wp_upload_dir()['basedir'];
$full_path = realpath( $base_path . '/' . $filename );

// Ensure path is within allowed directory
if ( strpos( $full_path, $base_path ) !== 0 ) {
    return new WP_Error( 'path_traversal', 'Access denied' );
}

// Use WordPress filesystem API
$wp_filesystem = WP_Filesystem();
$wp_filesystem->get_contents( $full_path );
```

### Filesystem Permissions
```php
// Create files with proper permissions
$file = fopen( $path, 'w' );
fwrite( $file, $content );
fclose( $file );
chmod( $path, 0644 ); // Files: owner read/write, others read

// Directories
mkdir( $path, 0755 ); // Directories: owner all, others read/execute

// Use WordPress filesystem API for better security
global $wp_filesystem;
WP_Filesystem();
$wp_filesystem->put_contents( $path, $content, FS_CHMOD_FILE );
```

---

## wp-config.php Security

### Security Keys and Salts
```php
// Generate at: https://api.wordpress.org/secret-key/1.1/salt/
define( 'AUTH_KEY',         'unique-phrase-here' );
define( 'SECURE_AUTH_KEY',  'unique-phrase-here' );
define( 'LOGGED_IN_KEY',    'unique-phrase-here' );
define( 'NONCE_KEY',        'unique-phrase-here' );
define( 'AUTH_SALT',        'unique-phrase-here' );
define( 'SECURE_AUTH_SALT', 'unique-phrase-here' );
define( 'LOGGED_IN_SALT',   'unique-phrase-here' );
define( 'NONCE_SALT',       'unique-phrase-here' );
```

### Debug Settings for Production
```php
// PRODUCTION settings
define( 'WP_DEBUG', false );
define( 'WP_DEBUG_LOG', false );
define( 'WP_DEBUG_DISPLAY', false );
define( 'SCRIPT_DEBUG', false );

// DEVELOPMENT settings (never use in production)
define( 'WP_DEBUG', true );
define( 'WP_DEBUG_LOG', true );   // Logs to wp-content/debug.log
define( 'WP_DEBUG_DISPLAY', false ); // Don't show errors publicly
define( 'SCRIPT_DEBUG', true );   // Use non-minified scripts
```

### File Editing Disable
```php
// Disable plugin/theme editor (security best practice)
define( 'DISALLOW_FILE_EDIT', true );

// Also disable plugin/theme installation
define( 'DISALLOW_FILE_MODS', true );
```

### Database Security Constants
```php
// Use custom table prefix
$table_prefix = 'wp_xyz123_'; // Not just 'wp_'

// Database charset
define( 'DB_CHARSET', 'utf8mb4' );
define( 'DB_COLLATE', 'utf8mb4_unicode_ci' );
```

### SSL Enforcement
```php
// Force SSL for admin
define( 'FORCE_SSL_ADMIN', true );

// For entire site (if SSL certificate installed)
if ( isset( $_SERVER['HTTP_X_FORWARDED_PROTO'] ) &&
     $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https' ) {
    $_SERVER['HTTPS'] = 'on';
}
```

### Additional Security Constants
```php
// Limit post revisions
define( 'WP_POST_REVISIONS', 5 );

// Set autosave interval (seconds)
define( 'AUTOSAVE_INTERVAL', 120 );

// Empty trash automatically (days)
define( 'EMPTY_TRASH_DAYS', 7 );

// Block external HTTP requests (optional, may break plugins)
define( 'WP_HTTP_BLOCK_EXTERNAL', true );
define( 'WP_ACCESSIBLE_HOSTS', 'api.wordpress.org,*.github.com' );
```

---

## REST API Security

### Permission Callbacks
```php
register_rest_route( 'my-plugin/v1', '/items', array(
    'methods'             => 'GET',
    'callback'            => 'get_items',
    'permission_callback' => function() {
        return current_user_can( 'read' );
    },
) );

register_rest_route( 'my-plugin/v1', '/items', array(
    'methods'             => 'POST',
    'callback'            => 'create_item',
    'permission_callback' => function() {
        return current_user_can( 'edit_posts' );
    },
    'args'                => array(
        'title' => array(
            'required'          => true,
            'sanitize_callback' => 'sanitize_text_field',
            'validate_callback' => function( $value ) {
                return ! empty( $value );
            },
        ),
    ),
) );

// Never return true blindly for permission_callback
// BAD:
'permission_callback' => '__return_true' // Only for truly public endpoints
```

### Nonce Verification in REST
```php
// Enqueue script with REST nonce
wp_localize_script( 'my-script', 'myApi', array(
    'root'  => esc_url_raw( rest_url() ),
    'nonce' => wp_create_nonce( 'wp_rest' ),
) );

// JavaScript fetch with nonce
fetch( myApi.root + 'my-plugin/v1/items', {
    headers: {
        'X-WP-Nonce': myApi.nonce,
    },
} );
```

### Authentication Methods
```php
// Cookie authentication (for logged-in users)
// Automatic with X-WP-Nonce header

// Application Passwords (WordPress 5.6+)
// User generates in profile, used as basic auth

// OAuth / JWT (via plugins)
// For third-party integrations
```

### Sanitizing REST Parameters
```php
register_rest_route( 'my-plugin/v1', '/items/(?P<id>\d+)', array(
    'methods'  => 'GET',
    'callback' => 'get_item',
    'args'     => array(
        'id' => array(
            'validate_callback' => function( $param ) {
                return is_numeric( $param );
            },
            'sanitize_callback' => 'absint',
        ),
    ),
    'permission_callback' => function() {
        return current_user_can( 'read' );
    },
) );
```

---

## AJAX Security

### Nonce Verification
```php
// Create action with nonce verification
add_action( 'wp_ajax_my_action', 'handle_my_action' );
add_action( 'wp_ajax_nopriv_my_action', 'handle_my_action' ); // For logged-out users

function handle_my_action() {
    // Verify nonce
    if ( ! check_ajax_referer( 'my_nonce_action', 'security', false ) ) {
        wp_send_json_error( array( 'message' => 'Invalid nonce' ), 403 );
    }

    // Process request...
    wp_send_json_success( $data );
}
```

### Capability Checks
```php
function handle_admin_ajax_action() {
    // Verify nonce
    check_ajax_referer( 'admin_action', 'nonce' );

    // Verify capability
    if ( ! current_user_can( 'manage_options' ) ) {
        wp_send_json_error( array( 'message' => 'Unauthorized' ), 403 );
    }

    // Process admin action...
    wp_send_json_success( $result );
}
add_action( 'wp_ajax_admin_action', 'handle_admin_ajax_action' );
// Don't add nopriv for admin-only actions!
```

### Public vs Private Handlers
```php
// Private (logged-in users only)
add_action( 'wp_ajax_private_action', 'handle_private_action' );

// Public (both logged-in and logged-out)
add_action( 'wp_ajax_public_action', 'handle_public_action' );
add_action( 'wp_ajax_nopriv_public_action', 'handle_public_action' );

function handle_public_action() {
    // Still verify nonce!
    check_ajax_referer( 'public_action', 'nonce' );

    // Rate limiting recommended for public endpoints
    // Validate all input carefully
}
```

### Input Sanitization in AJAX
```php
function handle_form_submission() {
    check_ajax_referer( 'form_submit', 'nonce' );

    // Sanitize all input
    $name = isset( $_POST['name'] ) ? sanitize_text_field( $_POST['name'] ) : '';
    $email = isset( $_POST['email'] ) ? sanitize_email( $_POST['email'] ) : '';
    $message = isset( $_POST['message'] ) ? sanitize_textarea_field( $_POST['message'] ) : '';
    $id = isset( $_POST['id'] ) ? absint( $_POST['id'] ) : 0;

    // Validate
    if ( empty( $name ) || ! is_email( $email ) ) {
        wp_send_json_error( array( 'message' => 'Invalid input' ) );
    }

    // Process...
    wp_send_json_success( $result );
}
```

---

## Common Vulnerabilities

### XSS Prevention
```php
// Always escape output
echo esc_html( $user_data );
echo esc_attr( $attribute );
echo esc_url( $url );

// Use wp_kses for allowed HTML
echo wp_kses( $html, array(
    'a'      => array( 'href' => array(), 'target' => array() ),
    'strong' => array(),
    'em'     => array(),
) );

// Be careful with:
// - innerHTML / jQuery .html()
// - document.write()
// - eval()
// - Unescaped shortcode attributes
```

### SQL Injection Prevention
```php
// Always use prepared statements
$wpdb->prepare( "SELECT * FROM table WHERE id = %d", $id );

// Or use WordPress functions
get_post( $id );
get_post_meta( $post_id, 'key', true );

// Never concatenate user input in SQL
// Never trust input even from "trusted" sources
```

### CSRF Prevention
```php
// Always use nonces for state-changing operations
wp_nonce_field( 'action', 'nonce_field' );
wp_verify_nonce( $_POST['nonce_field'], 'action' );

// For links
$url = wp_nonce_url( $action_url, 'action' );
check_admin_referer( 'action' );
```

### File Inclusion Prevention
```php
// Never include files based on user input
// BAD:
include $_GET['page'] . '.php'; // Remote File Inclusion!

// GOOD: Whitelist approach
$allowed_pages = array( 'dashboard', 'settings', 'users' );
$page = sanitize_key( $_GET['page'] );

if ( in_array( $page, $allowed_pages, true ) ) {
    include plugin_dir_path( __FILE__ ) . 'pages/' . $page . '.php';
}
```

### Object Injection Prevention
```php
// Never unserialize user input
// BAD:
$data = unserialize( $_POST['data'] ); // Object injection!

// GOOD: Use JSON instead
$data = json_decode( $_POST['data'], true );

// Or use allowed_classes (PHP 7.0+)
$data = unserialize( $serialized, array( 'allowed_classes' => false ) );

// WordPress safely handles serialization:
maybe_unserialize( $value ); // Used internally, relatively safe
```

### Insecure Direct Object References
```php
// Always verify user has access to requested object
$post_id = absint( $_GET['post_id'] );
$post = get_post( $post_id );

// Check ownership or capability
if ( ! $post || $post->post_author !== get_current_user_id() ) {
    if ( ! current_user_can( 'edit_others_posts' ) ) {
        wp_die( 'Access denied' );
    }
}
```

---

## Security Headers

### Content-Security-Policy
```php
// Add via .htaccess or PHP
add_action( 'send_headers', function() {
    header( "Content-Security-Policy: default-src 'self'; script-src 'self' 'unsafe-inline'; style-src 'self' 'unsafe-inline';" );
} );

// More restrictive example
header( "Content-Security-Policy: default-src 'self'; script-src 'self'; style-src 'self'; img-src 'self' data:; font-src 'self';" );
```

### X-Frame-Options
```php
// Prevent clickjacking
add_action( 'send_headers', function() {
    header( 'X-Frame-Options: SAMEORIGIN' );
} );

// WordPress default for admin: SAMEORIGIN
// To completely deny framing:
header( 'X-Frame-Options: DENY' );
```

### X-Content-Type-Options
```php
// Prevent MIME type sniffing
add_action( 'send_headers', function() {
    header( 'X-Content-Type-Options: nosniff' );
} );
```

### Additional Security Headers
```php
add_action( 'send_headers', function() {
    // Prevent XSS attacks
    header( 'X-XSS-Protection: 1; mode=block' );

    // Referrer policy
    header( 'Referrer-Policy: strict-origin-when-cross-origin' );

    // Permissions policy
    header( "Permissions-Policy: camera=(), microphone=(), geolocation=()" );

    // Strict Transport Security (HTTPS only)
    if ( is_ssl() ) {
        header( 'Strict-Transport-Security: max-age=31536000; includeSubDomains' );
    }
} );
```

---

## Security Checklists

### Pre-Deployment Checklist
- [ ] All user input is sanitized
- [ ] All output is escaped appropriately
- [ ] Nonces used for all forms and AJAX requests
- [ ] Capability checks on all privileged operations
- [ ] Database queries use prepared statements
- [ ] No hardcoded credentials in code
- [ ] Debug mode disabled
- [ ] Error display disabled
- [ ] File permissions set correctly
- [ ] wp-config.php secured
- [ ] Security keys/salts unique and strong
- [ ] File editor disabled
- [ ] SSL enforced for admin
- [ ] Unused themes/plugins removed
- [ ] WordPress and all components up to date

### Code Review Checklist
- [ ] Check for direct superglobal usage (`$_GET`, `$_POST`, `$_REQUEST`)
- [ ] Verify all `echo`/`print` statements use escaping
- [ ] Check database queries for `$wpdb->prepare()`
- [ ] Look for `eval()`, `exec()`, `system()`, `passthru()`
- [ ] Check for `unserialize()` with user input
- [ ] Verify file operations validate paths
- [ ] Check AJAX handlers for nonce verification
- [ ] Review REST endpoints for permission callbacks
- [ ] Look for hardcoded paths or credentials
- [ ] Check for `extract()` usage with user input
- [ ] Verify capability checks before privileged operations
- [ ] Review `include`/`require` statements for user input
- [ ] Check for `md5()` used for passwords (should use `wp_hash_password()`)
