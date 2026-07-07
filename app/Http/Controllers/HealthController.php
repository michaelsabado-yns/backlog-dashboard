<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;

/**
 * Lightweight health check for uptime monitoring (no auth, no DB, no business logic).
 */
class HealthController extends Controller
{
    public function __invoke(): JsonResponse
    {
        return response()->json([
            'status' => 'ok',
            'timestamp' => now()->toIso8601String(),
        ]);
    }
}
