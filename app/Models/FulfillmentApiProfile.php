<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class FulfillmentApiProfile extends Model
{
    protected $fillable = [
        'supplier_user_id',
        'network',
        'name',
        'base_url',
        'api_key',
        'default_provider_bundle_type',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'api_key' => 'encrypted',
        ];
    }

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(User::class, 'supplier_user_id');
    }

    public function orders(): HasMany
    {
        return $this->hasMany(Order::class);
    }

    public static function activeForNetwork(string $network): ?self
    {
        return self::query()
            ->where('network', $network)
            ->where('is_active', true)
            ->first();
    }
}
