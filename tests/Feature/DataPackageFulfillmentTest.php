<?php

namespace Tests\Feature;

use App\Models\BundlePackage;
use App\Models\FulfillmentApiProfile;
use App\Models\Order;
use App\Models\Role;
use App\Models\User;
use App\Models\Wallet;
use App\Support\FulfillmentProviderType;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;
use PHPUnit\Framework\Attributes\DataProvider;
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

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function geonetProfile(User $supplier, array $overrides = []): FulfillmentApiProfile
    {
        return FulfillmentApiProfile::query()->create(array_merge([
            'supplier_user_id' => $supplier->id,
            'network' => 'MTN',
            'provider_type' => FulfillmentProviderType::GEONET,
            'name' => 'Geonettech MTN',
            'base_url' => 'https://provider.test',
            'api_key' => 'geonet-token',
            'default_provider_bundle_type' => 'YELLO',
            'is_active' => true,
        ], $overrides));
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function igetProfile(User $supplier, array $overrides = []): FulfillmentApiProfile
    {
        return FulfillmentApiProfile::query()->create(array_merge([
            'supplier_user_id' => $supplier->id,
            'network' => 'Telecel',
            'provider_type' => FulfillmentProviderType::IGET,
            'name' => 'iGet Telecel',
            'base_url' => 'https://provider.test',
            'api_key' => 'iget-key',
            'default_provider_bundle_type' => 'telecelup2u',
            'is_active' => true,
        ], $overrides));
    }

    /**
     * @return array<string, mixed>
     */
    private function geonetPlaceSuccessResponse(): array
    {
        return [
            'data' => [
                'status' => 'success',
                'orders' => [
                    ['status' => 'pending'],
                ],
            ],
        ];
    }

    public function test_agent_own_checkout_dispatches_to_geonet_for_mtn(): void
    {
        $supplier = $this->supplier();
        $this->geonetProfile($supplier);

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
            'provider_bundle_type' => 'YELLO',
            'internal_cost' => '5.00',
            'stock_count' => 10,
            'is_available' => true,
        ]);

        Http::fake([
            'https://provider.test/v1/place-order' => Http::response($this->geonetPlaceSuccessResponse(), 200),
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

        Http::assertSent(fn ($request) => $request->url() === 'https://provider.test/v1/place-order');
        $order = Order::query()->where('user_id', $agent->id)->firstOrFail();
        $this->assertSame((string) $order->id, $order->provider_order_reference);
    }

    public function test_agent_linked_buyer_order_is_not_sent_to_external_api(): void
    {
        $supplier = $this->supplier();
        $this->geonetProfile($supplier);

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
            'provider_bundle_type' => 'YELLO',
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

    #[DataProvider('normalizeBaseUrlProvider')]
    public function test_normalize_iget_base_url_strips_api_path_suffixes(string $input, string $expected): void
    {
        $this->assertSame($expected, FulfillmentApiProfile::normalizeBaseUrl($input));
    }

    /**
     * @return array<string, array{0: string, 1: string}>
     */
    public static function normalizeBaseUrlProvider(): array
    {
        return [
            'host only' => ['https://iget.onrender.com', 'https://iget.onrender.com'],
            'trailing slash' => ['https://iget.onrender.com/', 'https://iget.onrender.com'],
            'full place path' => ['https://iget.onrender.com/api/developer/orders/place', 'https://iget.onrender.com'],
            'developer root' => ['https://iget.onrender.com/api/developer', 'https://iget.onrender.com'],
        ];
    }

    #[DataProvider('normalizeGeonetBaseUrlProvider')]
    public function test_normalize_geonet_base_url_strips_v1_suffixes(string $input, string $expected): void
    {
        $this->assertSame($expected, FulfillmentApiProfile::normalizeGeonetBaseUrl($input));
    }

    /**
     * @return array<string, array{0: string, 1: string}>
     */
    public static function normalizeGeonetBaseUrlProvider(): array
    {
        return [
            'api root' => ['https://send.geonettech.com/api', 'https://send.geonettech.com/api'],
            'place path' => ['https://send.geonettech.com/api/v1/place-order', 'https://send.geonettech.com/api'],
        ];
    }

    public function test_dispatch_uses_normalized_geonet_base_when_profile_has_place_path(): void
    {
        $supplier = $this->supplier();
        $this->geonetProfile($supplier, [
            'base_url' => 'https://provider.test/v1/place-order',
        ]);

        $bundle = BundlePackage::query()->create([
            'agent_id' => null,
            'network' => 'MTN',
            'package_kind' => 'data',
            'name' => 'Bundle',
            'size_label' => '1GB',
            'provider_bundle_type' => 'YELLO',
            'internal_cost' => '5.00',
            'stock_count' => 10,
            'is_available' => true,
        ]);

        Http::fake([
            'https://provider.test/v1/place-order' => Http::response($this->geonetPlaceSuccessResponse(), 200),
        ]);

        $buyer = $this->buyerWithWallet('100.00');

        $this->actingAs($buyer)->post(route('buyer.orders.store'), [
            'network' => 'MTN',
            'phone_number' => '0244123456',
            'bundle_package_id' => $bundle->id,
            'confirm' => true,
        ]);

        Http::assertSent(fn ($request) => $request->url() === 'https://provider.test/v1/place-order');
    }

    public function test_admin_cannot_save_iget_console_url_as_api_base(): void
    {
        $supplier = $this->supplier();

        $this->actingAs($supplier)->post(route('admin.fulfillment-apis.store'), [
            'provider_type' => FulfillmentProviderType::IGET,
            'network' => 'Telecel',
            'name' => 'Wrong host',
            'base_url' => 'https://console.igetghana.com',
            'api_key' => 'test-key',
            'default_provider_bundle_type' => 'telecelup2u',
        ])->assertSessionHasErrors('base_url');

        $this->assertSame(0, FulfillmentApiProfile::query()->count());
    }

    public function test_admin_cannot_pair_geonet_with_telecel(): void
    {
        $supplier = $this->supplier();

        $this->actingAs($supplier)->post(route('admin.fulfillment-apis.store'), [
            'provider_type' => FulfillmentProviderType::GEONET,
            'network' => 'Telecel',
            'name' => 'Wrong pairing',
            'base_url' => 'https://send.geonettech.com/api',
            'api_key' => 'token',
        ])->assertSessionHasErrors('network');

        $this->assertSame(0, FulfillmentApiProfile::query()->count());
    }

    public function test_mtn_data_dispatches_to_geonet_and_stores_order_id_reference(): void
    {
        $supplier = $this->supplier();
        $this->geonetProfile($supplier);

        $bundle = BundlePackage::query()->create([
            'agent_id' => null,
            'network' => 'MTN',
            'package_kind' => 'data',
            'name' => 'API Bundle',
            'size_label' => '1GB',
            'provider_bundle_type' => 'YELLO',
            'internal_cost' => '5.00',
            'stock_count' => 10,
            'is_available' => true,
        ]);

        Http::fake([
            'https://provider.test/v1/place-order' => Http::response($this->geonetPlaceSuccessResponse(), 200),
        ]);

        $buyer = $this->buyerWithWallet('100.00');

        $this->actingAs($buyer)->post(route('buyer.orders.store'), [
            'network' => 'MTN',
            'phone_number' => '0244123456',
            'bundle_package_id' => $bundle->id,
            'confirm' => true,
        ])->assertRedirect(route('buyer.orders.index'));

        $order = Order::query()->latest('id')->firstOrFail();

        Http::assertSent(function ($request) use ($order) {
            return $request->url() === 'https://provider.test/v1/place-order'
                && $request->hasHeader('Authorization', 'Bearer geonet-token')
                && $request['recipient'] === '0244123456'
                && $request['network_key'] === 'YELLO'
                && $request['capacity'] === 1
                && $request['ref'] === (string) $order->id;
        });

        $this->assertSame((string) $order->id, $order->provider_order_reference);
        $this->assertNotNull($order->fulfillment_api_profile_id);
    }

    public function test_mtn_data_uses_config_fallback_network_key_when_codes_missing(): void
    {
        $supplier = $this->supplier();
        $this->geonetProfile($supplier, ['default_provider_bundle_type' => null]);

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
            'https://provider.test/v1/place-order' => Http::response($this->geonetPlaceSuccessResponse(), 200),
        ]);

        $buyer = $this->buyerWithWallet('100.00');

        $this->actingAs($buyer)->post(route('buyer.orders.store'), [
            'network' => 'MTN',
            'phone_number' => '0244123456',
            'bundle_package_id' => $bundle->id,
            'confirm' => true,
        ]);

        Http::assertSent(fn ($request) => $request['network_key'] === 'YELLO');
    }

    public function test_mtn_data_uses_profile_default_when_bundle_code_missing(): void
    {
        $supplier = $this->supplier();
        $this->geonetProfile($supplier, ['default_provider_bundle_type' => 'CUSTOM_KEY']);

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
            'https://provider.test/v1/place-order' => Http::response($this->geonetPlaceSuccessResponse(), 200),
        ]);

        $buyer = $this->buyerWithWallet('100.00');

        $this->actingAs($buyer)->post(route('buyer.orders.store'), [
            'network' => 'MTN',
            'phone_number' => '0244123456',
            'bundle_package_id' => $bundle->id,
            'confirm' => true,
        ]);

        Http::assertSent(fn ($request) => $request['network_key'] === 'CUSTOM_KEY');
    }

    public function test_telecel_dispatches_to_iget_without_bundle_code_using_config_fallback(): void
    {
        Config::set('datahome.fulfillment.fallback_codes.iget.TELECEL', 'telecel_fb_code');

        $supplier = $this->supplier();
        $this->igetProfile($supplier, ['default_provider_bundle_type' => null]);

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
        Config::set('datahome.fulfillment.fallback_codes.iget.TELECEL', '');

        $supplier = $this->supplier();
        $this->igetProfile($supplier, ['default_provider_bundle_type' => null]);

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

    public function test_admin_dispatch_to_provider_shows_error_when_telecel_bundle_code_missing(): void
    {
        Config::set('datahome.fulfillment.fallback_codes.iget.TELECEL', '');

        $supplier = $this->supplier();
        $this->igetProfile($supplier, ['default_provider_bundle_type' => null]);

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

        $this->assertStringContainsString('bundle code', strtolower((string) $order->fresh()->provider_dispatch_error));
    }

    public function test_admin_can_refresh_geonet_provider_status(): void
    {
        $supplier = $this->supplier();
        $this->geonetProfile($supplier);

        $bundle = BundlePackage::query()->create([
            'agent_id' => null,
            'network' => 'MTN',
            'package_kind' => 'data',
            'name' => 'API Bundle',
            'size_label' => '1GB',
            'provider_bundle_type' => 'YELLO',
            'internal_cost' => '5.00',
            'stock_count' => 10,
            'is_available' => true,
        ]);

        $buyer = $this->buyerWithWallet('100.00');

        Http::fake([
            'https://provider.test/v1/place-order' => Http::response($this->geonetPlaceSuccessResponse(), 200),
        ]);

        $this->actingAs($buyer)->post(route('buyer.orders.store'), [
            'network' => 'MTN',
            'phone_number' => '0244123456',
            'bundle_package_id' => $bundle->id,
            'confirm' => true,
        ]);

        $order = Order::query()->latest('id')->firstOrFail();
        $ref = (string) $order->provider_order_reference;
        $this->assertNotSame('', $ref);

        Http::fake([
            'https://provider.test/v1/order/'.$ref.'/status' => Http::response([
                'data' => [
                    'status' => 'success',
                    'order' => ['status' => 'completed'],
                ],
            ], 200),
        ]);

        $this->actingAs($supplier)->post(route('admin.orders.refresh-provider-status', $order))
            ->assertRedirect();

        $this->assertSame('completed', (string) $order->fresh()->provider_status);
    }
}
