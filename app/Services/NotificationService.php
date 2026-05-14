<?php

namespace App\Services;

use App\Models\Notification;
use App\Models\User;
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
     * @param  list<string>  $roles  Role slugs; empty = everyone (single global broadcast row only).
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
}
