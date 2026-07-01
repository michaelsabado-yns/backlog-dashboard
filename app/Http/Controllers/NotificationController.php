<?php

namespace App\Http\Controllers;

use App\Services\BacklogNotificationService;
use App\Services\NotificationTransformer;
use App\Support\BacklogApiKeyResolver;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response as HttpResponse;
use Inertia\Inertia;
use Inertia\Response;

class NotificationController extends Controller
{
    public function index(
        Request $request,
        BacklogNotificationService $backlogNotificationService,
        NotificationTransformer $notificationTransformer,
    ): Response {
        $apiKey = BacklogApiKeyResolver::resolve($request);

        if ($apiKey === null) {
            return Inertia::render('Notifications/Index', $this->emptyIndexPayload());
        }

        $forceRefresh = $request->boolean('force');

        $notifications = $this->getTransformedNotifications(
            $apiKey,
            $backlogNotificationService,
            $notificationTransformer,
            $forceRefresh,
        );

        return Inertia::render('Notifications/Index', [
            'notifications' => $notifications,
            'total_count' => count($notifications),
            'refreshed_at' => $backlogNotificationService->getLastFetchedAt($apiKey),
            'backlog_unread_count' => $backlogNotificationService->getCachedUnreadCount($apiKey),
            'from_cache' => $backlogNotificationService->servedFromCache(),
            'has_api_key' => true,
        ]);
    }

    public function show(
        int $id,
        Request $request,
        BacklogNotificationService $backlogNotificationService,
        NotificationTransformer $notificationTransformer,
    ): Response|HttpResponse|RedirectResponse {
        $apiKey = BacklogApiKeyResolver::resolve($request);

        if ($apiKey === null) {
            return redirect()->route('notifications.index');
        }

        $notification = collect(
            $this->getTransformedNotifications(
                $apiKey,
                $backlogNotificationService,
                $notificationTransformer,
            ),
        )->firstWhere('id', $id);

        if ($notification === null) {
            abort(404);
        }

        return Inertia::render('Notifications/Show', [
            'notification' => $notification,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function emptyIndexPayload(): array
    {
        return [
            'notifications' => [],
            'total_count' => 0,
            'refreshed_at' => null,
            'backlog_unread_count' => 0,
            'from_cache' => false,
            'has_api_key' => false,
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function getTransformedNotifications(
        string $apiKey,
        BacklogNotificationService $backlogNotificationService,
        NotificationTransformer $notificationTransformer,
        bool $forceRefresh = false,
    ): array {
        $rawNotifications = $backlogNotificationService->getNotifications(
            $apiKey,
            forceRefresh: $forceRefresh,
        );

        return $notificationTransformer->transform($rawNotifications);
    }
}
