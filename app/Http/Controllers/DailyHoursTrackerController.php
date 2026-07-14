<?php

namespace App\Http\Controllers;

use App\Services\BacklogActivityService;
use App\Services\BacklogIssueService;
use App\Services\BacklogNotificationService;
use App\Services\BacklogProjectService;
use App\Services\BacklogUserService;
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
        BacklogIssueService $backlogIssueService,
        BacklogProjectService $backlogProjectService,
        BacklogUserService $backlogUserService,
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
            'user_id' => ['nullable', 'integer', 'min:1'],
        ]);

        $date = $validated['date'] ?? now()->toDateString();
        $forceRefresh = (bool) ($validated['force'] ?? false);
        $clientSignature = $validated['signature'] ?? null;
        $timezone = $validated['timezone'] ?? null;
        $projectIds = BacklogProjectResolver::resolve($request);
        $trackedUser = $this->resolveTrackedUser(
            $backlogUserService,
            $backlogProjectService,
            $apiKey,
            $projectIds,
            $validated['user_id'] ?? null,
        );

        if ($trackedUser === null) {
            return response()->json(['message' => 'Failed to resolve tracked user.'], 422);
        }

        $trackedUserId = (int) $trackedUser['id'];

        $activityChanges = $backlogActivityService->getActualHoursChangesByIssueKey(
            $apiKey,
            $backlogProjectService,
            $projectIds,
            $date,
            $timezone,
            $trackedUserId,
        );
        $historyBounds = $backlogActivityService->getActivityHistoryBounds($apiKey, $timezone, $trackedUserId);
        $signature = $dailyHoursCacheService->buildSignature($activityChanges, $timezone);
        $dateMeta = $this->buildDateMetadata($date, $historyBounds, $backlogActivityService);

        if (! $forceRefresh) {
            $cached = $dailyHoursCacheService->get($apiKey, $date, $projectIds, $signature, $timezone, $trackedUserId);

            if ($cached !== null || ($clientSignature !== null && $clientSignature === $signature)) {
                $payload = $cached ?? [
                    'items' => [],
                    'signature' => $signature,
                    'fetched_at' => now()->toIso8601String(),
                ];

                $items = $this->attachIssueStatuses(
                    $backlogIssueService,
                    $apiKey,
                    is_array($payload['items'] ?? null) ? $payload['items'] : [],
                );

                return response()->json(array_merge([
                    'items' => $items,
                    'fetched_at' => $payload['fetched_at'],
                    'date' => $date,
                    'signature' => $signature,
                    'from_cache' => true,
                    'change_count' => $this->countHourChanges($activityChanges),
                    'scoped_project_ids' => $this->resolveScopedProjectIds($backlogProjectService, $apiKey, $projectIds),
                    'tracked_user' => $trackedUser,
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

        usort($items, static function (array $a, array $b): int {
            $updatedCompare = strcmp((string) ($b['updated_at'] ?? ''), (string) ($a['updated_at'] ?? ''));

            if ($updatedCompare !== 0) {
                return $updatedCompare;
            }

            return strcmp($a['issue_key'], $b['issue_key']);
        });

        $items = $this->attachIssueStatuses($backlogIssueService, $apiKey, $items);

        $fetchedAt = now()->toIso8601String();
        $dailyHoursCacheService->put($apiKey, $date, $projectIds, $signature, $items, $timezone, $trackedUserId);

        return response()->json(array_merge([
            'items' => $items,
            'fetched_at' => $fetchedAt,
            'date' => $date,
            'signature' => $signature,
            'from_cache' => false,
            'change_count' => $this->countHourChanges($activityChanges),
            'scoped_project_ids' => $this->resolveScopedProjectIds($backlogProjectService, $apiKey, $projectIds),
            'tracked_user' => $trackedUser,
        ], $dateMeta));
    }

    public function users(
        Request $request,
        BacklogUserService $backlogUserService,
        BacklogProjectService $backlogProjectService,
    ): JsonResponse {
        $apiKey = BacklogApiKeyResolver::resolve($request);

        if ($apiKey === null) {
            return response()->json(['message' => 'Missing Backlog API key.'], 401);
        }

        $projectIds = BacklogProjectResolver::resolve($request);
        $myself = $backlogUserService->getMyself($apiKey);
        $users = $backlogUserService->getBrowsableUsers($apiKey, $backlogProjectService, $projectIds);

        return response()->json([
            'users' => $users,
            'myself' => $myself,
            'fetched_at' => now()->toIso8601String(),
        ]);
    }

    public function dateBounds(
        Request $request,
        BacklogActivityService $backlogActivityService,
        BacklogUserService $backlogUserService,
        BacklogProjectService $backlogProjectService,
    ): JsonResponse {
        $apiKey = BacklogApiKeyResolver::resolve($request);

        if ($apiKey === null) {
            return response()->json(['message' => 'Missing Backlog API key.'], 401);
        }

        $validated = $request->validate([
            'timezone' => ['nullable', 'timezone'],
            'user_id' => ['nullable', 'integer', 'min:1'],
        ]);

        $timezone = $validated['timezone'] ?? null;
        $projectIds = BacklogProjectResolver::resolve($request);
        $trackedUser = $this->resolveTrackedUser(
            $backlogUserService,
            $backlogProjectService,
            $apiKey,
            $projectIds,
            $validated['user_id'] ?? null,
        );

        if ($trackedUser === null) {
            return response()->json(['message' => 'Failed to resolve tracked user.'], 422);
        }

        $bounds = $backlogActivityService->getActivityHistoryBounds($apiKey, $timezone, (int) $trackedUser['id']);
        $tz = $timezone ?: (string) config('app.timezone', 'UTC');

        return response()->json(array_merge($bounds, [
            'max_date' => now()->timezone($tz)->toDateString(),
            'fetched_at' => now()->toIso8601String(),
            'tracked_user' => $trackedUser,
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
            'project_key' => is_string($last['project_key'] ?? null) ? $last['project_key'] : '',
            'project_name' => is_string($last['project_name'] ?? null) && $last['project_name'] !== ''
                ? $last['project_name']
                : (is_string($last['project_key'] ?? null) && $last['project_key'] !== ''
                    ? $last['project_key']
                    : $this->resolveProjectKeyFromIssueKey($issueKey)),
            'actual_hours' => (float) $last['after'],
            'backlog_url' => $baseUrl.'/view/'.$issueKey,
            'updated_at' => $last['changed_at'],
            'previous_hours' => (float) $first['before'],
            'current_hours' => (float) $last['after'],
            // Net for the day (includes corrections): 0→2 then 2→1 = +1, not +2.
            'worked_hours' => (float) $last['after'] - (float) $first['before'],
            'hour_changes' => array_map(static fn (array $change): array => [
                'before' => $change['before'],
                'after' => $change['after'],
                'changed_at' => $change['changed_at'],
                'changed_by' => $change['changed_by'],
                'field' => $change['field'] ?? 'actualHours',
                'field_kind' => $change['field_kind'] ?? 'actual_hours',
                'source' => $change['source'] ?? 'user_activity',
            ], $changes),
            'issue_status' => null,
            'issue_status_color' => null,
        ];
    }

    /**
     * @param  array<int, array<string, mixed>>  $items
     * @return array<int, array<string, mixed>>
     */
    private function attachIssueStatuses(
        BacklogIssueService $backlogIssueService,
        string $apiKey,
        array $items,
    ): array {
        if ($items === []) {
            return [];
        }

        $statuses = $backlogIssueService->getStatusesByIssueKeys(
            $apiKey,
            array_map(
                static fn (array $item): string => (string) ($item['issue_key'] ?? ''),
                $items,
            ),
        );

        foreach ($items as $index => $item) {
            $issueKey = (string) ($item['issue_key'] ?? '');
            $status = $statuses[$issueKey] ?? null;

            $items[$index]['issue_status'] = is_array($status)
                ? ($status['issue_status'] ?? null)
                : ($item['issue_status'] ?? null);
            $items[$index]['issue_status_color'] = is_array($status)
                ? ($status['issue_status_color'] ?? null)
                : ($item['issue_status_color'] ?? null);
        }

        return $items;
    }

    private function resolveProjectKeyFromIssueKey(string $issueKey): string
    {
        if (preg_match('/^(.+)-\d+$/', $issueKey, $matches) === 1) {
            return $matches[1];
        }

        return 'Unknown project';
    }

    /**
     * @param  array<int, int>|null  $projectIds
     * @return array{id: int, name: string, user_id: string}|null
     */
    private function resolveTrackedUser(
        BacklogUserService $backlogUserService,
        BacklogProjectService $backlogProjectService,
        string $apiKey,
        ?array $projectIds,
        ?int $requestedUserId,
    ): ?array {
        $myself = $backlogUserService->getMyself($apiKey);

        if ($myself === null) {
            return null;
        }

        if ($requestedUserId === null || $requestedUserId === (int) $myself['id']) {
            return $myself;
        }

        foreach ($backlogUserService->getBrowsableUsers($apiKey, $backlogProjectService, $projectIds) as $user) {
            if ((int) $user['id'] === $requestedUserId) {
                return $user;
            }
        }

        return null;
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
