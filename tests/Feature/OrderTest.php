<?php

namespace Tests\Feature;

use App\Models\BundlePackage;
use App\Models\Order;
use App\Models\Role;
use App\Models\User;
use App\Models\Wallet;
use App\Services\OrderService;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use InvalidArgumentException;
use Tests\TestCase;

class OrderTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);
    }

    private function createPlatformBundle(): BundlePackage
    {
        return BundlePackage::query()->create([
            'agent_id' => null,
            'network' => 'MTN',
            'name' => 'Test Bundle',
            'size_label' => '1GB',
            'internal_cost' => '5.00',
            'stock_count' => 10,
            'is_available' => true,
        ]);
    }

    private function activeBuyerWithWallet(string $balance = '100.00'): User
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

    private function supplierUser(): User
    {
        $role = Role::query()->where('slug', Role::SLUG_SUPPLIER)->firstOrFail();

        return User::factory()->create([
            'role_id' => $role->id,
            'status' => 'active',
        ]);
    }

    public function test_wallet_debited_on_order_placement(): void
    {
        $bundle = $this->createPlatformBundle();
        $buyer = $this->activeBuyerWithWallet('100.00');

        $this->actingAs($buyer)->post(route('buyer.orders.store'), [
            'network' => 'MTN',
            'phone_number' => '0244123456',
            'bundle_package_id' => $bundle->id,
            'confirm' => true,
        ])->assertRedirect(route('buyer.orders.index'));

        $buyer->wallet->refresh();
        $this->assertSame('95.00', (string) $buyer->wallet->balance);
    }

    public function test_duplicate_order_blocked(): void
    {
        $bundle = $this->createPlatformBundle();
        $buyer = $this->activeBuyerWithWallet('100.00');

        $payload = [
            'network' => 'MTN',
            'phone_number' => '0244123456',
            'bundle_package_id' => $bundle->id,
            'confirm' => true,
        ];

        $this->actingAs($buyer)->post(route('buyer.orders.store'), $payload)->assertRedirect();
        $this->actingAs($buyer)->post(route('buyer.orders.store'), $payload)
            ->assertSessionHasErrors('order');
    }

    public function test_order_blocked_when_wallet_insufficient(): void
    {
        $bundle = $this->createPlatformBundle();
        $buyer = $this->activeBuyerWithWallet('1.00');

        $this->actingAs($buyer)->post(route('buyer.orders.store'), [
            'network' => 'MTN',
            'phone_number' => '0244123456',
            'bundle_package_id' => $bundle->id,
            'confirm' => true,
        ])->assertSessionHasErrors('amount');
    }

    public function test_buyer_cannot_update_order_status(): void
    {
        $bundle = $this->createPlatformBundle();
        $buyer = $this->activeBuyerWithWallet('100.00');

        $this->actingAs($buyer)->post(route('buyer.orders.store'), [
            'network' => 'MTN',
            'phone_number' => '0244123456',
            'bundle_package_id' => $bundle->id,
            'confirm' => true,
        ])->assertRedirect();

        $order = Order::query()->where('user_id', $buyer->id)->firstOrFail();

        $this->actingAs($buyer)->patchJson(route('orders.update-status', $order), [
            'status' => 'PROCESSING',
        ])->assertForbidden();
    }

    public function test_agent_can_update_own_order_status(): void
    {
        $agentRole = Role::query()->where('slug', Role::SLUG_AGENT)->firstOrFail();
        $buyerRole = Role::query()->where('slug', Role::SLUG_BUYER)->firstOrFail();

        $agent = User::factory()->create([
            'role_id' => $agentRole->id,
            'status' => 'active',
            'shop_slug' => 'my-agent',
        ]);

        $buyer = User::factory()->forAgent($agent)->create([
            'role_id' => $buyerRole->id,
            'status' => 'active',
        ]);

        Wallet::query()->create([
            'user_id' => $buyer->id,
            'balance' => '100.00',
            'is_frozen' => false,
        ]);

        $bundle = BundlePackage::query()->create([
            'agent_id' => null,
            'network' => 'MTN',
            'name' => 'Agent Buyer Bundle',
            'size_label' => '1GB',
            'internal_cost' => '4.00',
            'stock_count' => 10,
            'is_available' => true,
        ]);

        $this->actingAs($buyer)->post(route('buyer.orders.store'), [
            'network' => 'MTN',
            'phone_number' => '0244123456',
            'bundle_package_id' => $bundle->id,
            'confirm' => true,
        ])->assertRedirect();

        $order = Order::query()->where('user_id', $buyer->id)->firstOrFail();

        $this->actingAs($agent)->patch(route('agent.orders.status', $order), [
            'status' => 'PROCESSING',
        ])->assertRedirect();

        $this->assertSame('PROCESSING', $order->fresh()->status);
    }

    public function test_refund_credits_wallet_and_marks_refunded(): void
    {
        $bundle = $this->createPlatformBundle();
        $buyer = $this->activeBuyerWithWallet('100.00');
        $supplier = $this->supplierUser();

        $this->actingAs($buyer)->post(route('buyer.orders.store'), [
            'network' => 'MTN',
            'phone_number' => '0244123456',
            'bundle_package_id' => $bundle->id,
            'confirm' => true,
        ])->assertRedirect();

        $order = Order::query()->where('user_id', $buyer->id)->firstOrFail();
        $this->assertSame('95.00', (string) $buyer->wallet->fresh()->balance);

        $this->actingAs($supplier)->patch(route('admin.orders.status', $order), [
            'status' => 'REFUNDED',
        ])->assertRedirect();

        $order->refresh();
        $this->assertSame('REFUNDED', $order->status);
        $this->assertSame('100.00', (string) $buyer->wallet->fresh()->balance);
    }

    public function test_sent_order_cannot_be_changed(): void
    {
        $bundle = $this->createPlatformBundle();
        $buyer = $this->activeBuyerWithWallet('100.00');
        $supplier = $this->supplierUser();

        $this->actingAs($buyer)->post(route('buyer.orders.store'), [
            'network' => 'MTN',
            'phone_number' => '0244123456',
            'bundle_package_id' => $bundle->id,
            'confirm' => true,
        ])->assertRedirect();

        $order = Order::query()->where('user_id', $buyer->id)->firstOrFail();

        $this->actingAs($supplier)->patch(route('admin.orders.status', $order), [
            'status' => 'PROCESSING',
        ])->assertRedirect();

        $this->actingAs($supplier)->patch(route('admin.orders.status', $order->fresh()), [
            'status' => 'SENT',
        ])->assertRedirect();

        $order->refresh();
        $this->assertSame('SENT', $order->status);

        $this->expectException(InvalidArgumentException::class);
        app(OrderService::class)->updateStatus(
            $order->id,
            'REFUNDED',
            $supplier->id,
            null,
            true,
        );
    }
}
