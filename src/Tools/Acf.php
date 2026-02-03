<?php

declare(strict_types=1);

namespace WordPressBoost\Tools;

/**
 * ACF (Advanced Custom Fields) Tool
 *
 * Provides introspection into ACF field groups and fields.
 */
class Acf extends BaseTool
{
    public function getToolDefinitions(): array
    {
        return [
            $this->createToolDefinition(
                'list_acf_field_groups',
                'List all registered ACF field groups',
                [
                    'status' => [
                        'type' => 'string',
                        'description' => 'Filter by status: publish, draft, trash, all',
                        'enum' => ['publish', 'draft', 'trash', 'all'],
                    ],
                ]
            ),
            $this->createToolDefinition(
                'list_acf_fields',
                'List all fields within a specific ACF field group',
                [
                    'group' => [
                        'type' => 'string',
                        'description' => 'Field group key (e.g., group_123abc) or title',
                    ],
                ],
                ['group']
            ),
            $this->createToolDefinition(
                'get_acf_schema',
                'Get the complete ACF schema for code generation',
                [
                    'group' => [
                        'type' => 'string',
                        'description' => 'Specific field group key or title (optional, returns all if not specified)',
                    ],
                    'include_locations' => [
                        'type' => 'boolean',
                        'description' => 'Include location rules in output (default: true)',
                    ],
                ]
            ),
            $this->createToolDefinition(
                'get_acf_field',
                'Get detailed information about a specific ACF field',
                [
                    'field' => [
                        'type' => 'string',
                        'description' => 'Field key (e.g., field_123abc) or field name',
                    ],
                ],
                ['field']
            ),
        ];
    }

    public function handles(string $name): bool
    {
        return in_array($name, ['list_acf_field_groups', 'list_acf_fields', 'get_acf_schema', 'get_acf_field']);
    }

    public function execute(string $name, array $arguments): mixed
    {
        // Check if ACF is active
        if (!$this->isAcfActive()) {
            return [
                'error' => 'ACF (Advanced Custom Fields) is not active.',
                'suggestion' => 'Install and activate ACF to use these tools.',
            ];
        }

        return match ($name) {
            'list_acf_field_groups' => $this->listFieldGroups($arguments['status'] ?? 'publish'),
            'list_acf_fields' => $this->listFields($arguments['group']),
            'get_acf_schema' => $this->getSchema(
                $arguments['group'] ?? null,
                $arguments['include_locations'] ?? true
            ),
            'get_acf_field' => $this->getField($arguments['field']),
            default => throw new \RuntimeException("Unknown tool: {$name}"),
        };
    }

    private function isAcfActive(): bool
    {
        return class_exists('ACF') || function_exists('acf_get_field_groups');
    }

    private function listFieldGroups(string $status = 'publish'): array
    {
        $args = [];

        if ($status !== 'all') {
            $args['post_status'] = $status;
        }

        $groups = acf_get_field_groups($args);

        $result = [];
        foreach ($groups as $group) {
            $fieldCount = 0;
            $fields = acf_get_fields($group['key']);
            if ($fields) {
                $fieldCount = count($fields);
            }

            $result[] = [
                'key' => $group['key'],
                'title' => $group['title'],
                'active' => $group['active'],
                'menu_order' => $group['menu_order'],
                'position' => $group['position'],
                'style' => $group['style'],
                'label_placement' => $group['label_placement'],
                'instruction_placement' => $group['instruction_placement'],
                'field_count' => $fieldCount,
                'location_summary' => $this->getLocationSummary($group['location']),
            ];
        }

        return [
            'count' => count($result),
            'acf_version' => defined('ACF_VERSION') ? ACF_VERSION : 'unknown',
            'field_groups' => $result,
        ];
    }

    private function listFields(string $groupIdentifier): array
    {
        $group = $this->findFieldGroup($groupIdentifier);

        if ($group === null) {
            return [
                'error' => "Field group not found: {$groupIdentifier}",
                'suggestion' => 'Use list_acf_field_groups to see available groups',
            ];
        }

        $fields = acf_get_fields($group['key']);

        if (empty($fields)) {
            return [
                'group' => $group['title'],
                'key' => $group['key'],
                'count' => 0,
                'fields' => [],
            ];
        }

        $result = [];
        foreach ($fields as $field) {
            $result[] = $this->formatFieldInfo($field);
        }

        return [
            'group' => $group['title'],
            'key' => $group['key'],
            'count' => count($result),
            'fields' => $result,
        ];
    }

    private function getSchema(?string $groupIdentifier = null, bool $includeLocations = true): array
    {
        if ($groupIdentifier !== null) {
            $group = $this->findFieldGroup($groupIdentifier);

            if ($group === null) {
                return [
                    'error' => "Field group not found: {$groupIdentifier}",
                ];
            }

            return $this->formatGroupSchema($group, $includeLocations);
        }

        // Get all groups
        $groups = acf_get_field_groups();
        $result = [];

        foreach ($groups as $group) {
            if ($group['active']) {
                $result[] = $this->formatGroupSchema($group, $includeLocations);
            }
        }

        return [
            'count' => count($result),
            'acf_version' => defined('ACF_VERSION') ? ACF_VERSION : 'unknown',
            'schema' => $result,
        ];
    }

    private function getField(string $fieldIdentifier): array
    {
        // Try to get field by key first
        $field = acf_get_field($fieldIdentifier);

        if (!$field) {
            // Try to find by name
            $field = $this->findFieldByName($fieldIdentifier);
        }

        if (!$field) {
            return [
                'error' => "Field not found: {$fieldIdentifier}",
            ];
        }

        return $this->formatFieldDetails($field);
    }

    private function findFieldGroup(string $identifier): ?array
    {
        $groups = acf_get_field_groups();

        foreach ($groups as $group) {
            // Check by key
            if ($group['key'] === $identifier) {
                return $group;
            }

            // Check by title (case-insensitive)
            if (strtolower($group['title']) === strtolower($identifier)) {
                return $group;
            }
        }

        return null;
    }

    private function findFieldByName(string $name): ?array
    {
        $groups = acf_get_field_groups();

        foreach ($groups as $group) {
            $fields = acf_get_fields($group['key']);
            if ($fields) {
                $field = $this->searchFieldsRecursive($fields, $name);
                if ($field) {
                    return $field;
                }
            }
        }

        return null;
    }

    private function searchFieldsRecursive(array $fields, string $name): ?array
    {
        foreach ($fields as $field) {
            if ($field['name'] === $name) {
                return $field;
            }

            // Check sub_fields for groups/repeaters/flexible content
            if (!empty($field['sub_fields'])) {
                $found = $this->searchFieldsRecursive($field['sub_fields'], $name);
                if ($found) {
                    return $found;
                }
            }

            // Check layouts for flexible content
            if (!empty($field['layouts'])) {
                foreach ($field['layouts'] as $layout) {
                    if (!empty($layout['sub_fields'])) {
                        $found = $this->searchFieldsRecursive($layout['sub_fields'], $name);
                        if ($found) {
                            return $found;
                        }
                    }
                }
            }
        }

        return null;
    }

    private function formatFieldInfo(array $field): array
    {
        $info = [
            'key' => $field['key'],
            'name' => $field['name'],
            'label' => $field['label'],
            'type' => $field['type'],
            'required' => $field['required'] ?? false,
        ];

        // Add type-specific info
        switch ($field['type']) {
            case 'text':
            case 'textarea':
            case 'email':
            case 'url':
            case 'password':
                if (!empty($field['placeholder'])) {
                    $info['placeholder'] = $field['placeholder'];
                }
                if (!empty($field['maxlength'])) {
                    $info['maxlength'] = $field['maxlength'];
                }
                break;

            case 'number':
            case 'range':
                if (isset($field['min'])) {
                    $info['min'] = $field['min'];
                }
                if (isset($field['max'])) {
                    $info['max'] = $field['max'];
                }
                if (isset($field['step'])) {
                    $info['step'] = $field['step'];
                }
                break;

            case 'select':
            case 'checkbox':
            case 'radio':
            case 'button_group':
                if (!empty($field['choices'])) {
                    $info['choices'] = $field['choices'];
                }
                if (isset($field['multiple'])) {
                    $info['multiple'] = $field['multiple'];
                }
                break;

            case 'image':
            case 'file':
            case 'gallery':
                $info['return_format'] = $field['return_format'] ?? 'array';
                if (!empty($field['mime_types'])) {
                    $info['mime_types'] = $field['mime_types'];
                }
                break;

            case 'post_object':
            case 'relationship':
                if (!empty($field['post_type'])) {
                    $info['post_type'] = $field['post_type'];
                }
                if (!empty($field['taxonomy'])) {
                    $info['taxonomy'] = $field['taxonomy'];
                }
                break;

            case 'taxonomy':
                if (!empty($field['taxonomy'])) {
                    $info['taxonomy'] = $field['taxonomy'];
                }
                $info['field_type'] = $field['field_type'] ?? 'checkbox';
                break;

            case 'repeater':
                if (!empty($field['sub_fields'])) {
                    $info['sub_fields'] = array_map([$this, 'formatFieldInfo'], $field['sub_fields']);
                }
                if (isset($field['min'])) {
                    $info['min'] = $field['min'];
                }
                if (isset($field['max'])) {
                    $info['max'] = $field['max'];
                }
                break;

            case 'group':
                if (!empty($field['sub_fields'])) {
                    $info['sub_fields'] = array_map([$this, 'formatFieldInfo'], $field['sub_fields']);
                }
                break;

            case 'flexible_content':
                if (!empty($field['layouts'])) {
                    $info['layouts'] = [];
                    foreach ($field['layouts'] as $layout) {
                        $layoutInfo = [
                            'key' => $layout['key'],
                            'name' => $layout['name'],
                            'label' => $layout['label'],
                        ];
                        if (!empty($layout['sub_fields'])) {
                            $layoutInfo['sub_fields'] = array_map([$this, 'formatFieldInfo'], $layout['sub_fields']);
                        }
                        $info['layouts'][] = $layoutInfo;
                    }
                }
                break;
        }

        return $info;
    }

    private function formatFieldDetails(array $field): array
    {
        $details = [
            'key' => $field['key'],
            'name' => $field['name'],
            'label' => $field['label'],
            'type' => $field['type'],
            'instructions' => $field['instructions'] ?? '',
            'required' => $field['required'] ?? false,
            'conditional_logic' => $field['conditional_logic'] ?? false,
            'wrapper' => $field['wrapper'] ?? [],
            'default_value' => $field['default_value'] ?? null,
        ];

        // Get parent info
        if (!empty($field['parent'])) {
            $parent = acf_get_field_group($field['parent']);
            if ($parent) {
                $details['parent_group'] = [
                    'key' => $parent['key'],
                    'title' => $parent['title'],
                ];
            }
        }

        // Add usage example
        $details['usage'] = $this->getFieldUsageExample($field);

        // Add all type-specific settings
        $typeSpecificKeys = array_diff(array_keys($field), [
            'key', 'name', 'label', 'type', 'instructions', 'required',
            'conditional_logic', 'wrapper', 'default_value', 'parent',
            'ID', 'prefix', 'menu_order', '_name', '_valid',
        ]);

        foreach ($typeSpecificKeys as $key) {
            if (!empty($field[$key])) {
                $details[$key] = $field[$key];
            }
        }

        return $details;
    }

    private function formatGroupSchema(array $group, bool $includeLocations): array
    {
        $schema = [
            'key' => $group['key'],
            'title' => $group['title'],
            'active' => $group['active'],
            'style' => $group['style'],
            'position' => $group['position'],
        ];

        if ($includeLocations) {
            $schema['location'] = $group['location'];
            $schema['location_summary'] = $this->getLocationSummary($group['location']);
        }

        $fields = acf_get_fields($group['key']);
        if ($fields) {
            $schema['fields'] = array_map([$this, 'formatFieldInfo'], $fields);
        } else {
            $schema['fields'] = [];
        }

        return $schema;
    }

    private function getLocationSummary(array $location): array
    {
        $summaries = [];

        foreach ($location as $group) {
            $parts = [];
            foreach ($group as $rule) {
                $param = $rule['param'];
                $operator = $rule['operator'];
                $value = $rule['value'];

                $parts[] = "{$param} {$operator} {$value}";
            }
            $summaries[] = implode(' AND ', $parts);
        }

        return $summaries;
    }

    private function getFieldUsageExample(array $field): array
    {
        $name = $field['name'];
        $type = $field['type'];

        $examples = [
            'get_field' => "get_field('{$name}')",
            'the_field' => "the_field('{$name}')",
        ];

        switch ($type) {
            case 'image':
                $examples['image_array'] = "// Returns array with url, alt, sizes, etc.\n\$image = get_field('{$name}');\nif (\$image) {\n    echo '<img src=\"' . \$image['url'] . '\" alt=\"' . \$image['alt'] . '\">';\n}";
                break;

            case 'repeater':
                $examples['repeater_loop'] = "if (have_rows('{$name}')) {\n    while (have_rows('{$name}')) {\n        the_row();\n        // get_sub_field('sub_field_name');\n    }\n}";
                break;

            case 'flexible_content':
                $examples['flexible_loop'] = "if (have_rows('{$name}')) {\n    while (have_rows('{$name}')) {\n        the_row();\n        if (get_row_layout() == 'layout_name') {\n            // get_sub_field('sub_field_name');\n        }\n    }\n}";
                break;

            case 'group':
                $examples['group_access'] = "\$group = get_field('{$name}');\n// Access: \$group['sub_field_name']";
                break;

            case 'relationship':
            case 'post_object':
                $examples['relationship'] = "\$posts = get_field('{$name}');\nif (\$posts) {\n    foreach (\$posts as \$post) {\n        setup_postdata(\$post);\n        the_title();\n    }\n    wp_reset_postdata();\n}";
                break;
        }

        return $examples;
    }
}
