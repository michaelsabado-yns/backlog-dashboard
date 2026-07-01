<?php

namespace App\Http\Controllers;

use App\Services\BacklogNotificationService;
use App\Services\NotificationTransformer;
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
        $forceRefresh = $request->boolean('force');

        $notifications = $this->getTransformedNotifications(
            $backlogNotificationService,
            $notificationTransformer,
            $forceRefresh,
        );

        return Inertia::render('Notifications/Index', [
            'notifications' => $notifications,
            'total_count' => count($notifications),
            'refreshed_at' => $backlogNotificationService->getLastFetchedAt(),
            'backlog_unread_count' => $backlogNotificationService->getCachedUnreadCount(),
            'from_cache' => $backlogNotificationService->servedFromCache(),
        ]);
    }

    public function show(
        int $id,
        BacklogNotificationService $backlogNotificationService,
        NotificationTransformer $notificationTransformer,
    ): Response|HttpResponse {
        $notification = collect(
            $this->getTransformedNotifications(
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
     * @return array<int, array<string, mixed>>
     */
    private function getTransformedNotifications(
        BacklogNotificationService $backlogNotificationService,
        NotificationTransformer $notificationTransformer,
        bool $forceRefresh = false,
    ): array {
        $rawNotifications = $backlogNotificationService->getNotifications(
            forceRefresh: $forceRefresh,
        );

        return $notificationTransformer->transform($rawNotifications);
    }
}
