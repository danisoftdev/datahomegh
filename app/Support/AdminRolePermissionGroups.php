<?php

namespace App\Support;

final class AdminRolePermissionGroups
{
    /**
     * Permission slugs grouped for supplier admin role UI (must match seeded slugs).
     *
     * @return array<string, list<string>>
     */
    public static function groups(): array
    {
        return [
            'Orders' => [
                'place_orders',
                'update_order_status',
                'add_order_note',
                'bulk_update_orders',
                'view_all_orders',
                'export_orders',
            ],
            'Wallet' => [
                'view_wallet',
                'credit_wallet',
                'debit_wallet',
                'freeze_wallet',
            ],
            'Users' => [
                'manage_users',
                'manage_agents',
                'manage_buyers',
                'approve_accounts',
                'issue_reset_codes',
            ],
            'Bundles' => [
                'manage_bundles',
                'manage_stock',
            ],
            'System' => [
                'manage_roles',
                'broadcast_notifications',
            ],
        ];
    }

    /**
     * @return list<string>
     */
    public static function allSlugs(): array
    {
        $all = [];
        foreach (self::groups() as $slugs) {
            $all = array_merge($all, $slugs);
        }

        return array_values(array_unique($all));
    }
}
