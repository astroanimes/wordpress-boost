<?php

declare(strict_types=1);

namespace WordPressBoost\Tools;

/**
 * Database Tool
 *
 * Provides database introspection and query capabilities.
 */
class Database extends BaseTool
{
    public function getToolDefinitions(): array
    {
        return [
            $this->createToolDefinition(
                'database_schema',
                'Get database table structures including core WordPress tables and plugin tables',
                [
                    'table' => [
                        'type' => 'string',
                        'description' => 'Specific table name to inspect (without prefix). Leave empty for all tables.',
                    ],
                    'include_data_stats' => [
                        'type' => 'boolean',
                        'description' => 'Include row counts and data size (default: false)',
                    ],
                ]
            ),
            $this->createToolDefinition(
                'database_query',
                'Execute a SELECT query on the WordPress database (read-only)',
                [
                    'query' => [
                        'type' => 'string',
                        'description' => 'The SQL SELECT query to execute. Must be a SELECT statement.',
                    ],
                    'limit' => [
                        'type' => 'integer',
                        'description' => 'Maximum rows to return (default: 100, max: 1000)',
                    ],
                ],
                ['query']
            ),
            $this->createToolDefinition(
                'get_option',
                'Read a value from the wp_options table',
                [
                    'option' => [
                        'type' => 'string',
                        'description' => 'The option name to retrieve',
                    ],
                ],
                ['option']
            ),
            $this->createToolDefinition(
                'list_options',
                'List all options in the wp_options table',
                [
                    'search' => [
                        'type' => 'string',
                        'description' => 'Search pattern for option names (supports % wildcard)',
                    ],
                    'limit' => [
                        'type' => 'integer',
                        'description' => 'Maximum options to return (default: 100)',
                    ],
                    'autoload' => [
                        'type' => 'string',
                        'description' => 'Filter by autoload status: yes, no, or all',
                        'enum' => ['yes', 'no', 'all'],
                    ],
                ]
            ),
        ];
    }

    public function handles(string $name): bool
    {
        return in_array($name, ['database_schema', 'database_query', 'get_option', 'list_options']);
    }

    public function execute(string $name, array $arguments): mixed
    {
        return match ($name) {
            'database_schema' => $this->getDatabaseSchema(
                $arguments['table'] ?? null,
                $arguments['include_data_stats'] ?? false
            ),
            'database_query' => $this->executeQuery(
                $arguments['query'],
                $arguments['limit'] ?? 100
            ),
            'get_option' => $this->getOption($arguments['option']),
            'list_options' => $this->listOptions(
                $arguments['search'] ?? null,
                $arguments['limit'] ?? 100,
                $arguments['autoload'] ?? 'all'
            ),
            default => throw new \RuntimeException("Unknown tool: {$name}"),
        };
    }

    private function getDatabaseSchema(?string $table = null, bool $includeStats = false): array
    {
        global $wpdb;

        $tables = [];

        if ($table !== null) {
            // Specific table requested
            $fullTableName = $wpdb->prefix . $table;
            $tableInfo = $this->getTableSchema($fullTableName, $includeStats);

            if ($tableInfo === null) {
                // Try without prefix
                $tableInfo = $this->getTableSchema($table, $includeStats);
            }

            if ($tableInfo === null) {
                return [
                    'error' => "Table not found: {$table}",
                    'available_tables' => $this->getAllTableNames(),
                ];
            }

            $tables[] = $tableInfo;
        } else {
            // Get all tables
            $allTables = $wpdb->get_col("SHOW TABLES LIKE '{$wpdb->prefix}%'");

            foreach ($allTables as $tableName) {
                $tableInfo = $this->getTableSchema($tableName, $includeStats);
                if ($tableInfo !== null) {
                    $tables[] = $tableInfo;
                }
            }
        }

        return [
            'database' => $wpdb->dbname,
            'prefix' => $wpdb->prefix,
            'charset' => $wpdb->charset,
            'collate' => $wpdb->collate,
            'table_count' => count($tables),
            'tables' => $tables,
        ];
    }

    private function getTableSchema(string $tableName, bool $includeStats = false): ?array
    {
        global $wpdb;

        // Get table existence
        $tableExists = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = %s AND table_name = %s",
            $wpdb->dbname,
            $tableName
        ));

        if (!$tableExists) {
            return null;
        }

        // Get columns
        $columns = $wpdb->get_results("DESCRIBE `{$tableName}`", ARRAY_A);

        // Get indexes
        $indexes = $wpdb->get_results("SHOW INDEX FROM `{$tableName}`", ARRAY_A);

        // Group indexes
        $indexGroups = [];
        foreach ($indexes as $index) {
            $keyName = $index['Key_name'];
            if (!isset($indexGroups[$keyName])) {
                $indexGroups[$keyName] = [
                    'name' => $keyName,
                    'type' => $index['Key_name'] === 'PRIMARY' ? 'PRIMARY' : ($index['Non_unique'] == 0 ? 'UNIQUE' : 'INDEX'),
                    'columns' => [],
                ];
            }
            $indexGroups[$keyName]['columns'][] = $index['Column_name'];
        }

        $result = [
            'name' => $tableName,
            'short_name' => str_replace($wpdb->prefix, '', $tableName),
            'columns' => array_map(function ($col) {
                return [
                    'name' => $col['Field'],
                    'type' => $col['Type'],
                    'null' => $col['Null'] === 'YES',
                    'key' => $col['Key'],
                    'default' => $col['Default'],
                    'extra' => $col['Extra'],
                ];
            }, $columns),
            'indexes' => array_values($indexGroups),
        ];

        // Add statistics if requested
        if ($includeStats) {
            $stats = $wpdb->get_row("SELECT COUNT(*) as row_count FROM `{$tableName}`", ARRAY_A);
            $result['row_count'] = (int) ($stats['row_count'] ?? 0);

            // Get table size
            $sizeInfo = $wpdb->get_row($wpdb->prepare(
                "SELECT data_length, index_length FROM information_schema.tables WHERE table_schema = %s AND table_name = %s",
                $wpdb->dbname,
                $tableName
            ), ARRAY_A);

            if ($sizeInfo) {
                $result['data_size'] = $this->formatBytes((int) $sizeInfo['data_length']);
                $result['index_size'] = $this->formatBytes((int) $sizeInfo['index_length']);
            }
        }

        return $result;
    }

    private function getAllTableNames(): array
    {
        global $wpdb;
        return $wpdb->get_col("SHOW TABLES LIKE '{$wpdb->prefix}%'");
    }

    private function executeQuery(string $query, int $limit = 100): array
    {
        global $wpdb;

        // Ensure query is a SELECT statement
        $trimmedQuery = trim($query);
        if (!preg_match('/^SELECT\s/i', $trimmedQuery)) {
            return [
                'error' => 'Only SELECT queries are allowed for security reasons.',
            ];
        }

        // Prevent dangerous operations
        $dangerous = ['INSERT', 'UPDATE', 'DELETE', 'DROP', 'ALTER', 'CREATE', 'TRUNCATE', 'GRANT', 'REVOKE'];
        foreach ($dangerous as $keyword) {
            if (preg_match('/\b' . $keyword . '\b/i', $trimmedQuery)) {
                return [
                    'error' => "Dangerous keyword '{$keyword}' detected. Only SELECT queries are allowed.",
                ];
            }
        }

        // Enforce limit
        $limit = min($limit, 1000);
        if (!preg_match('/\bLIMIT\s+\d+/i', $trimmedQuery)) {
            $trimmedQuery = rtrim($trimmedQuery, ';') . " LIMIT {$limit}";
        }

        // Execute query
        $startTime = microtime(true);
        $results = $wpdb->get_results($trimmedQuery, ARRAY_A);
        $executionTime = round((microtime(true) - $startTime) * 1000, 2);

        if ($wpdb->last_error) {
            return [
                'error' => $wpdb->last_error,
                'query' => $trimmedQuery,
            ];
        }

        return [
            'query' => $trimmedQuery,
            'row_count' => count($results),
            'execution_time_ms' => $executionTime,
            'columns' => !empty($results) ? array_keys($results[0]) : [],
            'results' => $results,
        ];
    }

    private function getOption(string $optionName): array
    {
        $value = get_option($optionName, null);

        if ($value === null) {
            return [
                'option' => $optionName,
                'exists' => false,
                'value' => null,
            ];
        }

        return [
            'option' => $optionName,
            'exists' => true,
            'value' => $value,
            'type' => gettype($value),
            'serialized' => is_array($value) || is_object($value),
        ];
    }

    private function listOptions(?string $search = null, int $limit = 100, string $autoload = 'all'): array
    {
        global $wpdb;

        $where = [];
        $params = [];

        if ($search !== null) {
            $where[] = 'option_name LIKE %s';
            $params[] = '%' . $wpdb->esc_like($search) . '%';
        }

        if ($autoload !== 'all') {
            $where[] = 'autoload = %s';
            $params[] = $autoload;
        }

        $whereClause = !empty($where) ? 'WHERE ' . implode(' AND ', $where) : '';

        $query = "SELECT option_name, option_value, autoload FROM {$wpdb->options} {$whereClause} ORDER BY option_name LIMIT %d";
        $params[] = $limit;

        $results = $wpdb->get_results($wpdb->prepare($query, $params), ARRAY_A);

        $options = array_map(function ($row) {
            $value = maybe_unserialize($row['option_value']);
            return [
                'name' => $row['option_name'],
                'value_preview' => $this->getValuePreview($value),
                'type' => gettype($value),
                'autoload' => $row['autoload'],
                'size' => strlen($row['option_value']),
            ];
        }, $results);

        return [
            'count' => count($options),
            'limit' => $limit,
            'search' => $search,
            'options' => $options,
        ];
    }

    private function getValuePreview($value, int $maxLength = 100): string
    {
        if (is_array($value)) {
            $preview = json_encode($value);
            if (strlen($preview) > $maxLength) {
                return substr($preview, 0, $maxLength) . '...';
            }
            return $preview;
        }

        if (is_object($value)) {
            return '[object ' . get_class($value) . ']';
        }

        if (is_string($value) && strlen($value) > $maxLength) {
            return substr($value, 0, $maxLength) . '...';
        }

        return (string) $value;
    }
}
