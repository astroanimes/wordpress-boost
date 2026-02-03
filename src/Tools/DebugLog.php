<?php

declare(strict_types=1);

namespace WordPressBoost\Tools;

/**
 * Debug Log Tool
 *
 * Read and analyze WordPress debug.log entries.
 */
class DebugLog extends BaseTool
{
    public function getToolDefinitions(): array
    {
        return [
            $this->createToolDefinition(
                'last_error',
                'Read the last entries from WordPress debug.log',
                [
                    'lines' => [
                        'type' => 'integer',
                        'description' => 'Number of lines to read from the end (default: 50, max: 500)',
                    ],
                    'search' => [
                        'type' => 'string',
                        'description' => 'Filter entries containing this string',
                    ],
                    'level' => [
                        'type' => 'string',
                        'description' => 'Filter by error level: error, warning, notice, deprecated, all',
                        'enum' => ['error', 'warning', 'notice', 'deprecated', 'all'],
                    ],
                ]
            ),
            $this->createToolDefinition(
                'debug_log_info',
                'Get information about the debug.log file'
            ),
            $this->createToolDefinition(
                'parse_error',
                'Parse and explain a specific PHP error message',
                [
                    'error' => [
                        'type' => 'string',
                        'description' => 'The error message to parse',
                    ],
                ],
                ['error']
            ),
        ];
    }

    public function handles(string $name): bool
    {
        return in_array($name, ['last_error', 'debug_log_info', 'parse_error']);
    }

    public function execute(string $name, array $arguments): mixed
    {
        return match ($name) {
            'last_error' => $this->getLastErrors(
                $arguments['lines'] ?? 50,
                $arguments['search'] ?? null,
                $arguments['level'] ?? 'all'
            ),
            'debug_log_info' => $this->getDebugLogInfo(),
            'parse_error' => $this->parseError($arguments['error']),
            default => throw new \RuntimeException("Unknown tool: {$name}"),
        };
    }

    private function getLastErrors(int $lines = 50, ?string $search = null, string $level = 'all'): array
    {
        $logFile = $this->getLogFilePath();

        if (!$logFile || !file_exists($logFile)) {
            return [
                'error' => 'Debug log file not found.',
                'wp_debug' => defined('WP_DEBUG') && WP_DEBUG,
                'wp_debug_log' => defined('WP_DEBUG_LOG') && WP_DEBUG_LOG,
                'expected_path' => WP_CONTENT_DIR . '/debug.log',
            ];
        }

        if (!is_readable($logFile)) {
            return [
                'error' => 'Debug log file is not readable.',
                'path' => $logFile,
            ];
        }

        // Limit lines
        $lines = min($lines, 500);

        // Read last N lines using tail approach
        $allLines = $this->readLastLines($logFile, $lines * 2); // Read extra for filtering

        // Parse and filter entries
        $entries = $this->parseLogEntries($allLines);

        // Apply search filter
        if ($search !== null) {
            $entries = array_filter($entries, function ($entry) use ($search) {
                return stripos($entry['message'], $search) !== false ||
                       stripos($entry['file'] ?? '', $search) !== false;
            });
        }

        // Apply level filter
        if ($level !== 'all') {
            $entries = array_filter($entries, function ($entry) use ($level) {
                return strtolower($entry['level'] ?? '') === $level;
            });
        }

        // Limit to requested number
        $entries = array_slice($entries, -$lines);

        // Summary stats
        $stats = [
            'errors' => 0,
            'warnings' => 0,
            'notices' => 0,
            'deprecated' => 0,
        ];

        foreach ($entries as $entry) {
            $entryLevel = strtolower($entry['level'] ?? '');
            if (isset($stats[$entryLevel])) {
                $stats[$entryLevel]++;
            }
        }

        return [
            'file' => $logFile,
            'count' => count($entries),
            'stats' => $stats,
            'entries' => array_values($entries),
        ];
    }

    private function getDebugLogInfo(): array
    {
        $logFile = $this->getLogFilePath();

        $info = [
            'wp_debug' => defined('WP_DEBUG') && WP_DEBUG,
            'wp_debug_log' => defined('WP_DEBUG_LOG') ? WP_DEBUG_LOG : false,
            'wp_debug_display' => defined('WP_DEBUG_DISPLAY') && WP_DEBUG_DISPLAY,
            'log_errors' => ini_get('log_errors'),
            'error_log' => ini_get('error_log'),
        ];

        if ($logFile && file_exists($logFile)) {
            $info['file'] = $logFile;
            $info['exists'] = true;
            $info['size'] = $this->formatBytes(filesize($logFile));
            $info['size_bytes'] = filesize($logFile);
            $info['modified'] = date('Y-m-d H:i:s', filemtime($logFile));
            $info['readable'] = is_readable($logFile);
            $info['writable'] = is_writable($logFile);
        } else {
            $info['file'] = WP_CONTENT_DIR . '/debug.log';
            $info['exists'] = false;
        }

        return $info;
    }

    private function parseError(string $errorMessage): array
    {
        $result = [
            'original' => $errorMessage,
        ];

        // Parse PHP error format
        // Example: PHP Fatal error: Uncaught Error: Call to undefined function foo() in /path/to/file.php on line 123
        if (preg_match('/PHP\s+([\w\s]+):\s+(.+)\s+in\s+(.+)\s+on\s+line\s+(\d+)/', $errorMessage, $matches)) {
            $result['level'] = trim($matches[1]);
            $result['message'] = trim($matches[2]);
            $result['file'] = trim($matches[3]);
            $result['line'] = (int) $matches[4];
        }

        // Parse WordPress-style errors
        // Example: Notice: Undefined variable: foo in /path/to/file.php on line 123
        elseif (preg_match('/(Fatal error|Warning|Notice|Deprecated):\s+(.+)\s+in\s+(.+)\s+on\s+line\s+(\d+)/i', $errorMessage, $matches)) {
            $result['level'] = trim($matches[1]);
            $result['message'] = trim($matches[2]);
            $result['file'] = trim($matches[3]);
            $result['line'] = (int) $matches[4];
        }

        // Provide suggestions based on common errors
        if (isset($result['message'])) {
            $result['suggestions'] = $this->getSuggestions($result['message']);
        }

        return $result;
    }

    private function getLogFilePath(): ?string
    {
        // Check WP_DEBUG_LOG constant
        if (defined('WP_DEBUG_LOG')) {
            $debugLog = WP_DEBUG_LOG;

            if (is_string($debugLog)) {
                return $debugLog;
            }

            if ($debugLog === true) {
                return WP_CONTENT_DIR . '/debug.log';
            }
        }

        // Default location
        $defaultPath = WP_CONTENT_DIR . '/debug.log';
        if (file_exists($defaultPath)) {
            return $defaultPath;
        }

        // Check PHP error log
        $phpErrorLog = ini_get('error_log');
        if ($phpErrorLog && file_exists($phpErrorLog)) {
            return $phpErrorLog;
        }

        return null;
    }

    private function readLastLines(string $file, int $lines): array
    {
        $handle = fopen($file, 'r');
        if (!$handle) {
            return [];
        }

        // Get file size
        fseek($handle, 0, SEEK_END);
        $fileSize = ftell($handle);

        if ($fileSize === 0) {
            fclose($handle);
            return [];
        }

        // Read chunks from the end
        $chunkSize = 4096;
        $buffer = '';
        $position = $fileSize;

        while ($position > 0 && substr_count($buffer, "\n") < $lines + 1) {
            $readSize = min($chunkSize, $position);
            $position -= $readSize;
            fseek($handle, $position);
            $buffer = fread($handle, $readSize) . $buffer;
        }

        fclose($handle);

        $allLines = explode("\n", trim($buffer));

        return array_slice($allLines, -$lines);
    }

    private function parseLogEntries(array $lines): array
    {
        $entries = [];
        $currentEntry = null;

        foreach ($lines as $line) {
            if (empty(trim($line))) {
                continue;
            }

            // Check if this is a new entry (starts with date or PHP error marker)
            if (preg_match('/^\[(\d{2}-\w{3}-\d{4}\s+\d{2}:\d{2}:\d{2}\s+\w+)\]/', $line, $matches) ||
                preg_match('/^\[(\d{4}-\d{2}-\d{2}\s+\d{2}:\d{2}:\d{2})\]/', $line, $matches)) {
                if ($currentEntry !== null) {
                    $entries[] = $currentEntry;
                }

                $currentEntry = [
                    'timestamp' => $matches[1],
                    'message' => trim(substr($line, strlen($matches[0]) + 1)),
                    'level' => $this->detectLevel($line),
                    'file' => $this->extractFile($line),
                    'line_number' => $this->extractLineNumber($line),
                ];
            } elseif (preg_match('/^PHP\s+([\w\s]+):/', $line, $matches)) {
                if ($currentEntry !== null) {
                    $entries[] = $currentEntry;
                }

                $currentEntry = [
                    'timestamp' => null,
                    'message' => $line,
                    'level' => $this->detectLevel($line),
                    'file' => $this->extractFile($line),
                    'line_number' => $this->extractLineNumber($line),
                ];
            } elseif ($currentEntry !== null) {
                // Continuation of previous entry (stack trace, etc.)
                $currentEntry['message'] .= "\n" . $line;
            }
        }

        if ($currentEntry !== null) {
            $entries[] = $currentEntry;
        }

        return $entries;
    }

    private function detectLevel(string $line): string
    {
        $line = strtolower($line);

        if (strpos($line, 'fatal') !== false || strpos($line, 'error') !== false) {
            return 'error';
        }
        if (strpos($line, 'warning') !== false) {
            return 'warning';
        }
        if (strpos($line, 'notice') !== false) {
            return 'notice';
        }
        if (strpos($line, 'deprecated') !== false) {
            return 'deprecated';
        }

        return 'unknown';
    }

    private function extractFile(string $line): ?string
    {
        if (preg_match('/\s+in\s+(.+\.php)/', $line, $matches)) {
            return trim($matches[1]);
        }
        return null;
    }

    private function extractLineNumber(string $line): ?int
    {
        if (preg_match('/on\s+line\s+(\d+)/', $line, $matches)) {
            return (int) $matches[1];
        }
        return null;
    }

    private function getSuggestions(string $message): array
    {
        $suggestions = [];

        if (stripos($message, 'undefined variable') !== false) {
            $suggestions[] = 'Check if the variable is defined before use';
            $suggestions[] = 'Use isset() or null coalescing operator (??)';
        }

        if (stripos($message, 'undefined index') !== false || stripos($message, 'undefined array key') !== false) {
            $suggestions[] = 'Check if the array key exists with isset() or array_key_exists()';
            $suggestions[] = 'Use null coalescing operator: $array["key"] ?? "default"';
        }

        if (stripos($message, 'call to undefined function') !== false) {
            $suggestions[] = 'Check if the function name is spelled correctly';
            $suggestions[] = 'Verify the plugin/theme providing this function is active';
            $suggestions[] = 'Check if the function is loaded before being called';
        }

        if (stripos($message, 'class not found') !== false) {
            $suggestions[] = 'Check if the class file is included/required';
            $suggestions[] = 'Verify autoloader is properly configured';
            $suggestions[] = 'Check namespace declarations';
        }

        if (stripos($message, 'memory') !== false) {
            $suggestions[] = 'Increase memory_limit in php.ini or wp-config.php';
            $suggestions[] = 'Check for memory leaks in loops or recursion';
            $suggestions[] = 'Optimize queries to use less memory';
        }

        return $suggestions;
    }
}
