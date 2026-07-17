<?php

namespace App\Support;

use App\Models\Role;
use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Throwable;

final class BrandingFavicon
{
    public static function urlFromLogoPath(?string $path): ?string
    {
        if (! is_string($path) || trim($path) === '') {
            return null;
        }

        if (Str::startsWith($path, ['http://', 'https://'])) {
            return $path;
        }

        return Storage::disk('public')->url($path);
    }

    public static function supplierPrimaryUrl(): ?string
    {
        try {
            return Cache::remember('branding.supplier_favicon_url', now()->addMinutes(10), function (): ?string {
                if (! Schema::hasTable('users') || ! Schema::hasTable('roles')) {
                    return null;
                }

                $u = User::query()
                    ->where('status', 'active')
                    ->whereHas('role', fn ($q) => $q->where('slug', Role::SLUG_SUPPLIER))
                    ->orderBy('id')
                    ->first();

                return self::urlFromLogoPath($u?->logo);
            });
        } catch (Throwable) {
            return null;
        }
    }

    public static function urlForAdminSession(?User $user): ?string
    {
        if ($user !== null) {
            $user->loadMissing('role');
            if ($user->role?->slug === Role::SLUG_SUPPLIER) {
                $own = self::urlFromLogoPath($user->logo);
                if ($own !== null) {
                    return $own;
                }
            }
        }

        return self::supplierPrimaryUrl();
    }

    public static function urlForAgentSession(?User $user): ?string
    {
        if ($user !== null) {
            $user->loadMissing('role');
            if ($user->role?->slug === Role::SLUG_AGENT) {
                $own = self::urlFromLogoPath($user->logo);
                if ($own !== null) {
                    return $own;
                }
            }
        }

        return self::supplierPrimaryUrl();
    }

    /**
     * Buyer area: linked buyer sees agent shop mark; otherwise supplier.
     */
    public static function urlForAppLayout(?User $user): ?string
    {
        if ($user !== null) {
            $user->loadMissing(['role', 'agent']);
            if ($user->role?->slug === Role::SLUG_AGENT) {
                $own = self::urlFromLogoPath($user->logo);
                if ($own !== null) {
                    return $own;
                }
            }
            if ($user->role?->slug === Role::SLUG_BUYER && $user->agent_id !== null) {
                $fromAgent = self::urlFromLogoPath($user->agent?->logo);
                if ($fromAgent !== null) {
                    return $fromAgent;
                }
            }
        }

        return self::supplierPrimaryUrl();
    }
}
