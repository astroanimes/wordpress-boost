# Gutenberg Block Development Guidelines

## Block Registration (block.json)

The recommended way to register blocks is using `block.json`:

```json
{
    "$schema": "https://schemas.wp.org/trunk/block.json",
    "apiVersion": 3,
    "name": "my-plugin/my-block",
    "version": "1.0.0",
    "title": "My Block",
    "category": "widgets",
    "icon": "smiley",
    "description": "A custom block for displaying content.",
    "keywords": ["custom", "content"],
    "textdomain": "my-plugin",
    "attributes": {
        "content": {
            "type": "string",
            "source": "html",
            "selector": "p"
        },
        "alignment": {
            "type": "string",
            "default": "none"
        }
    },
    "supports": {
        "html": false,
        "align": ["wide", "full"],
        "color": {
            "background": true,
            "text": true
        },
        "typography": {
            "fontSize": true
        },
        "spacing": {
            "margin": true,
            "padding": true
        }
    },
    "editorScript": "file:./index.js",
    "editorStyle": "file:./index.css",
    "style": "file:./style-index.css",
    "render": "file:./render.php",
    "viewScript": "file:./view.js"
}
```

## PHP Block Registration

```php
// Register block from block.json
add_action( 'init', 'my_plugin_register_blocks' );

function my_plugin_register_blocks() {
    register_block_type( __DIR__ . '/blocks/my-block' );
}

// Register block with PHP callback
register_block_type( 'my-plugin/dynamic-block', array(
    'api_version'     => 3,
    'editor_script'   => 'my-block-editor',
    'render_callback' => 'render_my_dynamic_block',
    'attributes'      => array(
        'count' => array(
            'type'    => 'number',
            'default' => 5,
        ),
    ),
) );

function render_my_dynamic_block( $attributes, $content, $block ) {
    $count = $attributes['count'];

    $posts = get_posts( array(
        'posts_per_page' => $count,
        'post_status'    => 'publish',
    ) );

    ob_start();
    ?>
    <div <?php echo get_block_wrapper_attributes(); ?>>
        <ul>
            <?php foreach ( $posts as $post ) : ?>
                <li>
                    <a href="<?php echo esc_url( get_permalink( $post ) ); ?>">
                        <?php echo esc_html( $post->post_title ); ?>
                    </a>
                </li>
            <?php endforeach; ?>
        </ul>
    </div>
    <?php
    return ob_get_clean();
}
```

## JavaScript Block Structure

### Basic Block (index.js)
```js
import { registerBlockType } from '@wordpress/blocks';
import { useBlockProps, RichText, InspectorControls } from '@wordpress/block-editor';
import { PanelBody, TextControl, ToggleControl, SelectControl } from '@wordpress/components';
import { __ } from '@wordpress/i18n';

import './style.scss';
import './editor.scss';

registerBlockType( 'my-plugin/my-block', {
    edit: Edit,
    save: Save,
} );

function Edit( { attributes, setAttributes } ) {
    const { content, alignment, showBorder } = attributes;
    const blockProps = useBlockProps( {
        className: `align-${ alignment }`,
    } );

    return (
        <>
            <InspectorControls>
                <PanelBody title={ __( 'Settings', 'my-plugin' ) }>
                    <SelectControl
                        label={ __( 'Alignment', 'my-plugin' ) }
                        value={ alignment }
                        options={ [
                            { label: __( 'None', 'my-plugin' ), value: 'none' },
                            { label: __( 'Left', 'my-plugin' ), value: 'left' },
                            { label: __( 'Center', 'my-plugin' ), value: 'center' },
                            { label: __( 'Right', 'my-plugin' ), value: 'right' },
                        ] }
                        onChange={ ( value ) => setAttributes( { alignment: value } ) }
                    />
                    <ToggleControl
                        label={ __( 'Show Border', 'my-plugin' ) }
                        checked={ showBorder }
                        onChange={ ( value ) => setAttributes( { showBorder: value } ) }
                    />
                </PanelBody>
            </InspectorControls>
            <div { ...blockProps }>
                <RichText
                    tagName="p"
                    value={ content }
                    onChange={ ( value ) => setAttributes( { content: value } ) }
                    placeholder={ __( 'Enter content...', 'my-plugin' ) }
                />
            </div>
        </>
    );
}

function Save( { attributes } ) {
    const { content, alignment } = attributes;
    const blockProps = useBlockProps.save( {
        className: `align-${ alignment }`,
    } );

    return (
        <div { ...blockProps }>
            <RichText.Content tagName="p" value={ content } />
        </div>
    );
}
```

## Common Block Components

### Media Upload
```js
import { MediaUpload, MediaUploadCheck } from '@wordpress/block-editor';
import { Button } from '@wordpress/components';

<MediaUploadCheck>
    <MediaUpload
        onSelect={ ( media ) => setAttributes( { imageId: media.id, imageUrl: media.url } ) }
        allowedTypes={ [ 'image' ] }
        value={ imageId }
        render={ ( { open } ) => (
            <Button onClick={ open } variant="primary">
                { imageUrl ? __( 'Replace Image', 'my-plugin' ) : __( 'Select Image', 'my-plugin' ) }
            </Button>
        ) }
    />
</MediaUploadCheck>
```

### Color Settings
```js
import { InspectorControls, PanelColorSettings } from '@wordpress/block-editor';

<InspectorControls>
    <PanelColorSettings
        title={ __( 'Color Settings', 'my-plugin' ) }
        colorSettings={ [
            {
                value: backgroundColor,
                onChange: ( value ) => setAttributes( { backgroundColor: value } ),
                label: __( 'Background Color', 'my-plugin' ),
            },
            {
                value: textColor,
                onChange: ( value ) => setAttributes( { textColor: value } ),
                label: __( 'Text Color', 'my-plugin' ),
            },
        ] }
    />
</InspectorControls>
```

### Inner Blocks
```js
import { InnerBlocks, useBlockProps } from '@wordpress/block-editor';

function Edit() {
    const blockProps = useBlockProps();
    const ALLOWED_BLOCKS = [ 'core/paragraph', 'core/image', 'core/heading' ];
    const TEMPLATE = [
        [ 'core/heading', { placeholder: 'Enter heading...' } ],
        [ 'core/paragraph', { placeholder: 'Enter content...' } ],
    ];

    return (
        <div { ...blockProps }>
            <InnerBlocks
                allowedBlocks={ ALLOWED_BLOCKS }
                template={ TEMPLATE }
                templateLock={ false } // 'all', 'insert', or false
            />
        </div>
    );
}

function Save() {
    const blockProps = useBlockProps.save();
    return (
        <div { ...blockProps }>
            <InnerBlocks.Content />
        </div>
    );
}
```

### URL Input
```js
import { URLInput } from '@wordpress/block-editor';

<URLInput
    value={ url }
    onChange={ ( value ) => setAttributes( { url: value } ) }
    placeholder={ __( 'Enter URL...', 'my-plugin' ) }
/>
```

## Block Attributes

### Attribute Types
```json
{
    "attributes": {
        "text": {
            "type": "string",
            "default": ""
        },
        "number": {
            "type": "number",
            "default": 0
        },
        "boolean": {
            "type": "boolean",
            "default": false
        },
        "array": {
            "type": "array",
            "default": [],
            "items": {
                "type": "string"
            }
        },
        "object": {
            "type": "object",
            "default": {}
        },
        "richText": {
            "type": "string",
            "source": "html",
            "selector": ".content"
        },
        "imageId": {
            "type": "number"
        },
        "imageUrl": {
            "type": "string",
            "source": "attribute",
            "selector": "img",
            "attribute": "src"
        }
    }
}
```

### Attribute Sources
```json
{
    "content": {
        "type": "string",
        "source": "html",
        "selector": "p"
    },
    "url": {
        "type": "string",
        "source": "attribute",
        "selector": "a",
        "attribute": "href"
    },
    "title": {
        "type": "string",
        "source": "text",
        "selector": "h2"
    },
    "items": {
        "type": "array",
        "source": "query",
        "selector": "li",
        "query": {
            "text": {
                "type": "string",
                "source": "text"
            }
        }
    }
}
```

## Block Supports

```json
{
    "supports": {
        "align": true,
        "align": ["wide", "full"],
        "anchor": true,
        "className": true,
        "color": {
            "background": true,
            "text": true,
            "link": true,
            "gradients": true
        },
        "customClassName": true,
        "html": false,
        "inserter": true,
        "multiple": true,
        "reusable": true,
        "spacing": {
            "margin": true,
            "padding": true,
            "blockGap": true
        },
        "typography": {
            "fontSize": true,
            "lineHeight": true,
            "fontFamily": true
        }
    }
}
```

## Dynamic Blocks with PHP Render

### render.php
```php
<?php
/**
 * Block render template
 *
 * @var array    $attributes Block attributes.
 * @var string   $content    Block content.
 * @var WP_Block $block      Block instance.
 */

$count = $attributes['count'] ?? 5;
$category = $attributes['category'] ?? '';

$args = array(
    'posts_per_page' => $count,
    'post_status'    => 'publish',
);

if ( $category ) {
    $args['category_name'] = $category;
}

$posts = get_posts( $args );
$wrapper_attributes = get_block_wrapper_attributes( array(
    'class' => 'my-posts-block',
) );
?>

<div <?php echo $wrapper_attributes; ?>>
    <?php if ( $posts ) : ?>
        <ul class="posts-list">
            <?php foreach ( $posts as $post ) : ?>
                <li>
                    <a href="<?php echo esc_url( get_permalink( $post ) ); ?>">
                        <?php echo esc_html( $post->post_title ); ?>
                    </a>
                </li>
            <?php endforeach; ?>
        </ul>
    <?php else : ?>
        <p><?php esc_html_e( 'No posts found.', 'my-plugin' ); ?></p>
    <?php endif; ?>
</div>
```

## Block Variations

```js
import { registerBlockVariation } from '@wordpress/blocks';

registerBlockVariation( 'core/group', {
    name: 'card',
    title: __( 'Card', 'my-plugin' ),
    description: __( 'A card-style group block.', 'my-plugin' ),
    icon: 'id-alt',
    attributes: {
        className: 'is-style-card',
        style: {
            border: {
                radius: '8px',
            },
            spacing: {
                padding: '20px',
            },
        },
    },
    innerBlocks: [
        [ 'core/heading', { level: 3, placeholder: 'Card Title' } ],
        [ 'core/paragraph', { placeholder: 'Card content...' } ],
    ],
    scope: [ 'inserter' ],
} );
```

## Block Patterns

```php
// Register pattern
register_block_pattern(
    'my-plugin/hero-section',
    array(
        'title'       => __( 'Hero Section', 'my-plugin' ),
        'description' => __( 'A hero section with heading and CTA.', 'my-plugin' ),
        'categories'  => array( 'featured' ),
        'keywords'    => array( 'hero', 'banner' ),
        'content'     => '<!-- wp:group {"align":"full","style":{"spacing":{"padding":{"top":"100px","bottom":"100px"}}}} -->
            <div class="wp-block-group alignfull">
                <!-- wp:heading {"textAlign":"center"} -->
                <h2 class="has-text-align-center">Welcome to Our Site</h2>
                <!-- /wp:heading -->
                <!-- wp:paragraph {"align":"center"} -->
                <p class="has-text-align-center">Discover what we have to offer.</p>
                <!-- /wp:paragraph -->
                <!-- wp:buttons {"layout":{"type":"flex","justifyContent":"center"}} -->
                <div class="wp-block-buttons">
                    <!-- wp:button -->
                    <div class="wp-block-button"><a class="wp-block-button__link">Get Started</a></div>
                    <!-- /wp:button -->
                </div>
                <!-- /wp:buttons -->
            </div>
            <!-- /wp:group -->',
    )
);

// Register pattern category
register_block_pattern_category(
    'my-plugin-patterns',
    array( 'label' => __( 'My Plugin Patterns', 'my-plugin' ) )
);
```

## Block Styles

```php
// Register block style
register_block_style(
    'core/button',
    array(
        'name'  => 'outline',
        'label' => __( 'Outline', 'my-plugin' ),
    )
);

// With inline styles
register_block_style(
    'core/quote',
    array(
        'name'         => 'fancy',
        'label'        => __( 'Fancy', 'my-plugin' ),
        'inline_style' => '.is-style-fancy { border-left: 4px solid #0073aa; padding-left: 20px; }',
    )
);
```

```js
// Register in JavaScript
import { registerBlockStyle } from '@wordpress/blocks';

registerBlockStyle( 'core/button', {
    name: 'gradient',
    label: __( 'Gradient', 'my-plugin' ),
} );
```

## Build Setup (webpack)

### package.json
```json
{
    "scripts": {
        "build": "wp-scripts build",
        "start": "wp-scripts start",
        "format": "wp-scripts format",
        "lint:js": "wp-scripts lint-js"
    },
    "devDependencies": {
        "@wordpress/scripts": "^26.0.0"
    }
}
```

### webpack.config.js (custom)
```js
const defaultConfig = require( '@wordpress/scripts/config/webpack.config' );

module.exports = {
    ...defaultConfig,
    entry: {
        'my-block': './src/my-block/index.js',
        'another-block': './src/another-block/index.js',
    },
};
```

## Best Practices

1. **Use block.json** for registration when possible
2. **Follow WordPress coding standards** for JavaScript and PHP
3. **Test in multiple themes** to ensure compatibility
4. **Use block supports** instead of custom implementations
5. **Make blocks accessible** with proper ARIA attributes
6. **Internationalize all strings** using @wordpress/i18n
7. **Use semantic HTML** in block output
8. **Provide preview examples** in block.json
9. **Use CSS logical properties** for RTL support
10. **Keep blocks focused** - one purpose per block
