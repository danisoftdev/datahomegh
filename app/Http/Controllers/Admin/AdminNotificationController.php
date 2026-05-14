<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Notification;
use App\Services\NotificationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class AdminNotificationController extends Controller
{
    public function __construct(
        private readonly NotificationService $notificationService,
    ) {}

    public function index(): View
    {
        $notifications = Notification::query()
            ->with('user')
            ->latest('created_at')
            ->paginate(30);

        return view('admin.notifications.index', compact('notifications'));
    }

    public function destroy(Notification $notification): RedirectResponse
    {
        $this->notificationService->deleteByAdmin($notification);

        return back()->with('status', __('Notification deleted.'));
    }
}
