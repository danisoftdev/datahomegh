<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PaystackTransaction extends Model
{
    protected $fillable = [
        'user_id',
        'reference',
        'amount',
        'status',
        'channel',
        'paid_at',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'paid_at' => 'datetime',
            'metadata' => 'array',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
