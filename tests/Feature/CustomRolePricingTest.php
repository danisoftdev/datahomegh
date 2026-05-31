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

class CustomRolePricingTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);
    }

    private function supplier(): User
    {
        return User::factory()->create([
            'role_id' => Role::query()->where('slug', Role::SLUG_SUPPLIER)->value('id'),
            'status' => 'active',
        ]);
    }

    private function customRole(string $name = 'Wholesale'): Role
    {
        return Role::query()->create([
            'name' => $name,
            'slug' => 'wholesale',
            'is_enabled' => true,
            'available_at_registration' => false,
            'pricing_persona' => Role::PERSONA_BUYER,
            'requires_promotion_fee' => false,
        ]);
    }

    private function platformBundle(): BundlePackage
    {
        return BundlePackage::query()->create([
            'agent_id' => null,
            'network' => 'MTN',
            'package_kind' => 'data',
            'name' => 'MTN 1GB',
            'size_label' => '1GB',
            'internal_cost' => '10.00',
            'stock_count' => 10,
            'is_available' => true,
        ]);
    }

    public function test_admin_can_set_custom_role_price_on_platform_bundle(): void
    {
        $supplier = $this->supplier();
        $role = $this->customRole();
        $bundle = $this->platformBundle();

        $this->actingAs($supplier)->put(route('admin.bundles.update', $bundle), [
            'network' => 'MTN',
            'package_kind' => 'data',
            'name' => 'MTN 1GB',
            'size_label' => '1GB',
            'internal_cost' => '10.00',
            'stock_count' => 10,
            'is_available' => '1',
            'role_list_prices' => [
                (string) $role->id => '6.50',
            ],
        ])->assertRedirect(route('admin.bundles.index'));

        $this->assertDatabaseHas('role_prices', [
            'role_id' => $role->id,
            'bundle_package_id' => $bundle->id,
            'price' => '6.50',
        ]);
    }

    public function test_promoted_buyer_sees_custom_role_price_immediately(): void
    {
        $supplier = $this->supplier();
        $role = $this->customRole();
        $bundle = $this->platformBundle();

        RolePrice::query()->create([
            'role_id' => $role->id,
            'bundle_package_id' => $bundle->id,
            'price' => '6.50',
        ]);

        $buyer = User::factory()->create([
            'role_id' => Role::query()->where('slug', Role::SLUG_BUYER)->value('id'),
            'status' => 'active',
            'agent_id' => null,
        ]);
        Wallet::query()->create([
            'user_id' => $buyer->id,
            'balance' => '100.00',
            'is_frozen' => false,
        ]);

        $this->actingAs($supplier)->patch(route('admin.users.role', $buyer), [
            'role_id' => $role->id,
            'waive_promotion_fee' => '1',
        ])->assertRedirect();

        $buyer->refresh();
        $this->assertSame($role->id, $buyer->role_id);

        $this->actingAs($buyer)->post(route('buyer.orders.store'), [
            'network' => 'MTN',
            'phone_number' => '0244123456',
            'bundle_package_id' => $bundle->id,
            'confirm' => true,
        ])->assertRedirect(route('buyer.orders.index'));

        $this->assertSame('93.50', (string) $buyer->wallet->fresh()->balance);
    }

    public function test_promoted_agent_keeps_shop_and_uses_custom_role_price(): void
    {
        $supplier = $this->supplier();
        $role = Role::query()->create([
            'name' => 'VIP Agent',
            'slug' => 'vip-agent',
            'is_enabled' => true,
            'available_at_registration' => false,
            'pricing_persona' => Role::PERSONA_AGENT,
            'requires_promotion_fee' => false,
        ]);
        $bundle = $this->platformBundle();

        RolePrice::query()->create([
            'role_id' => $role->id,
            'bundle_package_id' => $bundle->id,
            'price' => '4.00',
        ]);

        $agent = User::factory()->create([
            'role_id' => Role::query()->where('slug', Role::SLUG_AGENT)->value('id'),
            'status' => 'active',
            'shop_slug' => 'vip-shop',
            'shop_name' => 'VIP Shop',
        ]);
        Wallet::query()->create([
            'user_id' => $agent->id,
            'balance' => '100.00',
            'is_frozen' => false,
        ]);

        $this->actingAs($supplier)->patch(route('admin.users.role', $agent), [
            'role_id' => $role->id,
            'waive_promotion_fee' => '1',
        ])->assertRedirect();

        $agent->refresh();
        $this->assertSame('vip-shop', $agent->shop_slug);
        $this->assertSame('VIP Shop', $agent->shop_name);
        $this->assertSame($role->id, $agent->role_id);

        $this->actingAs($agent)->get(route('shop.show', 'vip-shop'))->assertOk();

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

        $this->assertSame('96.00', (string) $agent->wallet->fresh()->balance);
    }

    public function test_custom_role_not_available_at_registration(): void
    {
        $role = $this->customRole();

        $this->assertFalse($role->available_at_registration);
        $this->assertTrue($role->isCustomRole());
    }
}
