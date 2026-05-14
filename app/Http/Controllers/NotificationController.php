<?php

namespace App\Http\Controllers;

use App\Models\Notification;
use App\Models\Role;
use App\Models\User;
use App\Services\NotificationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class NotificationController extends Controller
{
    public function __construct(
        private readonly NotificationService $notificationService,
    ) {}

    public function index(Request $request): View
    {
        $user = $request->user()->loadMissing('role');
        $userId = (int) $user->id;

        $roleSlug = $user->role?->slug;

        $notifications = Notification::query()
            ->forUser($userId, $roleSlug)
            ->latest('created_at')
            ->paginate(20);

        $layout = $this->layoutForUser($user);

        return view('notifications.index', compact('notifications', 'layout'));
    }

    public function unreadCount(Request $request): JsonResponse
    {
        $user = $request->user()->loadMissing('role');
        $count = $this->notificationService->unreadCount((int) $user->id, $user->role?->slug);

        return response()->json(['count' => $count]);
    }

    public function recent(Request $request): JsonResponse
    {
        $user = $request->user()->loadMissing('role');
        $userId = (int) $user->id;
        $roleSlug = $user->role?->slug;

        $items = Notification::query()
            ->forUser($userId, $roleSlug)
            ->latest('created_at')
            ->limit(10)
            ->get()
            ->map(fn (Notification $n) => $this->notificationPayload($n, $userId));

        return response()->json(['data' => $items]);
    }

    public function markRead(Request $request): JsonResponse
    {
        $user = $request->user()->loadMissing('role');
        $this->notificationService->markRead((int) $user->id, $user->role?->slug);

        return response()->json(['ok' => true]);
    }

    public function adminBroadcast(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'title' => ['required', 'string', 'max:200'],
            'message' => ['required', 'string', 'max:5000'],
            'type' => ['nullable', 'string', 'max:64'],
            'target_roles' => ['nullable', 'array'],
            'target_roles.*' => ['string', Rule::in([Role::SLUG_BUYER, Role::SLUG_AGENT])],
        ]);

        $roles = array_values(array_unique($validated['target_roles'] ?? []));

        $this->notificationService->broadcast(
            $validated['title'],
            $validated['message'],
            $roles,
            $validated['type'] ?? 'broadcast',
        );

        return response()->json(['ok' => true]);
    }

    /**
     * @return array<string, mixed>
     */
    private function notificationPayload(Notification $n, int $userId): array
    {
        $read = ! $n->isUnreadForUser($userId);

        return [
            'id' => $n->id,
            'title' => $n->title,
            'message' => $n->message,
            'type' => $n->type,
            'is_read' => $read,
            'created_at' => $n->created_at?->toIso8601String(),
        ];
    }

    private function layoutForUser(User $user): string
    {
        return match ($user->role?->slug) {
            Role::SLUG_SUPPLIER => 'layouts.admin',
            Role::SLUG_AGENT => 'layouts.app',
            default => 'layouts.buyer',
        };
    }
}
