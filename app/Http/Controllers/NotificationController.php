<?php

namespace App\Http\Controllers;

use App\Services\BacklogNotificationService;
use App\Services\NotificationTransformer;
use Illuminate\Http\Response as HttpResponse;
use Inertia\Inertia;
use Inertia\Response;

class NotificationController extends Controller
{
    public function index(
        BacklogNotificationService $backlogNotificationService,
        NotificationTransformer $notificationTransformer,
    ): Response {
        $notifications = $this->getTransformedNotifications(
            $backlogNotificationService,
            $notificationTransformer,
        );

        return Inertia::render('Notifications/Index', [
            'notifications' => $notifications,
            'total_count' => count($notifications),
            'refreshed_at' => now()->toIso8601String(),
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
    ): array {
        $rawNotifications = $backlogNotificationService->getNotifications();

        return $notificationTransformer->transform($rawNotifications);
    }
}
