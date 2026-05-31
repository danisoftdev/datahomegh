<?php

namespace App\Models;

use App\Support\FulfillmentProviderType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class FulfillmentApiProfile extends Model
{
    protected $fillable = [
        'supplier_user_id',
        'network',
        'provider_type',
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

    public function isIget(): bool
    {
        return $this->provider_type === FulfillmentProviderType::IGET;
    }

    public function isGeonet(): bool
    {
        return $this->provider_type === FulfillmentProviderType::GEONET;
    }

    public function isEncarta(): bool
    {
        return $this->provider_type === FulfillmentProviderType::ENCARTA;
    }

    public static function defaultBaseUrl(string $providerType): string
    {
        return (string) config('datahome.fulfillment.providers.'.$providerType.'.default_base_url', '');
    }

    /**
     * iGet host root. Strips accidental /api/developer/... suffixes.
     */
    public static function normalizeBaseUrl(string $baseUrl): string
    {
        $base = rtrim(trim($baseUrl), '/');

        foreach ([
            '/api/developer/orders/place',
            '/api/developer/orders/reference',
            '/api/developer',
        ] as $suffix) {
            $len = strlen($suffix);
            if ($len > 0 && strlen($base) >= $len && strcasecmp(substr($base, -$len), $suffix) === 0) {
                $base = rtrim(substr($base, 0, -$len), '/');
            }
        }

        return $base;
    }

    /**
     * Geonettech base (https://send.geonettech.com/api). Strips accidental /v1/... suffixes.
     */
    public static function normalizeGeonetBaseUrl(string $baseUrl): string
    {
        $base = rtrim(trim($baseUrl), '/');

        foreach ([
            '/v1/place-order',
            '/v1/order',
            '/v1/wallet/balance',
            '/v1',
        ] as $suffix) {
            $len = strlen($suffix);
            if ($len > 0 && strlen($base) >= $len && strcasecmp(substr($base, -$len), $suffix) === 0) {
                $base = rtrim(substr($base, 0, -$len), '/');
            }
        }

        return $base;
    }

    /**
     * Encarta base (https://encartastores.com/api). Strips accidental endpoint suffixes.
     */
    public static function normalizeEncartaBaseUrl(string $baseUrl): string
    {
        $base = rtrim(trim($baseUrl), '/');

        foreach ([
            '/ishare-status',
            '/afa-status-bulk',
            '/bulk-purchase',
            '/result-checker',
            '/ishare',
            '/purchase',
            '/afa-status',
            '/balance',
        ] as $suffix) {
            $len = strlen($suffix);
            if ($len > 0 && strlen($base) >= $len && strcasecmp(substr($base, -$len), $suffix) === 0) {
                $base = rtrim(substr($base, 0, -$len), '/');
            }
        }

        return $base;
    }
}
