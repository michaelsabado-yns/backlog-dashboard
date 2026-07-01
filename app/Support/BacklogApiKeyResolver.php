<?php

namespace App\Support;

use Illuminate\Http\Request;

class BacklogApiKeyResolver
{
    public static function resolve(Request $request): ?string
    {
        $key = $request->header('X-Backlog-Api-Key')
            ?? $request->cookie('backlog_api_key');

        if (! is_string($key)) {
            return null;
        }

        $key = trim($key);

        return $key !== '' ? $key : null;
    }
}
