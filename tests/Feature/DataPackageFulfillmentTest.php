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

    public function test_no_http_when_no_provider_bundle_code(): void
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
            'name' => 'Plain Bundle',
            'size_label' => '1GB',
            'provider_bundle_type' => null,
            'internal_cost' => '5.00',
            'stock_count' => 10,
            'is_available' => true,
        ]);

        Http::fake();

        $buyer = $this->buyerWithWallet('100.00');

        $this->actingAs($buyer)->post(route('buyer.orders.store'), [
            'network' => 'MTN',
            'phone_number' => '0244123456',
            'bundle_package_id' => $bundle->id,
            'confirm' => true,
        ])->assertRedirect(route('buyer.orders.index'));

        Http::assertNothingSent();

        $order = Order::query()->latest('id')->firstOrFail();
        $this->assertNull($order->provider_order_reference);
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
