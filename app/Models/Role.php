<?php

namespace App\Models;

use Database\Factories\RoleFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Role extends Model
{
    public const SLUG_SUPPLIER = 'supplier';

    public const SLUG_AGENT = 'agent';

    public const SLUG_BUYER = 'buyer';

    public const PERSONA_BUYER = 'buyer';

    public const PERSONA_AGENT = 'agent';

    /** @use HasFactory<RoleFactory> */
    use HasFactory;

    protected $fillable = [
        'name',
        'slug',
        'is_enabled',
        'available_at_registration',
        'pricing_persona',
        'requires_promotion_fee',
        'promotion_fee',
    ];

    protected function casts(): array
    {
        return [
            'is_enabled' => 'boolean',
            'available_at_registration' => 'boolean',
            'requires_promotion_fee' => 'boolean',
            'promotion_fee' => 'decimal:2',
        ];
    }

    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }

    public function permissions(): BelongsToMany
    {
        return $this->belongsToMany(Permission::class, 'role_permission');
    }

    public function rolePrices(): HasMany
    {
        return $this->hasMany(RolePrice::class);
    }

    public function isSystemRole(): bool
    {
        return in_array($this->slug, [self::SLUG_SUPPLIER, self::SLUG_AGENT, self::SLUG_BUYER], true);
    }

    public function isCustomRole(): bool
    {
        return ! $this->isSystemRole();
    }

    public function usesAgentPersona(): bool
    {
        return $this->pricing_persona === self::PERSONA_AGENT;
    }

    public function usesBuyerPersona(): bool
    {
        return $this->pricing_persona === self::PERSONA_BUYER;
    }

    public function promotionFeeAmount(): string
    {
        if (! $this->requires_promotion_fee) {
            return '0.00';
        }

        return bcadd((string) ($this->promotion_fee ?? '0'), '0', 2);
    }

    /**
     * Roles that may have platform bundle list prices (excludes supplier).
     *
     * @return Builder<Role>
     */
    public static function platformPricingQuery(): Builder
    {
        return static::query()
            ->where('slug', '!=', self::SLUG_SUPPLIER)
            ->where('is_enabled', true)
            ->orderByRaw("CASE slug WHEN 'buyer' THEN 1 WHEN 'agent' THEN 2 ELSE 3 END")
            ->orderBy('name');
    }
}
