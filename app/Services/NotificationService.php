<?php

namespace App\Services;

use App\Models\FcmToken;
use App\Models\Notification;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Kreait\Firebase\Contract\Messaging as MessagingContract;
use Kreait\Firebase\Messaging\CloudMessage;
use Kreait\Firebase\Messaging\Notification as FcmNotification;
use Throwable;

class NotificationService
{
    private const FCM_CHUNK = 500;

    public function __construct(
        private readonly MessagingContract $messaging,
    ) {}

    public function notify(int $userId, string $title, string $message, string $type): Notification
    {
        $notification = Notification::query()->create([
            'user_id' => $userId,
            'title' => $title,
            'message' => $message,
            'type' => $type,
            'is_read' => false,
            'created_at' => now(),
        ]);

        try {
            $tokens = FcmToken::query()->where('user_id', $userId)->pluck('token')->all();
            $this->sendFcmMulticastToTokens($tokens, $title, $message, $type);
        } catch (Throwable $e) {
            Log::warning('notification_fcm_failed', [
                'user_id' => $userId,
                'error' => $e->getMessage(),
            ]);
        }

        return $notification;
    }

    /**
     * @param  list<string>  $roles  Role slugs; empty = everyone (single global row + FCM to all token holders).
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

        $recipientUserIds = $roles === []
            ? User::query()->pluck('id')->all()
            : User::query()
                ->whereHas('role', fn ($q) => $q->whereIn('slug', $roles))
                ->pluck('id')
                ->all();

        try {
            $tokens = FcmToken::query()
                ->whereIn('user_id', $recipientUserIds)
                ->pluck('token')
                ->unique()
                ->values()
                ->all();
            $this->sendFcmMulticastToTokens($tokens, $title, $message, $type);
        } catch (Throwable $e) {
            Log::warning('notification_broadcast_fcm_failed', [
                'error' => $e->getMessage(),
            ]);
        }
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
     * @param  list<string>  $tokens
     */
    private function sendFcmMulticastToTokens(array $tokens, string $title, string $body, string $type): void
    {
        $tokens = array_values(array_filter(array_unique($tokens)));
        if ($tokens === []) {
            return;
        }

        $message = CloudMessage::new()
            ->withNotification(FcmNotification::create($title, $body))
            ->withData([
                'type' => $type,
            ]);

        foreach (array_chunk($tokens, self::FCM_CHUNK) as $chunk) {
            $this->messaging->sendMulticast($message, $chunk);
        }
    }
}
