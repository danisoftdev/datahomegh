<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Order extends Model
{
    protected $fillable = [
        'user_id',
        'agent_id',
        'network',
        'phone_number',
        'afa_registration',
        'bundle_package_id',
        'amount',
        'status',
        'provider_order_reference',
        'provider_status',
        'provider_status_synced_at',
        'fulfillment_api_profile_id',
        'provider_dispatch_error',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'afa_registration' => 'array',
            'provider_status_synced_at' => 'datetime',
        ];
    }

    protected $appends = [
        'status_color',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function agent(): BelongsTo
    {
        return $this->belongsTo(User::class, 'agent_id');
    }

    public function bundlePackage(): BelongsTo
    {
        return $this->belongsTo(BundlePackage::class);
    }

    public function fulfillmentApiProfile(): BelongsTo
    {
        return $this->belongsTo(FulfillmentApiProfile::class);
    }

    public function orderStatusHistories(): HasMany
    {
        return $this->hasMany(OrderStatusHistory::class);
    }

    /**
     * Orders the supplier may list or fulfil: placed by agents (self-checkout) or by buyers not linked to an agent.
     * Buyer orders under an agent are excluded (those are the agent’s queue).
     */
    public function scopeVisibleToSupplier(Builder $query): Builder
    {
        return $query;
    }

    public function getStatusColorAttribute(): string
    {
        return match ($this->status) {
            'PENDING' => 'inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-medium bg-yellow-100 text-yellow-800',
            'PROCESSING' => 'inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-medium bg-sky-100 text-sky-800',
            'SENT' => 'inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-medium bg-emerald-100 text-emerald-800',
            'FAILED' => 'inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-medium bg-red-100 text-red-800',
            'REFUNDED' => 'inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-medium bg-purple-100 text-purple-800',
            default => 'inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-medium bg-gray-100 text-gray-800',
        };
    }
}
