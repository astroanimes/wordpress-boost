<?php

declare(strict_types=1);

namespace WordPressBoost\Tools;

/**
 * Environment Tool
 *
 * Provides tools for listing WordPress constants and environment settings.
 */
class Environment extends BaseTool
{
    public function getToolDefinitions(): array
    {
        return [
            $this->createToolDefinition(
                'list_constants',
                'List WordPress constants by category: core, paths, database, debug, multisite, security, performance, or all',
                [
                    'category' => [
                        'type' => 'string',
                        'description' => 'Filter by category: core, paths, database, debug, multisite, security, performance, custom, or all',
                        'enum' => ['core', 'paths', 'database', 'debug', 'multisite', 'security', 'performance', 'custom', 'all'],
                    ],
                ]
            ),
            $this->createToolDefinition(
                'get_constant',
                'Get the value of a specific WordPress constant',
                [
                    'name' => [
                        'type' => 'string',
                        'description' => 'The constant name (e.g., WP_DEBUG, ABSPATH, DB_NAME)',
                    ],
                ],
                ['name']
            ),
            $this->createToolDefinition(
                'list_environment',
                'Show server environment information including PHP version, extensions, memory limits, and WordPress environment type'
            ),
        ];
    }

    public function handles(string $name): bool
    {
        return in_array($name, ['list_constants', 'get_constant', 'list_environment']);
    }

    public function execute(string $name, array $arguments): mixed
    {
        return match ($name) {
            'list_constants' => $this->listConstants($arguments['category'] ?? 'all'),
            'get_constant' => $this->getConstant($arguments['name']),
            'list_environment' => $this->listEnvironment(),
            default => throw new \RuntimeException("Unknown tool: {$name}"),
        };
    }

    private function listConstants(string $category = 'all'): array
    {
        $allConstants = [];

        // Core constants
        $coreConstants = [
            'WP_VERSION' => 'WordPress version',
            'ABSPATH' => 'Absolute path to WordPress directory',
            'WPINC' => 'WordPress includes directory name',
            'WP_LANG_DIR' => 'Languages directory path',
            'EMPTY_TRASH_DAYS' => 'Days before trash is emptied',
            'AUTOSAVE_INTERVAL' => 'Post autosave interval in seconds',
            'WP_POST_REVISIONS' => 'Number of post revisions to keep',
            'MEDIA_TRASH' => 'Whether media can be trashed',
            'SHORTINIT' => 'Whether to load minimal WordPress',
            'WP_FEATURE_BETTER_PASSWORDS' => 'Better password generation',
        ];

        // Path constants
        $pathConstants = [
            'ABSPATH' => 'Absolute path to WordPress directory',
            'WP_CONTENT_DIR' => 'Content directory path',
            'WP_CONTENT_URL' => 'Content directory URL',
            'WP_PLUGIN_DIR' => 'Plugins directory path',
            'WP_PLUGIN_URL' => 'Plugins directory URL',
            'WPMU_PLUGIN_DIR' => 'Must-use plugins directory path',
            'WPMU_PLUGIN_URL' => 'Must-use plugins directory URL',
            'WP_THEME_DIR' => 'Themes directory path',
            'UPLOADS' => 'Uploads directory relative path',
            'BLOGUPLOADDIR' => 'Blog uploads directory (multisite)',
            'WP_TEMP_DIR' => 'Temporary directory path',
        ];

        // Database constants
        $databaseConstants = [
            'DB_NAME' => 'Database name',
            'DB_USER' => 'Database username',
            'DB_PASSWORD' => 'Database password (hidden)',
            'DB_HOST' => 'Database host',
            'DB_CHARSET' => 'Database character set',
            'DB_COLLATE' => 'Database collation',
            'WP_ALLOW_REPAIR' => 'Allow database repair',
            'DO_NOT_UPGRADE_GLOBAL_TABLES' => 'Skip global table upgrades',
            'CUSTOM_USER_TABLE' => 'Custom users table name',
            'CUSTOM_USER_META_TABLE' => 'Custom usermeta table name',
        ];

        // Debug constants
        $debugConstants = [
            'WP_DEBUG' => 'Enable debug mode',
            'WP_DEBUG_LOG' => 'Log errors to wp-content/debug.log',
            'WP_DEBUG_DISPLAY' => 'Display errors on screen',
            'SCRIPT_DEBUG' => 'Use unminified scripts',
            'SAVEQUERIES' => 'Save database queries for analysis',
            'WP_DISABLE_FATAL_ERROR_HANDLER' => 'Disable fatal error handler',
            'WP_SANDBOX_SCRAPING' => 'Enable sandbox scraping',
            'ERRORLOGFILE' => 'Custom error log file path',
            'WP_ENVIRONMENT_TYPE' => 'Environment type (local, development, staging, production)',
            'WP_DEVELOPMENT_MODE' => 'Development mode settings',
        ];

        // Multisite constants
        $multisiteConstants = [
            'WP_ALLOW_MULTISITE' => 'Allow multisite feature',
            'MULTISITE' => 'Multisite is enabled',
            'SUBDOMAIN_INSTALL' => 'Subdomain installation',
            'DOMAIN_CURRENT_SITE' => 'Current site domain',
            'PATH_CURRENT_SITE' => 'Current site path',
            'SITE_ID_CURRENT_SITE' => 'Current site ID',
            'BLOG_ID_CURRENT_SITE' => 'Current blog ID',
            'NOBLOGREDIRECT' => 'Redirect non-existent blogs URL',
            'UPLOADBLOGSDIR' => 'Blogs upload directory',
            'UPLOADS' => 'Uploads directory',
            'WPMU_ACCEL_REDIRECT' => 'Enable X-Accel-Redirect',
            'WPMU_SENDFILE' => 'Enable X-Sendfile',
        ];

        // Security constants
        $securityConstants = [
            'AUTH_KEY' => 'Authentication key (hidden)',
            'SECURE_AUTH_KEY' => 'Secure authentication key (hidden)',
            'LOGGED_IN_KEY' => 'Logged in key (hidden)',
            'NONCE_KEY' => 'Nonce key (hidden)',
            'AUTH_SALT' => 'Authentication salt (hidden)',
            'SECURE_AUTH_SALT' => 'Secure authentication salt (hidden)',
            'LOGGED_IN_SALT' => 'Logged in salt (hidden)',
            'NONCE_SALT' => 'Nonce salt (hidden)',
            'DISALLOW_FILE_EDIT' => 'Disable theme/plugin editor',
            'DISALLOW_FILE_MODS' => 'Disable theme/plugin updates',
            'DISALLOW_UNFILTERED_HTML' => 'Disable unfiltered HTML',
            'ALLOW_UNFILTERED_UPLOADS' => 'Allow unfiltered uploads',
            'FORCE_SSL_ADMIN' => 'Force SSL for admin',
            'FORCE_SSL_LOGIN' => 'Force SSL for login (deprecated)',
            'COOKIEHASH' => 'Cookie hash',
            'PASS_COOKIE' => 'Password cookie name',
            'USER_COOKIE' => 'User cookie name',
            'AUTH_COOKIE' => 'Auth cookie name',
            'SECURE_AUTH_COOKIE' => 'Secure auth cookie name',
            'LOGGED_IN_COOKIE' => 'Logged in cookie name',
            'RECOVERY_MODE_COOKIE' => 'Recovery mode cookie name',
        ];

        // Performance constants
        $performanceConstants = [
            'WP_MEMORY_LIMIT' => 'PHP memory limit for WordPress',
            'WP_MAX_MEMORY_LIMIT' => 'Maximum memory limit (admin)',
            'WP_CACHE' => 'Enable advanced caching',
            'COMPRESS_CSS' => 'Compress CSS files',
            'COMPRESS_SCRIPTS' => 'Compress JavaScript files',
            'CONCATENATE_SCRIPTS' => 'Concatenate scripts',
            'ENFORCE_GZIP' => 'Enforce gzip compression',
            'DISABLE_WP_CRON' => 'Disable WordPress cron',
            'ALTERNATE_WP_CRON' => 'Use alternate cron method',
            'WP_CRON_LOCK_TIMEOUT' => 'Cron lock timeout',
            'FS_METHOD' => 'Filesystem access method',
            'FS_CHMOD_DIR' => 'Directory chmod value',
            'FS_CHMOD_FILE' => 'File chmod value',
            'IMAGE_EDIT_OVERWRITE' => 'Overwrite original images',
        ];

        $categories = [
            'core' => $coreConstants,
            'paths' => $pathConstants,
            'database' => $databaseConstants,
            'debug' => $debugConstants,
            'multisite' => $multisiteConstants,
            'security' => $securityConstants,
            'performance' => $performanceConstants,
        ];

        // Sensitive constants that should be masked
        $sensitiveConstants = [
            'DB_PASSWORD', 'AUTH_KEY', 'SECURE_AUTH_KEY', 'LOGGED_IN_KEY',
            'NONCE_KEY', 'AUTH_SALT', 'SECURE_AUTH_SALT', 'LOGGED_IN_SALT', 'NONCE_SALT'
        ];

        if ($category === 'all') {
            foreach ($categories as $cat => $constants) {
                $allConstants[$cat] = $this->getConstantsWithValues($constants, $sensitiveConstants);
            }
            $allConstants['custom'] = $this->getCustomConstants($sensitiveConstants);
        } elseif ($category === 'custom') {
            $allConstants['custom'] = $this->getCustomConstants($sensitiveConstants);
        } elseif (isset($categories[$category])) {
            $allConstants[$category] = $this->getConstantsWithValues($categories[$category], $sensitiveConstants);
        }

        return [
            'category' => $category,
            'constants' => $allConstants,
        ];
    }

    private function getConstantsWithValues(array $constants, array $sensitiveConstants): array
    {
        $result = [];

        foreach ($constants as $name => $description) {
            $defined = defined($name);
            $value = null;

            if ($defined) {
                if (in_array($name, $sensitiveConstants)) {
                    $value = '[HIDDEN]';
                } else {
                    $value = constant($name);
                }
            }

            $result[] = [
                'name' => $name,
                'description' => $description,
                'defined' => $defined,
                'value' => $value,
                'type' => $defined ? gettype(constant($name)) : null,
            ];
        }

        return $result;
    }

    private function getCustomConstants(array $sensitiveConstants): array
    {
        // Get all user-defined constants
        $allConstants = get_defined_constants(true);
        $userConstants = $allConstants['user'] ?? [];

        // Known WordPress constants to exclude from custom list
        $knownWpConstants = [
            'ABSPATH', 'WPINC', 'WP_CONTENT_DIR', 'WP_CONTENT_URL', 'WP_PLUGIN_DIR',
            'WP_PLUGIN_URL', 'WP_DEBUG', 'WP_DEBUG_LOG', 'WP_DEBUG_DISPLAY', 'SCRIPT_DEBUG',
            'DB_NAME', 'DB_USER', 'DB_PASSWORD', 'DB_HOST', 'DB_CHARSET', 'DB_COLLATE',
            'AUTH_KEY', 'SECURE_AUTH_KEY', 'LOGGED_IN_KEY', 'NONCE_KEY',
            'AUTH_SALT', 'SECURE_AUTH_SALT', 'LOGGED_IN_SALT', 'NONCE_SALT',
            'MULTISITE', 'WP_ALLOW_MULTISITE', 'SUBDOMAIN_INSTALL',
            'WP_MEMORY_LIMIT', 'WP_MAX_MEMORY_LIMIT', 'WP_CACHE',
            'DISALLOW_FILE_EDIT', 'DISALLOW_FILE_MODS', 'FORCE_SSL_ADMIN',
            'SAVEQUERIES', 'CONCATENATE_SCRIPTS', 'COMPRESS_SCRIPTS', 'COMPRESS_CSS',
            'WP_LANG_DIR', 'COOKIEHASH', 'USER_COOKIE', 'PASS_COOKIE', 'AUTH_COOKIE',
            'SECURE_AUTH_COOKIE', 'LOGGED_IN_COOKIE', 'WP_CRON_LOCK_TIMEOUT',
            'EMPTY_TRASH_DAYS', 'AUTOSAVE_INTERVAL', 'WP_POST_REVISIONS',
        ];

        $customConstants = [];
        foreach ($userConstants as $name => $value) {
            // Skip known WordPress constants
            if (in_array($name, $knownWpConstants) || strpos($name, 'COOKIE') !== false) {
                continue;
            }

            // Skip WordPress internal constants
            if (strpos($name, 'WP_') === 0 || strpos($name, 'WPMU_') === 0) {
                continue;
            }

            $isSensitive = in_array($name, $sensitiveConstants) ||
                           stripos($name, 'KEY') !== false ||
                           stripos($name, 'SECRET') !== false ||
                           stripos($name, 'PASSWORD') !== false ||
                           stripos($name, 'SALT') !== false;

            $customConstants[] = [
                'name' => $name,
                'value' => $isSensitive ? '[HIDDEN]' : $value,
                'type' => gettype($value),
            ];
        }

        return $customConstants;
    }

    private function getConstant(string $name): array
    {
        // Sensitive constants that should be masked
        $sensitiveConstants = [
            'DB_PASSWORD', 'AUTH_KEY', 'SECURE_AUTH_KEY', 'LOGGED_IN_KEY',
            'NONCE_KEY', 'AUTH_SALT', 'SECURE_AUTH_SALT', 'LOGGED_IN_SALT', 'NONCE_SALT'
        ];

        // Also check for sensitive patterns
        $isSensitive = in_array($name, $sensitiveConstants) ||
                       stripos($name, 'KEY') !== false ||
                       stripos($name, 'SECRET') !== false ||
                       stripos($name, 'PASSWORD') !== false ||
                       stripos($name, 'SALT') !== false;

        if (!defined($name)) {
            return [
                'name' => $name,
                'defined' => false,
                'value' => null,
                'message' => "Constant '{$name}' is not defined",
            ];
        }

        $value = constant($name);

        return [
            'name' => $name,
            'defined' => true,
            'value' => $isSensitive ? '[HIDDEN - Security sensitive]' : $value,
            'type' => gettype($value),
        ];
    }

    private function listEnvironment(): array
    {
        global $wpdb;

        // Get PHP extensions
        $extensions = get_loaded_extensions();
        sort($extensions);

        // Important extensions for WordPress
        $importantExtensions = [
            'curl', 'dom', 'exif', 'fileinfo', 'gd', 'imagick', 'intl',
            'json', 'mbstring', 'mysqli', 'openssl', 'pcre', 'sodium',
            'xml', 'zip', 'zlib'
        ];

        $extensionStatus = [];
        foreach ($importantExtensions as $ext) {
            $extensionStatus[$ext] = extension_loaded($ext);
        }

        // Get environment type
        $environmentType = 'production';
        if (function_exists('wp_get_environment_type')) {
            $environmentType = wp_get_environment_type();
        }

        return [
            'php' => [
                'version' => PHP_VERSION,
                'sapi' => PHP_SAPI,
                'memory_limit' => ini_get('memory_limit'),
                'max_execution_time' => ini_get('max_execution_time'),
                'max_input_time' => ini_get('max_input_time'),
                'max_input_vars' => ini_get('max_input_vars'),
                'post_max_size' => ini_get('post_max_size'),
                'upload_max_filesize' => ini_get('upload_max_filesize'),
                'max_file_uploads' => ini_get('max_file_uploads'),
                'display_errors' => ini_get('display_errors'),
                'log_errors' => ini_get('log_errors'),
                'error_log' => ini_get('error_log'),
                'default_timezone' => date_default_timezone_get(),
            ],
            'wordpress' => [
                'version' => $GLOBALS['wp_version'] ?? 'unknown',
                'environment_type' => $environmentType,
                'memory_limit' => defined('WP_MEMORY_LIMIT') ? WP_MEMORY_LIMIT : 'not set',
                'max_memory_limit' => defined('WP_MAX_MEMORY_LIMIT') ? WP_MAX_MEMORY_LIMIT : 'not set',
                'debug_mode' => defined('WP_DEBUG') && WP_DEBUG,
                'debug_log' => defined('WP_DEBUG_LOG') && WP_DEBUG_LOG,
                'debug_display' => defined('WP_DEBUG_DISPLAY') && WP_DEBUG_DISPLAY,
                'script_debug' => defined('SCRIPT_DEBUG') && SCRIPT_DEBUG,
                'cron_disabled' => defined('DISABLE_WP_CRON') && DISABLE_WP_CRON,
                'cache_enabled' => defined('WP_CACHE') && WP_CACHE,
                'multisite' => is_multisite(),
            ],
            'server' => [
                'software' => $_SERVER['SERVER_SOFTWARE'] ?? 'unknown',
                'hostname' => gethostname(),
                'os' => PHP_OS,
                'os_family' => PHP_OS_FAMILY,
                'architecture' => php_uname('m'),
            ],
            'database' => [
                'type' => 'MySQL/MariaDB',
                'server_version' => $wpdb->db_version(),
                'client_info' => $wpdb->db_server_info(),
                'charset' => $wpdb->charset,
                'collation' => $wpdb->collate,
            ],
            'extensions' => [
                'important' => $extensionStatus,
                'all_loaded_count' => count($extensions),
                'all_loaded' => $extensions,
            ],
            'paths' => [
                'document_root' => $_SERVER['DOCUMENT_ROOT'] ?? 'unknown',
                'wordpress_root' => ABSPATH,
                'content_dir' => WP_CONTENT_DIR,
                'plugin_dir' => WP_PLUGIN_DIR,
                'theme_dir' => get_theme_root(),
                'upload_dir' => wp_upload_dir()['basedir'],
            ],
        ];
    }
}
