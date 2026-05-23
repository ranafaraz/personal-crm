<?php

namespace App\Http\Controllers;

use App\Services\CrmNotificationService;
use App\Services\NotificationPreferenceService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class NotificationController extends Controller
{
    public function __construct(
        private readonly CrmNotificationService $service,
        private readonly NotificationPreferenceService $prefService,
    ) {}

    public function index(): View
    {
        return view('notifications.index');
    }

    public function markRead(Request $request, string $id): RedirectResponse|JsonResponse
    {
        $this->service->markAsRead($request->user(), $id);

        if ($request->expectsJson()) {
            return response()->json(['ok' => true]);
        }

        return back()->with('success', 'Notification marked as read.');
    }

    public function markAllRead(Request $request): RedirectResponse|JsonResponse
    {
        $count = $this->service->markAllAsRead($request->user());

        if ($request->expectsJson()) {
            return response()->json(['ok' => true, 'count' => $count]);
        }

        return back()->with('success', "Marked {$count} notifications as read.");
    }

    public function updatePreference(Request $request): RedirectResponse|JsonResponse
    {
        $validated = $request->validate([
            'notification_type' => ['required', 'string', 'in:' . implode(',', NotificationPreferenceService::TYPES)],
            'channel'           => ['required', 'string', 'in:' . implode(',', NotificationPreferenceService::CHANNELS)],
            'enabled'           => ['required', 'boolean'],
        ]);

        $this->prefService->setPreference(
            $request->user(),
            $validated['notification_type'],
            $validated['channel'],
            (bool) $validated['enabled'],
        );

        if ($request->expectsJson()) {
            return response()->json(['ok' => true]);
        }

        return back()->with('success', 'Preference updated.');
    }
}
