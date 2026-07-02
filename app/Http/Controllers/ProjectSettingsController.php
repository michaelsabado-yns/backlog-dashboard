<?php

namespace App\Http\Controllers;

use App\Services\BacklogProjectService;
use App\Support\BacklogApiKeyResolver;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Inertia\Inertia;
use Inertia\Response;

class ProjectSettingsController extends Controller
{
    public function index(Request $request): Response
    {
        $apiKey = BacklogApiKeyResolver::resolve($request);

        return Inertia::render('ProjectSettings/Index', [
            'has_api_key' => $apiKey !== null,
        ]);
    }

    public function projects(
        Request $request,
        BacklogProjectService $projectService,
    ): JsonResponse {
        $apiKey = BacklogApiKeyResolver::resolve($request);

        if ($apiKey === null) {
            return response()->json(['message' => 'Missing Backlog API key.'], 401);
        }

        return response()->json([
            'projects' => $projectService->getProjectsWithDetails($apiKey),
            'fetched_at' => now()->toIso8601String(),
        ]);
    }

    public function refreshProjects(
        Request $request,
        BacklogProjectService $projectService,
    ): JsonResponse {
        $apiKey = BacklogApiKeyResolver::resolve($request);

        if ($apiKey === null) {
            return response()->json(['message' => 'Missing Backlog API key.'], 401);
        }

        $projectService->clearCachedMappings($apiKey);

        return response()->json([
            'projects' => $projectService->getProjectsWithDetails($apiKey),
            'fetched_at' => now()->toIso8601String(),
        ]);
    }

    public function myself(Request $request): JsonResponse
    {
        $apiKey = BacklogApiKeyResolver::resolve($request);

        if ($apiKey === null) {
            return response()->json(['message' => 'Missing Backlog API key.'], 401);
        }

        $response = Http::get(
            rtrim((string) config('backlog.url'), '/').'/api/v2/users/myself',
            ['apiKey' => $apiKey],
        );

        if (! $response->successful()) {
            return response()->json(['message' => 'Failed to fetch current user.'], $response->status());
        }

        $user = $response->json();

        return response()->json([
            'user' => [
                'id' => $user['id'] ?? null,
                'name' => $user['name'] ?? null,
                'user_id' => $user['userId'] ?? null,
            ],
        ]);
    }
}
