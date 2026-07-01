<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

/**
 * Read-only Backlog notification client.
 *
 * Only uses GET /api/v2/notifications. Never marks notifications as read
 * or resets unread counts.
 */
class BacklogNotificationService
{
    private const CACHE_KEY_PREFIX = 'backlog.notifications';

    /**
     * @return array<int, array<string, mixed>>
     */
    public function getNotifications(int $count = 100): array
    {
        return $this->getCachedPayload($count)['data'];
    }

    public function getLastFetchedAt(int $count = 100): string
    {
        return $this->getCachedPayload($count)['fetched_at'];
    }

    public function getCacheExpiresAt(int $count = 100): string
    {
        return $this->getCachedPayload($count)['expires_at'];
    }

    /**
     * @return array{data: array<int, array<string, mixed>>, fetched_at: string, expires_at: string}
     */
    private function getCachedPayload(int $count): array
    {
        $ttlSeconds = max(1, (int) config('backlog.notifications_cache_ttl', 60));

        return Cache::remember(
            $this->cacheKey($count),
            now()->addSeconds($ttlSeconds),
            fn () => [
                'data' => $this->fetchFromApi($count),
                'fetched_at' => now()->toIso8601String(),
                'expires_at' => now()->addSeconds($ttlSeconds)->toIso8601String(),
            ],
        );
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
            ]
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
