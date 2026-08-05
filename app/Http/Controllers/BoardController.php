<?php

namespace App\Http\Controllers;

use App\Services\BacklogIssueService;
use App\Support\BacklogApiKeyResolver;
use App\Support\BacklogProjectResolver;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Inertia\Inertia;
use Inertia\Response;

class BoardController extends Controller
{
    public function index(Request $request): Response
    {
        $apiKey = BacklogApiKeyResolver::resolve($request);

        return Inertia::render('Board/Index', [
            'has_api_key' => $apiKey !== null,
        ]);
    }

    public function issues(Request $request, BacklogIssueService $backlogIssueService): JsonResponse
    {
        $apiKey = BacklogApiKeyResolver::resolve($request);

        if ($apiKey === null) {
            return response()->json(['message' => 'Missing Backlog API key.'], 401);
        }

        $forceRefresh = $request->boolean('force');
        $projectIds = BacklogProjectResolver::resolve($request);
        $cacheKey = $this->cacheKey($apiKey, $projectIds);

        if (! $forceRefresh) {
            $cached = Cache::get($cacheKey);

            if (is_array($cached)) {
                return response()->json(array_merge($cached, [
                    'from_cache' => true,
                ]));
            }
        } else {
            Cache::forget($cacheKey);
        }

        $board = $backlogIssueService->getMyBoard($apiKey, $projectIds);

        $payload = [
            'myself' => $board['myself'],
            'columns' => $board['columns'],
            'issues' => $board['issues'],
            'scoped_project_ids' => $projectIds ?? [],
            'fetched_at' => now()->toIso8601String(),
            'from_cache' => false,
        ];

        Cache::put($cacheKey, $payload, now()->addMinutes(5));

        return response()->json($payload);
    }

    /**
     * @param  array<int, int>|null  $projectIds
     */
    private function cacheKey(string $apiKey, ?array $projectIds): string
    {
        $projectPart = $projectIds === null
            ? 'all'
            : implode(',', $projectIds);

        return 'backlog.board.'.hash('sha256', $apiKey.'|'.$projectPart);
    }
}
