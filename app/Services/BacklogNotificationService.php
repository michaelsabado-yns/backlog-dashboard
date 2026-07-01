<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

/**
 * Read-only Backlog notification client.
 *
 * Only uses GET /api/v2/notifications and GET /api/v2/notifications/count.
 * Never marks notifications as read or resets unread counts.
 */
class BacklogNotificationService
{
    private const CACHE_KEY_PREFIX = 'backlog.notifications';

    private ?int $lastCurrentUnreadCount = null;

    private bool $lastServedFromCache = false;

    /**
     * @return array<int, array<string, mixed>>
     */
    public function getNotifications(int $count = 100, bool $forceRefresh = false): array
    {
        return $this->syncNotifications($count, $forceRefresh)['data'];
    }

    public function getLastFetchedAt(int $count = 100): string
    {
        $cached = Cache::get($this->cacheKey($count));

        return is_array($cached) ? ($cached['fetched_at'] ?? now()->toIso8601String()) : now()->toIso8601String();
    }

    public function getCachedUnreadCount(int $count = 100): int
    {
        return $this->lastCurrentUnreadCount ?? $this->getUnreadCount();
    }

    public function servedFromCache(): bool
    {
        return $this->lastServedFromCache;
    }

    public function getUnreadCount(): int
    {
        $response = Http::get(
            config('backlog.url').'/api/v2/notifications/count',
            [
                'apiKey' => config('backlog.api_key'),
                'alreadyRead' => false,
            ],
        );

        if (! $response->successful()) {
            return 0;
        }

        return (int) ($response->json('count') ?? 0);
    }

    /**
     * @return array{data: array<int, array<string, mixed>>, cached_unread_count: int, fetched_at: string, from_cache: bool}
     */
    private function syncNotifications(int $count, bool $forceRefresh): array
    {
        $cacheKey = $this->cacheKey($count);
        $cached = Cache::get($cacheKey);
        $cachedPayload = is_array($cached) ? $cached : null;

        $currentUnread = $this->getUnreadCount();
        $this->lastCurrentUnreadCount = $currentUnread;

        $cachedUnread = (int) ($cachedPayload['cached_unread_count'] ?? -1);
        $shouldUseCache = ! $forceRefresh
            && $cachedPayload !== null
            && $currentUnread <= $cachedUnread;

        if ($shouldUseCache) {
            $this->lastServedFromCache = true;

            return [
                'data' => $cachedPayload['data'],
                'cached_unread_count' => $currentUnread,
                'fetched_at' => $cachedPayload['fetched_at'],
                'from_cache' => true,
            ];
        }

        $this->lastServedFromCache = false;

        $data = $this->resolveNotificationData($count, $cachedPayload, $currentUnread, $cachedUnread, $forceRefresh);

        $payload = [
            'data' => $data,
            'cached_unread_count' => $currentUnread,
            'fetched_at' => now()->toIso8601String(),
        ];

        Cache::forever($cacheKey, $payload);

        return [
            'data' => $data,
            'cached_unread_count' => $currentUnread,
            'fetched_at' => $payload['fetched_at'],
            'from_cache' => false,
        ];
    }

    /**
     * @param  array<string, mixed>|null  $cachedPayload
     * @return array<int, array<string, mixed>>
     */
    private function resolveNotificationData(
        int $count,
        ?array $cachedPayload,
        int $currentUnread,
        int $cachedUnread,
        bool $forceRefresh,
    ): array {
        if ($forceRefresh || $cachedPayload === null) {
            return $this->fetchFromApi($count);
        }

        if ($currentUnread > $cachedUnread) {
            $delta = $currentUnread - $cachedUnread;
            $incremental = $this->fetchIncremental($cachedPayload['data'] ?? [], min($delta, $count), $count);

            if ($incremental !== null) {
                return $incremental;
            }
        }

        return $this->fetchFromApi($count);
    }

    /**
     * @param  array<int, array<string, mixed>>  $cachedData
     * @return array<int, array<string, mixed>>|null
     */
    private function fetchIncremental(array $cachedData, int $fetchCount, int $maxItems): ?array
    {
        $maxId = $this->resolveMaxNotificationId($cachedData);

        if ($maxId === null) {
            return null;
        }

        $response = Http::get(
            config('backlog.url').'/api/v2/notifications',
            [
                'apiKey' => config('backlog.api_key'),
                'minId' => $maxId + 1,
                'count' => max(1, min($fetchCount, 100)),
                'order' => 'asc',
            ],
        );

        if (! $response->successful()) {
            return null;
        }

        $newItems = $response->json() ?? [];

        if ($newItems === []) {
            return null;
        }

        $merged = $this->mergeNotifications($newItems, $cachedData, $maxItems);

        return $merged;
    }

    /**
     * @param  array<int, array<string, mixed>>  $newItems
     * @param  array<int, array<string, mixed>>  $cachedData
     * @return array<int, array<string, mixed>>
     */
    private function mergeNotifications(array $newItems, array $cachedData, int $maxItems): array
    {
        $combined = array_merge($newItems, $cachedData);

        return array_slice($this->dedupeById($combined), 0, $maxItems);
    }

    /**
     * @param  array<int, array<string, mixed>>  $notifications
     * @return array<int, array<string, mixed>>
     */
    private function dedupeById(array $notifications): array
    {
        $seen = [];
        $result = [];

        foreach ($notifications as $notification) {
            $id = $notification['id'] ?? null;

            if ($id === null || isset($seen[$id])) {
                continue;
            }

            $seen[$id] = true;
            $result[] = $notification;
        }

        usort($result, fn (array $a, array $b) => ($b['id'] ?? 0) <=> ($a['id'] ?? 0));

        return $result;
    }

    /**
     * @param  array<int, array<string, mixed>>  $notifications
     */
    private function resolveMaxNotificationId(array $notifications): ?int
    {
        $ids = array_filter(array_map(
            fn (array $notification) => isset($notification['id']) ? (int) $notification['id'] : null,
            $notifications,
        ));

        if ($ids === []) {
            return null;
        }

        return max($ids);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function fetchFromApi(int $count): array
    {
        $response = Http::get(
            config('backlog.url').'/api/v2/notifications',
            [
                'apiKey' => config('backlog.api_key'),
                'count' => $count,
            ],
        );

        if (! $response->successful()) {
            return [];
        }

        return $response->json() ?? [];
    }

    private function cacheKey(int $count): string
    {
        return self::CACHE_KEY_PREFIX.'.'.$count;
    }
}
