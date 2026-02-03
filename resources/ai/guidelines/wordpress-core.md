# WordPress Core Development Guidelines

## PHP Coding Standards

### Naming Conventions
- **Functions**: Lowercase with underscores: `my_function_name()`
- **Classes**: Capitalized words: `My_Class_Name`
- **Constants**: Uppercase with underscores: `MY_CONSTANT_NAME`
- **Variables**: Lowercase with underscores: `$my_variable`
- **Hooks**: Lowercase with underscores: `my_action_hook`, `my_filter_hook`

### Formatting
- Use tabs for indentation (not spaces)
- Opening braces on same line as statement
- Always use braces for control structures
- Space after control structure keywords: `if ( $condition )`
- Spaces inside parentheses: `function_call( $arg1, $arg2 )`
- Yoda conditions: `if ( true === $variable )`

### Example
```php
function my_custom_function( $arg1, $arg2 = '' ) {
    if ( empty( $arg1 ) ) {
        return false;
    }

    $result = do_something( $arg1, $arg2 );

    return $result;
}
```

## Security Best Practices

### Data Validation
```php
// Sanitize input
$title = sanitize_text_field( $_POST['title'] );
$email = sanitize_email( $_POST['email'] );
$url = esc_url_raw( $_POST['url'] );
$html = wp_kses_post( $_POST['content'] );
$int = absint( $_POST['id'] );

// Validate data
if ( ! is_email( $email ) ) {
    return new WP_Error( 'invalid_email', 'Invalid email address' );
}
```

### Output Escaping
```php
// Always escape output
echo esc_html( $text );           // Plain text
echo esc_attr( $attribute );      // HTML attributes
echo esc_url( $url );             // URLs
echo wp_kses_post( $html );       // Post content HTML
echo esc_textarea( $text );       // Textarea content
```

### Nonces
```php
// Create nonce field in form
wp_nonce_field( 'my_action', 'my_nonce' );

// Verify nonce
if ( ! wp_verify_nonce( $_POST['my_nonce'], 'my_action' ) ) {
    die( 'Security check failed' );
}

// For AJAX
check_ajax_referer( 'my_action', 'nonce' );
```

### Capability Checks
```php
// Check user capabilities
if ( ! current_user_can( 'edit_posts' ) ) {
    wp_die( 'Unauthorized access' );
}

// Common capabilities
// - manage_options (admin)
// - edit_posts (editor)
// - read (subscriber)
// - upload_files
// - edit_theme_options
```

### Database Security
```php
global $wpdb;

// Always use prepare() for queries with variables
$results = $wpdb->get_results(
    $wpdb->prepare(
        "SELECT * FROM {$wpdb->posts} WHERE post_type = %s AND post_status = %s",
        'page',
        'publish'
    )
);

// Never do this:
// $wpdb->query( "SELECT * FROM $table WHERE id = $id" ); // SQL injection!
```

## Hooks System

### Actions
```php
// Add action
add_action( 'init', 'my_init_function', 10 );
add_action( 'wp_enqueue_scripts', 'my_enqueue_scripts', 20 );

// Remove action
remove_action( 'wp_head', 'wp_generator' );

// Create custom action
do_action( 'my_custom_action', $arg1, $arg2 );

// Hook into custom action
add_action( 'my_custom_action', function( $arg1, $arg2 ) {
    // Do something
}, 10, 2 );
```

### Filters
```php
// Add filter
add_filter( 'the_content', 'my_content_filter', 10 );
add_filter( 'the_title', 'my_title_filter', 10, 2 );

// Always return the value
add_filter( 'the_content', function( $content ) {
    $content .= '<p>Added content</p>';
    return $content; // Must return!
} );

// Create custom filter
$value = apply_filters( 'my_custom_filter', $default_value, $context );
```

### Common Hook Priorities
- 1-9: Early execution
- 10: Default priority
- 11-99: After default
- PHP_INT_MAX: Very last

## Database Operations

### WP_Query
```php
$args = array(
    'post_type'      => 'post',
    'post_status'    => 'publish',
    'posts_per_page' => 10,
    'orderby'        => 'date',
    'order'          => 'DESC',
    'meta_query'     => array(
        array(
            'key'     => 'featured',
            'value'   => '1',
            'compare' => '=',
        ),
    ),
    'tax_query'      => array(
        array(
            'taxonomy' => 'category',
            'field'    => 'slug',
            'terms'    => 'news',
        ),
    ),
);

$query = new WP_Query( $args );

if ( $query->have_posts() ) {
    while ( $query->have_posts() ) {
        $query->the_post();
        // Display post
    }
    wp_reset_postdata(); // Always reset!
}
```

### Direct Database Queries
```php
global $wpdb;

// Get single value
$count = $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->posts}" );

// Get single row
$post = $wpdb->get_row(
    $wpdb->prepare( "SELECT * FROM {$wpdb->posts} WHERE ID = %d", $id )
);

// Get multiple rows
$posts = $wpdb->get_results(
    "SELECT * FROM {$wpdb->posts} WHERE post_type = 'post' LIMIT 10"
);

// Insert
$wpdb->insert(
    $wpdb->prefix . 'custom_table',
    array( 'column1' => 'value1', 'column2' => 123 ),
    array( '%s', '%d' )
);

// Update
$wpdb->update(
    $wpdb->prefix . 'custom_table',
    array( 'column1' => 'new_value' ),
    array( 'id' => 1 ),
    array( '%s' ),
    array( '%d' )
);

// Delete
$wpdb->delete(
    $wpdb->prefix . 'custom_table',
    array( 'id' => 1 ),
    array( '%d' )
);
```

## Options API

```php
// Get option
$value = get_option( 'my_option', 'default_value' );

// Update option (creates if doesn't exist)
update_option( 'my_option', $value );

// Add option (only if doesn't exist)
add_option( 'my_option', $value, '', 'no' ); // 'no' = don't autoload

// Delete option
delete_option( 'my_option' );

// Transients (cached options)
$data = get_transient( 'my_transient' );
if ( false === $data ) {
    $data = expensive_function();
    set_transient( 'my_transient', $data, HOUR_IN_SECONDS );
}
delete_transient( 'my_transient' );
```

## Error Handling

```php
// WP_Error
$result = some_function();
if ( is_wp_error( $result ) ) {
    $error_message = $result->get_error_message();
    $error_code = $result->get_error_code();
    // Handle error
}

// Create WP_Error
return new WP_Error(
    'error_code',
    'Error message',
    array( 'status' => 400 )
);

// Logging
if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
    error_log( 'Debug message: ' . print_r( $data, true ) );
}
```

## Internationalization

```php
// Translatable strings
__( 'Text to translate', 'text-domain' );
_e( 'Text to translate and echo', 'text-domain' );
_n( 'One item', '%s items', $count, 'text-domain' );
_x( 'Post', 'noun', 'text-domain' ); // Context

// With variables
sprintf( __( 'Hello, %s', 'text-domain' ), $name );
printf( __( 'Hello, %s', 'text-domain' ), $name );

// Escape and translate
esc_html__( 'Text', 'text-domain' );
esc_attr__( 'Text', 'text-domain' );
esc_html_e( 'Text', 'text-domain' );
```

## Performance Tips

### Avoid N+1 Queries
```php
// Bad: Query in loop
foreach ( $post_ids as $id ) {
    $post = get_post( $id ); // Query per iteration
}

// Good: Single query
$posts = get_posts( array( 'include' => $post_ids ) );
```

### Use Object Cache
```php
$data = wp_cache_get( 'my_key', 'my_group' );
if ( false === $data ) {
    $data = expensive_operation();
    wp_cache_set( 'my_key', $data, 'my_group', 3600 );
}
```

### Limit Query Fields
```php
// Only get what you need
$ids = $wpdb->get_col( "SELECT ID FROM {$wpdb->posts} WHERE post_type = 'post'" );
```

## Common Mistakes to Avoid

1. **Not escaping output** - Always escape
2. **Not using nonces** - Always verify
3. **Direct SQL with user input** - Always prepare()
4. **Not checking capabilities** - Always verify permissions
5. **Forgetting wp_reset_postdata()** - Always reset after custom loops
6. **Hardcoding table names** - Use $wpdb->prefix
7. **Not returning in filters** - Always return the filtered value
8. **Wrong hook timing** - Check when hooks fire
