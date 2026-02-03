# WordPress Boost

An MCP (Model Context Protocol) server that provides AI agents with deep context about WordPress codebases. Inspired by Laravel Boost.

## Overview

WordPress Boost gives AI coding assistants (Claude Code, Cursor, Windsurf, etc.) real-time introspection into WordPress applications, helping them write better WordPress code by understanding:

- Hooks (actions & filters) and their callbacks
- Database schema and content
- Registered post types and taxonomies
- REST API endpoints
- ACF field groups and fields
- WooCommerce configuration
- Gutenberg blocks and patterns
- And much more...

## Installation

### With Composer (Recommended)

```bash
cd /path/to/wordpress
composer require thanoseleftherakos/wordpress-boost --dev
wp boost:install
```

### Without Composer

For WordPress projects that don't use Composer:

```bash
# Clone to a location outside your WordPress project
git clone https://github.com/thanoseleftherakos/wordpress-boost.git ~/wordpress-boost
cd ~/wordpress-boost
composer install

# Create .mcp.json in your WordPress project
cat > /path/to/wordpress/.mcp.json << 'EOF'
{
    "servers": {
        "wordpress-boost": {
            "command": "php",
            "args": ["/Users/YOUR_USERNAME/wordpress-boost/bin/wp-boost"]
        }
    }
}
EOF
```

Replace `/Users/YOUR_USERNAME/wordpress-boost` with the actual path where you cloned the repo.

## Quick Start

### For Cursor / VS Code / Windsurf

These editors auto-detect `.mcp.json` files. If you used the installation steps above, just open your WordPress project and the MCP server will be available automatically.

### For Claude Code

```bash
cd /path/to/wordpress

# With Composer installation:
claude mcp add wordpress-boost -- php vendor/bin/wp-boost

# Without Composer (use absolute path to cloned repo):
claude mcp add wordpress-boost -- php ~/wordpress-boost/bin/wp-boost
```

No `--path` needed - WordPress Boost auto-discovers your WordPress installation from the current directory.

### With WP-CLI

If you installed via Composer:

```bash
cd /path/to/wordpress
wp boost:mcp
```

### Manual MCP Configuration

Add to your editor's MCP configuration:

```json
{
  "mcpServers": {
    "wordpress-boost": {
      "command": "php",
      "args": ["/path/to/wp-boost/bin/wp-boost"],
      "cwd": "/path/to/your/wordpress"
    }
  }
}
```

## Available Tools

### Site Information
| Tool | Description |
|------|-------------|
| `site_info` | WordPress version, PHP version, active theme, plugins, debug settings |
| `list_plugins` | All installed plugins with versions and status |
| `list_themes` | Available themes with parent/child relationships |

### Hooks Introspection
| Tool | Description |
|------|-------------|
| `list_hooks` | All registered actions & filters |
| `get_hook_callbacks` | Callbacks attached to a hook with priorities |
| `search_hooks` | Search hooks by pattern |

### WordPress Structure
| Tool | Description |
|------|-------------|
| `list_post_types` | Registered post types with configurations |
| `list_taxonomies` | Registered taxonomies |
| `list_shortcodes` | Registered shortcodes |
| `list_rest_endpoints` | WP REST API routes |
| `list_rewrite_rules` | URL rewrite rules |
| `list_cron_events` | Scheduled WP-Cron tasks |
| `template_hierarchy` | Template resolution information |

### Database Tools
| Tool | Description |
|------|-------------|
| `database_schema` | Table structures (core + plugin tables) |
| `database_query` | Execute SELECT queries via $wpdb |
| `get_option` | Read wp_options values |
| `list_options` | List available options |

### Development Context
| Tool | Description |
|------|-------------|
| `search_docs` | Search WordPress developer documentation |
| `wp_shell` | Execute PHP code in WordPress context |
| `last_error` | Read debug.log entries |
| `list_wp_cli_commands` | Available WP-CLI commands |

### ACF Integration (when ACF is active)
| Tool | Description |
|------|-------------|
| `list_acf_field_groups` | All registered field groups |
| `list_acf_fields` | Fields within a group |
| `get_acf_schema` | Full ACF structure for code generation |

### WooCommerce Integration (when WooCommerce is active)
| Tool | Description |
|------|-------------|
| `woo_info` | WooCommerce version and settings |
| `list_product_types` | Registered product types |
| `woo_schema` | WooCommerce table structures |
| `list_payment_gateways` | Payment gateway configurations |
| `list_shipping_methods` | Shipping method configurations |

### Gutenberg Blocks
| Tool | Description |
|------|-------------|
| `list_block_types` | Registered block types |
| `list_block_patterns` | Registered block patterns |
| `list_block_categories` | Block categories |

### Data Generation (requires fakerphp/faker)
| Tool | Description |
|------|-------------|
| `create_posts` | Generate test posts |
| `create_pages` | Generate test pages |
| `create_users` | Generate test users |
| `create_terms` | Generate taxonomy terms |
| `create_products` | Generate WooCommerce products |
| `populate_acf` | Populate ACF fields with test data |

## Security

### wp_shell Safety

The `wp_shell` tool only works when `WP_DEBUG` is enabled. It also prevents dangerous operations:

- No `exec`, `shell_exec`, `system`, or similar functions
- No file writing operations
- No `eval` or `create_function`

### Database Security

- Only `SELECT` queries are allowed via `database_query`
- All queries use `$wpdb->prepare()` internally
- Results are limited to prevent memory issues

## Requirements

- PHP 7.4 or higher
- WordPress 5.0 or higher
- Composer (to install wordpress-boost dependencies, not required in your WordPress project)
- WP-CLI (optional, for `wp boost:*` commands)

## Optional Dependencies

- `fakerphp/faker` - For data generation tools

## Development

### Running Tests

```bash
composer test
```

### Code Style

```bash
composer cs-fix
```

## How It Works

```
1. AI Agent (Claude Code, Cursor, etc.)
         ↓
2. MCP Protocol (JSON-RPC over stdio)
         ↓
3. WordPress Boost Server
         ↓
4. WordPress Bootstrap (wp-load.php)
         ↓
5. WordPress Functions & Data
```

WordPress Boost loads WordPress in CLI mode, giving full access to all WordPress functions, hooks, and data while communicating with AI agents via the MCP protocol.

## License

MIT License - see LICENSE file for details.

## Credits

- Inspired by [Laravel Boost](https://laravel.com/docs/12.x/boost)
- Built for the [Model Context Protocol](https://modelcontextprotocol.io/)
