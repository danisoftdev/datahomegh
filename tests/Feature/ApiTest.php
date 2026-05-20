<?php

namespace Tests\Feature;

use App\Models\BundlePackage;
use App\Models\Order;
use App\Models\Role;
use App\Models\User;
use App\Models\Wallet;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);
    }

    public function test_api_login_returns_token(): void
    {
        $buyerRole = Role::query()->where('slug', Role::SLUG_BUYER)->firstOrFail();
        $buyer = User::factory()->create([
            'role_id' => $buyerRole->id,
            'username' => 'apibuyer',
            'password' => 'ApiPass123!',
            'status' => 'active',
        ]);

        Wallet::query()->create([
            'user_id' => $buyer->id,
            'balance' => '0.00',
            'is_frozen' => false,
        ]);

        $response = $this->postJson('/api/v1/auth/login', [
            'username' => 'apibuyer',
            'password' => 'ApiPass123!',
        ]);

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonStructure(['data' => ['token', 'token_type', 'user']]);

        $this->assertNotEmpty($response->json('data.token'));
    }

    public function test_api_protected_route_requires_token(): void
    {
        $this->getJson('/api/v1/wallet')->assertUnauthorized();
    }

    public function test_api_order_placement_debits_wallet(): void
    {
        $buyerRole = Role::query()->where('slug', Role::SLUG_BUYER)->firstOrFail();
        $buyer = User::factory()->create([
            'role_id' => $buyerRole->id,
            'username' => 'apibuyer2',
            'password' => 'ApiPass123!',
            'status' => 'active',
        ]);

        Wallet::query()->create([
            'user_id' => $buyer->id,
            'balance' => '50.00',
            'is_frozen' => false,
        ]);

        $bundle = BundlePackage::query()->create([
            'agent_id' => null,
            'network' => 'MTN',
            'package_kind' => 'data',
            'name' => 'API bundle',
            'size_label' => '1GB',
            'internal_cost' => '5.00',
            'stock_count' => 10,
            'is_available' => true,
        ]);

        $login = $this->postJson('/api/v1/auth/login', [
            'username' => 'apibuyer2',
            'password' => 'ApiPass123!',
        ])->assertOk();

        $token = $login->json('data.token');

        $this->postJson('/api/v1/orders', [
            'network' => 'MTN',
            'phone_number' => '0244123456',
            'bundle_package_id' => $bundle->id,
            'confirm' => true,
        ], [
            'Authorization' => 'Bearer '.$token,
        ])->assertCreated();

        $this->assertSame('45.00', (string) $buyer->wallet->fresh()->balance);
    }

    public function test_api_buyer_can_cancel_pending_order(): void
    {
        $buyerRole = Role::query()->where('slug', Role::SLUG_BUYER)->firstOrFail();
        $buyer = User::factory()->create([
            'role_id' => $buyerRole->id,
            'username' => 'apibuyer3',
            'password' => 'ApiPass123!',
            'status' => 'active',
        ]);

        Wallet::query()->create([
            'user_id' => $buyer->id,
            'balance' => '50.00',
            'is_frozen' => false,
        ]);

        $bundle = BundlePackage::query()->create([
            'agent_id' => null,
            'network' => 'MTN',
            'package_kind' => 'data',
            'name' => 'API bundle c',
            'size_label' => '1GB',
            'internal_cost' => '5.00',
            'stock_count' => 10,
            'is_available' => true,
        ]);

        $login = $this->postJson('/api/v1/auth/login', [
            'username' => 'apibuyer3',
            'password' => 'ApiPass123!',
        ])->assertOk();

        $token = $login->json('data.token');

        $place = $this->postJson('/api/v1/orders', [
            'network' => 'MTN',
            'phone_number' => '0244123456',
            'bundle_package_id' => $bundle->id,
            'confirm' => true,
        ], [
            'Authorization' => 'Bearer '.$token,
        ])->assertCreated();

        $orderId = $place->json('data.id');

        $this->postJson('/api/v1/orders/'.$orderId.'/cancel', [], [
            'Authorization' => 'Bearer '.$token,
        ])->assertOk()
            ->assertJsonPath('success', true);

        $this->assertSame('REFUNDED', Order::query()->findOrFail($orderId)->status);
        $this->assertSame('50.00', (string) $buyer->wallet->fresh()->balance);
    }

    public function test_api_invalid_token_returns_401(): void
    {
        $this->getJson('/api/v1/wallet', [
            'Authorization' => 'Bearer invalid-token-value',
        ])->assertUnauthorized();
    }
}
