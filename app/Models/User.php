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
use RuntimeException;

class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasApiTokens, HasFactory, Notifiable, SoftDeletes;

    protected static function booted(): void
    {
        static::creating(function (User $user): void {
            if (self::roleIdIsSupplier($user->role_id) && self::supplierUserCount() >= 1) {
                throw new RuntimeException('Only one supplier (superadmin) account is allowed.');
            }
        });

        static::updating(function (User $user): void {
            if (! $user->isDirty('role_id')) {
                return;
            }

            if (! self::roleIdIsSupplier($user->role_id)) {
                return;
            }

            $otherSuppliers = self::supplierUserCount(exceptUserId: $user->id);
            if ($otherSuppliers >= 1) {
                throw new RuntimeException('Only one supplier (superadmin) account is allowed.');
            }
        });
    }

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

    public function inAppNotifications(): HasMany
    {
        return $this->hasMany(Notification::class);
    }

    public function bundlePackages(): HasMany
    {
        return $this->hasMany(BundlePackage::class, 'agent_id');
    }

    public function resalePlans(): HasMany
    {
        return $this->hasMany(ResalePlan::class, 'agent_id');
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

    public function hasAgentShop(): bool
    {
        return filled($this->shop_slug);
    }

    /**
     * Agent dashboard, platform checkout, and shop tools — includes promoted agents who keep their shop.
     */
    public function canAccessAgentArea(): bool
    {
        if ($this->isSupplier()) {
            return false;
        }

        if ($this->isAgent() || $this->hasAgentShop()) {
            return true;
        }

        $this->loadMissing('role');

        return $this->role?->usesAgentPersona() ?? false;
    }

    /**
     * Buyer dashboard and checkout — not when the user still owns an agent shop.
     */
    public function canAccessBuyerArea(): bool
    {
        if ($this->isSupplier() || $this->hasAgentShop()) {
            return false;
        }

        if ($this->isBuyer()) {
            return true;
        }

        $this->loadMissing('role');

        return $this->role?->usesBuyerPersona() ?? false;
    }

    public function usesAgentPlatformCatalog(): bool
    {
        return $this->canAccessAgentArea();
    }

    /**
     * Buyers registered under an agent must not use supplier Paystack top-up;
     * they fund via the agent (MoMo) and the agent credits this wallet.
     */
    public function fundsWalletViaAgent(): bool
    {
        return $this->isBuyer() && $this->agent_id !== null;
    }

    /**
     * The single supplier (platform admin) account, if one exists.
     */
    public static function supplierUser(): ?User
    {
        return static::query()
            ->whereHas('role', fn (Builder $q) => $q->where('slug', Role::SLUG_SUPPLIER))
            ->first();
    }

    /**
     * Actionable support links for dashboards (WhatsApp chat, channel URL, phone).
     *
     * @return array{whatsapp_chat: ?string, whatsapp_channel: ?string, telephone: ?string, whatsapp_label: ?string}
     */
    public function supportContactLinks(): array
    {
        $wa = self::whatsappMeUrlForNumber($this->whatsapp_number);

        return [
            'whatsapp_chat' => $wa['url'],
            'whatsapp_channel' => self::normalizeHttpUrl($this->whatsapp_channel),
            'telephone' => self::telephoneHref($this->phone),
            'whatsapp_label' => $wa['label'],
        ];
    }

    public function hasSupportContactContent(): bool
    {
        $links = $this->supportContactLinks();

        return ($links['whatsapp_chat'] ?? null) !== null
            || ($links['whatsapp_channel'] ?? null) !== null
            || ($links['telephone'] ?? null) !== null;
    }

    /**
     * Agent (or supplier) has configured WhatsApp chat and/or a channel link for shop support.
     */
    public function hasWhatsAppOrChannelForShop(): bool
    {
        return trim((string) $this->whatsapp_number) !== ''
            || trim((string) $this->whatsapp_channel) !== '';
    }

    /**
     * Contact card shown on the buyer dashboard: linked agent when they publish WhatsApp details, otherwise the platform supplier, then agent phone-only.
     *
     * @return array{user: ?User, heading: string}
     */
    public static function dashboardSupportForBuyer(User $buyer): array
    {
        $supplier = self::supplierUser();

        if ($buyer->agent_id !== null) {
            $buyer->loadMissing('agent');
            $agent = $buyer->agent;
            if ($agent instanceof self && $agent->hasWhatsAppOrChannelForShop()) {
                return ['user' => $agent, 'heading' => __('Your agent & shop')];
            }
            if ($supplier instanceof self && $supplier->hasSupportContactContent()) {
                return ['user' => $supplier, 'heading' => __('Platform support')];
            }
            if ($agent instanceof self && $agent->hasSupportContactContent()) {
                return ['user' => $agent, 'heading' => __('Your agent & shop')];
            }

            return ['user' => null, 'heading' => ''];
        }

        if ($supplier instanceof self && $supplier->hasSupportContactContent()) {
            return ['user' => $supplier, 'heading' => __('Platform support')];
        }

        return ['user' => null, 'heading' => ''];
    }

    /**
     * @return array{url: ?string, label: ?string}
     */
    private static function whatsappMeUrlForNumber(?string $raw): array
    {
        if ($raw === null || trim($raw) === '') {
            return ['url' => null, 'label' => null];
        }

        $label = trim($raw);
        $digits = preg_replace('/\D+/', '', $label) ?? '';
        if ($digits === '') {
            return ['url' => null, 'label' => $label];
        }

        if (str_starts_with($digits, '0') && strlen($digits) >= 10) {
            $digits = '233'.substr($digits, 1);
        }

        return ['url' => 'https://wa.me/'.$digits, 'label' => $label];
    }

    private static function normalizeHttpUrl(?string $raw): ?string
    {
        if ($raw === null || trim($raw) === '') {
            return null;
        }

        $t = trim($raw);
        if (! str_starts_with($t, 'http://') && ! str_starts_with($t, 'https://')) {
            $t = 'https://'.$t;
        }

        return filter_var($t, FILTER_VALIDATE_URL) ? $t : null;
    }

    private static function telephoneHref(?string $phone): ?string
    {
        $phone = trim((string) $phone);
        if ($phone === '') {
            return null;
        }

        return 'tel:'.preg_replace('/\s+/', '', $phone);
    }

    private static function roleIdIsSupplier(?int $roleId): bool
    {
        if ($roleId === null) {
            return false;
        }

        return Role::query()->where('id', $roleId)->where('slug', Role::SLUG_SUPPLIER)->exists();
    }

    private static function supplierUserCount(?int $exceptUserId = null): int
    {
        return User::query()
            ->whereHas('role', fn (Builder $q) => $q->where('slug', Role::SLUG_SUPPLIER))
            ->when($exceptUserId !== null, fn (Builder $q) => $q->where('id', '!=', $exceptUserId))
            ->count();
    }
}
