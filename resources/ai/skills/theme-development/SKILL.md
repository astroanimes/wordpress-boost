# Skill: WordPress Theme Development

## Description
Expert guidance for creating and customizing WordPress themes, including classic themes and block themes.

## When to Use
- User asks to create a new WordPress theme
- User needs help with template hierarchy
- User wants to customize theme appearance
- User needs to add theme features (menus, widgets, customizer)
- User wants to create a child theme
- User needs help with theme.json configuration

## WordPress Boost Tools to Use
```
- site_info: Get current theme information
- list_themes: See all installed themes
- template_hierarchy: Understand template loading
- list_hooks: Find theme-related hooks
- wp_shell: Test theme functions
```

## Key Concepts

### Essential functions.php Setup
```php
function mytheme_setup() {
    add_theme_support( 'title-tag' );
    add_theme_support( 'post-thumbnails' );
    add_theme_support( 'html5', array( 'search-form', 'comment-form', 'comment-list', 'gallery', 'caption' ) );
    add_theme_support( 'custom-logo' );
    add_theme_support( 'wp-block-styles' );
    add_theme_support( 'align-wide' );
    add_theme_support( 'editor-styles' );

    register_nav_menus( array(
        'primary' => __( 'Primary Menu', 'mytheme' ),
        'footer'  => __( 'Footer Menu', 'mytheme' ),
    ) );
}
add_action( 'after_setup_theme', 'mytheme_setup' );
```

### Child Theme Creation
```css
/* style.css */
/*
Theme Name: Parent Theme Child
Template: parent-theme-folder-name
*/
```

```php
/* functions.php */
add_action( 'wp_enqueue_scripts', function() {
    wp_enqueue_style( 'parent-style', get_template_directory_uri() . '/style.css' );
});
```

## Common Tasks

### 1. Add Custom Logo Support
```php
add_theme_support( 'custom-logo', array(
    'height'      => 100,
    'width'       => 400,
    'flex-width'  => true,
    'flex-height' => true,
) );

// Display in template
if ( has_custom_logo() ) {
    the_custom_logo();
}
```

### 2. Register Widget Areas
```php
register_sidebar( array(
    'name'          => 'Sidebar',
    'id'            => 'sidebar-1',
    'before_widget' => '<section class="widget %2$s">',
    'after_widget'  => '</section>',
    'before_title'  => '<h2 class="widget-title">',
    'after_title'   => '</h2>',
) );
```

### 3. Enqueue Scripts/Styles
```php
add_action( 'wp_enqueue_scripts', function() {
    wp_enqueue_style( 'mytheme-style', get_stylesheet_uri(), array(), '1.0.0' );
    wp_enqueue_script( 'mytheme-script', get_template_directory_uri() . '/js/main.js', array('jquery'), '1.0.0', true );
});
```

### 4. Add Customizer Options
```php
add_action( 'customize_register', function( $wp_customize ) {
    $wp_customize->add_section( 'mytheme_options', array(
        'title' => 'Theme Options',
    ) );

    $wp_customize->add_setting( 'accent_color', array(
        'default' => '#0073aa',
        'sanitize_callback' => 'sanitize_hex_color',
    ) );

    $wp_customize->add_control( new WP_Customize_Color_Control(
        $wp_customize, 'accent_color', array(
            'label'   => 'Accent Color',
            'section' => 'mytheme_options',
        )
    ) );
});
```

## Template Hierarchy Quick Reference
- **Front Page**: front-page.php → home.php → index.php
- **Single Post**: single-{type}-{slug}.php → single-{type}.php → single.php → singular.php → index.php
- **Page**: page-{slug}.php → page-{id}.php → page.php → singular.php → index.php
- **Archive**: archive-{type}.php → archive.php → index.php
- **Category**: category-{slug}.php → category-{id}.php → category.php → archive.php → index.php
- **Search**: search.php → index.php
- **404**: 404.php → index.php

## Checklist
- [ ] style.css has required header fields
- [ ] index.php exists (required)
- [ ] Theme supports title-tag
- [ ] Theme supports post-thumbnails
- [ ] Scripts/styles properly enqueued
- [ ] Text domain set for translations
- [ ] Escaping used for all output
- [ ] Screenshot provided (1200x900)
