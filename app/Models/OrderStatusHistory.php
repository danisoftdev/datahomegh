<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use RuntimeException;

class OrderStatusHistory extends Model
{
    protected $table = 'order_status_history';

    public $timestamps = false;

    protected $fillable = [
        'order_id',
        'old_status',
        'new_status',
        'changed_by',
        'note',
        'visible_to_buyer',
        'created_at',
    ];

    protected function casts(): array
    {
        return [
            'visible_to_buyer' => 'boolean',
            'created_at' => 'datetime',
        ];
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function changedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'changed_by');
    }

    public function save(array $options = []): bool
    {
        if ($this->exists) {
            throw new RuntimeException('Order status history rows are immutable and cannot be updated.');
        }

        return parent::save($options);
    }
}
