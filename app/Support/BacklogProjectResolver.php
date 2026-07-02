<?php

namespace App\Support;

use Illuminate\Http\Request;

class BacklogProjectResolver
{
    /**
     * @return array<int, int>|null Null means all joined projects, [] means none selected.
     */
    public static function resolve(Request $request): ?array
    {
        if (! $request->headers->has('X-Backlog-Project-Ids')) {
            return null;
        }

        $header = $request->header('X-Backlog-Project-Ids');

        if (! is_string($header) || trim($header) === '') {
            return [];
        }

        return array_values(array_unique(array_filter(array_map(
            static fn (string $id): int => (int) trim($id),
            explode(',', $header),
        ))));
    }
}
