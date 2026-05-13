<?php

namespace App\Models;

use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasApiTokens, HasFactory, Notifiable, SoftDeletes;

    protected $fillable = [
        'username',
        'name',
        'email',
        'phone',
        'password',
        'role_id',
        'agent_id',
        'shop_slug',
        'shop_name',
        'profile_picture',
        'logo',
        'whatsapp_number',
        'whatsapp_channel',
        'business_description',
        'status',
        'wallet_frozen',
        'daily_order_limit',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'password' => 'hashed',
            'wallet_frozen' => 'boolean',
            'daily_order_limit' => 'integer',
            'deleted_at' => 'datetime',
        ];
    }

    public function role(): BelongsTo
    {
        return $this->belongsTo(Role::class);
    }

    public function agent(): BelongsTo
    {
        return $this->belongsTo(User::class, 'agent_id');
    }

    public function buyers(): HasMany
    {
        return $this->hasMany(User::class, 'agent_id');
    }

    public function wallet(): HasOne
    {
        return $this->hasOne(Wallet::class);
    }

    public function orders(): HasMany
    {
        return $this->hasMany(Order::class);
    }

    public function walletLedgers(): HasMany
    {
        return $this->hasMany(WalletLedger::class);
    }

    public function fcmTokens(): HasMany
    {
        return $this->hasMany(FcmToken::class);
    }

    public function inAppNotifications(): HasMany
    {
        return $this->hasMany(Notification::class);
    }

    public function bundlePackages(): HasMany
    {
        return $this->hasMany(BundlePackage::class, 'agent_id');
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('status', 'active');
    }

    public function scopePending(Builder $query): Builder
    {
        return $query->where('status', 'pending');
    }

    public function scopeAgents(Builder $query): Builder
    {
        return $query->whereHas('role', fn (Builder $q) => $q->where('slug', Role::SLUG_AGENT));
    }

    public function scopeBuyers(Builder $query): Builder
    {
        return $query->whereHas('role', fn (Builder $q) => $q->where('slug', Role::SLUG_BUYER));
    }

    public function isSupplier(): bool
    {
        return $this->role?->slug === Role::SLUG_SUPPLIER;
    }

    public function isAgent(): bool
    {
        return $this->role?->slug === Role::SLUG_AGENT;
    }

    public function isBuyer(): bool
    {
        return $this->role?->slug === Role::SLUG_BUYER;
    }
}
