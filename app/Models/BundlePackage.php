<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class BundlePackage extends Model
{
    protected $fillable = [
        'agent_id',
        'network',
        'name',
        'size_label',
        'internal_cost',
        'stock_count',
        'is_available',
    ];

    protected function casts(): array
    {
        return [
            'internal_cost' => 'decimal:2',
            'stock_count' => 'integer',
            'is_available' => 'boolean',
        ];
    }

    public function agent(): BelongsTo
    {
        return $this->belongsTo(User::class, 'agent_id');
    }

    public function resalePlans(): HasMany
    {
        return $this->hasMany(ResalePlan::class);
    }

    public function rolePrices(): HasMany
    {
        return $this->hasMany(RolePrice::class);
    }

    public function scopeAvailable(Builder $query): Builder
    {
        return $query->where('is_available', true)->where('stock_count', '>', 0);
    }

    public function scopeByNetwork(Builder $query, string $net): Builder
    {
        return $query->where('network', $net);
    }
}
