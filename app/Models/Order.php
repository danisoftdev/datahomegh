<?php

namespace App\Models;

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
        'bundle_package_id',
        'amount',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
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

    public function orderStatusHistories(): HasMany
    {
        return $this->hasMany(OrderStatusHistory::class);
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
