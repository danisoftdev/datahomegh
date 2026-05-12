<?php

namespace Database\Seeders;

use App\Models\Permission;
use App\Models\Role;
use Illuminate\Database\Seeder;

class RolesAndPermissionsSeeder extends Seeder
{
    /**
     * Permission slug => human-readable name.
     *
     * @var array<string, string>
     */
    private const PERMISSIONS = [
        'place_orders' => 'Place orders',
        'manage_bundles' => 'Manage bundles',
        'manage_buyers' => 'Manage buyers',
        'manage_agents' => 'Manage agents',
        'view_wallet' => 'View wallet',
        'update_order_status' => 'Update order status',
        'add_order_note' => 'Add order note',
        'bulk_update_orders' => 'Bulk update orders',
        'credit_wallet' => 'Credit wallet',
        'debit_wallet' => 'Debit wallet',
        'freeze_wallet' => 'Freeze wallet',
        'manage_roles' => 'Manage roles',
        'manage_users' => 'Manage users',
        'broadcast_notifications' => 'Broadcast notifications',
        'approve_accounts' => 'Approve accounts',
        'issue_reset_codes' => 'Issue reset codes',
        'view_all_orders' => 'View all orders',
        'export_orders' => 'Export orders',
        'manage_stock' => 'Manage stock',
    ];

    private const AGENT_PERMISSION_SLUGS = [
        'place_orders',
        'manage_bundles',
        'manage_buyers',
        'view_wallet',
        'update_order_status',
        'add_order_note',
        'bulk_update_orders',
        'approve_accounts',
        'view_all_orders',
    ];

    private const BUYER_PERMISSION_SLUGS = [
        'place_orders',
        'view_wallet',
    ];

    public function run(): void
    {
        $supplier = Role::query()->firstOrCreate(
            ['slug' => Role::SLUG_SUPPLIER],
            ['name' => 'Supplier', 'is_enabled' => true]
        );

        $agent = Role::query()->firstOrCreate(
            ['slug' => Role::SLUG_AGENT],
            ['name' => 'Agent', 'is_enabled' => true]
        );

        $buyer = Role::query()->firstOrCreate(
            ['slug' => Role::SLUG_BUYER],
            ['name' => 'Buyer', 'is_enabled' => true]
        );

        $permissionModels = collect(self::PERMISSIONS)->map(function (string $name, string $slug) {
            return Permission::query()->firstOrCreate(
                ['slug' => $slug],
                ['name' => $name, 'description' => null]
            );
        });

        $supplier->permissions()->sync($permissionModels->pluck('id')->all());

        $agentIds = $permissionModels->whereIn('slug', self::AGENT_PERMISSION_SLUGS)->pluck('id')->all();
        $agent->permissions()->sync($agentIds);

        $buyerIds = $permissionModels->whereIn('slug', self::BUYER_PERMISSION_SLUGS)->pluck('id')->all();
        $buyer->permissions()->sync($buyerIds);
    }
};
