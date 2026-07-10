<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;

class DailyHoursCacheService
{
    /**
     * @param  array<string, array<int, array<string, mixed>>>  $activityChanges
     */
    public function buildSignature(array $activityChanges, ?string $timezone = null): string
    {
        if ($activityChanges === []) {
            return 'empty';
        }

        $parts = [];

        ksort($activityChanges);

        foreach ($activityChanges as $issueKey => $changes) {
            foreach ($changes as $change) {
                $parts[] = implode('|', [
                    $issueKey,
                    $change['before'] ?? 0,
                    $change['after'] ?? 0,
                    $change['changed_at'] ?? '',
                    $change['activity_id'] ?? '',
                ]);
            }
        }

        sort($parts);

        return hash('sha256', implode("\n", $parts));
    }

    /**
     * @param  array<int, int>|null  $projectIds
     * @return array{items: array<int, array<string, mixed>>, signature: string, fetched_at: string}|null
     */
    public function get(string $apiKey, string $date, ?array $projectIds, string $signature, ?string $timezone = null, ?int $userId = null): ?array
    {
        $cached = Cache::get($this->cacheKey($apiKey, $date, $projectIds, $timezone, $userId));

        if (! is_array($cached) || ($cached['signature'] ?? null) !== $signature) {
            return null;
        }

        return $cached;
    }

    /**
     * @param  array<int, int>|null  $projectIds
     * @param  array<int, array<string, mixed>>  $items
     */
    public function put(string $apiKey, string $date, ?array $projectIds, string $signature, array $items, ?string $timezone = null, ?int $userId = null): void
    {
        Cache::put($this->cacheKey($apiKey, $date, $projectIds, $timezone, $userId), [
            'items' => $items,
            'signature' => $signature,
            'fetched_at' => now()->toIso8601String(),
        ], now()->addDay());
    }

    /**
     * @param  array<int, int>|null  $projectIds
     */
    private function cacheKey(string $apiKey, string $date, ?array $projectIds, ?string $timezone = null, ?int $userId = null): string
    {
        $projectKey = 'all';

        if ($projectIds !== null) {
            $sorted = $projectIds;
            sort($sorted);
            $projectKey = implode(',', $sorted);
        }

        $timezoneKey = is_string($timezone) && $timezone !== '' ? $timezone : 'UTC';
        $userKey = $userId !== null && $userId > 0 ? (string) $userId : 'self';

        return 'backlog.daily_hours.'
            .hash('sha256', trim($apiKey))
            .'.'.$date
            .'.'.hash('sha256', $projectKey)
            .'.'.hash('sha256', $timezoneKey)
            .'.'.$userKey;
    }
}
