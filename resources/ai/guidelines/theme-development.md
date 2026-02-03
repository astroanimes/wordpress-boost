# WordPress Theme Development Guidelines

## Theme Header (style.css)

```css
/*
Theme Name: My Theme
Theme URI: https://example.com/my-theme
Author: Your Name
Author URI: https://example.com
Description: A custom WordPress theme
Version: 1.0.0
Requires at least: 6.0
Tested up to: 6.4
Requires PHP: 7.4
License: GNU General Public License v2 or later
License URI: http://www.gnu.org/licenses/gpl-2.0.html
Text Domain: my-theme
Tags: custom-logo, custom-menu, featured-images, threaded-comments
*/
```

## Theme Setup (functions.php)

```php
<?php
/**
 * Theme functions and definitions
 */

if ( ! defined( 'MY_THEME_VERSION' ) ) {
    define( 'MY_THEME_VERSION', '1.0.0' );
}

/**
 * Theme setup
 */
function my_theme_setup() {
    // Make theme available for translation
    load_theme_textdomain( 'my-theme', get_template_directory() . '/languages' );

    // Add default posts and comments RSS feed links
    add_theme_support( 'automatic-feed-links' );

    // Let WordPress manage the document title
    add_theme_support( 'title-tag' );

    // Enable support for Post Thumbnails
    add_theme_support( 'post-thumbnails' );
    set_post_thumbnail_size( 1200, 9999 );
    add_image_size( 'my-theme-featured', 800, 450, true );

    // Register nav menus
    register_nav_menus( array(
        'primary' => __( 'Primary Menu', 'my-theme' ),
        'footer'  => __( 'Footer Menu', 'my-theme' ),
    ) );

    // HTML5 markup support
    add_theme_support( 'html5', array(
        'search-form',
        'comment-form',
        'comment-list',
        'gallery',
        'caption',
        'style',
        'script',
    ) );

    // Custom logo
    add_theme_support( 'custom-logo', array(
        'height'      => 100,
        'width'       => 400,
        'flex-width'  => true,
        'flex-height' => true,
    ) );

    // Custom background
    add_theme_support( 'custom-background', array(
        'default-color' => 'ffffff',
    ) );

    // Block editor support
    add_theme_support( 'wp-block-styles' );
    add_theme_support( 'align-wide' );
    add_theme_support( 'responsive-embeds' );

    // Editor styles
    add_theme_support( 'editor-styles' );
    add_editor_style( 'assets/css/editor-style.css' );
}
add_action( 'after_setup_theme', 'my_theme_setup' );

/**
 * Register widget areas
 */
function my_theme_widgets_init() {
    register_sidebar( array(
        'name'          => __( 'Sidebar', 'my-theme' ),
        'id'            => 'sidebar-1',
        'description'   => __( 'Add widgets here.', 'my-theme' ),
        'before_widget' => '<section id="%1$s" class="widget %2$s">',
        'after_widget'  => '</section>',
        'before_title'  => '<h2 class="widget-title">',
        'after_title'   => '</h2>',
    ) );

    register_sidebar( array(
        'name'          => __( 'Footer', 'my-theme' ),
        'id'            => 'footer-1',
        'description'   => __( 'Footer widget area.', 'my-theme' ),
        'before_widget' => '<div id="%1$s" class="widget %2$s">',
        'after_widget'  => '</div>',
        'before_title'  => '<h3 class="widget-title">',
        'after_title'   => '</h3>',
    ) );
}
add_action( 'widgets_init', 'my_theme_widgets_init' );

/**
 * Enqueue scripts and styles
 */
function my_theme_scripts() {
    // Main stylesheet
    wp_enqueue_style(
        'my-theme-style',
        get_stylesheet_uri(),
        array(),
        MY_THEME_VERSION
    );

    // Additional CSS
    wp_enqueue_style(
        'my-theme-main',
        get_template_directory_uri() . '/assets/css/main.css',
        array(),
        MY_THEME_VERSION
    );

    // Main script
    wp_enqueue_script(
        'my-theme-script',
        get_template_directory_uri() . '/assets/js/main.js',
        array( 'jquery' ),
        MY_THEME_VERSION,
        true // Load in footer
    );

    // Localize script
    wp_localize_script( 'my-theme-script', 'myTheme', array(
        'ajaxUrl' => admin_url( 'admin-ajax.php' ),
        'nonce'   => wp_create_nonce( 'my_theme_nonce' ),
    ) );

    // Comment reply script
    if ( is_singular() && comments_open() && get_option( 'thread_comments' ) ) {
        wp_enqueue_script( 'comment-reply' );
    }
}
add_action( 'wp_enqueue_scripts', 'my_theme_scripts' );
```

## Template Hierarchy

Understanding the template hierarchy is crucial:

```
Front Page:    front-page.php → home.php → index.php
Home (Blog):   home.php → index.php
Single Post:   single-{post-type}-{slug}.php → single-{post-type}.php → single.php → singular.php → index.php
Page:          {custom-template}.php → page-{slug}.php → page-{id}.php → page.php → singular.php → index.php
Category:      category-{slug}.php → category-{id}.php → category.php → archive.php → index.php
Tag:           tag-{slug}.php → tag-{id}.php → tag.php → archive.php → index.php
Taxonomy:      taxonomy-{taxonomy}-{term}.php → taxonomy-{taxonomy}.php → taxonomy.php → archive.php → index.php
Author:        author-{nicename}.php → author-{id}.php → author.php → archive.php → index.php
Date:          date.php → archive.php → index.php
Archive:       archive-{post-type}.php → archive.php → index.php
Search:        search.php → index.php
404:           404.php → index.php
Attachment:    {mimetype}-{subtype}.php → {subtype}.php → {mimetype}.php → attachment.php → single.php → index.php
```

## The Loop

```php
<?php if ( have_posts() ) : ?>

    <?php while ( have_posts() ) : the_post(); ?>

        <article id="post-<?php the_ID(); ?>" <?php post_class(); ?>>
            <header class="entry-header">
                <?php the_title( '<h1 class="entry-title">', '</h1>' ); ?>
            </header>

            <?php if ( has_post_thumbnail() ) : ?>
                <div class="entry-thumbnail">
                    <?php the_post_thumbnail( 'large' ); ?>
                </div>
            <?php endif; ?>

            <div class="entry-content">
                <?php the_content(); ?>
            </div>

            <footer class="entry-footer">
                <?php
                the_category( ', ' );
                the_tags( '<span class="tags">', ', ', '</span>' );
                ?>
            </footer>
        </article>

    <?php endwhile; ?>

    <?php the_posts_pagination(); ?>

<?php else : ?>

    <p><?php esc_html_e( 'No posts found.', 'my-theme' ); ?></p>

<?php endif; ?>
```

## Template Parts

```php
// Include template part
get_template_part( 'template-parts/content', get_post_type() );

// With arguments (WP 5.5+)
get_template_part( 'template-parts/card', 'post', array(
    'show_author' => true,
    'show_date'   => true,
) );

// In template part, access args:
$show_author = $args['show_author'] ?? false;
```

## Child Themes

### Child Theme style.css
```css
/*
Theme Name: My Theme Child
Template: my-theme
Version: 1.0.0
*/
```

### Child Theme functions.php
```php
<?php
function my_child_theme_enqueue_styles() {
    wp_enqueue_style(
        'parent-style',
        get_template_directory_uri() . '/style.css'
    );
    wp_enqueue_style(
        'child-style',
        get_stylesheet_uri(),
        array( 'parent-style' ),
        wp_get_theme()->get( 'Version' )
    );
}
add_action( 'wp_enqueue_scripts', 'my_child_theme_enqueue_styles' );
```

### Path Functions
```php
// Parent theme
get_template_directory()       // Path: /wp-content/themes/parent-theme
get_template_directory_uri()   // URL: https://site.com/wp-content/themes/parent-theme

// Active theme (child if active, otherwise parent)
get_stylesheet_directory()     // Path
get_stylesheet_directory_uri() // URL
```

## Customizer API

```php
function my_theme_customize_register( $wp_customize ) {
    // Add section
    $wp_customize->add_section( 'my_theme_options', array(
        'title'    => __( 'Theme Options', 'my-theme' ),
        'priority' => 30,
    ) );

    // Add setting
    $wp_customize->add_setting( 'my_theme_color', array(
        'default'           => '#0073aa',
        'sanitize_callback' => 'sanitize_hex_color',
        'transport'         => 'postMessage',
    ) );

    // Add control
    $wp_customize->add_control( new WP_Customize_Color_Control(
        $wp_customize,
        'my_theme_color',
        array(
            'label'    => __( 'Primary Color', 'my-theme' ),
            'section'  => 'my_theme_options',
            'settings' => 'my_theme_color',
        )
    ) );

    // Selective refresh
    $wp_customize->selective_refresh->add_partial( 'blogname', array(
        'selector'        => '.site-title a',
        'render_callback' => function() {
            bloginfo( 'name' );
        },
    ) );
}
add_action( 'customize_register', 'my_theme_customize_register' );

// Use customizer value
$color = get_theme_mod( 'my_theme_color', '#0073aa' );
```

## Block Theme Support (theme.json)

```json
{
    "$schema": "https://schemas.wp.org/trunk/theme.json",
    "version": 2,
    "settings": {
        "color": {
            "palette": [
                {
                    "slug": "primary",
                    "color": "#0073aa",
                    "name": "Primary"
                },
                {
                    "slug": "secondary",
                    "color": "#23282d",
                    "name": "Secondary"
                }
            ]
        },
        "typography": {
            "fontSizes": [
                {
                    "slug": "small",
                    "size": "14px",
                    "name": "Small"
                },
                {
                    "slug": "medium",
                    "size": "18px",
                    "name": "Medium"
                }
            ]
        },
        "layout": {
            "contentSize": "800px",
            "wideSize": "1200px"
        }
    },
    "styles": {
        "color": {
            "background": "#ffffff",
            "text": "#333333"
        }
    }
}
```

## Best Practices

### Performance
- Minify CSS/JS in production
- Use conditional loading for scripts
- Optimize images
- Leverage browser caching
- Use lazy loading for images

### Accessibility
- Use semantic HTML
- Provide skip links
- Ensure keyboard navigation
- Add ARIA labels where needed
- Maintain color contrast ratios

### Security
- Escape all output
- Validate and sanitize input
- Use nonces for forms
- Prefix function names

### Code Organization
- Keep functions.php clean - use includes
- Use template parts for reusable code
- Follow WordPress coding standards
- Comment complex code
