<?php

namespace App\Http\Controllers;

use App\Services\BacklogActivityService;
use App\Services\BacklogNotificationService;
use App\Services\BacklogProjectService;
use App\Services\DailyHoursCacheService;
use App\Services\NotificationTransformer;
use App\Support\BacklogApiKeyResolver;
use App\Support\BacklogProjectResolver;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class DailyHoursTrackerController extends Controller
{
    public function index(Request $request): Response
    {
        $apiKey = BacklogApiKeyResolver::resolve($request);

        return Inertia::render('DailyHoursTracker/Index', [
            'has_api_key' => $apiKey !== null,
        ]);
    }

    public function myIssues(
        Request $request,
        BacklogActivityService $backlogActivityService,
        BacklogProjectService $backlogProjectService,
        DailyHoursCacheService $dailyHoursCacheService,
    ): JsonResponse {
        $apiKey = BacklogApiKeyResolver::resolve($request);

        if ($apiKey === null) {
            return response()->json(['message' => 'Missing Backlog API key.'], 401);
        }

        $validated = $request->validate([
            'date' => ['nullable', 'date_format:Y-m-d'],
            'signature' => ['nullable', 'string', 'max:128'],
            'force' => ['nullable', 'boolean'],
            'timezone' => ['nullable', 'timezone'],
        ]);

        $date = $validated['date'] ?? now()->toDateString();
        $forceRefresh = (bool) ($validated['force'] ?? false);
        $clientSignature = $validated['signature'] ?? null;
        $timezone = $validated['timezone'] ?? null;
        $projectIds = BacklogProjectResolver::resolve($request);

        $activityChanges = $backlogActivityService->getActualHoursChangesByIssueKey(
            $apiKey,
            $backlogProjectService,
            $projectIds,
            $date,
            $timezone,
        );
        $historyBounds = $backlogActivityService->getActivityHistoryBounds($apiKey, $timezone);
        $signature = $dailyHoursCacheService->buildSignature($activityChanges, $timezone);
        $dateMeta = $this->buildDateMetadata($date, $historyBounds, $backlogActivityService);

        if (! $forceRefresh) {
            $cached = $dailyHoursCacheService->get($apiKey, $date, $projectIds, $signature, $timezone);

            if ($cached !== null || ($clientSignature !== null && $clientSignature === $signature)) {
                $payload = $cached ?? [
                    'items' => [],
                    'signature' => $signature,
                    'fetched_at' => now()->toIso8601String(),
                ];

                return response()->json(array_merge([
                    'items' => $payload['items'],
                    'fetched_at' => $payload['fetched_at'],
                    'date' => $date,
                    'signature' => $signature,
                    'from_cache' => true,
                    'change_count' => $this->countHourChanges($activityChanges),
                    'scoped_project_ids' => $this->resolveScopedProjectIds($backlogProjectService, $apiKey, $projectIds),
                ], $dateMeta));
            }
        }

        $baseUrl = rtrim((string) config('backlog.url'), '/');
        $items = [];

        foreach ($activityChanges as $issueKey => $changes) {
            if ($changes === []) {
                continue;
            }

            $item = $this->buildDailyItemFromActivity($issueKey, $changes, $baseUrl);

            if ((float) ($item['worked_hours'] ?? 0) <= 0) {
                continue;
            }

            $items[] = $item;
        }

        usort($items, static fn (array $a, array $b): int => ($b['worked_hours'] <=> $a['worked_hours'])
            ?: strcmp($a['issue_key'], $b['issue_key']));

        $fetchedAt = now()->toIso8601String();
        $dailyHoursCacheService->put($apiKey, $date, $projectIds, $signature, $items, $timezone);

        return response()->json(array_merge([
            'items' => $items,
            'fetched_at' => $fetchedAt,
            'date' => $date,
            'signature' => $signature,
            'from_cache' => false,
            'change_count' => $this->countHourChanges($activityChanges),
            'scoped_project_ids' => $this->resolveScopedProjectIds($backlogProjectService, $apiKey, $projectIds),
        ], $dateMeta));
    }

    public function dateBounds(
        Request $request,
        BacklogActivityService $backlogActivityService,
    ): JsonResponse {
        $apiKey = BacklogApiKeyResolver::resolve($request);

        if ($apiKey === null) {
            return response()->json(['message' => 'Missing Backlog API key.'], 401);
        }

        $validated = $request->validate([
            'timezone' => ['nullable', 'timezone'],
        ]);

        $timezone = $validated['timezone'] ?? null;
        $bounds = $backlogActivityService->getActivityHistoryBounds($apiKey, $timezone);
        $tz = $timezone ?: (string) config('app.timezone', 'UTC');

        return response()->json(array_merge($bounds, [
            'max_date' => now()->timezone($tz)->toDateString(),
            'fetched_at' => now()->toIso8601String(),
        ]));
    }

    /**
     * @param  array{
     *     history_starts_at: string|null,
     *     history_ends_at: string|null,
     *     earliest_activity_at: string|null
     * }  $historyBounds
     * @return array<string, mixed>
     */
    private function buildDateMetadata(
        string $date,
        array $historyBounds,
        BacklogActivityService $backlogActivityService,
    ): array {
        $historyStartsAt = $historyBounds['history_starts_at'] ?? null;
        $historyEndsAt = $historyBounds['history_ends_at'] ?? null;
        $dateReachable = $backlogActivityService->isDateReachable($date, $historyStartsAt);

        return [
            'history_starts_at' => $historyStartsAt,
            'history_ends_at' => $historyEndsAt,
            'earliest_activity_at' => $historyBounds['earliest_activity_at'] ?? null,
            'date_reachable' => $dateReachable,
            'before_history' => $historyStartsAt !== null && $date < $historyStartsAt,
            'empty_reason' => $dateReachable ? 'no_hours_on_date' : 'before_history',
        ];
    }

    /**
     * @param  array<int, int>|null  $projectIds
     * @return array<int, int>
     */
    private function resolveScopedProjectIds(
        BacklogProjectService $backlogProjectService,
        string $apiKey,
        ?array $projectIds,
    ): array {
        $projects = $backlogProjectService->getProjectMappings($apiKey, $projectIds);

        return array_values(array_map(
            static fn (array $project): int => (int) $project['id'],
            array_filter($projects, static fn (array $project): bool => ($project['archived'] ?? false) !== true),
        ));
    }

    /**
     * @param  array<string, array<int, array<string, mixed>>>  $activityChanges
     */
    private function countHourChanges(array $activityChanges): int
    {
        $count = 0;

        foreach ($activityChanges as $changes) {
            $count += count($changes);
        }

        return $count;
    }

    /**
     * @param  array<int, array<string, mixed>>  $changes
     * @return array<string, mixed>
     */
    private function buildDailyItemFromActivity(string $issueKey, array $changes, string $baseUrl): array
    {
        $first = $changes[0];
        $last = $changes[count($changes) - 1];

        return [
            'issue_key' => $issueKey,
            'summary' => is_string($last['summary'] ?? null) && $last['summary'] !== ''
                ? $last['summary']
                : $issueKey,
            'actual_hours' => (float) $last['after'],
            'backlog_url' => $baseUrl.'/view/'.$issueKey,
            'updated_at' => $last['changed_at'],
            'previous_hours' => (float) $first['before'],
            'current_hours' => (float) $last['after'],
            'worked_hours' => array_sum(array_map(
                static fn (array $change): float => max(0, (float) $change['after'] - (float) $change['before']),
                $changes,
            )),
            'hour_changes' => array_map(static fn (array $change): array => [
                'before' => $change['before'],
                'after' => $change['after'],
                'changed_at' => $change['changed_at'],
                'changed_by' => $change['changed_by'],
                'field' => $change['field'] ?? 'actualHours',
                'field_kind' => $change['field_kind'] ?? 'actual_hours',
                'source' => $change['source'] ?? 'user_activity',
            ], $changes),
        ];
    }

    public function notifications(
        Request $request,
        BacklogNotificationService $backlogNotificationService,
        NotificationTransformer $notificationTransformer,
    ): JsonResponse {
        $apiKey = BacklogApiKeyResolver::resolve($request);

        if ($apiKey === null) {
            return response()->json(['message' => 'Missing Backlog API key.'], 401);
        }

        $validated = $request->validate([
            'date' => ['required', 'date_format:Y-m-d'],
        ]);

        $notifications = collect(
            $notificationTransformer->transform(
                $backlogNotificationService->getNotifications($apiKey),
            ),
        )
            ->filter(function (array $notification) use ($validated) {
                if ($notification['issue_key'] === null || $notification['created_at'] === null) {
                    return false;
                }

                return Carbon::parse($notification['created_at'])->toDateString() === $validated['date'];
            })
            ->values()
            ->all();

        return response()->json([
            'notifications' => $notifications,
            'fetched_at' => now()->toIso8601String(),
            'date' => $validated['date'],
        ]);
    }
}
