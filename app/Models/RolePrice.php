<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RolePrice extends Model
{
    protected $fillable = [
        'role_id',
        'bundle_package_id',
        'price',
    ];

    protected function casts(): array
    {
        return [
            'price' => 'decimal:2',
        ];
    }

    public function role(): BelongsTo
    {
        return $this->belongsTo(Role::class);
    }

    public function bundlePackage(): BelongsTo
    {
        return $this->belongsTo(BundlePackage::class);
    }
}
