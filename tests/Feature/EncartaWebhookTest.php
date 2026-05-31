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
use Tests\TestCase;

class EncartaWebhookTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);
    }

    public function test_encarta_webhook_rejects_invalid_signature(): void
    {
        $body = json_encode(['event' => 'order.delivered', 'data' => []], JSON_THROW_ON_ERROR);

        $this->call('POST', route('webhooks.encarta'), [], [], [], [
            'HTTP_X_WEBHOOK_SIGNATURE' => 'sha256=invalid',
            'CONTENT_TYPE' => 'application/json',
        ], $body)
            ->assertStatus(401);
    }

    public function test_encarta_webhook_order_delivered_updates_provider_status_only(): void
    {
        $supplier = $this->supplier();
        $profile = $this->encartaProfile($supplier);
        $buyer = $this->buyer();
        $bundle = $this->bundle();

        $order = Order::query()->create([
            'user_id' => $buyer->id,
            'network' => 'MTN',
            'phone_number' => '0244123456',
            'bundle_package_id' => $bundle->id,
            'amount' => '5.00',
            'status' => 'PROCESSING',
            'provider_order_reference' => 'ENC-123',
            'fulfillment_api_profile_id' => $profile->id,
            'provider_status' => 'processing',
        ]);

        $payload = [
            'event' => 'order.delivered',
            'timestamp' => now()->toIso8601String(),
            'data' => [
                'reference' => 'ENC-123',
                'status' => 'delivered',
                'recipient' => '0244123456',
                'volume_mb' => 2048,
                'amount' => 5.00,
                'provider_reference' => 'PROV_XYZ',
            ],
        ];

        $body = json_encode($payload, JSON_THROW_ON_ERROR);

        $this->call('POST', route('webhooks.encarta'), [], [], [], $this->signedHeaders($body), $body)
            ->assertOk()
            ->assertSee('ok');

        $order->refresh();

        $this->assertSame('PROCESSING', $order->status);
        $this->assertSame('delivered', $order->provider_status);
        $this->assertSame('PROV_XYZ', $order->provider_order_reference);
    }

    public function test_encarta_webhook_order_failed_updates_provider_status_only(): void
    {
        $supplier = $this->supplier();
        $profile = $this->encartaProfile($supplier);
        $buyer = $this->buyer();
        $bundle = $this->bundle();

        $order = Order::query()->create([
            'user_id' => $buyer->id,
            'network' => 'MTN',
            'phone_number' => '0244123456',
            'bundle_package_id' => $bundle->id,
            'amount' => '5.00',
            'status' => 'PROCESSING',
            'provider_order_reference' => (string) 42,
            'fulfillment_api_profile_id' => $profile->id,
        ]);

        $payload = [
            'event' => 'order.failed',
            'data' => [
                'reference' => '42',
                'status' => 'failed',
                'recipient' => '0244123456',
            ],
        ];

        $body = json_encode($payload, JSON_THROW_ON_ERROR);

        $this->call('POST', route('webhooks.encarta'), [], [], [], $this->signedHeaders($body), $body)
            ->assertOk();

        $this->assertSame('PROCESSING', $order->fresh()->status);
        $this->assertSame('failed', $order->fresh()->provider_status);
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

    private function encartaProfile(User $supplier): FulfillmentApiProfile
    {
        return FulfillmentApiProfile::query()->create([
            'supplier_user_id' => $supplier->id,
            'network' => 'MTN',
            'provider_type' => FulfillmentProviderType::ENCARTA,
            'name' => 'Encarta MTN',
            'base_url' => 'https://encartastores.com/api',
            'api_key' => 'encarta-key',
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
        $secret = (string) config('datahome.fulfillment.providers.encarta.webhook_secret', 'direct');

        return [
            'HTTP_X_WEBHOOK_SIGNATURE' => 'sha256='.hash_hmac('sha256', $body, $secret),
            'CONTENT_TYPE' => 'application/json',
        ];
    }
}
