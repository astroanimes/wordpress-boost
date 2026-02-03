<?php

declare(strict_types=1);

namespace WordPressBoost\Tools;

/**
 * Base Tool
 *
 * Abstract base class for all WordPress Boost tools.
 */
abstract class BaseTool
{
    /**
     * Get tool definitions for MCP
     *
     * @return array Array of tool definitions
     */
    abstract public function getToolDefinitions(): array;

    /**
     * Check if this tool handles the given method
     *
     * @param string $name The tool name
     * @return bool
     */
    abstract public function handles(string $name): bool;

    /**
     * Execute a tool method
     *
     * @param string $name The tool name
     * @param array $arguments The tool arguments
     * @return mixed The result
     */
    abstract public function execute(string $name, array $arguments): mixed;

    /**
     * Create a tool definition
     *
     * @param string $name Tool name
     * @param string $description Tool description
     * @param array $properties Input properties schema
     * @param array $required Required properties
     * @return array
     */
    protected function createToolDefinition(
        string $name,
        string $description,
        array $properties = [],
        array $required = []
    ): array {
        $definition = [
            'name' => $name,
            'description' => $description,
        ];

        if (!empty($properties)) {
            $definition['inputSchema'] = [
                'type' => 'object',
                'properties' => $properties,
            ];

            if (!empty($required)) {
                $definition['inputSchema']['required'] = $required;
            }
        }

        return $definition;
    }

    /**
     * Format bytes to human-readable string
     */
    protected function formatBytes(int $bytes): string
    {
        $units = ['B', 'KB', 'MB', 'GB'];
        $i = 0;
        while ($bytes >= 1024 && $i < count($units) - 1) {
            $bytes /= 1024;
            $i++;
        }
        return round($bytes, 2) . ' ' . $units[$i];
    }

    /**
     * Check if we're in debug mode
     */
    protected function isDebugMode(): bool
    {
        return defined('WP_DEBUG') && WP_DEBUG;
    }
}
