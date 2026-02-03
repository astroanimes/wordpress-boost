<?php

declare(strict_types=1);

namespace WordPressBoost\Tools;

/**
 * WP-CLI Tool
 *
 * List available WP-CLI commands.
 */
class WpCli extends BaseTool
{
    public function getToolDefinitions(): array
    {
        return [
            $this->createToolDefinition(
                'list_wp_cli_commands',
                'List available WP-CLI commands and their descriptions',
                [
                    'search' => [
                        'type' => 'string',
                        'description' => 'Search commands by name or description',
                    ],
                    'namespace' => [
                        'type' => 'string',
                        'description' => 'Filter by command namespace (e.g., cache, plugin, theme)',
                    ],
                ]
            ),
            $this->createToolDefinition(
                'get_wp_cli_command',
                'Get detailed help for a specific WP-CLI command',
                [
                    'command' => [
                        'type' => 'string',
                        'description' => 'The command name (e.g., "plugin list", "cache flush")',
                    ],
                ],
                ['command']
            ),
        ];
    }

    public function handles(string $name): bool
    {
        return in_array($name, ['list_wp_cli_commands', 'get_wp_cli_command']);
    }

    public function execute(string $name, array $arguments): mixed
    {
        return match ($name) {
            'list_wp_cli_commands' => $this->listCommands(
                $arguments['search'] ?? null,
                $arguments['namespace'] ?? null
            ),
            'get_wp_cli_command' => $this->getCommandHelp($arguments['command']),
            default => throw new \RuntimeException("Unknown tool: {$name}"),
        };
    }

    private function listCommands(?string $search = null, ?string $namespace = null): array
    {
        // Check if WP-CLI is available
        if (!defined('WP_CLI') || !class_exists('WP_CLI')) {
            return $this->getBuiltInCommands($search, $namespace);
        }

        // Try to get commands from WP-CLI
        try {
            $commands = \WP_CLI::get_root_command()->get_subcommands();
            return $this->formatWpCliCommands($commands, $search, $namespace);
        } catch (\Exception $e) {
            return $this->getBuiltInCommands($search, $namespace);
        }
    }

    private function getCommandHelp(string $commandName): array
    {
        // Common WP-CLI commands with descriptions
        $commandHelp = $this->getCommandDatabase();

        // Parse command name
        $parts = explode(' ', trim($commandName));
        $namespace = $parts[0];

        if (isset($commandHelp[$commandName])) {
            return $commandHelp[$commandName];
        }

        // Check for namespace-level help
        if (isset($commandHelp[$namespace])) {
            $help = $commandHelp[$namespace];
            $help['note'] = "Showing help for '{$namespace}'. For specific subcommand help, use the full command name.";
            return $help;
        }

        return [
            'error' => "Command not found: {$commandName}",
            'suggestion' => 'Use list_wp_cli_commands to see available commands',
            'common_commands' => [
                'wp plugin list',
                'wp theme list',
                'wp cache flush',
                'wp db query',
                'wp post list',
                'wp user list',
            ],
        ];
    }

    private function getBuiltInCommands(?string $search = null, ?string $namespace = null): array
    {
        $commands = [
            // Cache
            ['name' => 'cache add', 'namespace' => 'cache', 'description' => 'Adds a value to the object cache'],
            ['name' => 'cache delete', 'namespace' => 'cache', 'description' => 'Removes a value from the object cache'],
            ['name' => 'cache flush', 'namespace' => 'cache', 'description' => 'Flushes the object cache'],
            ['name' => 'cache get', 'namespace' => 'cache', 'description' => 'Gets a value from the object cache'],

            // Config
            ['name' => 'config create', 'namespace' => 'config', 'description' => 'Generates a wp-config.php file'],
            ['name' => 'config get', 'namespace' => 'config', 'description' => 'Gets the value of a specific constant'],
            ['name' => 'config list', 'namespace' => 'config', 'description' => 'Lists variables and constants from wp-config.php'],
            ['name' => 'config set', 'namespace' => 'config', 'description' => 'Sets the value of a specific constant'],

            // Core
            ['name' => 'core check-update', 'namespace' => 'core', 'description' => 'Checks for WordPress updates'],
            ['name' => 'core download', 'namespace' => 'core', 'description' => 'Downloads core WordPress files'],
            ['name' => 'core install', 'namespace' => 'core', 'description' => 'Installs WordPress'],
            ['name' => 'core update', 'namespace' => 'core', 'description' => 'Updates WordPress to a newer version'],
            ['name' => 'core version', 'namespace' => 'core', 'description' => 'Displays the WordPress version'],

            // Database
            ['name' => 'db check', 'namespace' => 'db', 'description' => 'Checks the database'],
            ['name' => 'db cli', 'namespace' => 'db', 'description' => 'Opens MySQL console using credentials from wp-config.php'],
            ['name' => 'db export', 'namespace' => 'db', 'description' => 'Exports the database to a file'],
            ['name' => 'db import', 'namespace' => 'db', 'description' => 'Imports a database from a file'],
            ['name' => 'db optimize', 'namespace' => 'db', 'description' => 'Optimizes the database'],
            ['name' => 'db query', 'namespace' => 'db', 'description' => 'Executes a SQL query against the database'],
            ['name' => 'db repair', 'namespace' => 'db', 'description' => 'Repairs the database'],
            ['name' => 'db search', 'namespace' => 'db', 'description' => 'Finds a string in the database'],

            // Plugin
            ['name' => 'plugin activate', 'namespace' => 'plugin', 'description' => 'Activates one or more plugins'],
            ['name' => 'plugin deactivate', 'namespace' => 'plugin', 'description' => 'Deactivates one or more plugins'],
            ['name' => 'plugin delete', 'namespace' => 'plugin', 'description' => 'Deletes plugin files without deactivating or uninstalling'],
            ['name' => 'plugin install', 'namespace' => 'plugin', 'description' => 'Installs one or more plugins'],
            ['name' => 'plugin list', 'namespace' => 'plugin', 'description' => 'Gets a list of plugins'],
            ['name' => 'plugin search', 'namespace' => 'plugin', 'description' => 'Searches the WordPress.org plugin directory'],
            ['name' => 'plugin update', 'namespace' => 'plugin', 'description' => 'Updates one or more plugins'],

            // Post
            ['name' => 'post create', 'namespace' => 'post', 'description' => 'Creates a new post'],
            ['name' => 'post delete', 'namespace' => 'post', 'description' => 'Deletes one or more posts'],
            ['name' => 'post get', 'namespace' => 'post', 'description' => 'Gets details about a post'],
            ['name' => 'post list', 'namespace' => 'post', 'description' => 'Gets a list of posts'],
            ['name' => 'post update', 'namespace' => 'post', 'description' => 'Updates one or more posts'],

            // Theme
            ['name' => 'theme activate', 'namespace' => 'theme', 'description' => 'Activates a theme'],
            ['name' => 'theme delete', 'namespace' => 'theme', 'description' => 'Deletes one or more themes'],
            ['name' => 'theme install', 'namespace' => 'theme', 'description' => 'Installs one or more themes'],
            ['name' => 'theme list', 'namespace' => 'theme', 'description' => 'Gets a list of themes'],
            ['name' => 'theme search', 'namespace' => 'theme', 'description' => 'Searches the WordPress.org theme directory'],
            ['name' => 'theme update', 'namespace' => 'theme', 'description' => 'Updates one or more themes'],

            // User
            ['name' => 'user create', 'namespace' => 'user', 'description' => 'Creates a new user'],
            ['name' => 'user delete', 'namespace' => 'user', 'description' => 'Deletes one or more users'],
            ['name' => 'user get', 'namespace' => 'user', 'description' => 'Gets details about a user'],
            ['name' => 'user list', 'namespace' => 'user', 'description' => 'Lists users'],
            ['name' => 'user update', 'namespace' => 'user', 'description' => 'Updates an existing user'],

            // Transient
            ['name' => 'transient delete', 'namespace' => 'transient', 'description' => 'Deletes a transient value'],
            ['name' => 'transient get', 'namespace' => 'transient', 'description' => 'Gets a transient value'],
            ['name' => 'transient set', 'namespace' => 'transient', 'description' => 'Sets a transient value'],

            // Option
            ['name' => 'option add', 'namespace' => 'option', 'description' => 'Adds a new option value'],
            ['name' => 'option delete', 'namespace' => 'option', 'description' => 'Deletes an option'],
            ['name' => 'option get', 'namespace' => 'option', 'description' => 'Gets the value of an option'],
            ['name' => 'option list', 'namespace' => 'option', 'description' => 'Lists options'],
            ['name' => 'option update', 'namespace' => 'option', 'description' => 'Updates an option value'],

            // Rewrite
            ['name' => 'rewrite flush', 'namespace' => 'rewrite', 'description' => 'Flushes rewrite rules'],
            ['name' => 'rewrite list', 'namespace' => 'rewrite', 'description' => 'Gets a list of the current rewrite rules'],
            ['name' => 'rewrite structure', 'namespace' => 'rewrite', 'description' => 'Updates the permalink structure'],

            // Cron
            ['name' => 'cron event delete', 'namespace' => 'cron', 'description' => 'Deletes a cron event'],
            ['name' => 'cron event list', 'namespace' => 'cron', 'description' => 'Lists scheduled cron events'],
            ['name' => 'cron event run', 'namespace' => 'cron', 'description' => 'Runs a cron event immediately'],
            ['name' => 'cron event schedule', 'namespace' => 'cron', 'description' => 'Schedules a new cron event'],
            ['name' => 'cron schedule list', 'namespace' => 'cron', 'description' => 'Lists available cron schedules'],

            // Search-Replace
            ['name' => 'search-replace', 'namespace' => 'search-replace', 'description' => 'Searches/replaces strings in the database'],

            // Menu
            ['name' => 'menu create', 'namespace' => 'menu', 'description' => 'Creates a new menu'],
            ['name' => 'menu delete', 'namespace' => 'menu', 'description' => 'Deletes one or more menus'],
            ['name' => 'menu list', 'namespace' => 'menu', 'description' => 'Gets a list of menus'],

            // Widget
            ['name' => 'widget add', 'namespace' => 'widget', 'description' => 'Adds a widget to a sidebar'],
            ['name' => 'widget delete', 'namespace' => 'widget', 'description' => 'Removes a widget from a sidebar'],
            ['name' => 'widget list', 'namespace' => 'widget', 'description' => 'Lists widgets associated with a sidebar'],
        ];

        // Filter by search
        if ($search !== null) {
            $commands = array_filter($commands, function ($cmd) use ($search) {
                return stripos($cmd['name'], $search) !== false ||
                       stripos($cmd['description'], $search) !== false;
            });
        }

        // Filter by namespace
        if ($namespace !== null) {
            $commands = array_filter($commands, function ($cmd) use ($namespace) {
                return $cmd['namespace'] === $namespace;
            });
        }

        // Get unique namespaces
        $namespaces = array_unique(array_column($commands, 'namespace'));
        sort($namespaces);

        return [
            'count' => count($commands),
            'namespaces' => $namespaces,
            'commands' => array_values($commands),
        ];
    }

    private function formatWpCliCommands(array $commands, ?string $search, ?string $namespace): array
    {
        $result = [];

        foreach ($commands as $name => $command) {
            // Filter by namespace
            if ($namespace !== null && $name !== $namespace) {
                continue;
            }

            $description = '';
            if (method_exists($command, 'get_shortdesc')) {
                $description = $command->get_shortdesc();
            }

            // Filter by search
            if ($search !== null) {
                if (stripos($name, $search) === false && stripos($description, $search) === false) {
                    continue;
                }
            }

            $result[] = [
                'name' => $name,
                'namespace' => $name,
                'description' => $description,
            ];

            // Get subcommands
            if (method_exists($command, 'get_subcommands')) {
                $subcommands = $command->get_subcommands();
                foreach ($subcommands as $subName => $subCommand) {
                    $subDesc = '';
                    if (method_exists($subCommand, 'get_shortdesc')) {
                        $subDesc = $subCommand->get_shortdesc();
                    }

                    $fullName = "{$name} {$subName}";

                    // Filter by search
                    if ($search !== null) {
                        if (stripos($fullName, $search) === false && stripos($subDesc, $search) === false) {
                            continue;
                        }
                    }

                    $result[] = [
                        'name' => $fullName,
                        'namespace' => $name,
                        'description' => $subDesc,
                    ];
                }
            }
        }

        $namespaces = array_unique(array_column($result, 'namespace'));
        sort($namespaces);

        return [
            'count' => count($result),
            'namespaces' => $namespaces,
            'commands' => $result,
        ];
    }

    private function getCommandDatabase(): array
    {
        return [
            'cache' => [
                'name' => 'cache',
                'description' => 'Adds, removes, fetches, and flushes the WP Object Cache object',
                'subcommands' => ['add', 'delete', 'flush', 'get', 'set', 'type'],
            ],
            'plugin' => [
                'name' => 'plugin',
                'description' => 'Manages plugins, including installs, activations, and updates',
                'subcommands' => ['activate', 'deactivate', 'delete', 'get', 'install', 'is-active', 'is-installed', 'list', 'path', 'search', 'status', 'toggle', 'uninstall', 'update'],
            ],
            'plugin list' => [
                'name' => 'plugin list',
                'description' => 'Gets a list of plugins',
                'options' => [
                    '--status=<status>' => 'Filter by status: active, inactive, must-use, dropin, active-network',
                    '--field=<field>' => 'Prints the value of a single field for each plugin',
                    '--fields=<fields>' => 'Limit output to specific fields',
                    '--format=<format>' => 'Output format: table, csv, count, json, yaml',
                ],
                'examples' => [
                    'wp plugin list --status=active' => 'List active plugins',
                    'wp plugin list --format=json' => 'Output as JSON',
                ],
            ],
            'theme' => [
                'name' => 'theme',
                'description' => 'Manages themes, including installs, activations, and updates',
                'subcommands' => ['activate', 'delete', 'get', 'install', 'is-active', 'is-installed', 'list', 'mod', 'path', 'search', 'status', 'update'],
            ],
            'db' => [
                'name' => 'db',
                'description' => 'Performs basic database operations using credentials from wp-config.php',
                'subcommands' => ['check', 'cli', 'columns', 'create', 'drop', 'export', 'import', 'optimize', 'prefix', 'query', 'repair', 'reset', 'search', 'size', 'tables'],
            ],
            'db query' => [
                'name' => 'db query',
                'description' => 'Executes a SQL query against the database',
                'options' => [
                    '<sql>' => 'SQL query to execute (can also be piped via STDIN)',
                    '--dbuser=<user>' => 'Username for database',
                    '--dbpass=<pass>' => 'Password for database',
                ],
                'examples' => [
                    'wp db query "SELECT * FROM wp_posts LIMIT 5"' => 'Run a SELECT query',
                ],
            ],
            'post' => [
                'name' => 'post',
                'description' => 'Manages posts, content, and meta',
                'subcommands' => ['create', 'delete', 'edit', 'exists', 'generate', 'get', 'list', 'meta', 'term', 'update'],
            ],
            'user' => [
                'name' => 'user',
                'description' => 'Manages users, roles, and capabilities',
                'subcommands' => ['add-cap', 'add-role', 'check-password', 'create', 'delete', 'generate', 'get', 'import-csv', 'list', 'meta', 'remove-cap', 'remove-role', 'reset-password', 'session', 'set-role', 'spam', 'unspam', 'update'],
            ],
        ];
    }
}
