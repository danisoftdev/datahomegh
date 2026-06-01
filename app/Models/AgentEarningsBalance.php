<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AgentEarningsBalance extends Model
{
    protected $fillable = [
        'agent_id',
        'balance',
        'pending_withdrawal',
    ];

    protected function casts(): array
    {
        return [
            'balance' => 'decimal:2',
            'pending_withdrawal' => 'decimal:2',
        ];
    }

    public function agent(): BelongsTo
    {
        return $this->belongsTo(User::class, 'agent_id');
    }

    public function ledgerEntries(): HasMany
    {
        return $this->hasMany(AgentEarningsLedger::class, 'agent_id', 'agent_id');
    }
}
