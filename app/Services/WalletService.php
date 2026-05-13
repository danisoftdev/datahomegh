<?php

namespace App\Services;

use App\Events\WalletCredited;
use App\Exceptions\InsufficientBalanceException;
use App\Models\Wallet;
use App\Models\WalletLedger;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class WalletService
{
    public function credit(int $userId, string|float $amount, string $source, ?string $ref = null, ?string $note = null): WalletLedger
    {
        $wallet = Wallet::query()->where('user_id', $userId)->firstOrFail();

        $ledger = $wallet->credit($amount, $source, $ref, $note);

        event(new WalletCredited($ledger));

        return $ledger;
    }

    /**
     * @throws InsufficientBalanceException
     */
    public function debit(int $userId, string|float $amount, string $source, ?string $ref = null, ?string $note = null): WalletLedger
    {
        $wallet = Wallet::query()->where('user_id', $userId)->firstOrFail();

        try {
            return $wallet->debit($amount, $source, $ref, $note);
        } catch (RuntimeException $e) {
            if (str_contains($e->getMessage(), 'Insufficient wallet balance')) {
                throw InsufficientBalanceException::forAmount((string) $amount);
            }

            throw $e;
        }
    }

    public function freeze(int $userId): void
    {
        DB::transaction(function () use ($userId): void {
            $wallet = Wallet::query()->where('user_id', $userId)->lockForUpdate()->firstOrFail();
            $wallet->is_frozen = true;
            $wallet->save();
        });
    }

    public function unfreeze(int $userId): void
    {
        DB::transaction(function () use ($userId): void {
            $wallet = Wallet::query()->where('user_id', $userId)->lockForUpdate()->firstOrFail();
            $wallet->is_frozen = false;
            $wallet->save();
        });
    }
}
