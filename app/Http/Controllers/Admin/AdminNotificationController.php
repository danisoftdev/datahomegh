<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Notification;
use App\Services\NotificationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class AdminNotificationController extends Controller
{
    public function __construct(
        private readonly NotificationService $notificationService,
    ) {}

    public function index(): View
    {
        $adminId = (int) auth()->id();
        $notifications = Notification::query()
            ->with('user')
            ->where(function ($q) use ($adminId): void {
                $q->where('user_id', $adminId)
                    ->orWhere(function ($q2): void {
                        $q2->whereNull('user_id')
                            ->whereNotNull('broadcast_group_id')
                            ->whereNull('admin_archived_at');
                    });
            })
            ->latest('created_at')
            ->paginate(30);

        return view('admin.notifications.index', compact('notifications'));
    }

    public function destroy(Request $request, Notification $notification): RedirectResponse
    {
        $deleted = $this->notificationService->deleteByAdmin($notification, (int) $request->user()->id);
        if ($deleted === 0) {
            abort(403);
        }

        return back()->with('status', __('Notification removed.'));
    }
}
