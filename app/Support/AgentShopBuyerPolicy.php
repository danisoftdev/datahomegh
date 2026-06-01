<?php

namespace App\Support;

use App\Models\User;

final class AgentShopBuyerPolicy
{
    public static function walletCutoffGhs(): string
    {
        return bcadd((string) config('datahome.agent_shop.wallet_cutoff_ghs', 5), '0', 2);
    }

    public static function isAgentShopBuyer(User $buyer): bool
    {
        return $buyer->isBuyer() && $buyer->agent_id !== null;
    }

    public static function requiresPaystackCheckout(User $buyer): bool
    {
        if (! self::isAgentShopBuyer($buyer)) {
            return false;
        }

        return (bool) $buyer->paystack_checkout_only;
    }

    public static function canUseWalletCheckout(User $buyer): bool
    {
        if (! self::isAgentShopBuyer($buyer)) {
            return true;
        }

        if ($buyer->paystack_checkout_only) {
            return false;
        }

        $buyer->loadMissing('wallet');
        $balance = bcadd((string) ($buyer->wallet?->balance ?? '0'), '0', 2);

        return bccomp($balance, self::walletCutoffGhs(), 2) > 0;
    }

    public static function applyWalletCutoffIfNeeded(User $buyer): void
    {
        if (! self::isAgentShopBuyer($buyer) || $buyer->paystack_checkout_only) {
            return;
        }

        $buyer->loadMissing('wallet');
        $balance = bcadd((string) ($buyer->wallet?->balance ?? '0'), '0', 2);

        if (bccomp($balance, self::walletCutoffGhs(), 2) <= 0) {
            $buyer->paystack_checkout_only = true;
            $buyer->save();
        }
    }

    public static function canReceiveAgentWalletCredit(User $buyer): bool
    {
        return self::isAgentShopBuyer($buyer) && ! $buyer->paystack_checkout_only;
    }
}
