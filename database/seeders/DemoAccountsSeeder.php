<?php

namespace Database\Seeders;

use App\Models\AgentEarningsBalance;
use App\Models\BundlePackage;
use App\Models\ResalePlan;
use App\Models\Role;
use App\Models\RolePrice;
use App\Models\User;
use App\Models\Wallet;
use Illuminate\Database\Seeder;

class DemoAccountsSeeder extends Seeder
{
    public const DEMO_PASSWORD = 'Demo@12345';

    public function run(): void
    {
        $supplierRole = Role::query()->where('slug', Role::SLUG_SUPPLIER)->firstOrFail();
        $agentRole = Role::query()->where('slug', Role::SLUG_AGENT)->firstOrFail();
        $buyerRole = Role::query()->where('slug', Role::SLUG_BUYER)->firstOrFail();

        $supplier = User::query()->where('username', 'superadmin')->first();
        if ($supplier === null) {
            $supplier = User::query()->create([
                'username' => 'superadmin',
                'name' => 'DataHomeGH Admin',
                'email' => 'admin@datahomegh.shop',
                'phone' => '0200000000',
                'password' => 'Admin@12345',
                'role_id' => $supplierRole->id,
                'shop_name' => 'DataHomeGH',
                'status' => 'active',
                'wallet_frozen' => false,
            ]);
        }

        Wallet::query()->updateOrCreate(
            ['user_id' => $supplier->id],
            ['balance' => '0.00', 'is_frozen' => false],
        );

        $agent = User::query()->updateOrCreate(
            ['username' => 'demo_agent'],
            [
                'name' => 'Legacy Demo Agent',
                'email' => 'agent@demo.datahomegh.test',
                'phone' => '0244000001',
                'password' => self::DEMO_PASSWORD,
                'role_id' => $agentRole->id,
                'agent_id' => null,
                'shop_slug' => 'demo-legacy',
                'shop_name' => 'Legacy Demo Shop',
                'whatsapp_number' => '0244000001',
                'business_description' => 'Demo agent shop for local testing and demos.',
                'status' => 'active',
                'wallet_frozen' => false,
            ],
        );

        Wallet::query()->updateOrCreate(
            ['user_id' => $agent->id],
            ['balance' => '500.00', 'is_frozen' => false],
        );

        if (\Illuminate\Support\Facades\Schema::hasTable('agent_earnings_balances')) {
            AgentEarningsBalance::query()->updateOrCreate(
                ['agent_id' => $agent->id],
                ['balance' => '0.00', 'pending_withdrawal' => '0.00'],
            );
        }

        $shopBuyer = User::query()->updateOrCreate(
            ['username' => 'demo_shop_buyer'],
            [
                'name' => 'Demo Shop Buyer',
                'email' => 'shopbuyer@demo.datahomegh.test',
                'phone' => '0244000002',
                'password' => self::DEMO_PASSWORD,
                'role_id' => $buyerRole->id,
                'agent_id' => $agent->id,
                'status' => 'active',
                'wallet_frozen' => false,
                'paystack_checkout_only' => false,
            ],
        );

        Wallet::query()->updateOrCreate(
            ['user_id' => $shopBuyer->id],
            ['balance' => '200.00', 'is_frozen' => false],
        );

        $platformBuyer = User::query()->updateOrCreate(
            ['username' => 'demo_buyer'],
            [
                'name' => 'Demo Platform Buyer',
                'email' => 'buyer@demo.datahomegh.test',
                'phone' => '0244000003',
                'password' => self::DEMO_PASSWORD,
                'role_id' => $buyerRole->id,
                'agent_id' => null,
                'status' => 'active',
                'wallet_frozen' => false,
            ],
        );

        Wallet::query()->updateOrCreate(
            ['user_id' => $platformBuyer->id],
            ['balance' => '200.00', 'is_frozen' => false],
        );

        $platformBundle = BundlePackage::query()->updateOrCreate(
            [
                'agent_id' => null,
                'network' => 'MTN',
                'size_label' => '2GB',
                'package_kind' => 'data',
            ],
            [
                'name' => 'MTN 2GB',
                'internal_cost' => '8.00',
                'stock_count' => 500,
                'is_available' => true,
            ],
        );

        RolePrice::query()->updateOrCreate(
            ['role_id' => $buyerRole->id, 'bundle_package_id' => $platformBundle->id],
            ['price' => '10.00'],
        );

        RolePrice::query()->updateOrCreate(
            ['role_id' => $agentRole->id, 'bundle_package_id' => $platformBundle->id],
            ['price' => '7.50'],
        );

        $agentBundle = BundlePackage::query()->updateOrCreate(
            [
                'agent_id' => $agent->id,
                'network' => 'MTN',
                'size_label' => '2GB',
                'package_kind' => 'data',
            ],
            [
                'name' => 'MTN 2GB',
                'internal_cost' => '8.00',
                'stock_count' => 100,
                'is_available' => true,
            ],
        );

        ResalePlan::query()->updateOrCreate(
            ['bundle_package_id' => $agentBundle->id, 'agent_id' => $agent->id, 'label' => 'Retail'],
            ['price' => '12.00', 'is_active' => true],
        );

        $shopUrl = url('/'.$agent->shop_slug);

        if ($this->command) {
            $this->command->newLine();
            $this->command->info('=== DataHomeGH demo accounts (local testing) ===');
            $this->command->table(
                ['Role', 'Username', 'Password', 'Notes'],
                [
                    ['Supplier (admin)', 'superadmin', 'Admin@12345', 'Full admin dashboard'],
                    ['Agent', 'demo_agent', self::DEMO_PASSWORD, 'Shop: '.$shopUrl.' · wallet GH₵500'],
                    ['Agent-shop buyer', 'demo_shop_buyer', self::DEMO_PASSWORD, 'Linked to demo_agent · wallet GH₵200'],
                    ['Platform buyer', 'demo_buyer', self::DEMO_PASSWORD, 'Main store buyer · wallet GH₵200'],
                ],
            );
            $this->command->line('Login: '.url('/login'));
            $this->command->newLine();
        }
    }
}
