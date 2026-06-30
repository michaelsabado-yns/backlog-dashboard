<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;

/**
 * Read-only Backlog notification client.
 *
 * Only uses GET /api/v2/notifications. Never marks notifications as read
 * or resets unread counts.
 */
class BacklogNotificationService
{
    /**
     * @return array<int, array<string, mixed>>
     */
    public function getNotifications(int $count = 100): array
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
}