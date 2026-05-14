<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Notification extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'user_id',
        'broadcast_group_id',
        'broadcast_role_slugs',
        'title',
        'message',
        'type',
        'is_read',
        'created_at',
    ];

    protected function casts(): array
    {
        return [
            'is_read' => 'boolean',
            'created_at' => 'datetime',
            'broadcast_role_slugs' => 'array',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function reads(): HasMany
    {
        return $this->hasMany(NotificationRead::class);
    }

    /**
     * In-app notifications visible to the user: their own rows plus broadcast rows
     * (user_id null) that are not superseded by a per-user copy in the same broadcast group.
     * Supplier accounts only see rows addressed to them (user_id = supplier); broadcast rows are excluded.
     *
     * @param  non-empty-string|null  $roleSlug  Authenticated user's role slug (for targeted broadcasts).
     */
    public function scopeForUser(Builder $query, int $userId, ?string $roleSlug = null): Builder
    {
        return $query->where(function (Builder $q) use ($userId, $roleSlug) {
            $q->where('user_id', $userId);
            if ($roleSlug === Role::SLUG_SUPPLIER) {
                return;
            }
            $q->orWhere(function (Builder $q2) use ($userId, $roleSlug) {
                $q2->whereNull('user_id')
                    ->where(function (Builder $qRole) use ($roleSlug) {
                        $qRole->whereNull('broadcast_role_slugs');
                        if ($roleSlug !== null && $roleSlug !== '') {
                            $qRole->orWhereJsonContains('broadcast_role_slugs', $roleSlug);
                        }
                    })
                    ->where(function (Builder $q3) use ($userId) {
                        $q3->whereNull('broadcast_group_id')
                            ->orWhereNotExists(function ($sub) use ($userId) {
                                $sub->selectRaw('1')
                                    ->from('notifications as nb')
                                    ->whereColumn('nb.broadcast_group_id', 'notifications.broadcast_group_id')
                                    ->where('nb.user_id', $userId)
                                    ->whereNotNull('notifications.broadcast_group_id');
                            });
                    });
            });
        });
    }

    public function scopeVisibleBroadcastsFor(Builder $query, int $userId, ?string $roleSlug = null): Builder
    {
        if ($roleSlug === Role::SLUG_SUPPLIER) {
            return $query->whereRaw('1 = 0');
        }

        return $query->whereNull('user_id')
            ->where(function (Builder $qRole) use ($roleSlug) {
                $qRole->whereNull('broadcast_role_slugs');
                if ($roleSlug !== null && $roleSlug !== '') {
                    $qRole->orWhereJsonContains('broadcast_role_slugs', $roleSlug);
                }
            })
            ->where(function (Builder $q) use ($userId) {
                $q->whereNull('broadcast_group_id')
                    ->orWhereNotExists(function ($sub) use ($userId) {
                        $sub->selectRaw('1')
                            ->from('notifications as nb')
                            ->whereColumn('nb.broadcast_group_id', 'notifications.broadcast_group_id')
                            ->where('nb.user_id', $userId)
                            ->whereNotNull('notifications.broadcast_group_id');
                    });
            });
    }

    public function isUnreadForUser(int $userId): bool
    {
        if ($this->user_id === $userId) {
            return ! $this->is_read;
        }

        if ($this->user_id === null) {
            return ! $this->reads()->where('user_id', $userId)->exists();
        }

        return false;
    }
}
