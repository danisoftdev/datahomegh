<?php

namespace App\Services;

use App\Models\Notification;
use App\Models\Order;
use App\Models\Role;
use App\Models\User;
use App\Notifications\BroadcastAnnouncementNotification;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class NotificationService
{
    public function notify(int $userId, string $title, string $message, string $type): Notification
    {
        return Notification::query()->create([
            'user_id' => $userId,
            'title' => $title,
            'message' => $message,
            'type' => $type,
            'is_read' => false,
            'created_at' => now(),
        ]);
    }

    /**
     * Broadcast in-app notifications and queue email copies for buyers/agents with a valid email address.
     * Email recipients match the same audience as per-user in-app rows (any account status, excluding soft-deleted users).
     *
     * @param  list<string>  $roles  Role slugs (buyer, agent only). Empty = global broadcast row (suppliers do not see broadcast rows in-app).
     */
    public function broadcast(string $title, string $message, array $roles = [], string $type = 'broadcast'): void
    {
        $groupId = (string) Str::uuid();
        $now = now();

        DB::transaction(function () use ($title, $message, $roles, $type, $groupId, $now): void {
            Notification::query()->create([
                'user_id' => null,
                'broadcast_group_id' => $groupId,
                'broadcast_role_slugs' => $roles === [] ? null : array_values($roles),
                'title' => $title,
                'message' => $message,
                'type' => $type,
                'is_read' => false,
                'created_at' => $now,
            ]);

            if ($roles === []) {
                return;
            }

            $userIds = User::query()
                ->whereHas('role', fn ($q) => $q->whereIn('slug', $roles))
                ->pluck('id');

            $rows = [];
            foreach ($userIds as $id) {
                $rows[] = [
                    'user_id' => $id,
                    'broadcast_group_id' => $groupId,
                    'title' => $title,
                    'message' => $message,
                    'type' => $type,
                    'is_read' => false,
                    'created_at' => $now,
                ];
            }

            foreach (array_chunk($rows, 500) as $chunk) {
                Notification::query()->insert($chunk);
            }
        });

        $this->dispatchBroadcastEmails($title, $message, $roles);
    }

    /**
     * Queue a mail copy for buyers and/or agents who have a valid email (in addition to in-app rows).
     *
     * @param  list<string>  $roles  Same slugs passed to {@see broadcast()}; empty means global → mail buyers + agents.
     */
    private function dispatchBroadcastEmails(string $title, string $message, array $roles): void
    {
        $roleSlugs = $this->broadcastEmailRoleSlugs($roles);

        if ($roleSlugs === []) {
            return;
        }

        User::query()
            ->whereHas('role', fn ($q) => $q->whereIn('slug', $roleSlugs))
            ->whereNotNull('email')
            ->where('email', '!=', '')
            ->orderBy('id')
            ->chunkById(100, function ($users) use ($title, $message): void {
                foreach ($users as $user) {
                    $email = trim((string) $user->email);
                    if ($email === '' || ! filter_var($email, FILTER_VALIDATE_EMAIL)) {
                        continue;
                    }

                    $user->notify(new BroadcastAnnouncementNotification($title, $message));
                }
            });
    }

    /**
     * @param  list<string>  $roles
     * @return list<string>
     */
    private function broadcastEmailRoleSlugs(array $roles): array
    {
        if ($roles === []) {
            return [Role::SLUG_BUYER, Role::SLUG_AGENT];
        }

        return array_values(array_unique(array_intersect(
            $roles,
            [Role::SLUG_BUYER, Role::SLUG_AGENT],
        )));
    }

    public function markRead(int $userId, ?string $roleSlug = null): void
    {
        Notification::query()
            ->where('user_id', $userId)
            ->where('is_read', false)
            ->update(['is_read' => true]);

        $broadcastIds = Notification::query()
            ->visibleBroadcastsFor($userId, $roleSlug)
            ->whereDoesntHave('reads', fn ($q) => $q->where('user_id', $userId))
            ->pluck('id');

        $reads = [];
        $t = now();
        foreach ($broadcastIds as $nid) {
            $reads[] = [
                'notification_id' => $nid,
                'user_id' => $userId,
                'read_at' => $t,
            ];
        }

        foreach (array_chunk($reads, 500) as $chunk) {
            DB::table('notification_reads')->insertOrIgnore($chunk);
        }
    }

    public function unreadCount(int $userId, ?string $roleSlug = null): int
    {
        $personal = Notification::query()
            ->where('user_id', $userId)
            ->where('is_read', false)
            ->count();

        $broadcast = Notification::query()
            ->visibleBroadcastsFor($userId, $roleSlug)
            ->whereDoesntHave('reads', fn ($q) => $q->where('user_id', $userId))
            ->count();

        return $personal + $broadcast;
    }

    /**
     * Admin-only removal: personal rows are deleted; broadcast templates are archived from the admin list only
     * (buyer/agent copies and in-app visibility stay unchanged).
     */
    public function deleteByAdmin(Notification $notification, int $adminUserId): int
    {
        return (int) DB::transaction(function () use ($notification, $adminUserId): int {
            if ((int) $notification->user_id === $adminUserId) {
                return $notification->delete() ? 1 : 0;
            }

            if ($notification->user_id === null && $notification->broadcast_group_id !== null && $notification->broadcast_group_id !== '') {
                return $notification->update(['admin_archived_at' => now()]) ? 1 : 0;
            }

            return 0;
        });
    }

    /**
     * Remove a notification from the signed-in user's inbox only (per-user row delete, or dismiss a shared broadcast row).
     */
    public function dismissFromInbox(int $userId, ?string $roleSlug, Notification $notification): bool
    {
        $visible = Notification::query()
            ->forUser($userId, $roleSlug)
            ->whereKey($notification->getKey())
            ->exists();

        if (! $visible) {
            return false;
        }

        if ($notification->user_id !== null && (int) $notification->user_id === $userId) {
            return (bool) $notification->delete();
        }

        if ($notification->user_id === null) {
            DB::table('notification_dismissals')->insertOrIgnore([
                'notification_id' => $notification->id,
                'user_id' => $userId,
                'created_at' => now(),
            ]);

            return true;
        }

        return false;
    }

    public function notifySuppliersNewAgentPendingApproval(User $agentUser): void
    {
        $title = 'New agent registration';
        $code = $agentUser->shop_slug ? ' (code: '.$agentUser->shop_slug.')' : '';
        $message = "{$agentUser->name} (@{$agentUser->username}) applied as an agent and awaits approval.".$code;
        User::query()
            ->whereHas('role', fn ($q) => $q->where('slug', Role::SLUG_SUPPLIER))
            ->cursor()
            ->each(function (User $supplier) use ($title, $message): void {
                $this->notify($supplier->id, $title, $message, 'agent_registered');
            });
    }

    public function notifySuppliersNewOrder(Order $order): void
    {
        $order->loadMissing(['user', 'bundlePackage']);
        $buyer = $order->user?->username ?? '#'.$order->user_id;
        $bundle = $order->bundlePackage?->name ?? __('Bundle');
        $title = 'New order #'.$order->id;
        $message = "{$buyer} · {$order->network} · {$order->phone_number} · {$bundle}";

        User::query()
            ->whereHas('role', fn ($q) => $q->where('slug', Role::SLUG_SUPPLIER))
            ->cursor()
            ->each(function (User $supplier) use ($title, $message): void {
                $this->notify($supplier->id, $title, $message, 'order_received');
            });
    }
}
