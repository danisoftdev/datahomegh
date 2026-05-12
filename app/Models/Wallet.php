<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class Wallet extends Model
{
    protected $fillable = [
        'user_id',
        'balance',
        'is_frozen',
    ];

    protected function casts(): array
    {
        return [
            'balance' => 'decimal:2',
            'is_frozen' => 'boolean',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function walletLedgers(): HasMany
    {
        return $this->hasMany(WalletLedger::class, 'user_id', 'user_id');
    }

    /**
     * @return WalletLedger The immutable ledger row for this credit.
     */
    public function credit(string|float $amount, string $source, ?string $ref = null, ?string $note = null): WalletLedger
    {
        $amountStr = $this->normalizeAmount($amount);

        return DB::transaction(function () use ($amountStr, $source, $ref, $note) {
            /** @var self $locked */
            $locked = static::query()->whereKey($this->getKey())->lockForUpdate()->firstOrFail();

            if ($locked->is_frozen) {
                throw new RuntimeException('Wallet is frozen; credits are not allowed.');
            }

            $before = $locked->balance;
            $after = bcadd((string) $before, $amountStr, 2);

            $locked->balance = $after;
            $locked->save();

            return WalletLedger::query()->create([
                'user_id' => $locked->user_id,
                'type' => 'CREDIT',
                'amount' => $amountStr,
                'balance_before' => $before,
                'balance_after' => $after,
                'source' => $source,
                'reference' => $ref,
                'note' => $note,
                'created_at' => now(),
            ]);
        });
    }

    /**
     * @return WalletLedger The immutable ledger row for this debit.
     */
    public function debit(string|float $amount, string $source, ?string $ref = null, ?string $note = null): WalletLedger
    {
        $amountStr = $this->normalizeAmount($amount);

        return DB::transaction(function () use ($amountStr, $source, $ref, $note) {
            /** @var self $locked */
            $locked = static::query()->whereKey($this->getKey())->lockForUpdate()->firstOrFail();

            if ($locked->is_frozen) {
                throw new RuntimeException('Wallet is frozen; debits are not allowed.');
            }

            $before = $locked->balance;

            if (bccomp((string) $before, $amountStr, 2) < 0) {
                throw new RuntimeException('Insufficient wallet balance for this debit.');
            }

            $after = bcsub((string) $before, $amountStr, 2);

            $locked->balance = $after;
            $locked->save();

            return WalletLedger::query()->create([
                'user_id' => $locked->user_id,
                'type' => 'DEBIT',
                'amount' => $amountStr,
                'balance_before' => $before,
                'balance_after' => $after,
                'source' => $source,
                'reference' => $ref,
                'note' => $note,
                'created_at' => now(),
            ]);
        });
    }

    private function normalizeAmount(string|float $amount): string
    {
        if (is_float($amount) || is_int($amount)) {
            return number_format((float) $amount, 2, '.', '');
        }

        if (! is_numeric($amount)) {
            throw new RuntimeException('Amount must be numeric.');
        }

        return bcadd((string) $amount, '0', 2);
    }
}
