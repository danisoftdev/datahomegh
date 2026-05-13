<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Notification;
use Illuminate\View\View;

class AdminNotificationController extends Controller
{
    public function index(): View
    {
        $notifications = Notification::query()
            ->with('user')
            ->latest('created_at')
            ->paginate(30);

        return view('admin.notifications.index', compact('notifications'));
    }
}
