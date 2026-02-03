<?php

declare(strict_types=1);

namespace WordPressBoost\Commands;

use WordPressBoost\McpServer;
use WordPressBoost\Commands\InstallCommand;

/**
 * WordPress Boost MCP Command
 *
 * WP-CLI command for starting the MCP server.
 */
class McpCommand
{
    /**
     * Start the WordPress Boost MCP server.
     *
     * ## DESCRIPTION
     *
     * Starts the MCP (Model Context Protocol) server which provides AI agents
     * with deep context about your WordPress codebase.
     *
     * ## OPTIONS
     *
     * [--debug]
     * : Enable debug output to stderr.
     *
     * ## EXAMPLES
     *
     *     # Start the MCP server
     *     wp boost:mcp
     *
     *     # Start with debug output
     *     wp boost:mcp --debug
     *
     * @when after_wp_load
     */
    public function mcp(array $args, array $assocArgs): void
    {
        $debug = isset($assocArgs['debug']);

        if ($debug) {
            fwrite(STDERR, "[wordpress-boost] Starting MCP server...\n");
            fwrite(STDERR, "[wordpress-boost] WordPress version: " . get_bloginfo('version') . "\n");
            fwrite(STDERR, "[wordpress-boost] PHP version: " . PHP_VERSION . "\n");
        }

        $server = new McpServer();
        $server->run();
    }

    /**
     * Display information about WordPress Boost.
     *
     * ## EXAMPLES
     *
     *     wp boost:info
     *
     * @when after_wp_load
     */
    public function info(array $args, array $assocArgs): void
    {
        \WP_CLI::log('WordPress Boost v1.0.0');
        \WP_CLI::log('');
        \WP_CLI::log('An MCP server that provides AI agents with deep context about WordPress codebases.');
        \WP_CLI::log('');
        \WP_CLI::log('Available commands:');
        \WP_CLI::log('  wp boost:install - Install .mcp.json and AI guidelines');
        \WP_CLI::log('  wp boost:mcp     - Start the MCP server');
        \WP_CLI::log('  wp boost:info    - Display this information');
        \WP_CLI::log('');
        \WP_CLI::log('Quick setup:');
        \WP_CLI::log('');
        \WP_CLI::log('  For Cursor/VS Code/Windsurf:');
        \WP_CLI::log('    wp boost:install');
        \WP_CLI::log('');
        \WP_CLI::log('  For Claude Code:');
        \WP_CLI::log('    claude mcp add wordpress-boost -- php vendor/bin/wp-boost');
    }

    /**
     * Install WordPress Boost configuration files.
     *
     * ## DESCRIPTION
     *
     * Creates .mcp.json for editor auto-detection (Cursor, VS Code, Windsurf)
     * and copies AI guidelines to your project.
     *
     * ## EXAMPLES
     *
     *     # Install WordPress Boost configuration
     *     wp boost:install
     *
     * @when after_wp_load
     */
    public function install(array $args, array $assocArgs): void
    {
        $installer = new InstallCommand(ABSPATH);
        $result = $installer->install();

        foreach ($result['messages'] as $message) {
            if (strpos($message, 'Failed') !== false) {
                \WP_CLI::error($message, false);
            } else {
                \WP_CLI::success($message);
            }
        }

        \WP_CLI::log(InstallCommand::getNextStepsMessage());
    }

    /**
     * Register the WP-CLI commands.
     */
    public static function register(): void
    {
        if (!defined('WP_CLI') || !WP_CLI) {
            return;
        }

        \WP_CLI::add_command('boost:mcp', [self::class, 'mcp']);
        \WP_CLI::add_command('boost:info', [self::class, 'info']);
        \WP_CLI::add_command('boost:install', [self::class, 'install']);
    }
}

// Auto-register commands when loaded via WP-CLI
if (defined('WP_CLI') && WP_CLI) {
    McpCommand::register();
}
