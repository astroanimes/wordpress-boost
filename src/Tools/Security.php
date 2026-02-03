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
            $this->createToolDefinition(
                'site_security_audit',
                'Perform a comprehensive WordPress site security audit. Checks for information disclosure, XML-RPC, login security, configuration, updates, file permissions, and security headers. Returns issues categorized by severity with actionable recommendations.',
                [],
                []
            ),
        ];
    }

    public function handles(string $name): bool
    {
        return in_array($name, ['security_audit', 'security_check_file', 'list_security_functions', 'site_security_audit']);
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
            'site_security_audit' => $this->siteSecurityAudit(),
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
     * Perform a comprehensive WordPress site security audit
     */
    private function siteSecurityAudit(): array
    {
        // Check if WordPress is loaded
        if (!defined('ABSPATH')) {
            return [
                'error' => 'WordPress is not loaded. This tool must be run within a WordPress environment.',
            ];
        }

        $categories = [
            'information_disclosure' => [
                'label' => 'Information Disclosure',
                'checks' => $this->checkInformationDisclosure(),
            ],
            'xmlrpc' => [
                'label' => 'XML-RPC Security',
                'checks' => $this->checkXmlRpc(),
            ],
            'login_security' => [
                'label' => 'Login & Access Security',
                'checks' => $this->checkLoginSecurity(),
            ],
            'configuration' => [
                'label' => 'Configuration Security',
                'checks' => $this->checkConfiguration(),
            ],
            'updates' => [
                'label' => 'Update Status',
                'checks' => $this->checkUpdates(),
            ],
            'file_permissions' => [
                'label' => 'File Permissions',
                'checks' => $this->checkFilePermissions(),
            ],
            'security_headers' => [
                'label' => 'Security Headers',
                'checks' => $this->checkSecurityHeaders(),
            ],
        ];

        // Calculate summary and score
        $summary = ['critical' => 0, 'warning' => 0, 'passed' => 0, 'info' => 0];
        $totalChecks = 0;
        $scorePoints = 0;

        foreach ($categories as &$category) {
            foreach ($category['checks'] as $check) {
                $status = $check['status'];
                $summary[$status] = ($summary[$status] ?? 0) + 1;
                $totalChecks++;

                // Score calculation
                $scorePoints += match ($status) {
                    'passed' => 100,
                    'info' => 75,
                    'warning' => 50,
                    'critical' => 0,
                    default => 50,
                };
            }
        }

        $score = $totalChecks > 0 ? (int) round($scorePoints / $totalChecks) : 0;
        $grade = match (true) {
            $score >= 90 => 'A',
            $score >= 80 => 'B',
            $score >= 70 => 'C',
            $score >= 60 => 'D',
            default => 'F',
        };

        // Generate recommendations
        $recommendations = $this->generateRecommendations($categories);

        return [
            'wordpress_version' => get_bloginfo('version'),
            'php_version' => PHP_VERSION,
            'score' => $score,
            'grade' => $grade,
            'summary' => $summary,
            'categories' => $categories,
            'recommendations' => $recommendations,
        ];
    }

    /**
     * Check for information disclosure vulnerabilities
     */
    private function checkInformationDisclosure(): array
    {
        $checks = [];

        // Sensitive files - check file_exists() in ABSPATH
        $sensitiveFiles = [
            'readme.html' => 'Contains WordPress version information',
            'license.txt' => 'Confirms WordPress installation',
            'wp-config-sample.php' => 'Sample config should be removed',
        ];

        foreach ($sensitiveFiles as $file => $reason) {
            $fileKey = str_replace(['.', '-'], '_', $file);
            $exists = file_exists(ABSPATH . $file);
            $checks[$fileKey] = [
                'status' => $exists ? 'warning' : 'passed',
                'message' => $exists
                    ? "File {$file} is accessible: {$reason}"
                    : "File {$file} not accessible",
                'recommendation' => $exists ? "Remove or restrict access to {$file}" : null,
            ];
        }

        // Debug log accessible
        $debugLog = WP_CONTENT_DIR . '/debug.log';
        $debugLogExists = file_exists($debugLog);
        $checks['debug_log'] = [
            'status' => $debugLogExists ? 'critical' : 'passed',
            'message' => $debugLogExists
                ? 'debug.log exists and may be publicly accessible'
                : 'No debug.log file found',
            'recommendation' => $debugLogExists
                ? 'Delete debug.log or move it outside web root, and add .htaccess rules to block access'
                : null,
        ];

        // install.php accessible (informational - WordPress handles this)
        $installPhpExists = file_exists(ABSPATH . 'wp-admin/install.php');
        $checks['install_php'] = [
            'status' => $installPhpExists ? 'info' : 'passed',
            'message' => $installPhpExists
                ? 'install.php exists (WordPress protects this when already installed)'
                : 'install.php not found',
        ];

        // REST API user enumeration
        $checks['rest_api_users'] = $this->checkRestApiUsers();

        // Generator meta tag - check if filter removes it
        $hasGeneratorFilter = has_filter('the_generator');
        $checks['generator_tag'] = [
            'status' => $hasGeneratorFilter ? 'passed' : 'warning',
            'message' => $hasGeneratorFilter
                ? 'Generator tag is filtered'
                : 'WordPress version exposed in generator meta tag',
            'recommendation' => !$hasGeneratorFilter
                ? "Add remove_action('wp_head', 'wp_generator') or filter 'the_generator'"
                : null,
        ];

        return $checks;
    }

    /**
     * Check if REST API users endpoint is publicly accessible
     */
    private function checkRestApiUsers(): array
    {
        // Check if the REST API is available and users endpoint is accessible
        // We check if there's a filter blocking it
        $usersEndpointBlocked = has_filter('rest_endpoints', function ($endpoints) {
            return !isset($endpoints['/wp/v2/users']);
        }) || has_filter('rest_user_query');

        // Also check for common security plugin filters
        $hasRestUserFilters = has_filter('rest_user_collection_params')
            || has_filter('rest_prepare_user')
            || has_filter('rest_authentication_errors');

        $isProtected = $usersEndpointBlocked || $hasRestUserFilters;

        return [
            'status' => $isProtected ? 'passed' : 'warning',
            'message' => $isProtected
                ? 'REST API users endpoint appears to be protected'
                : 'REST API users endpoint may be publicly accessible (/wp-json/wp/v2/users)',
            'recommendation' => !$isProtected
                ? 'Restrict REST API access to authenticated users or disable users endpoint'
                : null,
        ];
    }

    /**
     * Check XML-RPC security
     */
    private function checkXmlRpc(): array
    {
        $checks = [];

        // Check if xmlrpc.php exists
        $xmlrpcExists = file_exists(ABSPATH . 'xmlrpc.php');

        // Check if XML-RPC is disabled via filter
        $xmlrpcDisabled = has_filter('xmlrpc_enabled');
        $xmlrpcActuallyDisabled = false;

        if ($xmlrpcDisabled) {
            // Try to determine if the filter disables it
            $xmlrpcActuallyDisabled = !apply_filters('xmlrpc_enabled', true);
        }

        $isVulnerable = $xmlrpcExists && !$xmlrpcActuallyDisabled;

        $checks['xmlrpc_enabled'] = [
            'status' => $isVulnerable ? 'warning' : 'passed',
            'message' => $isVulnerable
                ? 'XML-RPC is enabled - can be used for brute force and DDoS attacks'
                : 'XML-RPC is disabled or blocked',
            'recommendation' => $isVulnerable
                ? "Disable XML-RPC if not needed: add_filter('xmlrpc_enabled', '__return_false');"
                : null,
        ];

        // Check pingbacks
        $pingsOpen = function_exists('pings_open') ? pings_open() : get_option('default_ping_status') === 'open';
        $checks['pingbacks'] = [
            'status' => $pingsOpen ? 'warning' : 'passed',
            'message' => $pingsOpen
                ? 'Pingbacks are enabled - can be used for DDoS amplification'
                : 'Pingbacks disabled',
            'recommendation' => $pingsOpen
                ? 'Disable pingbacks in Settings > Discussion or via filter'
                : null,
        ];

        return $checks;
    }

    /**
     * Check login and access security
     */
    private function checkLoginSecurity(): array
    {
        $checks = [];

        // Default admin URL (informational - recommend using custom login URL)
        $checks['default_login_url'] = [
            'status' => 'info',
            'message' => 'Standard login URLs in use (/wp-admin/, /wp-login.php)',
            'recommendation' => 'Consider using a security plugin to change login URLs to reduce brute force attacks',
        ];

        // Admin username exists
        $adminUser = get_user_by('login', 'admin');
        $administratorUser = get_user_by('login', 'administrator');
        $hasCommonUsername = $adminUser || $administratorUser;

        $checks['admin_username'] = [
            'status' => $hasCommonUsername ? 'warning' : 'passed',
            'message' => $hasCommonUsername
                ? 'Common admin username exists (admin/administrator) - easy to guess'
                : 'No common admin usernames found',
            'recommendation' => $hasCommonUsername
                ? 'Create a new administrator account with a unique username and delete the common one'
                : null,
        ];

        // Check if user ID 1 is administrator
        $userOne = get_user_by('id', 1);
        $userOneIsAdmin = $userOne && in_array('administrator', $userOne->roles ?? []);

        $checks['admin_user_id_1'] = [
            'status' => $userOneIsAdmin ? 'info' : 'passed',
            'message' => $userOneIsAdmin
                ? 'Administrator has user ID 1 - easily enumerable via ?author=1'
                : 'Administrator is not user ID 1',
            'recommendation' => $userOneIsAdmin
                ? 'Consider creating a new admin user and changing user ID 1 to a non-admin role'
                : null,
        ];

        // Count administrators
        $admins = get_users(['role' => 'administrator']);
        $adminCount = count($admins);
        $tooManyAdmins = $adminCount > 3;

        $checks['admin_count'] = [
            'status' => $tooManyAdmins ? 'warning' : 'passed',
            'message' => "Found {$adminCount} administrator account(s)",
            'recommendation' => $tooManyAdmins
                ? 'Review admin accounts - limit to necessary users only and use Editor role where possible'
                : null,
        ];

        return $checks;
    }

    /**
     * Check WordPress configuration security
     */
    private function checkConfiguration(): array
    {
        $checks = [];

        // Debug settings
        $wpDebugEnabled = defined('WP_DEBUG') && WP_DEBUG;
        $checks['wp_debug'] = [
            'status' => $wpDebugEnabled ? 'warning' : 'passed',
            'message' => $wpDebugEnabled ? 'WP_DEBUG is enabled' : 'WP_DEBUG is disabled',
            'recommendation' => $wpDebugEnabled
                ? "Set WP_DEBUG to false in production: define('WP_DEBUG', false);"
                : null,
        ];

        $wpDebugDisplayEnabled = defined('WP_DEBUG_DISPLAY') && WP_DEBUG_DISPLAY;
        $checks['wp_debug_display'] = [
            'status' => $wpDebugDisplayEnabled ? 'critical' : 'passed',
            'message' => $wpDebugDisplayEnabled
                ? 'WP_DEBUG_DISPLAY is enabled - errors shown publicly!'
                : 'WP_DEBUG_DISPLAY is disabled',
            'recommendation' => $wpDebugDisplayEnabled
                ? "Disable immediately: define('WP_DEBUG_DISPLAY', false);"
                : null,
        ];

        $wpDebugLogEnabled = defined('WP_DEBUG_LOG') && WP_DEBUG_LOG;
        $checks['wp_debug_log'] = [
            'status' => $wpDebugLogEnabled ? 'info' : 'passed',
            'message' => $wpDebugLogEnabled
                ? 'WP_DEBUG_LOG is enabled - ensure debug.log is protected'
                : 'WP_DEBUG_LOG is disabled',
            'recommendation' => $wpDebugLogEnabled
                ? 'Ensure debug.log is not publicly accessible and is regularly cleared'
                : null,
        ];

        // File editor
        $fileEditDisabled = defined('DISALLOW_FILE_EDIT') && DISALLOW_FILE_EDIT;
        $checks['file_edit_disabled'] = [
            'status' => $fileEditDisabled ? 'passed' : 'warning',
            'message' => $fileEditDisabled
                ? 'File editing is disabled'
                : 'Theme/plugin editor is enabled - security risk',
            'recommendation' => !$fileEditDisabled
                ? "Add define('DISALLOW_FILE_EDIT', true); to wp-config.php"
                : null,
        ];

        // File mods
        $fileModsDisabled = defined('DISALLOW_FILE_MODS') && DISALLOW_FILE_MODS;
        $checks['file_mods_disabled'] = [
            'status' => $fileModsDisabled ? 'passed' : 'info',
            'message' => $fileModsDisabled
                ? 'File modifications disabled (updates via dashboard blocked)'
                : 'File modifications allowed',
        ];

        // SSL
        $forceSslAdmin = defined('FORCE_SSL_ADMIN') && FORCE_SSL_ADMIN;
        $checks['force_ssl_admin'] = [
            'status' => $forceSslAdmin ? 'passed' : 'warning',
            'message' => $forceSslAdmin
                ? 'SSL is forced for admin'
                : 'FORCE_SSL_ADMIN not set',
            'recommendation' => !$forceSslAdmin
                ? "Add define('FORCE_SSL_ADMIN', true); to wp-config.php"
                : null,
        ];

        $isHttps = is_ssl() || (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on')
            || (isset($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https');
        $checks['https_active'] = [
            'status' => $isHttps ? 'passed' : 'critical',
            'message' => 'Site ' . ($isHttps ? 'is' : 'is NOT') . ' using HTTPS',
            'recommendation' => !$isHttps
                ? 'Configure SSL certificate and redirect all traffic to HTTPS'
                : null,
        ];

        // Security keys - check if all 8 are defined and not default
        $keys = [
            'AUTH_KEY', 'SECURE_AUTH_KEY', 'LOGGED_IN_KEY', 'NONCE_KEY',
            'AUTH_SALT', 'SECURE_AUTH_SALT', 'LOGGED_IN_SALT', 'NONCE_SALT',
        ];
        $allKeysDefined = true;
        $missingKeys = [];

        foreach ($keys as $key) {
            if (!defined($key) || constant($key) === 'put your unique phrase here' || constant($key) === '') {
                $allKeysDefined = false;
                $missingKeys[] = $key;
            }
        }

        $checks['security_keys'] = [
            'status' => $allKeysDefined ? 'passed' : 'critical',
            'message' => $allKeysDefined
                ? 'All security keys are properly defined'
                : 'Security keys missing or using default values: ' . implode(', ', $missingKeys),
            'recommendation' => !$allKeysDefined
                ? 'Generate unique keys at https://api.wordpress.org/secret-key/1.1/salt/'
                : null,
        ];

        // Table prefix
        global $wpdb;
        $usingDefaultPrefix = $wpdb->prefix === 'wp_';
        $checks['table_prefix'] = [
            'status' => $usingDefaultPrefix ? 'info' : 'passed',
            'message' => $usingDefaultPrefix
                ? 'Using default table prefix (wp_)'
                : 'Using custom table prefix (' . $wpdb->prefix . ')',
            'recommendation' => $usingDefaultPrefix
                ? 'Consider using a custom table prefix for new installations'
                : null,
        ];

        // Auto-updates
        $autoUpdateCore = defined('WP_AUTO_UPDATE_CORE') ? WP_AUTO_UPDATE_CORE : 'minor';
        $checks['auto_update_core'] = [
            'status' => $autoUpdateCore === true || $autoUpdateCore === 'minor' ? 'passed' : 'info',
            'message' => 'Auto-update core: ' . ($autoUpdateCore === true ? 'all updates' : ($autoUpdateCore === 'minor' ? 'minor updates only' : ($autoUpdateCore === false ? 'disabled' : $autoUpdateCore))),
        ];

        return $checks;
    }

    /**
     * Check WordPress update status
     */
    private function checkUpdates(): array
    {
        $checks = [];

        // Load update functions if not already loaded
        if (!function_exists('get_core_updates')) {
            require_once ABSPATH . 'wp-admin/includes/update.php';
        }

        // Force update check if transients are stale
        wp_version_check();
        wp_update_plugins();
        wp_update_themes();

        // Core updates
        $coreUpdates = get_core_updates();
        $hasUpdate = !empty($coreUpdates) && isset($coreUpdates[0]->response) && $coreUpdates[0]->response === 'upgrade';
        $latestVersion = $hasUpdate && isset($coreUpdates[0]->version) ? $coreUpdates[0]->version : null;

        $checks['core_update'] = [
            'status' => $hasUpdate ? 'critical' : 'passed',
            'message' => $hasUpdate
                ? 'WordPress update available: ' . ($latestVersion ?? 'unknown') . ' (current: ' . get_bloginfo('version') . ')'
                : 'WordPress core is up to date (' . get_bloginfo('version') . ')',
            'recommendation' => $hasUpdate
                ? 'Update WordPress core immediately for security patches'
                : null,
        ];

        // Plugin updates
        $pluginUpdates = get_plugin_updates();
        $pluginUpdateCount = is_array($pluginUpdates) ? count($pluginUpdates) : 0;

        $checks['plugin_updates'] = [
            'status' => $pluginUpdateCount > 0 ? 'warning' : 'passed',
            'message' => $pluginUpdateCount > 0
                ? "{$pluginUpdateCount} plugin(s) have updates available"
                : 'All plugins are up to date',
            'recommendation' => $pluginUpdateCount > 0
                ? 'Update plugins to their latest versions'
                : null,
        ];

        // Theme updates
        $themeUpdates = get_theme_updates();
        $themeUpdateCount = is_array($themeUpdates) ? count($themeUpdates) : 0;

        $checks['theme_updates'] = [
            'status' => $themeUpdateCount > 0 ? 'warning' : 'passed',
            'message' => $themeUpdateCount > 0
                ? "{$themeUpdateCount} theme(s) have updates available"
                : 'All themes are up to date',
            'recommendation' => $themeUpdateCount > 0
                ? 'Update themes to their latest versions'
                : null,
        ];

        // Inactive plugins (attack surface)
        if (!function_exists('get_plugins')) {
            require_once ABSPATH . 'wp-admin/includes/plugin.php';
        }

        $allPlugins = get_plugins();
        $activePlugins = get_option('active_plugins', []);
        $inactiveCount = count($allPlugins) - count($activePlugins);

        $checks['inactive_plugins'] = [
            'status' => $inactiveCount > 0 ? 'info' : 'passed',
            'message' => $inactiveCount > 0
                ? "{$inactiveCount} inactive plugin(s) - consider removing unused plugins"
                : 'No inactive plugins',
            'recommendation' => $inactiveCount > 0
                ? 'Remove inactive plugins to reduce attack surface'
                : null,
        ];

        return $checks;
    }

    /**
     * Check file permissions
     */
    private function checkFilePermissions(): array
    {
        $checks = [];

        // wp-config.php permissions
        $wpConfigPath = ABSPATH . 'wp-config.php';
        if (!file_exists($wpConfigPath)) {
            // Check one level up (common setup)
            $wpConfigPath = dirname(ABSPATH) . '/wp-config.php';
        }

        if (file_exists($wpConfigPath)) {
            $perms = fileperms($wpConfigPath) & 0777;
            $permsOctal = decoct($perms);
            $isSecure = in_array($perms, [0400, 0440, 0600, 0640, 0644]);

            $checks['wp_config_permissions'] = [
                'status' => $isSecure ? 'passed' : 'warning',
                'message' => "wp-config.php permissions: {$permsOctal}",
                'recommendation' => !$isSecure
                    ? 'Set wp-config.php permissions to 400 or 440 for better security'
                    : null,
            ];
        } else {
            $checks['wp_config_permissions'] = [
                'status' => 'warning',
                'message' => 'wp-config.php not found in expected location',
            ];
        }

        // .htaccess exists
        $htaccessExists = file_exists(ABSPATH . '.htaccess');
        $checks['htaccess_exists'] = [
            'status' => $htaccessExists ? 'passed' : 'info',
            'message' => $htaccessExists
                ? '.htaccess file exists'
                : 'No .htaccess file found (may be using nginx or other server)',
        ];

        // Uploads directory
        $uploadsDir = wp_upload_dir();
        $uploadsWritable = is_writable($uploadsDir['basedir']);
        $checks['uploads_dir'] = [
            'status' => $uploadsWritable ? 'passed' : 'warning',
            'message' => $uploadsWritable
                ? 'Uploads directory is writable'
                : 'Uploads directory is not writable - uploads will fail',
        ];

        // Check for index.php in uploads (prevents directory listing)
        $uploadsIndexExists = file_exists($uploadsDir['basedir'] . '/index.php');
        $checks['uploads_index'] = [
            'status' => $uploadsIndexExists ? 'passed' : 'info',
            'message' => $uploadsIndexExists
                ? 'Uploads directory has index.php (prevents listing)'
                : 'Uploads directory may allow directory listing',
            'recommendation' => !$uploadsIndexExists
                ? 'Add an empty index.php to uploads directory'
                : null,
        ];

        return $checks;
    }

    /**
     * Check security headers
     */
    private function checkSecurityHeaders(): array
    {
        $checks = [];

        // Make a request to the site and check headers
        $response = wp_remote_get(home_url('/'), [
            'sslverify' => false,
            'timeout' => 10,
        ]);

        if (is_wp_error($response)) {
            return [
                'headers_check' => [
                    'status' => 'warning',
                    'message' => 'Could not check security headers: ' . $response->get_error_message(),
                ],
            ];
        }

        $headers = wp_remote_retrieve_headers($response);

        // Convert to lowercase keys for consistent checking
        $headersLower = [];
        foreach ($headers as $key => $value) {
            $headersLower[strtolower($key)] = $value;
        }

        $securityHeaders = [
            'x-frame-options' => [
                'name' => 'X-Frame-Options',
                'severity' => 'warning',
                'purpose' => 'Clickjacking protection',
            ],
            'x-content-type-options' => [
                'name' => 'X-Content-Type-Options',
                'severity' => 'warning',
                'purpose' => 'MIME sniffing protection',
            ],
            'strict-transport-security' => [
                'name' => 'Strict-Transport-Security (HSTS)',
                'severity' => 'info',
                'purpose' => 'Force HTTPS connections',
            ],
            'content-security-policy' => [
                'name' => 'Content-Security-Policy',
                'severity' => 'info',
                'purpose' => 'XSS and injection protection',
            ],
            'x-xss-protection' => [
                'name' => 'X-XSS-Protection',
                'severity' => 'info',
                'purpose' => 'Legacy XSS filter',
            ],
            'referrer-policy' => [
                'name' => 'Referrer-Policy',
                'severity' => 'info',
                'purpose' => 'Control referrer information',
            ],
        ];

        foreach ($securityHeaders as $header => $info) {
            $hasHeader = isset($headersLower[$header]);
            $headerKey = str_replace('-', '_', $header);

            $checks[$headerKey] = [
                'status' => $hasHeader ? 'passed' : $info['severity'],
                'message' => $hasHeader
                    ? "{$info['name']} header is set: " . (is_string($headersLower[$header]) ? $headersLower[$header] : 'present')
                    : "{$info['name']} header is missing ({$info['purpose']})",
                'recommendation' => !$hasHeader
                    ? "Add {$info['name']} header for {$info['purpose']}"
                    : null,
            ];
        }

        // Check for X-Powered-By (should be hidden)
        $hasPoweredBy = isset($headersLower['x-powered-by']);
        $checks['x_powered_by'] = [
            'status' => $hasPoweredBy ? 'warning' : 'passed',
            'message' => $hasPoweredBy
                ? 'X-Powered-By header exposes PHP version: ' . $headersLower['x-powered-by']
                : 'X-Powered-By header is hidden',
            'recommendation' => $hasPoweredBy
                ? 'Hide X-Powered-By header in php.ini: expose_php = Off'
                : null,
        ];

        return $checks;
    }

    /**
     * Generate prioritized recommendations from all checks
     */
    private function generateRecommendations(array $categories): array
    {
        $recommendations = [];

        $priorityMap = [
            'critical' => 1,
            'warning' => 2,
            'info' => 3,
        ];

        foreach ($categories as $categoryKey => $category) {
            foreach ($category['checks'] as $checkKey => $check) {
                if (isset($check['recommendation']) && $check['recommendation'] !== null) {
                    $priority = match ($check['status']) {
                        'critical' => 'critical',
                        'warning' => 'high',
                        'info' => 'medium',
                        default => 'low',
                    };

                    $recommendations[] = [
                        'priority' => $priority,
                        'category' => $category['label'],
                        'check' => $checkKey,
                        'action' => $check['recommendation'],
                    ];
                }
            }
        }

        // Sort by priority
        usort($recommendations, function ($a, $b) use ($priorityMap) {
            $priorityA = match ($a['priority']) {
                'critical' => 1,
                'high' => 2,
                'medium' => 3,
                default => 4,
            };
            $priorityB = match ($b['priority']) {
                'critical' => 1,
                'high' => 2,
                'medium' => 3,
                default => 4,
            };
            return $priorityA <=> $priorityB;
        });

        return $recommendations;
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
