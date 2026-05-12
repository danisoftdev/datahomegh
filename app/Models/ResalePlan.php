<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ResalePlan extends Model
{
    protected $fillable = [
        'bundle_package_id',
        'agent_id',
        'price',
        'label',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'price' => 'decimal:2',
            'is_active' => 'boolean',
        ];
    }

    public function bundlePackage(): BelongsTo
    {
        return $this->belongsTo(BundlePackage::class);
    }

    public function agent(): BelongsTo
    {
        return $this->belongsTo(User::class, 'agent_id');
    }
}
