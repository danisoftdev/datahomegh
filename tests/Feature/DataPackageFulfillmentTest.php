<?php

namespace Tests\Feature;

use App\Models\BundlePackage;
use App\Models\FulfillmentApiProfile;
use App\Models\Order;
use App\Models\Role;
use App\Models\User;
use App\Models\Wallet;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class DataPackageFulfillmentTest extends TestCase
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

    private function buyerWithWallet(string $balance = '100.00'): User
    {
        $buyerRole = Role::query()->where('slug', Role::SLUG_BUYER)->firstOrFail();
        $buyer = User::factory()->create([
            'role_id' => $buyerRole->id,
            'status' => 'active',
        ]);
        Wallet::query()->create([
            'user_id' => $buyer->id,
            'balance' => $balance,
            'is_frozen' => false,
        ]);

        return $buyer;
    }

    public function test_agent_own_checkout_dispatches_to_external_api(): void
    {
        $supplier = $this->supplier();

        FulfillmentApiProfile::query()->create([
            'supplier_user_id' => $supplier->id,
            'network' => 'MTN',
            'name' => 'Test API',
            'base_url' => 'https://provider.test',
            'api_key' => 'secret-key',
            'default_provider_bundle_type' => 'mtnup2u',
            'is_active' => true,
        ]);

        $agentRole = Role::query()->where('slug', Role::SLUG_AGENT)->firstOrFail();
        $agent = User::factory()->create([
            'role_id' => $agentRole->id,
            'status' => 'active',
            'shop_slug' => 'agent-api',
        ]);
        Wallet::query()->create([
            'user_id' => $agent->id,
            'balance' => '100.00',
            'is_frozen' => false,
        ]);

        $bundle = BundlePackage::query()->create([
            'agent_id' => null,
            'network' => 'MTN',
            'package_kind' => 'data',
            'name' => 'Platform bundle',
            'size_label' => '1GB',
            'provider_bundle_type' => 'mtnup2u',
            'internal_cost' => '5.00',
            'stock_count' => 10,
            'is_available' => true,
        ]);

        Http::fake([
            'https://provider.test/api/developer/orders/place' => Http::response([
                'success' => true,
                'data' => ['order' => ['orderReference' => 'AGENT-REF', 'status' => 'pending']],
            ], 200),
        ]);

        $this->actingAs($agent)->post(route('agent.orders.store'), [
            'confirm' => true,
            'items' => [
                [
                    'network' => 'MTN',
                    'phone_number' => '0244123456',
                    'bundle_package_id' => $bundle->id,
                ],
            ],
        ])->assertRedirect();

        Http::assertSent(fn ($request) => $request->url() === 'https://provider.test/api/developer/orders/place');
        $this->assertSame('AGENT-REF', Order::query()->where('user_id', $agent->id)->value('provider_order_reference'));
    }

    public function test_agent_linked_buyer_order_is_not_sent_to_external_api(): void
    {
        $supplier = $this->supplier();

        FulfillmentApiProfile::query()->create([
            'supplier_user_id' => $supplier->id,
            'network' => 'MTN',
            'name' => 'Test API',
            'base_url' => 'https://provider.test',
            'api_key' => 'secret-key',
            'default_provider_bundle_type' => 'mtnup2u',
            'is_active' => true,
        ]);

        $agentRole = Role::query()->where('slug', Role::SLUG_AGENT)->firstOrFail();
        $agent = User::factory()->create([
            'role_id' => $agentRole->id,
            'status' => 'active',
            'shop_slug' => 'shop-test',
        ]);

        $buyerRole = Role::query()->where('slug', Role::SLUG_BUYER)->firstOrFail();
        $buyer = User::factory()->create([
            'role_id' => $buyerRole->id,
            'agent_id' => $agent->id,
            'status' => 'active',
        ]);
        Wallet::query()->create([
            'user_id' => $buyer->id,
            'balance' => '100.00',
            'is_frozen' => false,
        ]);

        $bundle = BundlePackage::query()->create([
            'agent_id' => $agent->id,
            'network' => 'MTN',
            'package_kind' => 'data',
            'name' => 'Agent resale bundle',
            'size_label' => '1GB',
            'provider_bundle_type' => 'mtnup2u',
            'internal_cost' => '5.00',
            'stock_count' => 10,
            'is_available' => true,
        ]);

        Http::fake();

        $this->actingAs($buyer)->post(route('buyer.orders.store'), [
            'network' => 'MTN',
            'phone_number' => '0244123456',
            'bundle_package_id' => $bundle->id,
            'confirm' => true,
        ])->assertRedirect(route('buyer.orders.index'));

        Http::assertNothingSent();

        $order = Order::query()->where('user_id', $buyer->id)->firstOrFail();
        $this->assertSame((int) $agent->id, (int) $order->agent_id);
        $this->assertNull($order->provider_order_reference);
        $this->assertNull($order->provider_dispatch_error);
    }

    public function test_data_order_dispatches_to_active_provider_and_stores_reference(): void
    {
        $supplier = $this->supplier();

        FulfillmentApiProfile::query()->create([
            'supplier_user_id' => $supplier->id,
            'network' => 'MTN',
            'name' => 'Test API',
            'base_url' => 'https://provider.test',
            'api_key' => 'secret-key',
            'is_active' => true,
        ]);

        $bundle = BundlePackage::query()->create([
            'agent_id' => null,
            'network' => 'MTN',
            'package_kind' => 'data',
            'name' => 'API Bundle',
            'size_label' => '1GB',
            'provider_bundle_type' => 'mtnup2u',
            'internal_cost' => '5.00',
            'stock_count' => 10,
            'is_available' => true,
        ]);

        Http::fake([
            'https://provider.test/api/developer/orders/place' => Http::response([
                'success' => true,
                'data' => [
                    'order' => [
                        'orderReference' => 'EXTREF123',
                        'status' => 'pending',
                    ],
                ],
            ], 200),
        ]);

        $buyer = $this->buyerWithWallet('100.00');

        $this->actingAs($buyer)->post(route('buyer.orders.store'), [
            'network' => 'MTN',
            'phone_number' => '0244123456',
            'bundle_package_id' => $bundle->id,
            'confirm' => true,
        ])->assertRedirect(route('buyer.orders.index'));

        Http::assertSent(function ($request) {
            return $request->url() === 'https://provider.test/api/developer/orders/place'
                && $request->header('X-API-Key')[0] === 'secret-key'
                && $request['recipientNumber'] === '0244123456'
                && $request['bundleType'] === 'mtnup2u'
                && $request['capacity'] === 1;
        });

        $order = Order::query()->latest('id')->firstOrFail();
        $this->assertSame('EXTREF123', $order->provider_order_reference);
        $this->assertNotNull($order->fulfillment_api_profile_id);
    }

    public function test_mtn_data_dispatches_without_bundle_code_using_config_fallback(): void
    {
        $supplier = $this->supplier();

        FulfillmentApiProfile::query()->create([
            'supplier_user_id' => $supplier->id,
            'network' => 'MTN',
            'name' => 'Test API',
            'base_url' => 'https://provider.test',
            'api_key' => 'secret-key',
            'default_provider_bundle_type' => null,
            'is_active' => true,
        ]);

        $bundle = BundlePackage::query()->create([
            'agent_id' => null,
            'network' => 'MTN',
            'package_kind' => 'data',
            'name' => 'Plain Bundle',
            'size_label' => '1GB',
            'provider_bundle_type' => null,
            'internal_cost' => '5.00',
            'stock_count' => 10,
            'is_available' => true,
        ]);

        Http::fake([
            'https://provider.test/api/developer/orders/place' => Http::response([
                'success' => true,
                'data' => [
                    'order' => [
                        'orderReference' => 'EXTREF-FB',
                        'status' => 'pending',
                    ],
                ],
            ], 200),
        ]);

        $buyer = $this->buyerWithWallet('100.00');

        $this->actingAs($buyer)->post(route('buyer.orders.store'), [
            'network' => 'MTN',
            'phone_number' => '0244123456',
            'bundle_package_id' => $bundle->id,
            'confirm' => true,
        ])->assertRedirect(route('buyer.orders.index'));

        Http::assertSent(function ($request) {
            return $request->url() === 'https://provider.test/api/developer/orders/place'
                && $request['bundleType'] === 'mtnup2u';
        });

        $order = Order::query()->latest('id')->firstOrFail();
        $this->assertSame('EXTREF-FB', $order->provider_order_reference);
    }

    public function test_mtn_data_uses_profile_default_when_bundle_code_missing(): void
    {
        $supplier = $this->supplier();

        FulfillmentApiProfile::query()->create([
            'supplier_user_id' => $supplier->id,
            'network' => 'MTN',
            'name' => 'Test API',
            'base_url' => 'https://provider.test',
            'api_key' => 'secret-key',
            'default_provider_bundle_type' => 'mtn_special',
            'is_active' => true,
        ]);

        $bundle = BundlePackage::query()->create([
            'agent_id' => null,
            'network' => 'MTN',
            'package_kind' => 'data',
            'name' => 'Plain Bundle',
            'size_label' => '1GB',
            'provider_bundle_type' => null,
            'internal_cost' => '5.00',
            'stock_count' => 10,
            'is_available' => true,
        ]);

        Http::fake([
            'https://provider.test/api/developer/orders/place' => Http::response([
                'success' => true,
                'data' => ['order' => ['orderReference' => 'EXTREF-PD', 'status' => 'pending']],
            ], 200),
        ]);

        $buyer = $this->buyerWithWallet('100.00');

        $this->actingAs($buyer)->post(route('buyer.orders.store'), [
            'network' => 'MTN',
            'phone_number' => '0244123456',
            'bundle_package_id' => $bundle->id,
            'confirm' => true,
        ]);

        Http::assertSent(fn ($request) => $request['bundleType'] === 'mtn_special');
        $this->assertSame('EXTREF-PD', Order::query()->latest('id')->value('provider_order_reference'));
    }

    public function test_telecel_dispatches_without_bundle_code_using_config_fallback(): void
    {
        Config::set('datahome.fulfillment.telecel_data_fallback_bundle_type', 'telecel_fb_code');

        $supplier = $this->supplier();

        FulfillmentApiProfile::query()->create([
            'supplier_user_id' => $supplier->id,
            'network' => 'Telecel',
            'name' => 'Telecel API',
            'base_url' => 'https://provider.test',
            'api_key' => 'secret-key',
            'default_provider_bundle_type' => null,
            'is_active' => true,
        ]);

        $bundle = BundlePackage::query()->create([
            'agent_id' => null,
            'network' => 'Telecel',
            'package_kind' => 'data',
            'name' => 'Telecel bundle',
            'size_label' => '1GB',
            'provider_bundle_type' => null,
            'internal_cost' => '5.00',
            'stock_count' => 10,
            'is_available' => true,
        ]);

        Http::fake([
            'https://provider.test/api/developer/orders/place' => Http::response([
                'success' => true,
                'data' => ['order' => ['orderReference' => 'TEL-REF', 'status' => 'pending']],
            ], 200),
        ]);

        $buyer = $this->buyerWithWallet('100.00');

        $this->actingAs($buyer)->post(route('buyer.orders.store'), [
            'network' => 'Telecel',
            'phone_number' => '0200000000',
            'bundle_package_id' => $bundle->id,
            'confirm' => true,
        ]);

        Http::assertSent(fn ($request) => $request['bundleType'] === 'telecel_fb_code');
        $this->assertSame('TEL-REF', Order::query()->latest('id')->value('provider_order_reference'));
    }

    public function test_telecel_stays_without_codes_when_fallback_empty(): void
    {
        Config::set('datahome.fulfillment.telecel_data_fallback_bundle_type', '');

        $supplier = $this->supplier();

        FulfillmentApiProfile::query()->create([
            'supplier_user_id' => $supplier->id,
            'network' => 'Telecel',
            'name' => 'Telecel API',
            'base_url' => 'https://provider.test',
            'api_key' => 'secret-key',
            'default_provider_bundle_type' => null,
            'is_active' => true,
        ]);

        $bundle = BundlePackage::query()->create([
            'agent_id' => null,
            'network' => 'Telecel',
            'package_kind' => 'data',
            'name' => 'Telecel bundle',
            'size_label' => '1GB',
            'provider_bundle_type' => null,
            'internal_cost' => '5.00',
            'stock_count' => 10,
            'is_available' => true,
        ]);

        Http::fake();

        $buyer = $this->buyerWithWallet('100.00');

        $this->actingAs($buyer)->post(route('buyer.orders.store'), [
            'network' => 'Telecel',
            'phone_number' => '0200000000',
            'bundle_package_id' => $bundle->id,
            'confirm' => true,
        ]);

        Http::assertNothingSent();
        $order = Order::query()->latest('id')->firstOrFail();
        $this->assertNull($order->provider_order_reference);
        $this->assertNotNull($order->provider_dispatch_error);
    }

    public function test_airteltigo_dispatches_without_bundle_code_using_config_fallback(): void
    {
        Config::set('datahome.fulfillment.airteltigo_data_fallback_bundle_type', 'at_fb_code');

        $supplier = $this->supplier();

        FulfillmentApiProfile::query()->create([
            'supplier_user_id' => $supplier->id,
            'network' => 'AirtelTigo',
            'name' => 'AT API',
            'base_url' => 'https://provider.test',
            'api_key' => 'secret-key',
            'default_provider_bundle_type' => null,
            'is_active' => true,
        ]);

        $bundle = BundlePackage::query()->create([
            'agent_id' => null,
            'network' => 'AirtelTigo',
            'package_kind' => 'data',
            'name' => 'AT bundle',
            'size_label' => '1GB',
            'provider_bundle_type' => null,
            'internal_cost' => '5.00',
            'stock_count' => 10,
            'is_available' => true,
        ]);

        Http::fake([
            'https://provider.test/api/developer/orders/place' => Http::response([
                'success' => true,
                'data' => ['order' => ['orderReference' => 'AT-REF', 'status' => 'pending']],
            ], 200),
        ]);

        $buyer = $this->buyerWithWallet('100.00');

        $this->actingAs($buyer)->post(route('buyer.orders.store'), [
            'network' => 'AirtelTigo',
            'phone_number' => '0271234567',
            'bundle_package_id' => $bundle->id,
            'confirm' => true,
        ]);

        Http::assertSent(fn ($request) => $request['bundleType'] === 'at_fb_code');
        $this->assertSame('AT-REF', Order::query()->latest('id')->value('provider_order_reference'));
    }

    public function test_admin_dispatch_to_provider_shows_error_when_telecel_bundle_code_missing(): void
    {
        Config::set('datahome.fulfillment.telecel_data_fallback_bundle_type', '');

        $supplier = $this->supplier();

        FulfillmentApiProfile::query()->create([
            'supplier_user_id' => $supplier->id,
            'network' => 'Telecel',
            'name' => 'Test API',
            'base_url' => 'https://provider.test',
            'api_key' => 'secret-key',
            'default_provider_bundle_type' => null,
            'is_active' => true,
        ]);

        $bundle = BundlePackage::query()->create([
            'agent_id' => null,
            'network' => 'Telecel',
            'package_kind' => 'data',
            'name' => 'Plain Bundle',
            'size_label' => '1GB',
            'provider_bundle_type' => null,
            'internal_cost' => '5.00',
            'stock_count' => 10,
            'is_available' => true,
        ]);

        $buyer = $this->buyerWithWallet('100.00');

        $this->actingAs($buyer)->post(route('buyer.orders.store'), [
            'network' => 'Telecel',
            'phone_number' => '0200000000',
            'bundle_package_id' => $bundle->id,
            'confirm' => true,
        ]);

        $order = Order::query()->latest('id')->firstOrFail();

        $this->actingAs($supplier)->post(route('admin.orders.dispatch-to-provider', $order))
            ->assertRedirect()
            ->assertSessionHas('error');

        $this->assertStringContainsString('no provider bundle code', strtolower((string) $order->fresh()->provider_dispatch_error));
    }

    public function test_admin_can_refresh_provider_status(): void
    {
        $supplier = $this->supplier();

        FulfillmentApiProfile::query()->create([
            'supplier_user_id' => $supplier->id,
            'network' => 'MTN',
            'name' => 'Test API',
            'base_url' => 'https://provider.test',
            'api_key' => 'secret-key',
            'is_active' => true,
        ]);

        $bundle = BundlePackage::query()->create([
            'agent_id' => null,
            'network' => 'MTN',
            'package_kind' => 'data',
            'name' => 'API Bundle',
            'size_label' => '1GB',
            'provider_bundle_type' => 'mtnup2u',
            'internal_cost' => '5.00',
            'stock_count' => 10,
            'is_available' => true,
        ]);

        $buyer = $this->buyerWithWallet('100.00');

        Http::fake([
            'https://provider.test/api/developer/orders/place' => Http::response([
                'success' => true,
                'data' => ['order' => ['orderReference' => 'REFXYZ', 'status' => 'pending']],
            ], 200),
            'https://provider.test/api/developer/orders/reference/REFXYZ' => Http::response([
                'success' => true,
                'data' => ['order' => ['orderReference' => 'REFXYZ', 'status' => 'completed']],
            ], 200),
        ]);

        $this->actingAs($buyer)->post(route('buyer.orders.store'), [
            'network' => 'MTN',
            'phone_number' => '0244123456',
            'bundle_package_id' => $bundle->id,
            'confirm' => true,
        ]);

        $order = Order::query()->latest('id')->firstOrFail();
        $this->assertSame('REFXYZ', $order->provider_order_reference);

        Http::fake([
            'https://provider.test/api/developer/orders/reference/REFXYZ' => Http::response([
                'success' => true,
                'data' => ['order' => ['orderReference' => 'REFXYZ', 'status' => 'completed']],
            ], 200),
        ]);

        $this->actingAs($supplier)->post(route('admin.orders.refresh-provider-status', $order))
            ->assertRedirect();

        $this->assertSame('completed', (string) $order->fresh()->provider_status);
    }
}
