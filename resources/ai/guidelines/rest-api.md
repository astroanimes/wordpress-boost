# WordPress REST API Development Guidelines

## REST API Basics

### Default Endpoints
WordPress provides built-in endpoints:
- `/wp-json/wp/v2/posts` - Posts
- `/wp-json/wp/v2/pages` - Pages
- `/wp-json/wp/v2/users` - Users
- `/wp-json/wp/v2/categories` - Categories
- `/wp-json/wp/v2/tags` - Tags
- `/wp-json/wp/v2/comments` - Comments
- `/wp-json/wp/v2/media` - Media
- `/wp-json/wp/v2/types` - Post types
- `/wp-json/wp/v2/taxonomies` - Taxonomies
- `/wp-json/wp/v2/settings` - Site settings

### Namespaces
```
/wp-json/                      # API root
/wp-json/wp/v2/                # Core WordPress (v2)
/wp-json/wc/v3/                # WooCommerce (v3)
/wp-json/my-plugin/v1/         # Custom namespace
```

## Registering Custom Endpoints

### Basic Endpoint
```php
add_action( 'rest_api_init', 'register_my_routes' );

function register_my_routes() {
    register_rest_route( 'my-plugin/v1', '/items', array(
        'methods'             => WP_REST_Server::READABLE, // GET
        'callback'            => 'get_items_callback',
        'permission_callback' => '__return_true', // Public endpoint
    ) );
}

function get_items_callback( WP_REST_Request $request ) {
    $items = get_posts( array(
        'post_type'      => 'item',
        'posts_per_page' => 10,
    ) );

    $data = array();
    foreach ( $items as $item ) {
        $data[] = array(
            'id'    => $item->ID,
            'title' => $item->post_title,
            'link'  => get_permalink( $item->ID ),
        );
    }

    return rest_ensure_response( $data );
}
```

### RESTful CRUD Endpoints
```php
add_action( 'rest_api_init', 'register_items_routes' );

function register_items_routes() {
    $namespace = 'my-plugin/v1';
    $resource = 'items';

    // GET /items - List all
    register_rest_route( $namespace, '/' . $resource, array(
        array(
            'methods'             => WP_REST_Server::READABLE,
            'callback'            => 'get_items',
            'permission_callback' => '__return_true',
            'args'                => get_items_args(),
        ),
        array(
            'methods'             => WP_REST_Server::CREATABLE, // POST
            'callback'            => 'create_item',
            'permission_callback' => 'items_permissions_check',
            'args'                => get_item_args(),
        ),
    ) );

    // GET/PUT/DELETE /items/{id} - Single item
    register_rest_route( $namespace, '/' . $resource . '/(?P<id>[\d]+)', array(
        array(
            'methods'             => WP_REST_Server::READABLE,
            'callback'            => 'get_item',
            'permission_callback' => '__return_true',
        ),
        array(
            'methods'             => WP_REST_Server::EDITABLE, // PUT/PATCH
            'callback'            => 'update_item',
            'permission_callback' => 'items_permissions_check',
            'args'                => get_item_args(),
        ),
        array(
            'methods'             => WP_REST_Server::DELETABLE, // DELETE
            'callback'            => 'delete_item',
            'permission_callback' => 'items_permissions_check',
        ),
    ) );
}

// List items
function get_items( WP_REST_Request $request ) {
    $per_page = $request->get_param( 'per_page' ) ?: 10;
    $page = $request->get_param( 'page' ) ?: 1;
    $search = $request->get_param( 'search' );

    $args = array(
        'post_type'      => 'item',
        'posts_per_page' => $per_page,
        'paged'          => $page,
    );

    if ( $search ) {
        $args['s'] = $search;
    }

    $query = new WP_Query( $args );

    $data = array();
    foreach ( $query->posts as $post ) {
        $data[] = prepare_item_for_response( $post );
    }

    $response = rest_ensure_response( $data );

    // Add pagination headers
    $response->header( 'X-WP-Total', $query->found_posts );
    $response->header( 'X-WP-TotalPages', $query->max_num_pages );

    return $response;
}

// Get single item
function get_item( WP_REST_Request $request ) {
    $id = $request->get_param( 'id' );
    $post = get_post( $id );

    if ( ! $post || $post->post_type !== 'item' ) {
        return new WP_Error(
            'rest_item_not_found',
            __( 'Item not found.', 'my-plugin' ),
            array( 'status' => 404 )
        );
    }

    return rest_ensure_response( prepare_item_for_response( $post ) );
}

// Create item
function create_item( WP_REST_Request $request ) {
    $title = sanitize_text_field( $request->get_param( 'title' ) );
    $content = wp_kses_post( $request->get_param( 'content' ) );

    $post_id = wp_insert_post( array(
        'post_type'    => 'item',
        'post_title'   => $title,
        'post_content' => $content,
        'post_status'  => 'publish',
    ) );

    if ( is_wp_error( $post_id ) ) {
        return $post_id;
    }

    $post = get_post( $post_id );
    $response = rest_ensure_response( prepare_item_for_response( $post ) );
    $response->set_status( 201 );
    $response->header( 'Location', rest_url( 'my-plugin/v1/items/' . $post_id ) );

    return $response;
}

// Update item
function update_item( WP_REST_Request $request ) {
    $id = $request->get_param( 'id' );
    $post = get_post( $id );

    if ( ! $post || $post->post_type !== 'item' ) {
        return new WP_Error(
            'rest_item_not_found',
            __( 'Item not found.', 'my-plugin' ),
            array( 'status' => 404 )
        );
    }

    $update_args = array( 'ID' => $id );

    if ( $request->has_param( 'title' ) ) {
        $update_args['post_title'] = sanitize_text_field( $request->get_param( 'title' ) );
    }

    if ( $request->has_param( 'content' ) ) {
        $update_args['post_content'] = wp_kses_post( $request->get_param( 'content' ) );
    }

    $result = wp_update_post( $update_args );

    if ( is_wp_error( $result ) ) {
        return $result;
    }

    return rest_ensure_response( prepare_item_for_response( get_post( $id ) ) );
}

// Delete item
function delete_item( WP_REST_Request $request ) {
    $id = $request->get_param( 'id' );
    $post = get_post( $id );

    if ( ! $post || $post->post_type !== 'item' ) {
        return new WP_Error(
            'rest_item_not_found',
            __( 'Item not found.', 'my-plugin' ),
            array( 'status' => 404 )
        );
    }

    $result = wp_delete_post( $id, true );

    if ( ! $result ) {
        return new WP_Error(
            'rest_cannot_delete',
            __( 'Item could not be deleted.', 'my-plugin' ),
            array( 'status' => 500 )
        );
    }

    return rest_ensure_response( array(
        'deleted' => true,
        'id'      => $id,
    ) );
}

// Prepare item data
function prepare_item_for_response( $post ) {
    return array(
        'id'         => $post->ID,
        'title'      => $post->post_title,
        'content'    => $post->post_content,
        'excerpt'    => $post->post_excerpt,
        'status'     => $post->post_status,
        'date'       => $post->post_date,
        'modified'   => $post->post_modified,
        'link'       => get_permalink( $post->ID ),
        'author'     => (int) $post->post_author,
        'meta'       => array(
            'custom_field' => get_post_meta( $post->ID, 'custom_field', true ),
        ),
        '_links'     => array(
            'self' => array(
                array( 'href' => rest_url( 'my-plugin/v1/items/' . $post->ID ) ),
            ),
            'collection' => array(
                array( 'href' => rest_url( 'my-plugin/v1/items' ) ),
            ),
        ),
    );
}
```

## Permission Callbacks

```php
// Check user capabilities
function items_permissions_check( WP_REST_Request $request ) {
    return current_user_can( 'edit_posts' );
}

// Different permission for different methods
function items_permission_check( WP_REST_Request $request ) {
    $method = $request->get_method();

    if ( 'GET' === $method ) {
        return true; // Public read access
    }

    if ( 'POST' === $method ) {
        return current_user_can( 'publish_posts' );
    }

    if ( in_array( $method, array( 'PUT', 'PATCH', 'DELETE' ), true ) ) {
        $post_id = $request->get_param( 'id' );
        return current_user_can( 'edit_post', $post_id );
    }

    return false;
}

// Check specific capability
function admin_only_permission_check() {
    return current_user_can( 'manage_options' );
}

// Nonce verification for cookie authentication
function nonce_permission_check( WP_REST_Request $request ) {
    $nonce = $request->get_header( 'X-WP-Nonce' );
    if ( ! wp_verify_nonce( $nonce, 'wp_rest' ) ) {
        return new WP_Error(
            'rest_forbidden',
            __( 'Invalid nonce.', 'my-plugin' ),
            array( 'status' => 403 )
        );
    }
    return true;
}
```

## Argument Validation

```php
function get_items_args() {
    return array(
        'page' => array(
            'description'       => __( 'Current page of the collection.', 'my-plugin' ),
            'type'              => 'integer',
            'default'           => 1,
            'minimum'           => 1,
            'sanitize_callback' => 'absint',
        ),
        'per_page' => array(
            'description'       => __( 'Maximum number of items per page.', 'my-plugin' ),
            'type'              => 'integer',
            'default'           => 10,
            'minimum'           => 1,
            'maximum'           => 100,
            'sanitize_callback' => 'absint',
        ),
        'search' => array(
            'description'       => __( 'Search term.', 'my-plugin' ),
            'type'              => 'string',
            'sanitize_callback' => 'sanitize_text_field',
        ),
        'orderby' => array(
            'description'       => __( 'Sort collection by attribute.', 'my-plugin' ),
            'type'              => 'string',
            'default'           => 'date',
            'enum'              => array( 'date', 'title', 'id' ),
        ),
        'order' => array(
            'description'       => __( 'Order sort direction.', 'my-plugin' ),
            'type'              => 'string',
            'default'           => 'desc',
            'enum'              => array( 'asc', 'desc' ),
        ),
    );
}

function get_item_args() {
    return array(
        'title' => array(
            'description'       => __( 'Item title.', 'my-plugin' ),
            'type'              => 'string',
            'required'          => true,
            'sanitize_callback' => 'sanitize_text_field',
            'validate_callback' => function( $value ) {
                if ( strlen( $value ) < 3 ) {
                    return new WP_Error(
                        'rest_invalid_param',
                        __( 'Title must be at least 3 characters.', 'my-plugin' )
                    );
                }
                return true;
            },
        ),
        'content' => array(
            'description'       => __( 'Item content.', 'my-plugin' ),
            'type'              => 'string',
            'sanitize_callback' => 'wp_kses_post',
        ),
        'status' => array(
            'description'       => __( 'Item status.', 'my-plugin' ),
            'type'              => 'string',
            'default'           => 'publish',
            'enum'              => array( 'publish', 'draft', 'pending' ),
        ),
    );
}
```

## REST Controller Class

```php
class My_Items_Controller extends WP_REST_Controller {
    protected $namespace = 'my-plugin/v1';
    protected $rest_base = 'items';

    public function register_routes() {
        register_rest_route( $this->namespace, '/' . $this->rest_base, array(
            array(
                'methods'             => WP_REST_Server::READABLE,
                'callback'            => array( $this, 'get_items' ),
                'permission_callback' => array( $this, 'get_items_permissions_check' ),
                'args'                => $this->get_collection_params(),
            ),
            array(
                'methods'             => WP_REST_Server::CREATABLE,
                'callback'            => array( $this, 'create_item' ),
                'permission_callback' => array( $this, 'create_item_permissions_check' ),
                'args'                => $this->get_endpoint_args_for_item_schema( WP_REST_Server::CREATABLE ),
            ),
            'schema' => array( $this, 'get_public_item_schema' ),
        ) );

        register_rest_route( $this->namespace, '/' . $this->rest_base . '/(?P<id>[\d]+)', array(
            array(
                'methods'             => WP_REST_Server::READABLE,
                'callback'            => array( $this, 'get_item' ),
                'permission_callback' => array( $this, 'get_item_permissions_check' ),
            ),
            array(
                'methods'             => WP_REST_Server::EDITABLE,
                'callback'            => array( $this, 'update_item' ),
                'permission_callback' => array( $this, 'update_item_permissions_check' ),
                'args'                => $this->get_endpoint_args_for_item_schema( WP_REST_Server::EDITABLE ),
            ),
            array(
                'methods'             => WP_REST_Server::DELETABLE,
                'callback'            => array( $this, 'delete_item' ),
                'permission_callback' => array( $this, 'delete_item_permissions_check' ),
            ),
            'schema' => array( $this, 'get_public_item_schema' ),
        ) );
    }

    public function get_items( $request ) {
        // Implementation
    }

    public function get_item( $request ) {
        // Implementation
    }

    public function create_item( $request ) {
        // Implementation
    }

    public function update_item( $request ) {
        // Implementation
    }

    public function delete_item( $request ) {
        // Implementation
    }

    public function get_items_permissions_check( $request ) {
        return true;
    }

    public function get_item_permissions_check( $request ) {
        return true;
    }

    public function create_item_permissions_check( $request ) {
        return current_user_can( 'publish_posts' );
    }

    public function update_item_permissions_check( $request ) {
        $id = $request->get_param( 'id' );
        return current_user_can( 'edit_post', $id );
    }

    public function delete_item_permissions_check( $request ) {
        $id = $request->get_param( 'id' );
        return current_user_can( 'delete_post', $id );
    }

    public function get_item_schema() {
        return array(
            '$schema'    => 'http://json-schema.org/draft-04/schema#',
            'title'      => 'item',
            'type'       => 'object',
            'properties' => array(
                'id' => array(
                    'description' => __( 'Unique identifier.', 'my-plugin' ),
                    'type'        => 'integer',
                    'readonly'    => true,
                ),
                'title' => array(
                    'description' => __( 'Item title.', 'my-plugin' ),
                    'type'        => 'string',
                ),
                'content' => array(
                    'description' => __( 'Item content.', 'my-plugin' ),
                    'type'        => 'string',
                ),
            ),
        );
    }
}

// Register controller
add_action( 'rest_api_init', function() {
    $controller = new My_Items_Controller();
    $controller->register_routes();
} );
```

## Modifying Existing Endpoints

### Add Custom Fields to Posts
```php
add_action( 'rest_api_init', 'register_custom_rest_fields' );

function register_custom_rest_fields() {
    register_rest_field( 'post', 'custom_field', array(
        'get_callback'    => function( $post ) {
            return get_post_meta( $post['id'], 'custom_field', true );
        },
        'update_callback' => function( $value, $post ) {
            return update_post_meta( $post->ID, 'custom_field', sanitize_text_field( $value ) );
        },
        'schema'          => array(
            'description' => __( 'Custom field value.', 'my-plugin' ),
            'type'        => 'string',
        ),
    ) );
}
```

### Filter REST Response
```php
add_filter( 'rest_prepare_post', 'modify_post_response', 10, 3 );

function modify_post_response( $response, $post, $request ) {
    // Add custom data
    $response->data['reading_time'] = calculate_reading_time( $post->post_content );

    // Remove sensitive data
    unset( $response->data['author'] );

    return $response;
}
```

## Authentication

### Application Passwords (WP 5.6+)
```php
// Built-in support - use Basic Auth header
// Authorization: Basic base64(username:application_password)
```

### JWT Authentication (plugin required)
```php
// Usually provided by JWT Authentication plugin
// Authorization: Bearer <token>
```

### Cookie Authentication (for logged-in users)
```js
// JavaScript fetch with nonce
fetch( '/wp-json/my-plugin/v1/items', {
    method: 'POST',
    headers: {
        'Content-Type': 'application/json',
        'X-WP-Nonce': wpApiSettings.nonce,
    },
    body: JSON.stringify( { title: 'New Item' } ),
} );
```

## JavaScript API Client

```js
// Using wp.apiFetch (in block editor or enqueued)
import apiFetch from '@wordpress/api-fetch';

// GET request
apiFetch( { path: '/my-plugin/v1/items' } ).then( items => {
    console.log( items );
} );

// POST request
apiFetch( {
    path: '/my-plugin/v1/items',
    method: 'POST',
    data: { title: 'New Item', content: 'Content here' },
} ).then( item => {
    console.log( item );
} );

// With middleware
apiFetch.use( apiFetch.createRootURLMiddleware( 'https://example.com/wp-json' ) );
apiFetch.use( apiFetch.createNonceMiddleware( wpApiSettings.nonce ) );
```

## Best Practices

1. **Use proper HTTP methods**: GET for reading, POST for creating, PUT/PATCH for updating, DELETE for deleting
2. **Return appropriate status codes**: 200 OK, 201 Created, 204 No Content, 400 Bad Request, 401 Unauthorized, 403 Forbidden, 404 Not Found
3. **Always include permission_callback**: Never use `__return_true` for write operations
4. **Validate and sanitize input**: Use argument validation and sanitize callbacks
5. **Use schemas**: Define proper JSON schemas for documentation and validation
6. **Version your API**: Use namespace versioning (v1, v2)
7. **Handle errors gracefully**: Return WP_Error with meaningful messages
8. **Paginate collections**: Include pagination headers and support page/per_page params
9. **Use hypermedia links**: Include _links for discoverability
10. **Cache responses**: Use appropriate caching headers
