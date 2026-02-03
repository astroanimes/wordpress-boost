<?php

declare(strict_types=1);

namespace WordPressBoost\Tools;

/**
 * Security Tool
 *
 * Provides security auditing capabilities for WordPress code.
 *
 * Note: This tool uses regex pattern matching, not AST parsing.
 * It may produce false positives and should be used as a first-pass
 * check, not a replacement for manual security review.
 */
class Security extends BaseTool
{
    /**
     * Security check patterns
     *
     * @var array<string, array{pattern: string, severity: string, message: string, recommendation: string}>
     */
    private array $patterns = [
        'unsanitized_get' => [
            'pattern' => '/\$_GET\s*\[/',
            'severity' => 'high',
            'message' => 'Direct $_GET access without sanitization',
            'recommendation' => 'Use sanitize_text_field(), absint(), or appropriate sanitization function',
        ],
        'unsanitized_post' => [
            'pattern' => '/\$_POST\s*\[/',
            'severity' => 'high',
            'message' => 'Direct $_POST access without sanitization',
            'recommendation' => 'Use sanitize_text_field(), absint(), or appropriate sanitization function',
        ],
        'unsanitized_request' => [
            'pattern' => '/\$_REQUEST\s*\[/',
            'severity' => 'high',
            'message' => 'Direct $_REQUEST access without sanitization',
            'recommendation' => 'Use sanitize_text_field(), absint(), or appropriate sanitization function',
        ],
        'unsanitized_server' => [
            'pattern' => '/\$_SERVER\s*\[\s*[\'"](?!REQUEST_METHOD|HTTPS|HTTP_HOST)[^\]]+[\'"]\s*\]/',
            'severity' => 'medium',
            'message' => 'Direct $_SERVER access - some values are user-controlled',
            'recommendation' => 'Sanitize $_SERVER values appropriately',
        ],
        'unsanitized_cookie' => [
            'pattern' => '/\$_COOKIE\s*\[/',
            'severity' => 'high',
            'message' => 'Direct $_COOKIE access without sanitization',
            'recommendation' => 'Sanitize cookie values before use',
        ],
        'unsanitized_files' => [
            'pattern' => '/\$_FILES\s*\[.*\]\s*\[[\'"](?:name|type)[\'"]/',
            'severity' => 'high',
            'message' => 'Using $_FILES name/type directly - can be spoofed',
            'recommendation' => 'Use wp_check_filetype_and_ext() for validation',
        ],
        'missing_prepare_query' => [
            'pattern' => '/\$wpdb\s*->\s*query\s*\(\s*["\'][^"\']*\$/',
            'severity' => 'critical',
            'message' => 'SQL query with variable - possible SQL injection',
            'recommendation' => 'Use $wpdb->prepare() for all queries with variables',
        ],
        'missing_prepare_get' => [
            'pattern' => '/\$wpdb\s*->\s*get_(var|row|col|results)\s*\(\s*["\'][^"\']*\$/',
            'severity' => 'critical',
            'message' => 'Database query with variable - possible SQL injection',
            'recommendation' => 'Use $wpdb->prepare() for all queries with variables',
        ],
        'concat_in_query' => [
            'pattern' => '/\$wpdb\s*->\s*(query|get_var|get_row|get_col|get_results)\s*\([^)]*\.\s*\$/',
            'severity' => 'critical',
            'message' => 'String concatenation in database query - possible SQL injection',
            'recommendation' => 'Use $wpdb->prepare() instead of string concatenation',
        ],
        'unescaped_echo' => [
            'pattern' => '/echo\s+\$(?!this\b|wpdb\b)/',
            'severity' => 'medium',
            'message' => 'Unescaped variable in echo statement',
            'recommendation' => 'Use esc_html(), esc_attr(), or esc_url() before output',
        ],
        'unescaped_print' => [
            'pattern' => '/print\s+\$(?!this\b|wpdb\b)/',
            'severity' => 'medium',
            'message' => 'Unescaped variable in print statement',
            'recommendation' => 'Use esc_html(), esc_attr(), or esc_url() before output',
        ],
        'unescaped_short_echo' => [
            'pattern' => '/<\?=\s*\$(?!this\b)/',
            'severity' => 'medium',
            'message' => 'Unescaped variable in short echo tag',
            'recommendation' => 'Use esc_html(), esc_attr(), or esc_url() before output',
        ],
        'eval_usage' => [
            'pattern' => '/\beval\s*\(/',
            'severity' => 'critical',
            'message' => 'eval() usage detected - extremely dangerous',
            'recommendation' => 'Avoid eval() entirely - find alternative approach',
        ],
        'exec_usage' => [
            'pattern' => '/\bexec\s*\(/',
            'severity' => 'critical',
            'message' => 'exec() usage detected - potential command injection',
            'recommendation' => 'Avoid shell execution or use escapeshellarg() and escapeshellcmd()',
        ],
        'system_usage' => [
            'pattern' => '/\bsystem\s*\(/',
            'severity' => 'critical',
            'message' => 'system() usage detected - potential command injection',
            'recommendation' => 'Avoid shell execution or use escapeshellarg() and escapeshellcmd()',
        ],
        'passthru_usage' => [
            'pattern' => '/\bpassthru\s*\(/',
            'severity' => 'critical',
            'message' => 'passthru() usage detected - potential command injection',
            'recommendation' => 'Avoid shell execution or use escapeshellarg() and escapeshellcmd()',
        ],
        'shell_exec_usage' => [
            'pattern' => '/\bshell_exec\s*\(/',
            'severity' => 'critical',
            'message' => 'shell_exec() usage detected - potential command injection',
            'recommendation' => 'Avoid shell execution or use escapeshellarg() and escapeshellcmd()',
        ],
        'backtick_exec' => [
            'pattern' => '/`[^`]*\$[^`]*`/',
            'severity' => 'critical',
            'message' => 'Backtick execution with variable - potential command injection',
            'recommendation' => 'Avoid shell execution with user input',
        ],
        'unserialize_usage' => [
            'pattern' => '/\bunserialize\s*\(\s*\$/',
            'severity' => 'high',
            'message' => 'unserialize() with variable - potential object injection',
            'recommendation' => 'Use json_decode() instead, or unserialize with allowed_classes: false',
        ],
        'file_get_contents_url' => [
            'pattern' => '/file_get_contents\s*\(\s*\$/',
            'severity' => 'medium',
            'message' => 'file_get_contents() with variable - potential SSRF or path traversal',
            'recommendation' => 'Use wp_remote_get() for URLs, validate paths for files',
        ],
        'include_variable' => [
            'pattern' => '/\b(include|include_once|require|require_once)\s*[\(\s]+\$/',
            'severity' => 'critical',
            'message' => 'Dynamic file inclusion - potential Local/Remote File Inclusion',
            'recommendation' => 'Use whitelist approach for file inclusion',
        ],
        'extract_usage' => [
            'pattern' => '/\bextract\s*\(\s*\$_(GET|POST|REQUEST|COOKIE)/',
            'severity' => 'critical',
            'message' => 'extract() with superglobal - variable injection vulnerability',
            'recommendation' => 'Never use extract() with user input',
        ],
        'missing_nonce_form' => [
            'pattern' => '/<form[^>]*method=["\']post["\'][^>]*>(?:(?!wp_nonce_field|_wpnonce).)*<\/form>/is',
            'severity' => 'high',
            'message' => 'POST form without nonce field',
            'recommendation' => 'Add wp_nonce_field() inside the form',
        ],
        'ajax_no_nonce' => [
            'pattern' => '/wp_ajax_(?:nopriv_)?(\w+).*?function\s+\w+.*?\{(?:(?!check_ajax_referer|wp_verify_nonce).)*?\}/s',
            'severity' => 'high',
            'message' => 'AJAX handler without nonce verification',
            'recommendation' => 'Add check_ajax_referer() at the start of handler',
        ],
        'missing_capability_check' => [
            'pattern' => '/add_menu_page\s*\([^)]+["\']manage_options["\']\s*,\s*["\'](\w+)["\']/i',
            'severity' => 'medium',
            'message' => 'Admin menu callback - verify capability check exists in callback',
            'recommendation' => 'Ensure current_user_can() check in callback function',
        ],
        'md5_password' => [
            'pattern' => '/md5\s*\(\s*\$.*password/i',
            'severity' => 'high',
            'message' => 'MD5 used for password hashing - insecure',
            'recommendation' => 'Use wp_hash_password() for WordPress passwords',
        ],
        'hardcoded_password' => [
            'pattern' => '/["\']password["\']\s*=>\s*["\'][^"\']+["\']/',
            'severity' => 'high',
            'message' => 'Possible hardcoded password detected',
            'recommendation' => 'Store credentials in wp-config.php or environment variables',
        ],
        'hardcoded_api_key' => [
            'pattern' => '/(api[_-]?key|apikey|secret[_-]?key|access[_-]?token)\s*[=:]\s*["\'][a-zA-Z0-9]{20,}["\']/i',
            'severity' => 'high',
            'message' => 'Possible hardcoded API key or secret detected',
            'recommendation' => 'Store secrets in wp-config.php or environment variables',
        ],
        'debug_enabled' => [
            'pattern' => '/define\s*\(\s*["\']WP_DEBUG["\']\s*,\s*true\s*\)/',
            'severity' => 'medium',
            'message' => 'WP_DEBUG enabled - should be disabled in production',
            'recommendation' => 'Set WP_DEBUG to false in production',
        ],
        'display_errors' => [
            'pattern' => '/define\s*\(\s*["\']WP_DEBUG_DISPLAY["\']\s*,\s*true\s*\)/',
            'severity' => 'high',
            'message' => 'WP_DEBUG_DISPLAY enabled - exposes errors publicly',
            'recommendation' => 'Set WP_DEBUG_DISPLAY to false in production',
        ],
        'preg_replace_e' => [
            'pattern' => '/preg_replace\s*\(\s*["\'][^"\']*\/e["\']/',
            'severity' => 'critical',
            'message' => 'preg_replace with /e modifier - code execution vulnerability',
            'recommendation' => 'Use preg_replace_callback() instead',
        ],
        'create_function' => [
            'pattern' => '/\bcreate_function\s*\(/',
            'severity' => 'high',
            'message' => 'create_function() usage - deprecated and potentially dangerous',
            'recommendation' => 'Use anonymous functions (closures) instead',
        ],
    ];

    /**
     * Security function categories
     */
    private array $securityFunctions = [
        'sanitization' => [
            'sanitize_text_field' => 'Sanitizes a string for safe database/output use. Removes tags, octets, encodes.',
            'sanitize_textarea_field' => 'Like sanitize_text_field but preserves newlines.',
            'sanitize_email' => 'Strips out all characters not allowed in an email.',
            'sanitize_file_name' => 'Sanitizes a filename, replacing whitespace with dashes.',
            'sanitize_html_class' => 'Sanitizes an HTML classname to ensure it only contains valid characters.',
            'sanitize_key' => 'Sanitizes a string key. Lowercase alphanumeric, dashes, underscores.',
            'sanitize_meta' => 'Sanitizes meta value based on meta key.',
            'sanitize_mime_type' => 'Sanitizes a MIME type string.',
            'sanitize_option' => 'Sanitizes various option values based on the option name.',
            'sanitize_sql_orderby' => 'Sanitizes an ORDER BY clause.',
            'sanitize_title' => 'Sanitizes a string into a valid title.',
            'sanitize_title_with_dashes' => 'Sanitizes a title, replacing whitespace with dashes.',
            'sanitize_user' => 'Sanitizes a username, stripping unsafe characters.',
            'sanitize_url' => 'Sanitizes a URL for database/redirect use.',
            'absint' => 'Returns the absolute integer value (positive).',
            'intval' => 'Returns integer value (can be negative).',
            'floatval' => 'Returns float value.',
            'wp_kses' => 'Filters content and keeps only allowed HTML elements.',
            'wp_kses_post' => 'Sanitizes content for allowed HTML tags for post content.',
            'wp_kses_data' => 'Sanitizes content with basic allowed HTML tags.',
            'wp_filter_nohtml_kses' => 'Strips all HTML from a text string.',
            'wp_strip_all_tags' => 'Properly strips all HTML tags including script and style.',
        ],
        'escaping' => [
            'esc_html' => 'Escapes for safe output in HTML context. Use for plain text.',
            'esc_attr' => 'Escapes for safe output in HTML attributes.',
            'esc_url' => 'Escapes a URL for safe output in href, src, etc.',
            'esc_url_raw' => 'Escapes a URL for database storage (no HTML entities).',
            'esc_js' => 'Escapes for safe output in JavaScript strings.',
            'esc_textarea' => 'Escapes for safe output in textarea elements.',
            'esc_sql' => 'Escapes data for use in SQL (prefer $wpdb->prepare()).',
            'esc_html__' => 'Retrieves translated string and escapes for HTML.',
            'esc_html_e' => 'Displays translated string escaped for HTML.',
            'esc_attr__' => 'Retrieves translated string and escapes for attributes.',
            'esc_attr_e' => 'Displays translated string escaped for attributes.',
            'wp_json_encode' => 'Encodes a variable into JSON with proper escaping.',
            'wp_specialchars_decode' => 'Converts HTML entities back to characters.',
        ],
        'nonces' => [
            'wp_create_nonce' => 'Creates a cryptographic nonce token.',
            'wp_verify_nonce' => 'Verifies that a nonce is correct and not expired.',
            'wp_nonce_field' => 'Outputs hidden nonce field for forms.',
            'wp_nonce_url' => 'Adds nonce to a URL.',
            'check_admin_referer' => 'Verifies nonce for admin screens.',
            'check_ajax_referer' => 'Verifies nonce for AJAX requests.',
            'wp_referer_field' => 'Outputs hidden referer field for forms.',
        ],
        'capabilities' => [
            'current_user_can' => 'Checks if current user has a specific capability.',
            'user_can' => 'Checks if a specific user has a capability.',
            'author_can' => 'Checks if post author has a capability.',
            'map_meta_cap' => 'Maps a capability to the primitive capabilities required.',
            'has_cap' => 'Checks if user has capability (method on WP_User).',
            'get_role' => 'Gets a role object by name.',
            'add_cap' => 'Adds a capability to a role.',
            'remove_cap' => 'Removes a capability from a role.',
        ],
        'database' => [
            '$wpdb->prepare' => 'Prepares a SQL query for safe execution with placeholders.',
            '$wpdb->insert' => 'Safely inserts a row into a table.',
            '$wpdb->update' => 'Safely updates a row in a table.',
            '$wpdb->delete' => 'Safely deletes a row from a table.',
            '$wpdb->replace' => 'Safely replaces a row in a table.',
            '$wpdb->esc_like' => 'Escapes special characters for use in LIKE clause.',
        ],
        'validation' => [
            'is_email' => 'Validates whether an email address is valid.',
            'wp_http_validate_url' => 'Validates a URL for safe HTTP requests.',
            'is_serialized' => 'Checks if data is serialized.',
            'is_serialized_string' => 'Checks if a string is serialized.',
            'wp_validate_boolean' => 'Validates and converts to boolean.',
            'validate_file' => 'Validates a file name and path.',
        ],
    ];

    public function getToolDefinitions(): array
    {
        return [
            $this->createToolDefinition(
                'security_audit',
                'Scan a file or directory for common WordPress security issues. Returns list of potential vulnerabilities with severity, location, and recommendations. Note: Uses pattern matching and may have false positives.',
                [
                    'path' => [
                        'type' => 'string',
                        'description' => 'File or directory path to audit (relative to WordPress root)',
                    ],
                    'checks' => [
                        'type' => 'array',
                        'description' => 'Specific checks to run (optional). Available: unsanitized_input, sql_injection, xss, nonce, capability, file_operations, credentials, dangerous_functions',
                        'items' => ['type' => 'string'],
                    ],
                ],
                ['path']
            ),
            $this->createToolDefinition(
                'security_check_file',
                'Check a specific file for security issues. Returns detailed findings with line numbers.',
                [
                    'file_path' => [
                        'type' => 'string',
                        'description' => 'Path to the file to check',
                    ],
                ],
                ['file_path']
            ),
            $this->createToolDefinition(
                'list_security_functions',
                'List all WordPress security functions with descriptions, organized by category (sanitization, escaping, nonces, capabilities, database, validation).',
                [
                    'category' => [
                        'type' => 'string',
                        'description' => 'Filter by category (optional)',
                        'enum' => ['all', 'sanitization', 'escaping', 'nonces', 'capabilities', 'database', 'validation'],
                    ],
                ],
                []
            ),
        ];
    }

    public function handles(string $name): bool
    {
        return in_array($name, ['security_audit', 'security_check_file', 'list_security_functions']);
    }

    public function execute(string $name, array $arguments): mixed
    {
        return match ($name) {
            'security_audit' => $this->securityAudit(
                $arguments['path'],
                $arguments['checks'] ?? null
            ),
            'security_check_file' => $this->securityCheckFile($arguments['file_path']),
            'list_security_functions' => $this->listSecurityFunctions($arguments['category'] ?? 'all'),
            default => throw new \RuntimeException("Unknown tool: {$name}"),
        };
    }

    /**
     * Audit a file or directory for security issues
     */
    private function securityAudit(string $path, ?array $checks = null): array
    {
        $fullPath = $this->resolvePath($path);

        if (!file_exists($fullPath)) {
            return [
                'error' => 'Path not found',
                'path' => $path,
            ];
        }

        $patterns = $this->getFilteredPatterns($checks);
        $issues = [];

        if (is_file($fullPath)) {
            $issues = $this->scanFile($fullPath, $patterns);
        } else {
            $issues = $this->scanDirectory($fullPath, $patterns);
        }

        // Sort by severity
        $severityOrder = ['critical' => 0, 'high' => 1, 'medium' => 2, 'low' => 3];
        usort($issues, function ($a, $b) use ($severityOrder) {
            return ($severityOrder[$a['severity']] ?? 4) <=> ($severityOrder[$b['severity']] ?? 4);
        });

        $summary = $this->summarizeIssues($issues);

        return [
            'path' => $path,
            'files_scanned' => $summary['files_scanned'],
            'total_issues' => count($issues),
            'summary' => $summary,
            'issues' => array_slice($issues, 0, 100), // Limit output
            'note' => 'This scan uses pattern matching and may produce false positives. Manual review recommended.',
        ];
    }

    /**
     * Check a specific file for security issues
     */
    private function securityCheckFile(string $filePath): array
    {
        $fullPath = $this->resolvePath($filePath);

        if (!is_file($fullPath)) {
            return [
                'error' => 'File not found',
                'path' => $filePath,
            ];
        }

        $issues = $this->scanFile($fullPath, $this->patterns);

        // Sort by line number
        usort($issues, function ($a, $b) {
            return $a['line'] <=> $b['line'];
        });

        return [
            'file' => $filePath,
            'total_issues' => count($issues),
            'issues' => $issues,
            'note' => 'This scan uses pattern matching and may produce false positives. Manual review recommended.',
        ];
    }

    /**
     * List security functions by category
     */
    private function listSecurityFunctions(string $category = 'all'): array
    {
        if ($category === 'all') {
            return [
                'categories' => array_keys($this->securityFunctions),
                'functions' => $this->securityFunctions,
            ];
        }

        if (!isset($this->securityFunctions[$category])) {
            return [
                'error' => 'Invalid category',
                'available' => array_keys($this->securityFunctions),
            ];
        }

        return [
            'category' => $category,
            'functions' => $this->securityFunctions[$category],
        ];
    }

    /**
     * Resolve path relative to WordPress root
     */
    private function resolvePath(string $path): string
    {
        // If absolute path, use as-is
        if (strpos($path, '/') === 0 || strpos($path, ':') === 1) {
            return $path;
        }

        // Try WordPress root first
        if (defined('ABSPATH')) {
            $wpPath = ABSPATH . ltrim($path, '/');
            if (file_exists($wpPath)) {
                return $wpPath;
            }
        }

        // Try current working directory
        $cwdPath = getcwd() . '/' . ltrim($path, '/');
        if (file_exists($cwdPath)) {
            return $cwdPath;
        }

        // Return as-is if nothing found
        return $path;
    }

    /**
     * Filter patterns based on requested checks
     */
    private function getFilteredPatterns(?array $checks): array
    {
        if ($checks === null || empty($checks)) {
            return $this->patterns;
        }

        $checkMap = [
            'unsanitized_input' => ['unsanitized_get', 'unsanitized_post', 'unsanitized_request', 'unsanitized_server', 'unsanitized_cookie', 'unsanitized_files'],
            'sql_injection' => ['missing_prepare_query', 'missing_prepare_get', 'concat_in_query'],
            'xss' => ['unescaped_echo', 'unescaped_print', 'unescaped_short_echo'],
            'nonce' => ['missing_nonce_form', 'ajax_no_nonce'],
            'capability' => ['missing_capability_check'],
            'file_operations' => ['file_get_contents_url', 'include_variable'],
            'credentials' => ['hardcoded_password', 'hardcoded_api_key'],
            'dangerous_functions' => ['eval_usage', 'exec_usage', 'system_usage', 'passthru_usage', 'shell_exec_usage', 'backtick_exec', 'unserialize_usage', 'preg_replace_e', 'create_function', 'extract_usage'],
        ];

        $filteredPatterns = [];
        foreach ($checks as $check) {
            if (isset($checkMap[$check])) {
                foreach ($checkMap[$check] as $patternName) {
                    if (isset($this->patterns[$patternName])) {
                        $filteredPatterns[$patternName] = $this->patterns[$patternName];
                    }
                }
            }
        }

        return !empty($filteredPatterns) ? $filteredPatterns : $this->patterns;
    }

    /**
     * Scan a single file for security issues
     */
    private function scanFile(string $filePath, array $patterns): array
    {
        $issues = [];

        // Only scan PHP files
        if (pathinfo($filePath, PATHINFO_EXTENSION) !== 'php') {
            return $issues;
        }

        $content = @file_get_contents($filePath);
        if ($content === false) {
            return $issues;
        }

        $lines = explode("\n", $content);
        $relativePath = $this->getRelativePath($filePath);

        foreach ($patterns as $checkName => $check) {
            // For multi-line patterns, check the whole file
            if (strpos($check['pattern'], '/s') !== false || strpos($check['pattern'], '/is') !== false) {
                if (preg_match_all($check['pattern'], $content, $matches, PREG_OFFSET_CAPTURE)) {
                    foreach ($matches[0] as $match) {
                        $lineNumber = substr_count(substr($content, 0, $match[1]), "\n") + 1;
                        $issues[] = [
                            'file' => $relativePath,
                            'line' => $lineNumber,
                            'check' => $checkName,
                            'severity' => $check['severity'],
                            'message' => $check['message'],
                            'recommendation' => $check['recommendation'],
                            'code' => trim($lines[$lineNumber - 1] ?? ''),
                        ];
                    }
                }
                continue;
            }

            // For single-line patterns, check line by line
            foreach ($lines as $lineNum => $line) {
                if (preg_match($check['pattern'], $line)) {
                    // Skip if line is a comment
                    $trimmedLine = trim($line);
                    if (strpos($trimmedLine, '//') === 0 || strpos($trimmedLine, '*') === 0 || strpos($trimmedLine, '#') === 0) {
                        continue;
                    }

                    // Skip false positives for sanitized/escaped output
                    if ($this->isFalsePositive($checkName, $line)) {
                        continue;
                    }

                    $issues[] = [
                        'file' => $relativePath,
                        'line' => $lineNum + 1,
                        'check' => $checkName,
                        'severity' => $check['severity'],
                        'message' => $check['message'],
                        'recommendation' => $check['recommendation'],
                        'code' => trim($line),
                    ];
                }
            }
        }

        return $issues;
    }

    /**
     * Check for common false positive patterns
     */
    private function isFalsePositive(string $checkName, string $line): bool
    {
        // Sanitization false positives
        if (in_array($checkName, ['unsanitized_get', 'unsanitized_post', 'unsanitized_request', 'unsanitized_cookie'])) {
            // Check if line contains a sanitization function
            $sanitizeFunctions = [
                'sanitize_', 'esc_', 'absint', 'intval', 'floatval', 'wp_kses',
                'wp_verify_nonce', 'check_ajax_referer', 'isset',
            ];
            foreach ($sanitizeFunctions as $func) {
                if (strpos($line, $func) !== false) {
                    return true;
                }
            }
        }

        // XSS false positives
        if (in_array($checkName, ['unescaped_echo', 'unescaped_print', 'unescaped_short_echo'])) {
            // Check if escape function is used on same line
            $escapeFunctions = ['esc_html', 'esc_attr', 'esc_url', 'esc_js', 'wp_kses', 'wp_json_encode'];
            foreach ($escapeFunctions as $func) {
                if (strpos($line, $func) !== false) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * Scan a directory recursively for security issues
     */
    private function scanDirectory(string $dirPath, array $patterns): array
    {
        $issues = [];

        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($dirPath, \RecursiveDirectoryIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::LEAVES_ONLY
        );

        $scannedFiles = 0;
        foreach ($iterator as $file) {
            if ($file->isFile() && $file->getExtension() === 'php') {
                // Skip vendor and node_modules
                $path = $file->getPathname();
                if (strpos($path, '/vendor/') !== false || strpos($path, '/node_modules/') !== false) {
                    continue;
                }

                $fileIssues = $this->scanFile($path, $patterns);
                $issues = array_merge($issues, $fileIssues);
                $scannedFiles++;

                // Limit to prevent timeout
                if ($scannedFiles >= 500) {
                    break;
                }
            }
        }

        return $issues;
    }

    /**
     * Get relative path for display
     */
    private function getRelativePath(string $fullPath): string
    {
        if (defined('ABSPATH') && strpos($fullPath, ABSPATH) === 0) {
            return substr($fullPath, strlen(ABSPATH));
        }

        $cwd = getcwd();
        if ($cwd && strpos($fullPath, $cwd) === 0) {
            return substr($fullPath, strlen($cwd) + 1);
        }

        return $fullPath;
    }

    /**
     * Summarize issues by severity and type
     */
    private function summarizeIssues(array $issues): array
    {
        $bySeverity = [
            'critical' => 0,
            'high' => 0,
            'medium' => 0,
            'low' => 0,
        ];

        $byType = [];
        $files = [];

        foreach ($issues as $issue) {
            $bySeverity[$issue['severity']] = ($bySeverity[$issue['severity']] ?? 0) + 1;
            $byType[$issue['check']] = ($byType[$issue['check']] ?? 0) + 1;
            $files[$issue['file']] = true;
        }

        // Sort by count
        arsort($byType);

        return [
            'by_severity' => $bySeverity,
            'by_type' => array_slice($byType, 0, 10), // Top 10 issue types
            'files_with_issues' => count($files),
            'files_scanned' => count($files), // Will be updated by caller for directories
        ];
    }
}
