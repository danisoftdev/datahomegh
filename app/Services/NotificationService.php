<?php

namespace App\Services;

use App\Models\Notification;
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
     * Broadcast in-app notifications and queue email copies for active buyers/agents with a valid email address.
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
            ->where('status', 'active')
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
     * Remove a notification. If it belongs to a broadcast group, delete every row in that group (template + per-user copies).
     */
    public function deleteByAdmin(Notification $notification): int
    {
        return (int) DB::transaction(function () use ($notification): int {
            $gid = $notification->broadcast_group_id;
            if ($gid !== null && $gid !== '') {
                return Notification::query()->where('broadcast_group_id', $gid)->delete();
            }

            return Notification::query()->whereKey($notification->getKey())->delete();
        });
    }
}
