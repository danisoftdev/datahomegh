<?php

namespace Database\Seeders;

use App\Models\Role;
use App\Models\User;
use App\Models\Wallet;
use Illuminate\Database\Seeder;

class SupplierAdminSeeder extends Seeder
{
    public function run(): void
    {
        $supplierRole = Role::query()->where('slug', Role::SLUG_SUPPLIER)->first();

        if ($supplierRole === null) {
            if ($this->command) {
                $this->command->error('Supplier role not found. Run RolesAndPermissionsSeeder first.');
            }

            return;
        }

        $anySupplier = User::query()
            ->whereHas('role', fn ($q) => $q->where('slug', Role::SLUG_SUPPLIER))
            ->orderBy('id')
            ->first();

        if ($anySupplier !== null) {
            Wallet::query()->firstOrCreate(
                ['user_id' => $anySupplier->id],
                ['balance' => 0, 'is_frozen' => false]
            );

            if ($this->command) {
                $this->command->warn('A supplier account already exists — skipped creating another. Only one supplier is allowed.');
            }

            return;
        }

        $plainPassword = 'Admin@12345';

        $user = User::query()->where('username', 'superadmin')->first();

        if ($user !== null) {
            if ($this->command) {
                $this->command->warn('User "superadmin" already exists but is not a supplier — skipped. Assign supplier role manually if needed.');
            }

            Wallet::query()->firstOrCreate(
                ['user_id' => $user->id],
                ['balance' => 0, 'is_frozen' => false]
            );

            return;
        }

        $user = User::query()->create([
            'username' => 'superadmin',
            'name' => 'DataHomeGH Admin',
            'email' => 'admin@datahomegh.shop',
            'phone' => '0200000000',
            'password' => $plainPassword,
            'role_id' => $supplierRole->id,
            'agent_id' => null,
            'shop_name' => 'DataHomeGH',
            'status' => 'active',
            'wallet_frozen' => false,
        ]);

        Wallet::query()->firstOrCreate(
            ['user_id' => $user->id],
            ['balance' => 0, 'is_frozen' => false]
        );

        if ($this->command) {
            $this->command->newLine();
            $this->command->info('=== DataHomeGH supplier admin (created) ===');
            $this->command->table(
                ['Field', 'Value'],
                [
                    ['Username', 'superadmin'],
                    ['Password', $plainPassword],
                    ['Email', 'admin@datahomegh.shop'],
                ]
            );
            $this->command->warn('Change this password after first login in production.');
            $this->command->newLine();
        }
    }
}
