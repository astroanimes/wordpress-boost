# Advanced Custom Fields (ACF) Development Guidelines

## Getting Field Values

### Basic Usage
```php
// Get field value from current post
$value = get_field( 'field_name' );

// Get field from specific post
$value = get_field( 'field_name', $post_id );

// Get field from options page
$value = get_field( 'field_name', 'option' );

// Get field from user
$value = get_field( 'field_name', 'user_' . $user_id );

// Get field from taxonomy term
$value = get_field( 'field_name', 'term_' . $term_id );

// Get field from comment
$value = get_field( 'field_name', 'comment_' . $comment_id );

// Get field from widget
$value = get_field( 'field_name', 'widget_' . $widget_id );

// Display field (echoes value)
the_field( 'field_name' );
```

### With Default Values
```php
$value = get_field( 'field_name' );
if ( ! $value ) {
    $value = 'default value';
}

// Or using null coalescing
$value = get_field( 'field_name' ) ?: 'default value';
```

## Common Field Types

### Text Fields
```php
// Text
$text = get_field( 'text_field' );
echo esc_html( $text );

// Textarea
$textarea = get_field( 'textarea_field' );
echo wp_kses_post( $textarea );

// WYSIWYG
$content = get_field( 'wysiwyg_field' );
echo $content; // Already escaped by ACF

// Number
$number = get_field( 'number_field' );
echo absint( $number );

// Email
$email = get_field( 'email_field' );
echo esc_attr( $email );

// URL
$url = get_field( 'url_field' );
echo esc_url( $url );
```

### Choice Fields
```php
// Select / Radio
$choice = get_field( 'select_field' );
echo esc_html( $choice );

// Multiple select (returns array)
$choices = get_field( 'multi_select_field' );
if ( $choices ) {
    foreach ( $choices as $choice ) {
        echo esc_html( $choice );
    }
}

// Checkbox (returns array)
$checkboxes = get_field( 'checkbox_field' );
if ( $checkboxes && in_array( 'value1', $checkboxes ) ) {
    // Value is checked
}

// True/False
$boolean = get_field( 'true_false_field' );
if ( $boolean ) {
    // Is true
}

// Button Group
$button = get_field( 'button_group_field' );
```

### Image Field
```php
// Return format: Array
$image = get_field( 'image_field' );
if ( $image ) {
    $url = $image['url'];
    $alt = $image['alt'];
    $sizes = $image['sizes'];

    // Specific size
    $medium_url = $image['sizes']['medium'];
    $medium_width = $image['sizes']['medium-width'];
    $medium_height = $image['sizes']['medium-height'];

    // Output
    echo '<img src="' . esc_url( $image['sizes']['large'] ) . '" alt="' . esc_attr( $alt ) . '">';
}

// Return format: ID
$image_id = get_field( 'image_field' );
if ( $image_id ) {
    echo wp_get_attachment_image( $image_id, 'large' );
}

// Return format: URL
$image_url = get_field( 'image_field' );
if ( $image_url ) {
    echo '<img src="' . esc_url( $image_url ) . '" alt="">';
}
```

### File Field
```php
// Return format: Array
$file = get_field( 'file_field' );
if ( $file ) {
    echo '<a href="' . esc_url( $file['url'] ) . '">' . esc_html( $file['filename'] ) . '</a>';
    echo '<span>' . esc_html( $file['filesize'] ) . '</span>';
}

// Return format: ID
$file_id = get_field( 'file_field' );
if ( $file_id ) {
    $url = wp_get_attachment_url( $file_id );
}
```

### Gallery Field
```php
$gallery = get_field( 'gallery_field' );
if ( $gallery ) {
    echo '<div class="gallery">';
    foreach ( $gallery as $image ) {
        echo '<img src="' . esc_url( $image['sizes']['thumbnail'] ) . '" alt="' . esc_attr( $image['alt'] ) . '">';
    }
    echo '</div>';
}
```

### Link Field
```php
$link = get_field( 'link_field' );
if ( $link ) {
    $url = $link['url'];
    $title = $link['title'];
    $target = $link['target'] ? $link['target'] : '_self';

    echo '<a href="' . esc_url( $url ) . '" target="' . esc_attr( $target ) . '">' . esc_html( $title ) . '</a>';
}
```

### Post Object Field
```php
// Single post
$post = get_field( 'post_object_field' );
if ( $post ) {
    // $post is a WP_Post object
    echo '<a href="' . esc_url( get_permalink( $post->ID ) ) . '">' . esc_html( $post->post_title ) . '</a>';
}

// Multiple posts
$posts = get_field( 'post_object_field' );
if ( $posts ) {
    foreach ( $posts as $post ) {
        setup_postdata( $post );
        the_title();
        the_excerpt();
    }
    wp_reset_postdata();
}
```

### Relationship Field
```php
$related_posts = get_field( 'relationship_field' );
if ( $related_posts ) {
    foreach ( $related_posts as $post ) {
        setup_postdata( $post );
        the_title();
        the_permalink();
    }
    wp_reset_postdata();
}
```

### Taxonomy Field
```php
// Single term
$term = get_field( 'taxonomy_field' );
if ( $term ) {
    echo '<a href="' . esc_url( get_term_link( $term ) ) . '">' . esc_html( $term->name ) . '</a>';
}

// Multiple terms
$terms = get_field( 'taxonomy_field' );
if ( $terms ) {
    foreach ( $terms as $term ) {
        echo esc_html( $term->name );
    }
}
```

### User Field
```php
$user = get_field( 'user_field' );
if ( $user ) {
    echo esc_html( $user['display_name'] );
    echo esc_html( $user['user_email'] );
    echo get_avatar( $user['ID'], 96 );
}
```

### Date/Time Fields
```php
// Date Picker
$date = get_field( 'date_field' );
if ( $date ) {
    // Returns in format set in field settings
    echo esc_html( $date );

    // Or convert to different format
    $date_obj = DateTime::createFromFormat( 'd/m/Y', $date );
    echo $date_obj->format( 'F j, Y' );
}

// Date Time Picker
$datetime = get_field( 'datetime_field' );

// Time Picker
$time = get_field( 'time_field' );
```

### Google Map Field
```php
$location = get_field( 'map_field' );
if ( $location ) {
    $lat = $location['lat'];
    $lng = $location['lng'];
    $address = $location['address'];

    // Use with Google Maps API
}
```

## Repeater Field

```php
// Check and loop
if ( have_rows( 'repeater_field' ) ) {
    while ( have_rows( 'repeater_field' ) ) {
        the_row();

        // Get sub field values
        $title = get_sub_field( 'title' );
        $content = get_sub_field( 'content' );
        $image = get_sub_field( 'image' );

        echo '<div class="item">';
        echo '<h3>' . esc_html( $title ) . '</h3>';
        echo wp_kses_post( $content );
        if ( $image ) {
            echo wp_get_attachment_image( $image, 'medium' );
        }
        echo '</div>';
    }
}

// Get all rows as array
$rows = get_field( 'repeater_field' );
if ( $rows ) {
    foreach ( $rows as $row ) {
        echo esc_html( $row['title'] );
    }
}

// Get specific row
$first_row = get_field( 'repeater_field' )[0];

// Count rows
$count = count( get_field( 'repeater_field' ) );
```

## Flexible Content Field

```php
if ( have_rows( 'flexible_content' ) ) {
    while ( have_rows( 'flexible_content' ) ) {
        the_row();

        // Get layout name
        $layout = get_row_layout();

        // Load different templates based on layout
        if ( $layout === 'text_block' ) {
            $text = get_sub_field( 'text' );
            echo '<div class="text-block">' . wp_kses_post( $text ) . '</div>';
        } elseif ( $layout === 'image_gallery' ) {
            $images = get_sub_field( 'gallery' );
            // Display gallery
        } elseif ( $layout === 'call_to_action' ) {
            $title = get_sub_field( 'title' );
            $link = get_sub_field( 'link' );
            // Display CTA
        }

        // Or use template parts
        get_template_part( 'template-parts/blocks/' . $layout );
    }
}
```

## Group Field

```php
$group = get_field( 'group_field' );
if ( $group ) {
    $title = $group['title'];
    $description = $group['description'];
    $image = $group['image'];
}

// Or use sub fields directly
if ( have_rows( 'group_field' ) ) {
    while ( have_rows( 'group_field' ) ) {
        the_row();
        $title = get_sub_field( 'title' );
    }
}
```

## Clone Field

```php
// Clone fields are accessed like regular fields
$cloned_value = get_field( 'cloned_field_name' );

// If prefixed with group name
$value = get_field( 'group_name_field_name' );
```

## Options Pages

### Register Options Page
```php
if ( function_exists( 'acf_add_options_page' ) ) {
    // Main options page
    acf_add_options_page( array(
        'page_title' => __( 'Theme Options', 'my-theme' ),
        'menu_title' => __( 'Theme Options', 'my-theme' ),
        'menu_slug'  => 'theme-options',
        'capability' => 'manage_options',
        'redirect'   => false,
        'icon_url'   => 'dashicons-admin-generic',
        'position'   => 30,
    ) );

    // Sub page
    acf_add_options_sub_page( array(
        'page_title'  => __( 'Header Settings', 'my-theme' ),
        'menu_title'  => __( 'Header', 'my-theme' ),
        'parent_slug' => 'theme-options',
    ) );
}

// Get option field
$logo = get_field( 'site_logo', 'option' );
$phone = get_field( 'contact_phone', 'option' );
```

## Registering Fields with PHP

```php
if ( function_exists( 'acf_add_local_field_group' ) ) {
    acf_add_local_field_group( array(
        'key' => 'group_hero_section',
        'title' => 'Hero Section',
        'fields' => array(
            array(
                'key' => 'field_hero_title',
                'label' => 'Title',
                'name' => 'hero_title',
                'type' => 'text',
                'required' => 1,
            ),
            array(
                'key' => 'field_hero_image',
                'label' => 'Background Image',
                'name' => 'hero_image',
                'type' => 'image',
                'return_format' => 'array',
                'preview_size' => 'medium',
            ),
            array(
                'key' => 'field_hero_buttons',
                'label' => 'Buttons',
                'name' => 'hero_buttons',
                'type' => 'repeater',
                'layout' => 'table',
                'sub_fields' => array(
                    array(
                        'key' => 'field_button_text',
                        'label' => 'Button Text',
                        'name' => 'button_text',
                        'type' => 'text',
                    ),
                    array(
                        'key' => 'field_button_link',
                        'label' => 'Button Link',
                        'name' => 'button_link',
                        'type' => 'link',
                    ),
                ),
            ),
        ),
        'location' => array(
            array(
                array(
                    'param' => 'page_template',
                    'operator' => '==',
                    'value' => 'templates/home.php',
                ),
            ),
        ),
    ) );
}
```

## ACF Blocks (Gutenberg)

### Register Block
```php
add_action( 'acf/init', 'register_acf_blocks' );

function register_acf_blocks() {
    if ( function_exists( 'acf_register_block_type' ) ) {
        acf_register_block_type( array(
            'name'            => 'testimonial',
            'title'           => __( 'Testimonial', 'my-theme' ),
            'description'     => __( 'A custom testimonial block.', 'my-theme' ),
            'render_template' => 'template-parts/blocks/testimonial.php',
            'render_callback' => 'render_testimonial_block', // Or use callback
            'category'        => 'formatting',
            'icon'            => 'format-quote',
            'keywords'        => array( 'testimonial', 'quote' ),
            'supports'        => array(
                'align' => true,
                'mode'  => false, // Disable preview/edit mode toggle
                'jsx'   => true,  // Enable InnerBlocks
            ),
            'example'         => array( // Preview data
                'attributes' => array(
                    'mode' => 'preview',
                    'data' => array(
                        'testimonial' => 'This is a sample testimonial.',
                        'author'      => 'John Doe',
                    ),
                ),
            ),
        ) );
    }
}

// Block template (template-parts/blocks/testimonial.php)
<?php
$testimonial = get_field( 'testimonial' );
$author = get_field( 'author' );
$image = get_field( 'image' );

$class_name = 'testimonial-block';
if ( ! empty( $block['className'] ) ) {
    $class_name .= ' ' . $block['className'];
}
if ( ! empty( $block['align'] ) ) {
    $class_name .= ' align' . $block['align'];
}
?>

<div class="<?php echo esc_attr( $class_name ); ?>">
    <blockquote>
        <?php echo wp_kses_post( $testimonial ); ?>
    </blockquote>
    <cite><?php echo esc_html( $author ); ?></cite>
</div>
```

## Querying by ACF Fields

```php
// Meta query
$args = array(
    'post_type'  => 'post',
    'meta_query' => array(
        array(
            'key'     => 'featured',
            'value'   => '1',
            'compare' => '=',
        ),
    ),
);

// Multiple conditions
$args = array(
    'post_type'  => 'event',
    'meta_query' => array(
        'relation' => 'AND',
        array(
            'key'     => 'event_date',
            'value'   => date( 'Y-m-d' ),
            'compare' => '>=',
            'type'    => 'DATE',
        ),
        array(
            'key'     => 'event_type',
            'value'   => 'conference',
            'compare' => '=',
        ),
    ),
);
```

## Best Practices

1. **Always escape output** based on field type
2. **Check if field exists** before using
3. **Use appropriate return format** for your needs
4. **Sync field groups to JSON** for version control
5. **Prefix field names** to avoid conflicts
6. **Use conditional logic** sparingly for performance
7. **Keep repeater rows reasonable** in number
8. **Register fields in PHP** for deployment consistency
