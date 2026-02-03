<?php
/**
 * WordPress Boost
 *
 * An MCP server that provides AI agents with deep context about WordPress codebases.
 *
 * @package     WordPressBoost
 * @author      WordPress Boost
 * @license     MIT
 *
 * This file should be loaded by WordPress to register WP-CLI commands.
 * Add to your theme's functions.php or as a must-use plugin:
 *
 *   require_once '/path/to/wordpress-boost/wordpress-boost.php';
 *
 * Or use Composer autoloading.
 */

declare(strict_types=1);

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

// Try to load Composer autoloader
$autoloadPaths = [
    __DIR__ . '/vendor/autoload.php',
    dirname(__DIR__, 2) . '/autoload.php', // In vendor directory
];

foreach ($autoloadPaths as $autoloadPath) {
    if (file_exists($autoloadPath)) {
        require_once $autoloadPath;
        break;
    }
}

// Register WP-CLI commands
if (defined('WP_CLI') && WP_CLI) {
    \WordPressBoost\Commands\McpCommand::register();
}
