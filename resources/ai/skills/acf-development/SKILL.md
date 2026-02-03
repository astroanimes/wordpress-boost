# Skill: Advanced Custom Fields (ACF) Development

## Description
Expert guidance for creating and using ACF field groups, including repeaters, flexible content, blocks, and options pages.

## When to Use
- User wants to add custom fields to posts/pages
- User needs repeater or flexible content fields
- User wants to create ACF blocks for Gutenberg
- User needs to set up options pages
- User wants to query posts by ACF field values
- User needs to register fields programmatically

## WordPress Boost Tools to Use
```
- acf_field_groups: List all ACF field groups
- acf_fields: Get fields in a group
- wp_shell: Test ACF functions
- list_post_types: Check where fields are assigned
```

## Key Concepts

### Getting Field Values
```php
// From current post
$value = get_field( 'field_name' );

// From specific post
$value = get_field( 'field_name', $post_id );

// From options page
$value = get_field( 'field_name', 'option' );

// From user
$value = get_field( 'field_name', 'user_' . $user_id );

// From taxonomy term
$value = get_field( 'field_name', 'term_' . $term_id );
```

### Always Check Before Output
```php
$value = get_field( 'text_field' );
if ( $value ) {
    echo esc_html( $value );
}
```

## Common Tasks

### 1. Display Image Field
```php
// Return format: Array
$image = get_field( 'image' );
if ( $image ) {
    echo '<img src="' . esc_url( $image['sizes']['medium'] ) . '" alt="' . esc_attr( $image['alt'] ) . '">';
}

// Return format: ID
$image_id = get_field( 'image' );
if ( $image_id ) {
    echo wp_get_attachment_image( $image_id, 'large' );
}
```

### 2. Loop Repeater Field
```php
if ( have_rows( 'team_members' ) ) {
    while ( have_rows( 'team_members' ) ) {
        the_row();

        $name = get_sub_field( 'name' );
        $photo = get_sub_field( 'photo' );
        $bio = get_sub_field( 'bio' );

        echo '<div class="team-member">';
        echo '<h3>' . esc_html( $name ) . '</h3>';
        if ( $photo ) {
            echo wp_get_attachment_image( $photo, 'thumbnail' );
        }
        echo wp_kses_post( $bio );
        echo '</div>';
    }
}
```

### 3. Flexible Content Field
```php
if ( have_rows( 'page_sections' ) ) {
    while ( have_rows( 'page_sections' ) ) {
        the_row();
        $layout = get_row_layout();

        if ( $layout === 'hero' ) {
            $title = get_sub_field( 'title' );
            $image = get_sub_field( 'background' );
            // Output hero section
        } elseif ( $layout === 'text_block' ) {
            $content = get_sub_field( 'content' );
            // Output text block
        } elseif ( $layout === 'gallery' ) {
            $images = get_sub_field( 'images' );
            // Output gallery
        }

        // Or use template parts
        get_template_part( 'template-parts/acf/' . $layout );
    }
}
```

### 4. Link Field
```php
$link = get_field( 'button_link' );
if ( $link ) {
    $url = $link['url'];
    $title = $link['title'];
    $target = $link['target'] ?: '_self';

    echo '<a href="' . esc_url( $url ) . '" target="' . esc_attr( $target ) . '">';
    echo esc_html( $title );
    echo '</a>';
}
```

### 5. Relationship/Post Object
```php
$posts = get_field( 'related_posts' );
if ( $posts ) {
    foreach ( $posts as $post ) {
        setup_postdata( $post );
        echo '<a href="' . get_permalink() . '">' . get_the_title() . '</a>';
    }
    wp_reset_postdata();
}
```

### 6. Register Options Page
```php
if ( function_exists( 'acf_add_options_page' ) ) {
    acf_add_options_page( array(
        'page_title' => 'Site Settings',
        'menu_title' => 'Site Settings',
        'menu_slug' => 'site-settings',
        'capability' => 'manage_options',
        'icon_url' => 'dashicons-admin-settings',
    ));

    acf_add_options_sub_page( array(
        'page_title' => 'Header Settings',
        'menu_title' => 'Header',
        'parent_slug' => 'site-settings',
    ));
}
```

### 7. Register ACF Block
```php
add_action( 'acf/init', function() {
    if ( function_exists( 'acf_register_block_type' ) ) {
        acf_register_block_type( array(
            'name' => 'testimonial',
            'title' => 'Testimonial',
            'description' => 'A testimonial block.',
            'render_template' => 'template-parts/blocks/testimonial.php',
            'category' => 'formatting',
            'icon' => 'format-quote',
            'keywords' => array( 'testimonial', 'quote' ),
            'supports' => array( 'align' => true ),
        ));
    }
});
```

Block Template (`template-parts/blocks/testimonial.php`):
```php
<?php
$quote = get_field( 'quote' );
$author = get_field( 'author' );
$class = 'testimonial-block';
if ( ! empty( $block['className'] ) ) {
    $class .= ' ' . $block['className'];
}
?>
<div class="<?php echo esc_attr( $class ); ?>">
    <blockquote><?php echo wp_kses_post( $quote ); ?></blockquote>
    <cite><?php echo esc_html( $author ); ?></cite>
</div>
```

### 8. Register Fields with PHP
```php
if ( function_exists( 'acf_add_local_field_group' ) ) {
    acf_add_local_field_group( array(
        'key' => 'group_hero',
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
                'label' => 'Background',
                'name' => 'hero_image',
                'type' => 'image',
                'return_format' => 'id',
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
    ));
}
```

### 9. Query by ACF Field
```php
$args = array(
    'post_type' => 'event',
    'meta_query' => array(
        array(
            'key' => 'event_date',
            'value' => date( 'Y-m-d' ),
            'compare' => '>=',
            'type' => 'DATE',
        ),
    ),
    'orderby' => 'meta_value',
    'meta_key' => 'event_date',
    'order' => 'ASC',
);
$events = new WP_Query( $args );
```

## Field Return Formats

| Field Type | Array | ID | URL |
|------------|-------|----|----|
| Image | Full data array | Attachment ID | Direct URL |
| File | Full data array | Attachment ID | Direct URL |
| Gallery | Array of arrays | Array of IDs | N/A |

## Checklist
- [ ] Check field exists before output
- [ ] Escape output appropriately
- [ ] Use correct return format for needs
- [ ] Reset postdata after relationship loops
- [ ] Consider null/empty states
- [ ] Sync fields to JSON for version control
