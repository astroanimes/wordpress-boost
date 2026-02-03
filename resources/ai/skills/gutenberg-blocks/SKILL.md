# Skill: Gutenberg Block Development

## Description
Expert guidance for creating custom Gutenberg blocks, block patterns, and block variations using modern WordPress block development.

## When to Use
- User wants to create a custom Gutenberg block
- User needs to add block patterns or variations
- User wants to extend existing blocks
- User needs to create dynamic blocks with PHP
- User wants to use InnerBlocks
- User needs block.json configuration help

## WordPress Boost Tools to Use
```
- list_blocks: See registered blocks
- get_block: Get block details
- list_hooks: Find block-related hooks
- wp_shell: Test block registration
```

## Key Concepts

### Block Types
1. **Static Block**: Saved content stored in post_content
2. **Dynamic Block**: Rendered by PHP on each page load
3. **Server-Side Rendered**: Uses render_callback or render.php

### block.json Structure
```json
{
    "$schema": "https://schemas.wp.org/trunk/block.json",
    "apiVersion": 3,
    "name": "my-plugin/my-block",
    "version": "1.0.0",
    "title": "My Block",
    "category": "widgets",
    "icon": "smiley",
    "description": "A custom block.",
    "keywords": ["custom"],
    "textdomain": "my-plugin",
    "attributes": {},
    "supports": {},
    "editorScript": "file:./index.js",
    "style": "file:./style-index.css"
}
```

## Common Tasks

### 1. Register Block with PHP
```php
add_action( 'init', function() {
    register_block_type( __DIR__ . '/blocks/my-block' );
});
```

### 2. Dynamic Block with PHP Render
```php
register_block_type( 'my-plugin/posts-list', array(
    'render_callback' => function( $attributes ) {
        $count = $attributes['count'] ?? 5;
        $posts = get_posts( array( 'posts_per_page' => $count ) );

        ob_start();
        echo '<ul class="posts-list">';
        foreach ( $posts as $post ) {
            echo '<li><a href="' . get_permalink( $post ) . '">' . esc_html( $post->post_title ) . '</a></li>';
        }
        echo '</ul>';
        return ob_get_clean();
    },
    'attributes' => array(
        'count' => array( 'type' => 'number', 'default' => 5 ),
    ),
));
```

### 3. Basic JavaScript Block (index.js)
```js
import { registerBlockType } from '@wordpress/blocks';
import { useBlockProps, RichText } from '@wordpress/block-editor';
import { __ } from '@wordpress/i18n';

registerBlockType( 'my-plugin/simple-block', {
    edit: ( { attributes, setAttributes } ) => {
        const blockProps = useBlockProps();
        return (
            <div { ...blockProps }>
                <RichText
                    tagName="p"
                    value={ attributes.content }
                    onChange={ ( content ) => setAttributes( { content } ) }
                    placeholder={ __( 'Enter text...', 'my-plugin' ) }
                />
            </div>
        );
    },
    save: ( { attributes } ) => {
        const blockProps = useBlockProps.save();
        return (
            <div { ...blockProps }>
                <RichText.Content tagName="p" value={ attributes.content } />
            </div>
        );
    },
} );
```

### 4. Add Inspector Controls
```js
import { InspectorControls } from '@wordpress/block-editor';
import { PanelBody, SelectControl, ToggleControl } from '@wordpress/components';

function Edit( { attributes, setAttributes } ) {
    return (
        <>
            <InspectorControls>
                <PanelBody title={ __( 'Settings', 'my-plugin' ) }>
                    <SelectControl
                        label={ __( 'Layout', 'my-plugin' ) }
                        value={ attributes.layout }
                        options={ [
                            { label: 'Grid', value: 'grid' },
                            { label: 'List', value: 'list' },
                        ] }
                        onChange={ ( layout ) => setAttributes( { layout } ) }
                    />
                    <ToggleControl
                        label={ __( 'Show Author', 'my-plugin' ) }
                        checked={ attributes.showAuthor }
                        onChange={ ( showAuthor ) => setAttributes( { showAuthor } ) }
                    />
                </PanelBody>
            </InspectorControls>
            <div { ...useBlockProps() }>
                {/* Block content */}
            </div>
        </>
    );
}
```

### 5. Use InnerBlocks
```js
import { InnerBlocks, useBlockProps } from '@wordpress/block-editor';

function Edit() {
    const ALLOWED_BLOCKS = [ 'core/heading', 'core/paragraph', 'core/image' ];
    const TEMPLATE = [
        [ 'core/heading', { placeholder: 'Title...' } ],
        [ 'core/paragraph', { placeholder: 'Content...' } ],
    ];

    return (
        <div { ...useBlockProps() }>
            <InnerBlocks
                allowedBlocks={ ALLOWED_BLOCKS }
                template={ TEMPLATE }
                templateLock={ false }
            />
        </div>
    );
}

function Save() {
    return (
        <div { ...useBlockProps.save() }>
            <InnerBlocks.Content />
        </div>
    );
}
```

### 6. Register Block Pattern
```php
register_block_pattern( 'my-plugin/hero', array(
    'title' => 'Hero Section',
    'categories' => array( 'featured' ),
    'content' => '<!-- wp:group {"align":"full"} -->
        <div class="wp-block-group alignfull">
            <!-- wp:heading {"textAlign":"center"} -->
            <h2 class="has-text-align-center">Welcome</h2>
            <!-- /wp:heading -->
            <!-- wp:paragraph {"align":"center"} -->
            <p class="has-text-align-center">Description text here.</p>
            <!-- /wp:paragraph -->
        </div>
        <!-- /wp:group -->',
));
```

### 7. Register Block Style
```php
register_block_style( 'core/button', array(
    'name' => 'outline',
    'label' => 'Outline',
) );
```

### 8. Block Supports
```json
{
    "supports": {
        "align": ["wide", "full"],
        "anchor": true,
        "color": {
            "background": true,
            "text": true,
            "gradients": true
        },
        "spacing": {
            "margin": true,
            "padding": true
        },
        "typography": {
            "fontSize": true,
            "lineHeight": true
        }
    }
}
```

## Build Setup

### package.json
```json
{
    "scripts": {
        "build": "wp-scripts build",
        "start": "wp-scripts start"
    },
    "devDependencies": {
        "@wordpress/scripts": "^26.0.0"
    }
}
```

### Directory Structure
```
blocks/
└── my-block/
    ├── block.json
    ├── index.js
    ├── edit.js
    ├── save.js
    ├── render.php
    ├── style.scss
    └── editor.scss
```

## Useful Attributes
```json
{
    "attributes": {
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
        "items": {
            "type": "array",
            "default": []
        },
        "settings": {
            "type": "object",
            "default": {}
        }
    }
}
```

## Checklist
- [ ] block.json created with proper schema
- [ ] Build script configured
- [ ] Proper text domain for translations
- [ ] Supports enabled appropriately
- [ ] Block registered on init hook
- [ ] Editor and frontend styles separate
- [ ] Accessible markup
