# Skill: Creating Custom Post Types

## When to Use
- User asks to create a new content type (e.g., "create a books post type")
- User needs to register a custom post type
- User wants to add a new section to their WordPress site

## Process

1. **Gather Requirements**
   - Post type name (singular and plural)
   - Public or private?
   - Needs archive page?
   - REST API support needed?
   - Associated taxonomies?
   - Supported features (title, editor, thumbnail, etc.)

2. **Use WordPress Boost Tools**
   ```
   - list_post_types: See existing post types
   - list_taxonomies: See existing taxonomies
   - get_post_type: Get configuration of similar post types
   ```

3. **Generate Code**

## Code Template

```php
/**
 * Register {Post Type Name} Custom Post Type
 */
function register_{post_type}_post_type() {
    $labels = array(
        'name'                  => '{Plural Name}',
        'singular_name'         => '{Singular Name}',
        'menu_name'             => '{Menu Name}',
        'name_admin_bar'        => '{Singular Name}',
        'add_new'               => 'Add New',
        'add_new_item'          => 'Add New {Singular Name}',
        'new_item'              => 'New {Singular Name}',
        'edit_item'             => 'Edit {Singular Name}',
        'view_item'             => 'View {Singular Name}',
        'all_items'             => 'All {Plural Name}',
        'search_items'          => 'Search {Plural Name}',
        'parent_item_colon'     => 'Parent {Plural Name}:',
        'not_found'             => 'No {plural name} found.',
        'not_found_in_trash'    => 'No {plural name} found in Trash.',
        'featured_image'        => '{Singular Name} Image',
        'set_featured_image'    => 'Set {singular name} image',
        'remove_featured_image' => 'Remove {singular name} image',
        'use_featured_image'    => 'Use as {singular name} image',
        'archives'              => '{Singular Name} Archives',
        'insert_into_item'      => 'Insert into {singular name}',
        'uploaded_to_this_item' => 'Uploaded to this {singular name}',
        'filter_items_list'     => 'Filter {plural name} list',
        'items_list_navigation' => '{Plural Name} list navigation',
        'items_list'            => '{Plural Name} list',
    );

    $args = array(
        'labels'             => $labels,
        'description'        => '{Description}',
        'public'             => true,
        'publicly_queryable' => true,
        'show_ui'            => true,
        'show_in_menu'       => true,
        'show_in_rest'       => true, // Enable Gutenberg editor
        'query_var'          => true,
        'rewrite'            => array( 'slug' => '{slug}' ),
        'capability_type'    => 'post',
        'has_archive'        => true,
        'hierarchical'       => false,
        'menu_position'      => 20,
        'menu_icon'          => 'dashicons-{icon}',
        'supports'           => array(
            'title',
            'editor',
            'thumbnail',
            'excerpt',
            'custom-fields',
        ),
    );

    register_post_type( '{post_type}', $args );
}
add_action( 'init', 'register_{post_type}_post_type' );
```

## Associated Taxonomy Template

```php
/**
 * Register {Taxonomy Name} Taxonomy
 */
function register_{taxonomy}_taxonomy() {
    $labels = array(
        'name'              => '{Plural Name}',
        'singular_name'     => '{Singular Name}',
        'search_items'      => 'Search {Plural Name}',
        'all_items'         => 'All {Plural Name}',
        'parent_item'       => 'Parent {Singular Name}',
        'parent_item_colon' => 'Parent {Singular Name}:',
        'edit_item'         => 'Edit {Singular Name}',
        'update_item'       => 'Update {Singular Name}',
        'add_new_item'      => 'Add New {Singular Name}',
        'new_item_name'     => 'New {Singular Name} Name',
        'menu_name'         => '{Plural Name}',
    );

    $args = array(
        'hierarchical'      => true, // true = categories, false = tags
        'labels'            => $labels,
        'show_ui'           => true,
        'show_in_rest'      => true,
        'show_admin_column' => true,
        'query_var'         => true,
        'rewrite'           => array( 'slug' => '{slug}' ),
    );

    register_taxonomy( '{taxonomy}', array( '{post_type}' ), $args );
}
add_action( 'init', 'register_{taxonomy}_taxonomy' );
```

## Common Menu Icons
- dashicons-admin-post
- dashicons-admin-page
- dashicons-book
- dashicons-calendar
- dashicons-cart
- dashicons-format-gallery
- dashicons-groups
- dashicons-location
- dashicons-portfolio
- dashicons-products
- dashicons-testimonial
- dashicons-video-alt3

## Checklist
- [ ] Post type slug is lowercase, no spaces (use underscores)
- [ ] Slug is 20 characters or less
- [ ] All labels are properly set
- [ ] REST API enabled if using Gutenberg
- [ ] Rewrite rules will need flush after adding
- [ ] Consider adding flush_rewrite_rules() on plugin activation
