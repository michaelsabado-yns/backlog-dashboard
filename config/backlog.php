<?php

return [
    'url' => env('BACKLOG_URL'),
    'api_key' => env('BACKLOG_API_KEY'),
    'notifications_cache_ttl' => (int) env('BACKLOG_NOTIFICATIONS_CACHE_TTL', 60),
];