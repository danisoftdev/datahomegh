<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use RuntimeException;

class WalletLedger extends Model
{
    protected $table = 'wallet_ledger';

    public $timestamps = false;

    protected $fillable = [
        'user_id',
        'type',
        'amount',
        'balance_before',
        'balance_after',
        'source',
        'reference',
        'note',
        'created_at',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'balance_before' => 'decimal:2',
            'balance_after' => 'decimal:2',
            'created_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function save(array $options = []): bool
    {
        if ($this->exists) {
            throw new RuntimeException('Wallet ledger rows are immutable and cannot be updated.');
        }

        return parent::save($options);
    }
}
