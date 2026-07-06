<?php

namespace App\Services;

use Carbon\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

/**
 * Read-only Backlog activity client.
 *
 * Uses GET /api/v2/users/:userId/activities for the current user's updates.
 */
class BacklogActivityService
{
    private const ISSUE_UPDATE_TYPES = [2, 14];

    /**
     * @param  array<int, int>|null  $projectIds
     * @return array<string, array<int, array<string, mixed>>>
     */
    public function getActualHoursChangesByIssueKey(
        string $apiKey,
        BacklogProjectService $projectService,
        ?array $projectIds,
        string $date,
        ?string $timezone = null,
    ): array {
        unset($projectService);

        $trimmedApiKey = trim($apiKey);

        if ($trimmedApiKey === '' || $date === '') {
            return [];
        }

        if ($projectIds !== null && $projectIds === []) {
            return [];
        }

        $user = $this->getMyself($trimmedApiKey);

        if ($user === null) {
            return [];
        }

        $allowedProjectIds = $projectIds !== null ? array_flip($projectIds) : null;
        $timezone = $this->resolveTimezone($timezone);

        [$changes] = $this->fetchUserActivitiesForDate(
            $trimmedApiKey,
            (int) $user['id'],
            $date,
            $timezone,
        );

        $grouped = [];

        foreach ($changes as $change) {
            $projectId = (int) ($change['project_id'] ?? 0);

            if ($allowedProjectIds !== null && ! isset($allowedProjectIds[$projectId])) {
                continue;
            }

            $issueKey = $change['issue_key'];
            $grouped[$issueKey] ??= [];
            $grouped[$issueKey][] = $change;
        }

        foreach ($grouped as $issueKey => $changesForIssue) {
            usort($changesForIssue, static fn (array $a, array $b): int => strcmp($a['changed_at'], $b['changed_at']));
            $grouped[$issueKey] = $changesForIssue;
        }

        return $grouped;
    }

    /**
     * @return array{
     *     history_starts_at: string|null,
     *     history_ends_at: string|null,
     *     earliest_activity_at: string|null
     * }
     */
    public function getActivityHistoryBounds(string $apiKey, ?string $timezone = null): array
    {
        $trimmedApiKey = trim($apiKey);

        if ($trimmedApiKey === '') {
            return [
                'history_starts_at' => null,
                'history_ends_at' => null,
                'earliest_activity_at' => null,
            ];
        }

        $timezone = $this->resolveTimezone($timezone);

        return Cache::remember(
            'backlog.activity_bounds.v2.'.hash('sha256', $trimmedApiKey).'.'.$timezone,
            now()->addHour(),
            fn () => $this->probeActivityBounds($trimmedApiKey, $timezone),
        );
    }

    public function isDateReachable(string $date, ?string $historyStartsAt): bool
    {
        if ($historyStartsAt !== null && $date < $historyStartsAt) {
            return false;
        }

        return true;
    }

    /**
     * @return array{
     *     history_starts_at: string|null,
     *     history_ends_at: string|null,
     *     earliest_activity_at: string|null
     * }
     */
    private function probeActivityBounds(string $apiKey, string $timezone): array
    {
        $user = $this->getMyself($apiKey);

        if ($user === null) {
            return [
                'history_starts_at' => null,
                'history_ends_at' => null,
                'earliest_activity_at' => null,
            ];
        }

        $batch = $this->fetchLatestActivities($apiKey, (int) $user['id']);
        $oldest = null;
        $newest = null;

        foreach ($batch as $activity) {
            $created = $activity['created'] ?? null;

            if (! is_string($created) || $created === '') {
                continue;
            }

            $createdDate = Carbon::parse($created)->timezone($timezone)->toDateString();

            if ($oldest === null || $createdDate < $oldest) {
                $oldest = $createdDate;
            }

            if ($newest === null || $createdDate > $newest) {
                $newest = $createdDate;
            }
        }

        return [
            // First selectable day is the day after the oldest activity in the
            // latest-100 batch, so the boundary day (likely incomplete) stays disabled.
            'history_starts_at' => $oldest !== null
                ? Carbon::createFromFormat('Y-m-d', $oldest, $timezone)->addDay()->toDateString()
                : null,
            'history_ends_at' => $newest,
            'earliest_activity_at' => $oldest,
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function fetchLatestActivities(string $apiKey, int $userId): array
    {
        $response = Http::get(
            rtrim((string) config('backlog.url'), '/').'/api/v2/users/'.$userId.'/activities',
            [
                'apiKey' => $apiKey,
                'count' => 100,
                'order' => 'desc',
                'activityTypeId' => self::ISSUE_UPDATE_TYPES,
            ],
        );

        if (! $response->successful()) {
            return [];
        }

        $batch = $response->json();

        return is_array($batch) ? $batch : [];
    }

    /**
     * @param  array<string, mixed>  $change
     */
    private function isTrackedHoursChange(array $change): bool
    {
        $field = (string) ($change['field'] ?? '');

        if ($field === 'actualHours') {
            return true;
        }

        if (($change['type'] ?? '') !== 'custom') {
            return false;
        }

        return $this->matchesSubActualHoursFieldName($field);
    }

    private function matchesSubActualHoursFieldName(string $name): bool
    {
        if (preg_match('/^actual hours \(sub\)$/i', $name) === 1) {
            return true;
        }

        if (preg_match('/^sub actual hours$/i', $name) === 1) {
            return true;
        }

        if (preg_match('/^actual hours \(sub\)/i', $name) === 1) {
            return true;
        }

        return preg_match('/^sub person in charge actual hours$/i', $name) === 1;
    }

    /**
     * @return array<string, mixed>|null
     */
    private function getMyself(string $apiKey): ?array
    {
        $response = Http::get(
            rtrim((string) config('backlog.url'), '/').'/api/v2/users/myself',
            ['apiKey' => $apiKey],
        );

        if (! $response->successful()) {
            return null;
        }

        $payload = $response->json();

        if (! is_array($payload) || ! isset($payload['id'])) {
            return null;
        }

        return $payload;
    }

    /**
     * @return array{0: array<int, array<string, mixed>>}
     */
    private function fetchUserActivitiesForDate(
        string $apiKey,
        int $userId,
        string $date,
        string $timezone,
    ): array {
        $results = [];

        foreach ($this->fetchLatestActivities($apiKey, $userId) as $activity) {
            $created = $activity['created'] ?? null;

            if (! is_string($created) || $created === '') {
                continue;
            }

            $createdDate = Carbon::parse($created)->timezone($timezone)->toDateString();

            if ($createdDate !== $date) {
                continue;
            }

            $issueKey = $this->resolveIssueKey($activity);

            if ($issueKey === null) {
                continue;
            }

            foreach ($activity['content']['changes'] ?? [] as $change) {
                if (! $this->isTrackedHoursChange($change)) {
                    continue;
                }

                $before = $this->parseHoursValue($change['old_value'] ?? null);
                $after = $this->parseHoursValue($change['new_value'] ?? null);

                if ($before === $after) {
                    continue;
                }

                $field = (string) ($change['field'] ?? 'actualHours');

                $results[] = [
                    'issue_key' => $issueKey,
                    'project_id' => (int) ($activity['project']['id'] ?? 0),
                    'summary' => is_string($activity['content']['summary'] ?? null)
                        ? $activity['content']['summary']
                        : '',
                    'field' => $field,
                    'field_kind' => $field === 'actualHours' ? 'actual_hours' : 'sub_actual_hours',
                    'before' => $before,
                    'after' => $after,
                    'worked_hours' => max(0, $after - $before),
                    'changed_at' => $created,
                    'changed_by' => $activity['createdUser']['name'] ?? 'Unknown',
                    'activity_id' => $activity['id'] ?? null,
                    'source' => 'user_activity',
                ];
            }
        }

        return [$results];
    }

    /**
     * @param  array<string, mixed>  $activity
     */
    private function resolveIssueKey(array $activity): ?string
    {
        $projectKey = $activity['project']['projectKey'] ?? null;
        $keyId = $activity['content']['key_id'] ?? null;

        if (! is_string($projectKey) || $projectKey === '' || $keyId === null) {
            return null;
        }

        return $projectKey.'-'.$keyId;
    }

    private function parseHoursValue(mixed $value): float
    {
        if ($value === null || $value === '') {
            return 0.0;
        }

        return (float) $value;
    }

    private function resolveTimezone(?string $timezone): string
    {
        if (! is_string($timezone) || trim($timezone) === '') {
            return (string) config('app.timezone', 'UTC');
        }

        try {
            new \DateTimeZone($timezone);

            return $timezone;
        } catch (\Exception) {
            return (string) config('app.timezone', 'UTC');
        }
    }
}
