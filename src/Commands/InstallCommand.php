<?php

declare(strict_types=1);

namespace WordPressBoost\Commands;

/**
 * WordPress Boost Install Command
 *
 * Installs WordPress Boost configuration files for MCP editors.
 */
class InstallCommand
{
    private string $wpPath;

    public function __construct(string $wpPath)
    {
        $this->wpPath = rtrim($wpPath, '/');
    }

    /**
     * Install WordPress Boost configuration files.
     *
     * Creates .mcp.json for editor auto-detection and copies AI guidelines.
     *
     * @return array{success: bool, messages: string[]}
     */
    public function install(): array
    {
        $messages = [];
        $success = true;

        // 1. Create .mcp.json
        $mcpResult = $this->createMcpJson();
        $messages[] = $mcpResult['message'];
        if (!$mcpResult['success']) {
            $success = false;
        }

        // 2. Copy .ai guidelines
        $aiResult = $this->copyAiGuidelines();
        $messages[] = $aiResult['message'];

        return [
            'success' => $success,
            'messages' => $messages,
        ];
    }

    /**
     * Create .mcp.json configuration file.
     */
    private function createMcpJson(): array
    {
        $mcpPath = $this->wpPath . '/.mcp.json';

        // Check if file already exists
        if (file_exists($mcpPath)) {
            return [
                'success' => true,
                'message' => '.mcp.json already exists (skipped)',
            ];
        }

        $config = [
            'servers' => [
                'wordpress-boost' => [
                    'command' => 'php',
                    'args' => ['vendor/bin/wp-boost'],
                ],
            ],
        ];

        $json = json_encode($config, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);

        if (file_put_contents($mcpPath, $json . "\n") === false) {
            return [
                'success' => false,
                'message' => 'Failed to create .mcp.json',
            ];
        }

        return [
            'success' => true,
            'message' => 'Created .mcp.json',
        ];
    }

    /**
     * Copy AI guidelines to the WordPress project.
     */
    private function copyAiGuidelines(): array
    {
        $sourceDir = $this->getPackageDir() . '/.ai';
        $targetDir = $this->wpPath . '/.ai';

        if (!is_dir($sourceDir)) {
            return [
                'success' => true,
                'message' => 'AI guidelines source not found (skipped)',
            ];
        }

        // Create target directory if it doesn't exist
        if (!is_dir($targetDir)) {
            if (!mkdir($targetDir, 0755, true)) {
                return [
                    'success' => false,
                    'message' => 'Failed to create .ai directory',
                ];
            }
        }

        $copied = $this->copyDirectory($sourceDir, $targetDir);

        if ($copied > 0) {
            return [
                'success' => true,
                'message' => "Copied {$copied} AI guideline files to .ai/",
            ];
        }

        return [
            'success' => true,
            'message' => 'AI guidelines already up to date',
        ];
    }

    /**
     * Recursively copy a directory.
     */
    private function copyDirectory(string $source, string $target): int
    {
        $copied = 0;

        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($source, \RecursiveDirectoryIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::SELF_FIRST
        );

        foreach ($iterator as $item) {
            $relativePath = substr($item->getPathname(), strlen($source) + 1);
            $targetPath = $target . '/' . $relativePath;

            if ($item->isDir()) {
                if (!is_dir($targetPath)) {
                    mkdir($targetPath, 0755, true);
                }
            } else {
                // Only copy if target doesn't exist or is older
                if (!file_exists($targetPath) || filemtime($item->getPathname()) > filemtime($targetPath)) {
                    copy($item->getPathname(), $targetPath);
                    $copied++;
                }
            }
        }

        return $copied;
    }

    /**
     * Get the package directory (where wordpress-boost is installed).
     */
    private function getPackageDir(): string
    {
        // This file is at src/Commands/InstallCommand.php
        // Package root is two directories up
        return dirname(__DIR__, 2);
    }

    /**
     * Get instructions for next steps after installation.
     */
    public static function getNextStepsMessage(): string
    {
        return <<<MSG

Next steps:

  For Cursor/VS Code/Windsurf:
    Your editor will auto-detect the .mcp.json file.
    Just open the project and the MCP server will be available.

  For Claude Code:
    Run: claude mcp add wordpress-boost -- php vendor/bin/wp-boost

MSG;
    }
}
