<?php

namespace Tests\Feature;

use App\Models\BundlePackage;
use App\Models\FulfillmentApiProfile;
use App\Models\Order;
use App\Models\Role;
use App\Models\User;
use App\Support\FulfillmentProviderType;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Tests\TestCase;

class Skanka5WebhookTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);
        Config::set('datahome.fulfillment.providers.skanka5.webhook_secret', 'test-skanka5-webhook-secret');
    }

    public function test_skanka5_webhook_rejects_invalid_signature(): void
    {
        $body = json_encode(['event' => 'orders.processed', 'items' => []], JSON_THROW_ON_ERROR);

        $this->call('POST', route('webhooks.skanka5'), [], [], [], [
            'HTTP_X_SKANKA5_SIGNATURE' => 'invalid',
            'CONTENT_TYPE' => 'application/json',
        ], $body)
            ->assertStatus(401);
    }

    public function test_skanka5_webhook_orders_processed_updates_provider_status_by_order_code(): void
    {
        $supplier = $this->supplier();
        $profile = $this->skanka5Profile($supplier);
        $buyer = $this->buyer();
        $bundle = $this->bundle();

        $order = Order::query()->create([
            'user_id' => $buyer->id,
            'network' => 'MTN',
            'phone_number' => '0244123456',
            'bundle_package_id' => $bundle->id,
            'amount' => '5.00',
            'status' => 'PROCESSING',
            'provider_order_reference' => 'SK5-LINE-001',
            'fulfillment_api_profile_id' => $profile->id,
            'provider_status' => 'pending',
        ]);

        $payload = [
            'event' => 'orders.processed',
            'items' => [
                [
                    'order_code' => 'SK5-LINE-001',
                    'status' => 'processed',
                    'msisdn' => '0244123456',
                ],
            ],
        ];

        $body = json_encode($payload, JSON_THROW_ON_ERROR);

        $this->call('POST', route('webhooks.skanka5'), [], [], [], $this->signedHeaders($body), $body)
            ->assertOk()
            ->assertJson(['received' => true]);

        $order->refresh();

        $this->assertSame('PROCESSING', $order->status);
        $this->assertSame('processed', $order->provider_status);
    }

    private function supplier(): User
    {
        $role = Role::query()->where('slug', Role::SLUG_SUPPLIER)->firstOrFail();

        return User::factory()->create([
            'role_id' => $role->id,
            'status' => 'active',
        ]);
    }

    private function buyer(): User
    {
        $role = Role::query()->where('slug', Role::SLUG_BUYER)->firstOrFail();

        return User::factory()->create([
            'role_id' => $role->id,
            'status' => 'active',
        ]);
    }

    private function skanka5Profile(User $supplier): FulfillmentApiProfile
    {
        return FulfillmentApiProfile::query()->create([
            'supplier_user_id' => $supplier->id,
            'network' => 'MTN',
            'provider_type' => FulfillmentProviderType::SKANKA5,
            'name' => 'Skanka5 MTN',
            'base_url' => 'https://agent.skanka5.com/api/v1',
            'api_key' => 'skanka5-key',
            'default_provider_bundle_type' => '3',
            'is_active' => true,
        ]);
    }

    private function bundle(): BundlePackage
    {
        return BundlePackage::query()->create([
            'agent_id' => null,
            'network' => 'MTN',
            'package_kind' => 'data',
            'name' => 'MTN 2GB',
            'size_label' => '2GB',
            'internal_cost' => '5.00',
            'stock_count' => 10,
            'is_available' => true,
        ]);
    }

    /**
     * @return array<string, string>
     */
    private function signedHeaders(string $body): array
    {
        $secret = (string) config('datahome.fulfillment.providers.skanka5.webhook_secret');

        return [
            'HTTP_X_SKANKA5_SIGNATURE' => hash_hmac('sha256', $body, $secret),
            'CONTENT_TYPE' => 'application/json',
        ];
    }
}
