<?php

namespace Tests\Feature;

use App\Models\BundlePackage;
use App\Models\FulfillmentApiProfile;
use App\Models\Order;
use App\Models\Role;
use App\Models\User;
use App\Models\Wallet;
use App\Services\Fulfillment\EncartaWebhookService;
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
            'default_provider_bundle_type' => 'Telecel-5959',
            'is_active' => true,
        ], $overrides));
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function encartaProfile(User $supplier, array $overrides = []): FulfillmentApiProfile
    {
        return FulfillmentApiProfile::query()->create(array_merge([
            'supplier_user_id' => $supplier->id,
            'network' => 'MTN',
            'provider_type' => FulfillmentProviderType::ENCARTA,
            'name' => 'Encarta MTN',
            'base_url' => 'https://provider.test/api',
            'api_key' => 'encarta-key',
            'is_active' => true,
        ], $overrides));
    }

    /**
     * @return array<string, mixed>
     */
    private function encartaBundlesResponse(int $bundleId = 42, int $capacityGb = 2): array
    {
        return [
            'status' => 'success',
            'data' => [
                ['id' => $bundleId, 'capacity_gb' => $capacityGb, 'network_code' => 'mtn'],
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function encartaPlaceSuccessResponse(): array
    {
        return [
            'success' => true,
            'message' => 'Order accepted',
            'data' => [
                'reference' => 'ENC-123',
                'status' => 'pending',
            ],
        ];
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

    public function test_promoted_dealer_role_with_agent_persona_dispatches_to_provider(): void
    {
        $supplier = $this->supplier();
        $this->encartaProfile($supplier);

        $dealerRole = Role::query()->create([
            'name' => 'Dealer',
            'slug' => 'dealer',
            'is_enabled' => true,
            'available_at_registration' => false,
            'pricing_persona' => Role::PERSONA_AGENT,
            'requires_promotion_fee' => false,
        ]);

        $dealer = User::factory()->create([
            'role_id' => $dealerRole->id,
            'status' => 'active',
            'shop_slug' => 'dealer-shop',
        ]);
        Wallet::query()->create([
            'user_id' => $dealer->id,
            'balance' => '100.00',
            'is_frozen' => false,
        ]);

        $bundle = BundlePackage::query()->create([
            'agent_id' => null,
            'network' => 'MTN',
            'package_kind' => 'data',
            'name' => 'MTN 2GB',
            'size_label' => '2GB',
            'internal_cost' => '8.00',
            'stock_count' => 10,
            'is_available' => true,
        ]);

        Http::fake([
            'https://provider.test/api/bundles*' => Http::response($this->encartaBundlesResponse(), 200),
            'https://provider.test/api/purchase' => Http::response($this->encartaPlaceSuccessResponse(), 200),
        ]);

        Config::set('datahome.fulfillment.providers.encarta.place_path', '/purchase');

        $this->actingAs($dealer)->post(route('agent.orders.store'), [
            'confirm' => true,
            'items' => [
                [
                    'network' => 'MTN',
                    'phone_number' => '0534620772',
                    'bundle_package_id' => $bundle->id,
                ],
            ],
        ])->assertRedirect();

        $order = Order::query()->where('user_id', $dealer->id)->firstOrFail();
        $this->assertSame('ENC-123', $order->provider_order_reference);
        $this->assertNull($order->provider_dispatch_error);
    }

    public function test_agent_linked_buyer_order_is_sent_to_external_api(): void
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

        Http::fake([
            'https://provider.test/v1/place-order' => Http::response($this->geonetPlaceSuccessResponse(), 200),
        ]);

        $this->actingAs($buyer)->post(route('buyer.orders.store'), [
            'network' => 'MTN',
            'phone_number' => '0244123456',
            'bundle_package_id' => $bundle->id,
            'confirm' => true,
        ])->assertRedirect(route('buyer.orders.index'));

        Http::assertSent(fn ($request) => $request->url() === 'https://provider.test/v1/place-order');

        $order = Order::query()->where('user_id', $buyer->id)->firstOrFail();
        $this->assertSame((int) $agent->id, (int) $order->agent_id);
        $this->assertSame((string) $order->id, $order->provider_order_reference);
        $this->assertSame('PENDING', $order->status);
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

    public function test_admin_fulfillment_apis_index_renders(): void
    {
        $supplier = $this->supplier();

        $this->actingAs($supplier)
            ->get(route('admin.fulfillment-apis.index'))
            ->assertOk()
            ->assertSee(__('External data APIs'), false);
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
            'default_provider_bundle_type' => 'Telecel-5959',
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

    public function test_admin_cannot_pair_encarta_with_telecel(): void
    {
        $supplier = $this->supplier();

        $this->actingAs($supplier)->post(route('admin.fulfillment-apis.store'), [
            'provider_type' => FulfillmentProviderType::ENCARTA,
            'network' => 'Telecel',
            'name' => 'Wrong pairing',
            'base_url' => 'https://encartastores.com/api',
            'api_key' => 'encarta-key',
        ])->assertSessionHasErrors('network');

        $this->assertSame(0, FulfillmentApiProfile::query()->count());
    }

    #[DataProvider('normalizeEncartaBaseUrlProvider')]
    public function test_normalize_encarta_base_url_strips_endpoint_suffixes(string $input, string $expected): void
    {
        $this->assertSame($expected, FulfillmentApiProfile::normalizeEncartaBaseUrl($input));
    }

    /**
     * @return array<string, array{0: string, 1: string}>
     */
    public static function normalizeEncartaBaseUrlProvider(): array
    {
        return [
            'api root' => ['https://encartastores.com/api', 'https://encartastores.com/api'],
            'ishare path' => ['https://encartastores.com/api/ishare', 'https://encartastores.com/api'],
            'purchase path' => ['https://encartastores.com/api/purchase', 'https://encartastores.com/api'],
            'bundles path' => ['https://encartastores.com/api/bundles', 'https://encartastores.com/api'],
        ];
    }

    public function test_mtn_data_dispatches_to_encarta_purchase_with_bundle_id(): void
    {
        $supplier = $this->supplier();
        $this->encartaProfile($supplier);

        $bundle = BundlePackage::query()->create([
            'agent_id' => null,
            'network' => 'MTN',
            'package_kind' => 'data',
            'name' => 'MTN 2GB',
            'size_label' => '2GB',
            'internal_cost' => '5.00',
            'stock_count' => 10,
            'is_available' => true,
        ]);

        Http::fake([
            'https://provider.test/api/bundles*' => Http::response($this->encartaBundlesResponse(), 200),
            'https://provider.test/api/purchase' => Http::response($this->encartaPlaceSuccessResponse(), 200),
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
            return $request->url() === 'https://provider.test/api/purchase'
                && $request->hasHeader('X-API-Key', 'encarta-key')
                && $request['recipient'] === '0244123456'
                && $request['bundle_id'] === 42
                && $request['idempotency_key'] === 'dhgh_'.$order->id
                && $request['webhook_url'] === EncartaWebhookService::webhookUrl();
        });

        $this->assertSame('ENC-123', $order->provider_order_reference);
    }

    public function test_encarta_uses_provider_bundle_type_when_set(): void
    {
        $supplier = $this->supplier();
        $this->encartaProfile($supplier);

        $bundle = BundlePackage::query()->create([
            'agent_id' => null,
            'network' => 'MTN',
            'package_kind' => 'data',
            'name' => 'MTN 2GB',
            'size_label' => '2GB',
            'provider_bundle_type' => '99',
            'internal_cost' => '5.00',
            'stock_count' => 10,
            'is_available' => true,
        ]);

        Http::fake([
            'https://provider.test/api/purchase' => Http::response($this->encartaPlaceSuccessResponse(), 200),
        ]);

        $buyer = $this->buyerWithWallet('100.00');

        $this->actingAs($buyer)->post(route('buyer.orders.store'), [
            'network' => 'MTN',
            'phone_number' => '0244123456',
            'bundle_package_id' => $bundle->id,
            'confirm' => true,
        ])->assertRedirect(route('buyer.orders.index'));

        Http::assertSent(function ($request) {
            return $request->url() === 'https://provider.test/api/purchase'
                && $request['bundle_id'] === 99;
        });

        Http::assertNotSent(fn ($request) => str_contains($request->url(), '/bundles'));
    }

    public function test_admin_refresh_encarta_provider_status_is_webhook_only(): void
    {
        $supplier = $this->supplier();
        $this->encartaProfile($supplier);

        $bundle = BundlePackage::query()->create([
            'agent_id' => null,
            'network' => 'MTN',
            'package_kind' => 'data',
            'name' => 'MTN 1GB',
            'size_label' => '1GB',
            'internal_cost' => '5.00',
            'stock_count' => 10,
            'is_available' => true,
        ]);

        Http::fake([
            'https://provider.test/api/bundles*' => Http::response($this->encartaBundlesResponse(42, 1), 200),
            'https://provider.test/api/purchase' => Http::response($this->encartaPlaceSuccessResponse(), 200),
        ]);

        $buyer = $this->buyerWithWallet('100.00');

        $this->actingAs($buyer)->post(route('buyer.orders.store'), [
            'network' => 'MTN',
            'phone_number' => '0244123456',
            'bundle_package_id' => $bundle->id,
            'confirm' => true,
        ]);

        $order = Order::query()->latest('id')->firstOrFail();
        $this->assertSame('ENC-123', $order->provider_order_reference);

        $this->actingAs($supplier)->post(route('admin.orders.refresh-provider-status', $order))
            ->assertRedirect()
            ->assertSessionHas('error');

        $this->assertSame('pending', $order->fresh()->provider_status);
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

    public function test_mtn_data_sends_yello_even_when_bundle_stored_mtn_label(): void
    {
        $supplier = $this->supplier();
        $this->geonetProfile($supplier, ['default_provider_bundle_type' => 'MTN']);

        $bundle = BundlePackage::query()->create([
            'agent_id' => null,
            'network' => 'MTN',
            'package_kind' => 'data',
            'name' => 'MTN 1GB',
            'size_label' => '1GB',
            'provider_bundle_type' => 'MTN',
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

    public function test_telecel_dispatches_to_iget_with_config_bundle_type(): void
    {
        $supplier = $this->supplier();
        $this->igetProfile($supplier);

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

        Http::assertSent(fn ($request) => $request['bundleType'] === 'Telecel-5959');
        $this->assertSame('TEL-REF', Order::query()->latest('id')->value('provider_order_reference'));
    }

    public function test_telecel_sends_telecel_5959_even_when_bundle_stored_old_code(): void
    {
        Config::set('datahome.fulfillment.fallback_codes.iget.TELECEL', 'Telecel-5959');

        $supplier = $this->supplier();
        $this->igetProfile($supplier);

        $bundle = BundlePackage::query()->create([
            'agent_id' => null,
            'network' => 'Telecel',
            'package_kind' => 'data',
            'name' => 'Telecel 2GB',
            'size_label' => '2GB',
            'provider_bundle_type' => 'telecelup2u',
            'internal_cost' => '5.00',
            'stock_count' => 10,
            'is_available' => true,
        ]);

        Http::fake([
            'https://provider.test/api/developer/orders/place' => Http::response([
                'success' => true,
                'data' => ['order' => ['orderReference' => 'TEL-2', 'status' => 'pending']],
            ], 200),
        ]);

        $buyer = $this->buyerWithWallet('100.00');

        $this->actingAs($buyer)->post(route('buyer.orders.store'), [
            'network' => 'Telecel',
            'phone_number' => '0200000000',
            'bundle_package_id' => $bundle->id,
            'confirm' => true,
        ]);

        Http::assertSent(fn ($request) => $request['bundleType'] === 'Telecel-5959' && $request['capacity'] === 2);
    }

    public function test_telecel_stays_without_dispatch_when_bundle_type_not_configured(): void
    {
        Config::set('datahome.fulfillment.fallback_codes.iget.TELECEL', '');

        $supplier = $this->supplier();
        $this->igetProfile($supplier);

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

    public function test_admin_dispatch_fails_when_telecel_bundle_type_not_configured(): void
    {
        Config::set('datahome.fulfillment.fallback_codes.iget.TELECEL', '');

        $supplier = $this->supplier();
        $this->igetProfile($supplier);

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

        $this->assertStringContainsString('bundletype', strtolower((string) $order->fresh()->provider_dispatch_error));
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
