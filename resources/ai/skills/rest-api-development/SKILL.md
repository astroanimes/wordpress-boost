# Skill: WordPress REST API Development

## Description
Expert guidance for creating custom REST API endpoints, extending existing endpoints, and working with the WordPress REST API.

## When to Use
- User wants to create custom API endpoints
- User needs to extend existing REST responses
- User wants to build headless WordPress
- User needs to authenticate API requests
- User wants to create a mobile app backend
- User needs to integrate with external services

## WordPress Boost Tools to Use
```
- rest_api_routes: List all REST routes
- list_hooks: Find REST API hooks
- wp_shell: Test API responses
- database_schema: Check data structure
```

## Key Concepts

### REST Route Structure
```
/wp-json/                      # API root
/wp-json/wp/v2/posts           # Core posts
/wp-json/my-plugin/v1/items    # Custom endpoint
```

### HTTP Methods
- `GET` - Read data
- `POST` - Create data
- `PUT/PATCH` - Update data
- `DELETE` - Delete data

## Common Tasks

### 1. Register Simple Endpoint
```php
add_action( 'rest_api_init', function() {
    register_rest_route( 'my-plugin/v1', '/hello', array(
        'methods' => 'GET',
        'callback' => function() {
            return array( 'message' => 'Hello World!' );
        },
        'permission_callback' => '__return_true',
    ));
});
```

### 2. Full CRUD Endpoints
```php
add_action( 'rest_api_init', function() {
    // GET all items
    register_rest_route( 'my-plugin/v1', '/items', array(
        'methods' => 'GET',
        'callback' => 'get_items',
        'permission_callback' => '__return_true',
    ));

    // GET single item
    register_rest_route( 'my-plugin/v1', '/items/(?P<id>\d+)', array(
        'methods' => 'GET',
        'callback' => 'get_item',
        'permission_callback' => '__return_true',
    ));

    // POST create item
    register_rest_route( 'my-plugin/v1', '/items', array(
        'methods' => 'POST',
        'callback' => 'create_item',
        'permission_callback' => function() {
            return current_user_can( 'edit_posts' );
        },
    ));

    // PUT update item
    register_rest_route( 'my-plugin/v1', '/items/(?P<id>\d+)', array(
        'methods' => 'PUT',
        'callback' => 'update_item',
        'permission_callback' => function() {
            return current_user_can( 'edit_posts' );
        },
    ));

    // DELETE item
    register_rest_route( 'my-plugin/v1', '/items/(?P<id>\d+)', array(
        'methods' => 'DELETE',
        'callback' => 'delete_item',
        'permission_callback' => function() {
            return current_user_can( 'delete_posts' );
        },
    ));
});

function get_items( $request ) {
    $posts = get_posts( array( 'post_type' => 'item' ) );
    $data = array();
    foreach ( $posts as $post ) {
        $data[] = prepare_item( $post );
    }
    return rest_ensure_response( $data );
}

function get_item( $request ) {
    $post = get_post( $request['id'] );
    if ( ! $post ) {
        return new WP_Error( 'not_found', 'Item not found', array( 'status' => 404 ) );
    }
    return rest_ensure_response( prepare_item( $post ) );
}

function create_item( $request ) {
    $post_id = wp_insert_post( array(
        'post_type' => 'item',
        'post_title' => sanitize_text_field( $request['title'] ),
        'post_content' => wp_kses_post( $request['content'] ),
        'post_status' => 'publish',
    ));
    return rest_ensure_response( prepare_item( get_post( $post_id ) ) );
}

function prepare_item( $post ) {
    return array(
        'id' => $post->ID,
        'title' => $post->post_title,
        'content' => $post->post_content,
        'link' => get_permalink( $post ),
    );
}
```

### 3. Add Custom Field to Posts API
```php
add_action( 'rest_api_init', function() {
    register_rest_field( 'post', 'reading_time', array(
        'get_callback' => function( $post ) {
            $content = get_post_field( 'post_content', $post['id'] );
            $word_count = str_word_count( strip_tags( $content ) );
            return ceil( $word_count / 200 );
        },
        'schema' => array(
            'description' => 'Estimated reading time in minutes',
            'type' => 'integer',
        ),
    ));
});
```

### 4. Argument Validation
```php
register_rest_route( 'my-plugin/v1', '/items', array(
    'methods' => 'GET',
    'callback' => 'get_items',
    'permission_callback' => '__return_true',
    'args' => array(
        'per_page' => array(
            'default' => 10,
            'sanitize_callback' => 'absint',
            'validate_callback' => function( $value ) {
                return $value >= 1 && $value <= 100;
            },
        ),
        'orderby' => array(
            'default' => 'date',
            'enum' => array( 'date', 'title', 'id' ),
        ),
        'search' => array(
            'sanitize_callback' => 'sanitize_text_field',
        ),
    ),
));
```

### 5. Custom Permission Check
```php
'permission_callback' => function( $request ) {
    // Check nonce for cookie auth
    $nonce = $request->get_header( 'X-WP-Nonce' );
    if ( $nonce && wp_verify_nonce( $nonce, 'wp_rest' ) ) {
        return current_user_can( 'edit_posts' );
    }

    // Check for specific capability
    return current_user_can( 'manage_options' );
}
```

### 6. Return Proper Errors
```php
function get_item( $request ) {
    $post = get_post( $request['id'] );

    if ( ! $post ) {
        return new WP_Error(
            'rest_not_found',
            'Item not found.',
            array( 'status' => 404 )
        );
    }

    if ( $post->post_status !== 'publish' && ! current_user_can( 'edit_post', $post->ID ) ) {
        return new WP_Error(
            'rest_forbidden',
            'You cannot view this item.',
            array( 'status' => 403 )
        );
    }

    return rest_ensure_response( prepare_item( $post ) );
}
```

### 7. Pagination Headers
```php
function get_items( $request ) {
    $per_page = $request['per_page'] ?? 10;
    $page = $request['page'] ?? 1;

    $query = new WP_Query( array(
        'post_type' => 'item',
        'posts_per_page' => $per_page,
        'paged' => $page,
    ));

    $data = array();
    foreach ( $query->posts as $post ) {
        $data[] = prepare_item( $post );
    }

    $response = rest_ensure_response( $data );
    $response->header( 'X-WP-Total', $query->found_posts );
    $response->header( 'X-WP-TotalPages', $query->max_num_pages );

    return $response;
}
```

### 8. JavaScript API Fetch
```js
// Using wp.apiFetch
import apiFetch from '@wordpress/api-fetch';

// GET
apiFetch( { path: '/my-plugin/v1/items' } ).then( console.log );

// POST
apiFetch( {
    path: '/my-plugin/v1/items',
    method: 'POST',
    data: { title: 'New Item' },
} );

// Vanilla fetch
fetch( '/wp-json/my-plugin/v1/items', {
    headers: {
        'Content-Type': 'application/json',
        'X-WP-Nonce': wpApiSettings.nonce,
    },
});
```

## Authentication Methods

| Method | Use Case |
|--------|----------|
| Cookie + Nonce | Same-origin requests |
| Application Passwords | Third-party apps |
| JWT (plugin) | Mobile apps, SPAs |
| OAuth (plugin) | Third-party services |

## Response Status Codes
- `200` - OK
- `201` - Created
- `204` - No Content (delete)
- `400` - Bad Request
- `401` - Unauthorized
- `403` - Forbidden
- `404` - Not Found
- `500` - Server Error

## Checklist
- [ ] Always include permission_callback
- [ ] Sanitize and validate input
- [ ] Return WP_Error for errors
- [ ] Use rest_ensure_response()
- [ ] Add pagination for lists
- [ ] Version your API namespace
- [ ] Document endpoints
