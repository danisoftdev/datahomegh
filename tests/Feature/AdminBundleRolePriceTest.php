<?php

namespace Tests\Feature;

use App\Models\BundlePackage;
use App\Models\Role;
use App\Models\RolePrice;
use App\Models\User;
use App\Models\Wallet;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminBundleRolePriceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);
    }

    private function supplier(): User
    {
        $role = Role::query()->where('slug', Role::SLUG_SUPPLIER)->firstOrFail();

        return User::factory()->create([
            'role_id' => $role->id,
            'status' => 'active',
        ]);
    }

    public function test_supplier_can_set_distinct_buyer_and_agent_list_prices_on_platform_bundle(): void
    {
        $supplier = $this->supplier();
        $bundle = BundlePackage::query()->create([
            'agent_id' => null,
            'network' => 'MTN',
            'package_kind' => 'data',
            'name' => 'Role price bundle',
            'size_label' => '1GB',
            'internal_cost' => '5.00',
            'stock_count' => 10,
            'is_available' => true,
        ]);

        $this->actingAs($supplier)->put(route('admin.bundles.update', $bundle), [
            'network' => 'MTN',
            'package_kind' => 'data',
            'name' => 'Role price bundle',
            'size_label' => '1GB',
            'internal_cost' => '5.00',
            'stock_count' => 10,
            'is_available' => '1',
            'buyer_list_price' => '12.50',
            'agent_list_price' => '7.25',
        ])->assertRedirect(route('admin.bundles.index'));

        $buyerRoleId = (int) Role::query()->where('slug', Role::SLUG_BUYER)->value('id');
        $agentRoleId = (int) Role::query()->where('slug', Role::SLUG_AGENT)->value('id');

        $this->assertDatabaseHas('role_prices', [
            'bundle_package_id' => $bundle->id,
            'role_id' => $buyerRoleId,
            'price' => '12.50',
        ]);
        $this->assertDatabaseHas('role_prices', [
            'bundle_package_id' => $bundle->id,
            'role_id' => $agentRoleId,
            'price' => '7.25',
        ]);

        $buyerRole = Role::query()->where('slug', Role::SLUG_BUYER)->firstOrFail();
        $buyer = User::factory()->create([
            'role_id' => $buyerRole->id,
            'status' => 'active',
            'agent_id' => null,
        ]);
        Wallet::query()->create([
            'user_id' => $buyer->id,
            'balance' => '100.00',
            'is_frozen' => false,
        ]);

        $this->actingAs($buyer)->post(route('buyer.orders.store'), [
            'network' => 'MTN',
            'phone_number' => '0244123456',
            'bundle_package_id' => $bundle->id,
            'confirm' => true,
        ])->assertRedirect(route('buyer.orders.index'));

        $this->assertSame('87.50', (string) $buyer->wallet->fresh()->balance);

        $agentRole = Role::query()->where('slug', Role::SLUG_AGENT)->firstOrFail();
        $agent = User::factory()->create([
            'role_id' => $agentRole->id,
            'status' => 'active',
            'shop_slug' => 'rp-shop',
        ]);
        Wallet::query()->create([
            'user_id' => $agent->id,
            'balance' => '100.00',
            'is_frozen' => false,
        ]);

        $this->actingAs($agent)->post(route('agent.orders.store'), [
            'confirm' => true,
            'items' => [
                [
                    'network' => 'MTN',
                    'phone_number' => '0244987654',
                    'bundle_package_id' => $bundle->id,
                ],
            ],
        ])->assertRedirect(route('agent.orders.index'));

        $this->assertSame('92.75', (string) $agent->wallet->fresh()->balance);
    }

    public function test_clearing_role_prices_falls_back_to_base_price(): void
    {
        $supplier = $this->supplier();
        $bundle = BundlePackage::query()->create([
            'agent_id' => null,
            'network' => 'MTN',
            'package_kind' => 'data',
            'name' => 'Clear rp',
            'size_label' => '1GB',
            'internal_cost' => '4.00',
            'stock_count' => 10,
            'is_available' => true,
        ]);

        $buyerRoleId = (int) Role::query()->where('slug', Role::SLUG_BUYER)->value('id');
        RolePrice::query()->create([
            'role_id' => $buyerRoleId,
            'bundle_package_id' => $bundle->id,
            'price' => '99.00',
        ]);

        $this->actingAs($supplier)->put(route('admin.bundles.update', $bundle), [
            'network' => 'MTN',
            'package_kind' => 'data',
            'name' => 'Clear rp',
            'size_label' => '1GB',
            'internal_cost' => '4.00',
            'stock_count' => 10,
            'is_available' => '1',
            'buyer_list_price' => '',
            'agent_list_price' => '',
        ])->assertRedirect(route('admin.bundles.index'));

        $this->assertDatabaseMissing('role_prices', [
            'bundle_package_id' => $bundle->id,
            'role_id' => $buyerRoleId,
        ]);
    }
}
