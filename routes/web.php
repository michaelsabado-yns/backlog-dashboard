<?php

use App\Http\Controllers\DailyHoursTrackerController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\ProjectSettingsController;
use App\Services\BacklogNotificationService;
use App\Support\BacklogApiKeyResolver;
use Illuminate\Foundation\Application;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::get('/', function () {
    return Inertia::render('Welcome', [
        'canLogin' => Route::has('login'),
        'canRegister' => Route::has('register'),
        'laravelVersion' => Application::VERSION,
        'phpVersion' => PHP_VERSION,
    ]);
});

Route::get('/dashboard', function () {
    return Inertia::render('Dashboard');
})->middleware(['auth', 'verified'])->name('dashboard');

Route::middleware('auth')->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
});

Route::get('/test-backlog', function (
    Request $request,
    BacklogNotificationService $service,
) {
    $apiKey = BacklogApiKeyResolver::resolve($request);

    if ($apiKey === null) {
        return response()->json(['error' => 'No API key provided'], 401);
    }

    return $service->getNotifications($apiKey);
});

Route::middleware([])->group(function () {
    Route::get('/notifications', [NotificationController::class, 'index'])
        ->name('notifications.index');
    Route::get('/notifications/{id}', [NotificationController::class, 'show'])
        ->name('notifications.show');
    Route::get('/daily-hours', [DailyHoursTrackerController::class, 'index'])
        ->name('daily-hours.index');
    Route::get('/daily-hours/my-issues', [DailyHoursTrackerController::class, 'myIssues'])
        ->name('daily-hours.my-issues');
    Route::get('/daily-hours/users', [DailyHoursTrackerController::class, 'users'])
        ->name('daily-hours.users');
    Route::get('/daily-hours/date-bounds', [DailyHoursTrackerController::class, 'dateBounds'])
        ->name('daily-hours.date-bounds');
    Route::get('/daily-hours/notifications', [DailyHoursTrackerController::class, 'notifications'])
        ->name('daily-hours.notifications');
    Route::get('/project-settings', [ProjectSettingsController::class, 'index'])
        ->name('project-settings.index');
    Route::get('/project-settings/projects', [ProjectSettingsController::class, 'projects'])
        ->name('project-settings.projects');
    Route::post('/project-settings/refresh', [ProjectSettingsController::class, 'refreshProjects'])
        ->name('project-settings.refresh');
    Route::get('/project-settings/myself', [ProjectSettingsController::class, 'myself'])
        ->name('project-settings.myself');
});

require __DIR__.'/auth.php';
