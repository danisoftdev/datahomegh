<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AgentEarningsLedger extends Model
{
    public $timestamps = false;

    protected $table = 'agent_earnings_ledger';

    protected $fillable = [
        'agent_id',
        'order_id',
        'withdrawal_request_id',
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

    public function save(array $options = []): bool
    {
        if ($this->exists) {
            throw new \RuntimeException('Agent earnings ledger entries are immutable.');
        }

        return parent::save($options);
    }

    public function agent(): BelongsTo
    {
        return $this->belongsTo(User::class, 'agent_id');
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function withdrawalRequest(): BelongsTo
    {
        return $this->belongsTo(AgentWithdrawalRequest::class, 'withdrawal_request_id');
    }
}
