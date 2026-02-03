# ACF (Advanced Custom Fields) Development Guidelines

## Field Groups

### Registering Field Groups in PHP
```php
add_action( 'acf/init', function() {
    acf_add_local_field_group( array(
        'key'      => 'group_unique_key',
        'title'    => 'My Field Group',
        'fields'   => array(
            array(
                'key'   => 'field_unique_key',
                'label' => 'My Field',
                'name'  => 'my_field',
                'type'  => 'text',
            ),
        ),
        'location' => array(
            array(
                array(
                    'param'    => 'post_type',
                    'operator' => '==',
                    'value'    => 'page',
                ),
            ),
        ),
    ) );
} );
```

### Location Rules
Common location parameters:
- `post_type` - Specific post type
- `page_template` - Page template file
- `page_type` - Front page, posts page, etc.
- `user_role` - User role
- `taxonomy` - Taxonomy term
- `options_page` - Options page

## Field Types

### Basic Fields
```php
// Text
array(
    'key'         => 'field_text',
    'label'       => 'Text Field',
    'name'        => 'text_field',
    'type'        => 'text',
    'placeholder' => 'Enter text...',
    'maxlength'   => 100,
)

// Textarea
array(
    'key'   => 'field_textarea',
    'label' => 'Textarea',
    'name'  => 'textarea_field',
    'type'  => 'textarea',
    'rows'  => 4,
)

// WYSIWYG
array(
    'key'     => 'field_wysiwyg',
    'label'   => 'Content',
    'name'    => 'content',
    'type'    => 'wysiwyg',
    'tabs'    => 'all', // all, visual, text
    'toolbar' => 'full', // full, basic
)

// Number
array(
    'key'  => 'field_number',
    'label' => 'Number',
    'name' => 'number_field',
    'type' => 'number',
    'min'  => 0,
    'max'  => 100,
    'step' => 1,
)
```

### Choice Fields
```php
// Select
array(
    'key'     => 'field_select',
    'label'   => 'Select',
    'name'    => 'select_field',
    'type'    => 'select',
    'choices' => array(
        'option1' => 'Option 1',
        'option2' => 'Option 2',
    ),
    'multiple' => false,
)

// Checkbox
array(
    'key'     => 'field_checkbox',
    'label'   => 'Checkbox',
    'name'    => 'checkbox_field',
    'type'    => 'checkbox',
    'choices' => array(
        'red'   => 'Red',
        'green' => 'Green',
        'blue'  => 'Blue',
    ),
)

// Radio
array(
    'key'     => 'field_radio',
    'label'   => 'Radio',
    'name'    => 'radio_field',
    'type'    => 'radio',
    'choices' => array(
        'yes' => 'Yes',
        'no'  => 'No',
    ),
)

// True/False
array(
    'key'     => 'field_true_false',
    'label'   => 'Enable Feature',
    'name'    => 'enable_feature',
    'type'    => 'true_false',
    'ui'      => true, // Toggle UI
)
```

### Relational Fields
```php
// Post Object
array(
    'key'         => 'field_post_object',
    'label'       => 'Related Post',
    'name'        => 'related_post',
    'type'        => 'post_object',
    'post_type'   => array( 'post', 'page' ),
    'return_format' => 'object', // object, id
    'multiple'    => false,
)

// Relationship
array(
    'key'         => 'field_relationship',
    'label'       => 'Related Posts',
    'name'        => 'related_posts',
    'type'        => 'relationship',
    'post_type'   => array( 'post' ),
    'filters'     => array( 'search', 'post_type', 'taxonomy' ),
    'return_format' => 'object',
)

// Taxonomy
array(
    'key'         => 'field_taxonomy',
    'label'       => 'Categories',
    'name'        => 'categories',
    'type'        => 'taxonomy',
    'taxonomy'    => 'category',
    'field_type'  => 'checkbox', // checkbox, multi_select, radio, select
    'return_format' => 'object',
)

// User
array(
    'key'         => 'field_user',
    'label'       => 'Author',
    'name'        => 'author',
    'type'        => 'user',
    'role'        => array( 'author', 'editor' ),
    'return_format' => 'array',
)
```

### Media Fields
```php
// Image
array(
    'key'           => 'field_image',
    'label'         => 'Featured Image',
    'name'          => 'featured_image',
    'type'          => 'image',
    'return_format' => 'array', // array, url, id
    'preview_size'  => 'medium',
    'mime_types'    => 'jpg, jpeg, png, gif',
)

// File
array(
    'key'           => 'field_file',
    'label'         => 'Document',
    'name'          => 'document',
    'type'          => 'file',
    'return_format' => 'array',
    'mime_types'    => 'pdf, doc, docx',
)

// Gallery
array(
    'key'           => 'field_gallery',
    'label'         => 'Gallery',
    'name'          => 'gallery',
    'type'          => 'gallery',
    'return_format' => 'array',
    'preview_size'  => 'thumbnail',
)
```

## Complex Fields

### Repeater
```php
array(
    'key'        => 'field_repeater',
    'label'      => 'Team Members',
    'name'       => 'team_members',
    'type'       => 'repeater',
    'min'        => 1,
    'max'        => 10,
    'layout'     => 'block', // block, table, row
    'sub_fields' => array(
        array(
            'key'   => 'field_member_name',
            'label' => 'Name',
            'name'  => 'name',
            'type'  => 'text',
        ),
        array(
            'key'   => 'field_member_photo',
            'label' => 'Photo',
            'name'  => 'photo',
            'type'  => 'image',
        ),
    ),
)
```

### Group
```php
array(
    'key'        => 'field_address_group',
    'label'      => 'Address',
    'name'       => 'address',
    'type'       => 'group',
    'layout'     => 'block',
    'sub_fields' => array(
        array(
            'key'   => 'field_street',
            'label' => 'Street',
            'name'  => 'street',
            'type'  => 'text',
        ),
        array(
            'key'   => 'field_city',
            'label' => 'City',
            'name'  => 'city',
            'type'  => 'text',
        ),
    ),
)
```

### Flexible Content
```php
array(
    'key'     => 'field_content_blocks',
    'label'   => 'Content Blocks',
    'name'    => 'content_blocks',
    'type'    => 'flexible_content',
    'layouts' => array(
        array(
            'key'        => 'layout_text_block',
            'name'       => 'text_block',
            'label'      => 'Text Block',
            'sub_fields' => array(
                array(
                    'key'   => 'field_text_content',
                    'label' => 'Content',
                    'name'  => 'content',
                    'type'  => 'wysiwyg',
                ),
            ),
        ),
        array(
            'key'        => 'layout_image_block',
            'name'       => 'image_block',
            'label'      => 'Image Block',
            'sub_fields' => array(
                array(
                    'key'   => 'field_image',
                    'label' => 'Image',
                    'name'  => 'image',
                    'type'  => 'image',
                ),
            ),
        ),
    ),
)
```

## Displaying Fields

### Basic Usage
```php
// Get field value
$value = get_field( 'field_name' );
$value = get_field( 'field_name', $post_id );

// Display field value
the_field( 'field_name' );
```

### Repeater Loop
```php
if ( have_rows( 'team_members' ) ) :
    while ( have_rows( 'team_members' ) ) : the_row();
        $name  = get_sub_field( 'name' );
        $photo = get_sub_field( 'photo' );

        echo '<div class="team-member">';
        echo '<h3>' . esc_html( $name ) . '</h3>';
        echo wp_get_attachment_image( $photo['ID'], 'thumbnail' );
        echo '</div>';
    endwhile;
endif;
```

### Flexible Content Loop
```php
if ( have_rows( 'content_blocks' ) ) :
    while ( have_rows( 'content_blocks' ) ) : the_row();
        if ( get_row_layout() === 'text_block' ) :
            the_sub_field( 'content' );
        elseif ( get_row_layout() === 'image_block' ) :
            $image = get_sub_field( 'image' );
            echo wp_get_attachment_image( $image['ID'], 'large' );
        endif;
    endwhile;
endif;
```

### Group Fields
```php
$address = get_field( 'address' );
if ( $address ) {
    echo $address['street'];
    echo $address['city'];
}
```

## Options Pages

### Creating Options Page
```php
add_action( 'acf/init', function() {
    acf_add_options_page( array(
        'page_title' => 'Site Settings',
        'menu_title' => 'Site Settings',
        'menu_slug'  => 'site-settings',
        'capability' => 'manage_options',
        'icon_url'   => 'dashicons-admin-settings',
    ) );

    // Sub-page
    acf_add_options_sub_page( array(
        'page_title'  => 'Social Media',
        'menu_title'  => 'Social Media',
        'parent_slug' => 'site-settings',
    ) );
} );
```

### Getting Options Values
```php
$value = get_field( 'field_name', 'option' );
```

## Hooks

### Modify Field Values
```php
// Before saving
add_filter( 'acf/update_value/name=my_field', function( $value, $post_id, $field ) {
    // Modify value before saving
    return $value;
}, 10, 3 );

// When loading
add_filter( 'acf/load_value/name=my_field', function( $value, $post_id, $field ) {
    // Modify value when loading
    return $value;
}, 10, 3 );

// Format value for display
add_filter( 'acf/format_value/name=my_field', function( $value, $post_id, $field ) {
    return $value;
}, 10, 3 );
```

### Modify Field Settings
```php
// Modify field choices
add_filter( 'acf/load_field/name=my_select', function( $field ) {
    $field['choices'] = get_dynamic_choices();
    return $field;
} );

// Validate field
add_filter( 'acf/validate_value/name=my_field', function( $valid, $value, $field, $input_name ) {
    if ( strlen( $value ) < 5 ) {
        return 'Value must be at least 5 characters';
    }
    return $valid;
}, 10, 4 );
```

## Best Practices

1. **Use unique keys** - Prefix with project name: `group_myproject_`, `field_myproject_`
2. **Register in code** - Use `acf/init` hook for version control
3. **Use conditional logic** - Show/hide fields based on other field values
4. **Set appropriate return formats** - Choose array, object, or ID based on needs
5. **Validate input** - Use `acf/validate_value` filter
6. **Escape output** - Always escape when displaying: `esc_html()`, `esc_attr()`
7. **Use appropriate field types** - Choose the right field type for the data
