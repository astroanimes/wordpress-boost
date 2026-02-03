<?php

declare(strict_types=1);

namespace WordPressBoost\Tools;

/**
 * Cron Events Tool
 *
 * Provides introspection into WordPress scheduled tasks (WP-Cron).
 */
class CronEvents extends BaseTool
{
    public function getToolDefinitions(): array
    {
        return [
            $this->createToolDefinition(
                'list_cron_events',
                'List all scheduled WP-Cron events',
                [
                    'hook' => [
                        'type' => 'string',
                        'description' => 'Filter by hook name',
                    ],
                ]
            ),
            $this->createToolDefinition(
                'get_cron_schedules',
                'List all registered cron schedules (intervals)'
            ),
            $this->createToolDefinition(
                'get_next_cron_event',
                'Get the next scheduled occurrence of a specific hook',
                [
                    'hook' => [
                        'type' => 'string',
                        'description' => 'The hook name to check',
                    ],
                ],
                ['hook']
            ),
        ];
    }

    public function handles(string $name): bool
    {
        return in_array($name, ['list_cron_events', 'get_cron_schedules', 'get_next_cron_event']);
    }

    public function execute(string $name, array $arguments): mixed
    {
        return match ($name) {
            'list_cron_events' => $this->listCronEvents($arguments['hook'] ?? null),
            'get_cron_schedules' => $this->getCronSchedules(),
            'get_next_cron_event' => $this->getNextCronEvent($arguments['hook']),
            default => throw new \RuntimeException("Unknown tool: {$name}"),
        };
    }

    private function listCronEvents(?string $hook = null): array
    {
        $crons = _get_cron_array();

        if (empty($crons)) {
            return [
                'count' => 0,
                'events' => [],
            ];
        }

        $events = [];
        $now = time();

        foreach ($crons as $timestamp => $cronHooks) {
            foreach ($cronHooks as $hookName => $hookEvents) {
                // Filter by hook name
                if ($hook !== null && $hookName !== $hook) {
                    continue;
                }

                foreach ($hookEvents as $key => $event) {
                    $events[] = [
                        'hook' => $hookName,
                        'timestamp' => $timestamp,
                        'datetime' => date('Y-m-d H:i:s', $timestamp),
                        'relative' => $this->getRelativeTime($timestamp, $now),
                        'schedule' => $event['schedule'] ?? false,
                        'interval' => $event['interval'] ?? null,
                        'args' => $event['args'] ?? [],
                        'key' => $key,
                        'is_overdue' => $timestamp < $now,
                    ];
                }
            }
        }

        // Sort by timestamp
        usort($events, fn($a, $b) => $a['timestamp'] <=> $b['timestamp']);

        // Count overdue
        $overdueCount = count(array_filter($events, fn($e) => $e['is_overdue']));

        return [
            'count' => count($events),
            'overdue_count' => $overdueCount,
            'current_time' => date('Y-m-d H:i:s', $now),
            'events' => $events,
        ];
    }

    private function getCronSchedules(): array
    {
        $schedules = wp_get_schedules();

        $result = [];
        foreach ($schedules as $name => $schedule) {
            $result[] = [
                'name' => $name,
                'display' => $schedule['display'],
                'interval' => $schedule['interval'],
                'interval_human' => $this->formatInterval($schedule['interval']),
            ];
        }

        // Sort by interval
        usort($result, fn($a, $b) => $a['interval'] <=> $b['interval']);

        return [
            'count' => count($result),
            'schedules' => $result,
        ];
    }

    private function getNextCronEvent(string $hookName): array
    {
        $crons = _get_cron_array();
        $now = time();

        if (empty($crons)) {
            return [
                'hook' => $hookName,
                'found' => false,
            ];
        }

        $nextEvent = null;

        foreach ($crons as $timestamp => $cronHooks) {
            if (isset($cronHooks[$hookName])) {
                foreach ($cronHooks[$hookName] as $key => $event) {
                    if ($nextEvent === null || $timestamp < $nextEvent['timestamp']) {
                        $nextEvent = [
                            'hook' => $hookName,
                            'found' => true,
                            'timestamp' => $timestamp,
                            'datetime' => date('Y-m-d H:i:s', $timestamp),
                            'relative' => $this->getRelativeTime($timestamp, $now),
                            'schedule' => $event['schedule'] ?? false,
                            'interval' => $event['interval'] ?? null,
                            'interval_human' => isset($event['interval']) ? $this->formatInterval($event['interval']) : null,
                            'args' => $event['args'] ?? [],
                            'is_overdue' => $timestamp < $now,
                        ];
                    }
                }
            }
        }

        if ($nextEvent === null) {
            // Check if the hook has any callbacks registered
            $hasCallbacks = has_action($hookName);

            return [
                'hook' => $hookName,
                'found' => false,
                'has_callbacks' => $hasCallbacks,
                'message' => $hasCallbacks
                    ? 'Hook has callbacks but no scheduled events.'
                    : 'No scheduled events and no callbacks found for this hook.',
            ];
        }

        return $nextEvent;
    }

    private function getRelativeTime(int $timestamp, int $now): string
    {
        $diff = $timestamp - $now;

        if ($diff < 0) {
            $diff = abs($diff);
            $suffix = 'ago';
        } else {
            $suffix = 'from now';
        }

        if ($diff < 60) {
            return $diff . ' seconds ' . $suffix;
        }

        if ($diff < 3600) {
            $minutes = floor($diff / 60);
            return $minutes . ' minute' . ($minutes > 1 ? 's' : '') . ' ' . $suffix;
        }

        if ($diff < 86400) {
            $hours = floor($diff / 3600);
            return $hours . ' hour' . ($hours > 1 ? 's' : '') . ' ' . $suffix;
        }

        $days = floor($diff / 86400);
        return $days . ' day' . ($days > 1 ? 's' : '') . ' ' . $suffix;
    }

    private function formatInterval(int $seconds): string
    {
        if ($seconds < 60) {
            return $seconds . ' seconds';
        }

        if ($seconds < 3600) {
            $minutes = floor($seconds / 60);
            return $minutes . ' minute' . ($minutes > 1 ? 's' : '');
        }

        if ($seconds < 86400) {
            $hours = floor($seconds / 3600);
            return $hours . ' hour' . ($hours > 1 ? 's' : '');
        }

        if ($seconds < 604800) {
            $days = floor($seconds / 86400);
            return $days . ' day' . ($days > 1 ? 's' : '');
        }

        $weeks = floor($seconds / 604800);
        return $weeks . ' week' . ($weeks > 1 ? 's' : '');
    }
}
