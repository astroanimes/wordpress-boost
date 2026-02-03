# Skill: WP-CLI Command Development

## Description
Expert guidance for creating custom WP-CLI commands and using WP-CLI for WordPress development and administration.

## When to Use
- User wants to create custom WP-CLI commands
- User needs to automate WordPress tasks
- User wants to manage WordPress from command line
- User needs database operations via CLI
- User wants to create deployment scripts
- User needs bulk operations on posts/users

## WordPress Boost Tools to Use
```
- wp_cli: Execute WP-CLI commands
- database_schema: Check database structure
- list_post_types: Available post types
- list_options: WordPress options
```

## Key Concepts

### Common WP-CLI Commands
```bash
# Core
wp core version
wp core update
wp core verify-checksums

# Plugins
wp plugin list
wp plugin install <plugin> --activate
wp plugin deactivate <plugin>
wp plugin update --all

# Themes
wp theme list
wp theme activate <theme>
wp theme update --all

# Database
wp db export backup.sql
wp db import backup.sql
wp db query "SELECT * FROM wp_options LIMIT 10"

# Posts
wp post list --post_type=page
wp post create --post_title="New Post" --post_status=publish
wp post delete 123 --force

# Users
wp user list
wp user create bob bob@example.com --role=editor
wp user update 1 --user_pass=newpassword

# Options
wp option get siteurl
wp option update blogname "New Site Name"

# Cache
wp cache flush
wp transient delete --all

# Search/Replace
wp search-replace 'old-domain.com' 'new-domain.com' --dry-run
```

## Common Tasks

### 1. Register Simple Command
```php
if ( defined( 'WP_CLI' ) && WP_CLI ) {
    WP_CLI::add_command( 'hello', function( $args ) {
        $name = $args[0] ?? 'World';
        WP_CLI::success( "Hello, $name!" );
    });
}
```

### 2. Command Class with Subcommands
```php
if ( defined( 'WP_CLI' ) && WP_CLI ) {

    class My_CLI_Command {

        /**
         * Lists all items.
         *
         * ## OPTIONS
         *
         * [--format=<format>]
         * : Output format (table, json, csv)
         * ---
         * default: table
         * options:
         *   - table
         *   - json
         *   - csv
         * ---
         *
         * ## EXAMPLES
         *
         *     wp mycommand list
         *     wp mycommand list --format=json
         *
         * @when after_wp_load
         */
        public function list( $args, $assoc_args ) {
            $items = get_posts( array( 'post_type' => 'item', 'posts_per_page' => -1 ) );

            $data = array();
            foreach ( $items as $item ) {
                $data[] = array(
                    'ID' => $item->ID,
                    'Title' => $item->post_title,
                    'Status' => $item->post_status,
                );
            }

            $format = $assoc_args['format'] ?? 'table';
            WP_CLI\Utils\format_items( $format, $data, array( 'ID', 'Title', 'Status' ) );
        }

        /**
         * Creates a new item.
         *
         * ## OPTIONS
         *
         * <title>
         * : The item title
         *
         * [--status=<status>]
         * : Post status
         * ---
         * default: publish
         * ---
         *
         * ## EXAMPLES
         *
         *     wp mycommand create "My Item"
         *     wp mycommand create "Draft Item" --status=draft
         *
         * @when after_wp_load
         */
        public function create( $args, $assoc_args ) {
            $title = $args[0];
            $status = $assoc_args['status'] ?? 'publish';

            $post_id = wp_insert_post( array(
                'post_type' => 'item',
                'post_title' => $title,
                'post_status' => $status,
            ));

            if ( is_wp_error( $post_id ) ) {
                WP_CLI::error( $post_id->get_error_message() );
            }

            WP_CLI::success( "Created item #$post_id" );
        }

        /**
         * Deletes an item.
         *
         * ## OPTIONS
         *
         * <id>
         * : The item ID to delete
         *
         * [--force]
         * : Skip trash and permanently delete
         *
         * ## EXAMPLES
         *
         *     wp mycommand delete 123
         *     wp mycommand delete 123 --force
         *
         * @when after_wp_load
         */
        public function delete( $args, $assoc_args ) {
            $id = $args[0];
            $force = isset( $assoc_args['force'] );

            $post = get_post( $id );
            if ( ! $post || $post->post_type !== 'item' ) {
                WP_CLI::error( "Item #$id not found" );
            }

            wp_delete_post( $id, $force );

            WP_CLI::success( "Deleted item #$id" );
        }

        /**
         * Processes all items.
         *
         * ## EXAMPLES
         *
         *     wp mycommand process
         *
         * @when after_wp_load
         */
        public function process( $args, $assoc_args ) {
            $items = get_posts( array( 'post_type' => 'item', 'posts_per_page' => -1 ) );

            $progress = \WP_CLI\Utils\make_progress_bar( 'Processing items', count( $items ) );

            foreach ( $items as $item ) {
                // Process item
                sleep( 1 ); // Simulate work

                $progress->tick();
            }

            $progress->finish();
            WP_CLI::success( 'All items processed!' );
        }
    }

    WP_CLI::add_command( 'mycommand', 'My_CLI_Command' );
}
```

### 3. Output Methods
```php
// Messages
WP_CLI::log( 'Regular message' );
WP_CLI::success( 'Success message' );
WP_CLI::warning( 'Warning message' );
WP_CLI::error( 'Error message' ); // Exits

// Colored output
WP_CLI::colorize( '%GGreen%n %RRed%n %YYellow%n' );

// Confirmation
WP_CLI::confirm( 'Are you sure?' );

// Tables
WP_CLI\Utils\format_items( 'table', $data, array( 'ID', 'Name' ) );
```

### 4. Input Handling
```php
/**
 * ## OPTIONS
 *
 * <name>
 * : Required positional argument
 *
 * [<optional>]
 * : Optional positional argument
 *
 * --required=<value>
 * : Required named argument
 *
 * [--optional=<value>]
 * : Optional named argument
 * ---
 * default: default_value
 * ---
 *
 * [--flag]
 * : Boolean flag
 */
public function example( $args, $assoc_args ) {
    $name = $args[0];
    $optional = $args[1] ?? 'default';
    $required = $assoc_args['required'];
    $optional_named = $assoc_args['optional'] ?? 'default';
    $flag = isset( $assoc_args['flag'] );
}
```

### 5. Progress Bar for Bulk Operations
```php
public function migrate( $args, $assoc_args ) {
    global $wpdb;

    $items = $wpdb->get_results( "SELECT * FROM old_table" );
    $count = count( $items );

    if ( ! $count ) {
        WP_CLI::warning( 'No items to migrate' );
        return;
    }

    $progress = \WP_CLI\Utils\make_progress_bar( 'Migrating', $count );

    $success = 0;
    $errors = 0;

    foreach ( $items as $item ) {
        $result = $this->migrate_item( $item );
        if ( $result ) {
            $success++;
        } else {
            $errors++;
        }
        $progress->tick();
    }

    $progress->finish();

    WP_CLI::success( "Migrated $success items, $errors errors" );
}
```

### 6. Dry Run Support
```php
public function cleanup( $args, $assoc_args ) {
    $dry_run = isset( $assoc_args['dry-run'] );

    $items = get_posts( array( 'post_status' => 'trash' ) );

    foreach ( $items as $item ) {
        if ( $dry_run ) {
            WP_CLI::log( "Would delete: {$item->post_title}" );
        } else {
            wp_delete_post( $item->ID, true );
            WP_CLI::log( "Deleted: {$item->post_title}" );
        }
    }

    if ( $dry_run ) {
        WP_CLI::warning( 'Dry run complete. Use without --dry-run to execute.' );
    }
}
```

## Useful WP-CLI Utilities

```php
// Run WordPress function
WP_CLI::runcommand( 'cache flush' );

// Get WordPress path
ABSPATH;

// Launch editor
$content = WP_CLI::launch_self( 'post get 1 --field=post_content' );

// Read from STDIN
$input = file_get_contents( 'php://stdin' );

// Get home URL without loading frontend
WP_CLI::get_config( 'url' );
```

## Command Registration File
```php
// my-plugin-cli.php
if ( ! defined( 'WP_CLI' ) || ! WP_CLI ) {
    return;
}

require_once __DIR__ . '/includes/class-my-cli-command.php';
WP_CLI::add_command( 'myplugin', 'My_CLI_Command' );
```

## Checklist
- [ ] Check WP_CLI constant before registering
- [ ] Add proper docblock documentation
- [ ] Support --format for list commands
- [ ] Support --dry-run for destructive commands
- [ ] Use progress bar for bulk operations
- [ ] Handle errors gracefully
- [ ] Use @when annotation appropriately
