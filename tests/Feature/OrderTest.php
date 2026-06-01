<?php

namespace Tests\Feature;

use App\Models\BundlePackage;
use App\Models\Order;
use App\Models\Role;
use App\Models\RolePrice;
use App\Models\User;
use App\Models\Wallet;
use App\Services\OrderService;
use App\Support\BundleCatalog;
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
            'package_kind' => 'data',
            'name' => 'Test Bundle',
            'size_label' => '1GB',
            'internal_cost' => '5.00',
            'stock_count' => 10,
            'is_available' => true,
        ]);
    }

    private function createAgentBundle(User $agent): BundlePackage
    {
        return BundlePackage::query()->create([
            'agent_id' => $agent->id,
            'network' => 'MTN',
            'package_kind' => 'data',
            'name' => 'Agent catalogue bundle',
            'size_label' => '2GB',
            'internal_cost' => '8.00',
            'stock_count' => 5,
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
            'agent_id' => $agent->id,
            'network' => 'MTN',
            'package_kind' => 'data',
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

    public function test_buyer_can_cancel_pending_order_refunds_wallet_restocks(): void
    {
        $bundle = $this->createPlatformBundle();
        $beforeStock = $bundle->stock_count;
        $buyer = $this->activeBuyerWithWallet('100.00');

        $this->actingAs($buyer)->post(route('buyer.orders.store'), [
            'network' => 'MTN',
            'phone_number' => '0244123456',
            'bundle_package_id' => $bundle->id,
            'confirm' => true,
        ])->assertRedirect();

        $order = Order::query()->where('user_id', $buyer->id)->firstOrFail();
        $this->assertSame('95.00', (string) $buyer->wallet->fresh()->balance);

        $this->actingAs($buyer)->post(route('buyer.orders.cancel', $order))
            ->assertRedirect(route('buyer.orders.show', $order));

        $order->refresh();
        $bundle->refresh();
        $this->assertSame('REFUNDED', $order->status);
        $this->assertSame('100.00', (string) $buyer->wallet->fresh()->balance);
        $this->assertSame($beforeStock, $bundle->stock_count);
    }

    public function test_buyer_cannot_cancel_after_processing(): void
    {
        $bundle = $this->createPlatformBundle();
        $buyer = $this->activeBuyerWithWallet('100.00');
        $supplier = $this->supplierUser();

        $this->actingAs($buyer)->post(route('buyer.orders.store'), [
            'network' => 'MTN',
            'phone_number' => '0244123456',
            'bundle_package_id' => $bundle->id,
            'confirm' => true,
        ]);

        $order = Order::query()->where('user_id', $buyer->id)->firstOrFail();

        $this->actingAs($supplier)->patch(route('admin.orders.status', $order), [
            'status' => 'PROCESSING',
        ])->assertRedirect();

        $this->actingAs($buyer)->post(route('buyer.orders.cancel', $order))
            ->assertRedirect()
            ->assertSessionHasErrors('cancel');
    }

    public function test_agent_can_cancel_own_pending_checkout_refunds_wallet(): void
    {
        $agentRole = Role::query()->where('slug', Role::SLUG_AGENT)->firstOrFail();
        $agent = User::factory()->create([
            'role_id' => $agentRole->id,
            'status' => 'active',
            'shop_slug' => 'agent-cancel',
        ]);

        Wallet::query()->create([
            'user_id' => $agent->id,
            'balance' => '100.00',
            'is_frozen' => false,
        ]);

        $platform = BundlePackage::query()->create([
            'agent_id' => null,
            'network' => 'MTN',
            'package_kind' => 'data',
            'name' => 'Platform bundle',
            'size_label' => '1GB',
            'internal_cost' => '5.00',
            'stock_count' => 10,
            'is_available' => true,
        ]);

        $this->actingAs($agent)->post(route('agent.orders.store'), [
            'confirm' => true,
            'items' => [
                [
                    'network' => 'MTN',
                    'phone_number' => '0244123456',
                    'bundle_package_id' => $platform->id,
                ],
            ],
        ])->assertRedirect();

        $order = Order::query()->where('user_id', $agent->id)->firstOrFail();

        $this->actingAs($agent)->post(route('agent.orders.cancel', $order))
            ->assertRedirect();

        $this->assertSame('REFUNDED', $order->fresh()->status);
        $this->assertSame('100.00', (string) $agent->wallet->fresh()->balance);
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
            'PROCESSING',
            $supplier->id,
            null,
            true,
        );
    }

    private function createMtnAfaBundle(): BundlePackage
    {
        return BundlePackage::query()->create([
            'agent_id' => null,
            'network' => 'MTN',
            'package_kind' => 'mtn_afa',
            'name' => 'MTN AFA Bundle',
            'size_label' => 'Registration',
            'internal_cost' => '12.00',
            'stock_count' => 5,
            'is_available' => true,
        ]);
    }

    public function test_mtn_afa_order_stores_registration(): void
    {
        $bundle = $this->createMtnAfaBundle();
        $buyer = $this->activeBuyerWithWallet('100.00');

        $this->actingAs($buyer)->post(route('buyer.orders.store'), [
            'network' => 'MTN_AFA',
            'phone_number' => '0244123456',
            'bundle_package_id' => $bundle->id,
            'confirm' => true,
            'afa_registration' => [
                'name' => 'Kwame Test',
                'phone' => '0244123456',
                'ghana_card_number' => 'GHA-123456789-0',
                'date_of_birth' => '1995-06-15',
                'occupation' => 'Trader',
                'location' => 'Accra',
            ],
        ])->assertRedirect(route('buyer.orders.index'));

        $order = Order::query()->where('user_id', $buyer->id)->firstOrFail();
        $this->assertSame('MTN', $order->network);
        $this->assertIsArray($order->afa_registration);
        $this->assertSame('Kwame Test', $order->afa_registration['name']);
        $this->assertSame('0244123456', $order->afa_registration['phone']);
    }

    public function test_mtn_afa_order_requires_registration_payload(): void
    {
        $bundle = $this->createMtnAfaBundle();
        $buyer = $this->activeBuyerWithWallet('100.00');

        $this->actingAs($buyer)->post(route('buyer.orders.store'), [
            'network' => 'MTN_AFA',
            'phone_number' => '0244123456',
            'bundle_package_id' => $bundle->id,
            'confirm' => true,
        ])->assertSessionHasErrors();
    }

    public function test_mtn_afa_charges_bundle_list_price_not_role_price(): void
    {
        $bundle = $this->createMtnAfaBundle();
        $buyer = $this->activeBuyerWithWallet('100.00');

        RolePrice::query()->create([
            'role_id' => $buyer->role_id,
            'bundle_package_id' => $bundle->id,
            'price' => '99.99',
        ]);

        $this->actingAs($buyer)->post(route('buyer.orders.store'), [
            'network' => 'MTN_AFA',
            'phone_number' => '0244123456',
            'bundle_package_id' => $bundle->id,
            'confirm' => true,
            'afa_registration' => [
                'name' => 'Kwame Test',
                'phone' => '0244123456',
                'ghana_card_number' => 'GHA-123456789-0',
                'date_of_birth' => '1995-06-15',
                'occupation' => 'Trader',
                'location' => 'Accra',
            ],
        ])->assertRedirect(route('buyer.orders.index'));

        $order = Order::query()->where('user_id', $buyer->id)->firstOrFail();
        $this->assertSame('12.00', (string) $order->amount);
        $this->assertSame('88.00', (string) $buyer->wallet->fresh()->balance);
    }

    public function test_batch_checkout_creates_multiple_orders_one_wallet_session(): void
    {
        $mtn = $this->createPlatformBundle();
        $telecel = BundlePackage::query()->create([
            'agent_id' => null,
            'network' => 'Telecel',
            'package_kind' => 'data',
            'name' => 'Telecel Bundle',
            'size_label' => '2GB',
            'internal_cost' => '6.00',
            'stock_count' => 10,
            'is_available' => true,
        ]);
        $buyer = $this->activeBuyerWithWallet('100.00');

        $this->actingAs($buyer)->post(route('buyer.orders.store'), [
            'confirm' => true,
            'items' => [
                [
                    'network' => 'MTN',
                    'phone_number' => '0244123456',
                    'bundle_package_id' => $mtn->id,
                ],
                [
                    'network' => 'Telecel',
                    'phone_number' => '0531234567',
                    'bundle_package_id' => $telecel->id,
                ],
            ],
        ])->assertRedirect(route('buyer.orders.index'));

        $this->assertSame(2, Order::query()->where('user_id', $buyer->id)->count());
        $buyer->wallet->refresh();
        $this->assertSame('89.00', (string) $buyer->wallet->balance);
    }

    public function test_batch_checkout_blocked_when_total_exceeds_balance(): void
    {
        $b1 = $this->createPlatformBundle();
        $b2 = BundlePackage::query()->create([
            'agent_id' => null,
            'network' => 'MTN',
            'package_kind' => 'data',
            'name' => 'Second MTN',
            'size_label' => '2GB',
            'internal_cost' => '8.00',
            'stock_count' => 10,
            'is_available' => true,
        ]);
        $buyer = $this->activeBuyerWithWallet('10.00');

        $this->actingAs($buyer)->post(route('buyer.orders.store'), [
            'confirm' => true,
            'items' => [
                [
                    'network' => 'MTN',
                    'phone_number' => '0244123456',
                    'bundle_package_id' => $b1->id,
                ],
                [
                    'network' => 'MTN',
                    'phone_number' => '0551234567',
                    'bundle_package_id' => $b2->id,
                ],
            ],
        ])->assertSessionHasErrors('amount');

        $this->assertSame(0, Order::query()->where('user_id', $buyer->id)->count());
        $this->assertSame('10.00', (string) $buyer->wallet->fresh()->balance);
    }

    public function test_batch_duplicate_lines_same_checkout_rejected(): void
    {
        $bundle = $this->createPlatformBundle();
        $buyer = $this->activeBuyerWithWallet('100.00');

        $this->actingAs($buyer)->post(route('buyer.orders.store'), [
            'confirm' => true,
            'items' => [
                [
                    'network' => 'MTN',
                    'phone_number' => '0244123456',
                    'bundle_package_id' => $bundle->id,
                ],
                [
                    'network' => 'MTN',
                    'phone_number' => '0244123456',
                    'bundle_package_id' => $bundle->id,
                ],
            ],
        ])->assertSessionHasErrors('order');

        $this->assertSame(0, Order::query()->where('user_id', $buyer->id)->count());
    }

    public function test_batch_respects_daily_order_limit_line_count(): void
    {
        $bundle = $this->createPlatformBundle();
        $b2 = BundlePackage::query()->create([
            'agent_id' => null,
            'network' => 'MTN',
            'package_kind' => 'data',
            'name' => 'Extra',
            'size_label' => '512MB',
            'internal_cost' => '2.00',
            'stock_count' => 10,
            'is_available' => true,
        ]);
        $buyerRole = Role::query()->where('slug', Role::SLUG_BUYER)->firstOrFail();
        $buyer = User::factory()->create([
            'role_id' => $buyerRole->id,
            'status' => 'active',
            'daily_order_limit' => 1,
        ]);
        Wallet::query()->create([
            'user_id' => $buyer->id,
            'balance' => '100.00',
            'is_frozen' => false,
        ]);

        $this->actingAs($buyer)->post(route('buyer.orders.store'), [
            'confirm' => true,
            'items' => [
                [
                    'network' => 'MTN',
                    'phone_number' => '0244123456',
                    'bundle_package_id' => $bundle->id,
                ],
                [
                    'network' => 'MTN',
                    'phone_number' => '0551234567',
                    'bundle_package_id' => $b2->id,
                ],
            ],
        ])->assertSessionHasErrors('order');

        $this->assertSame(0, Order::query()->where('user_id', $buyer->id)->count());
    }

    public function test_buyer_catalog_includes_only_agent_bundles_when_linked_to_agent(): void
    {
        $agentRole = Role::query()->where('slug', Role::SLUG_AGENT)->firstOrFail();
        $buyerRole = Role::query()->where('slug', Role::SLUG_BUYER)->firstOrFail();
        $agent = User::factory()->create(['role_id' => $agentRole->id, 'status' => 'active']);
        $otherAgent = User::factory()->create(['role_id' => $agentRole->id, 'status' => 'active']);
        $buyer = User::factory()->create([
            'role_id' => $buyerRole->id,
            'status' => 'active',
            'agent_id' => $agent->id,
        ]);

        $platform = $this->createPlatformBundle();
        $own = $this->createAgentBundle($agent);
        $other = $this->createAgentBundle($otherAgent);

        $ids = BundleCatalog::forBuyer($buyer)->pluck('id')->all();

        $this->assertSame([$own->id], $ids);
        $this->assertNotContains($platform->id, $ids);
        $this->assertNotContains($other->id, $ids);
    }

    public function test_buyer_catalog_includes_only_platform_bundles_when_no_agent(): void
    {
        $agentRole = Role::query()->where('slug', Role::SLUG_AGENT)->firstOrFail();
        $buyerRole = Role::query()->where('slug', Role::SLUG_BUYER)->firstOrFail();
        $agent = User::factory()->create(['role_id' => $agentRole->id, 'status' => 'active']);
        $buyer = User::factory()->create(['role_id' => $buyerRole->id, 'status' => 'active']);
        $platform = $this->createPlatformBundle();
        $agentBundle = $this->createAgentBundle($agent);

        $ids = BundleCatalog::forBuyer($buyer)->pluck('id')->all();

        $this->assertSame([$platform->id], $ids);
        $this->assertNotContains($agentBundle->id, $ids);
    }

    public function test_buyer_linked_to_agent_rejected_when_ordering_platform_bundle(): void
    {
        $agentRole = Role::query()->where('slug', Role::SLUG_AGENT)->firstOrFail();
        $agent = User::factory()->create(['role_id' => $agentRole->id, 'status' => 'active']);
        $buyer = $this->activeBuyerWithWallet('100.00');
        $buyer->update(['agent_id' => $agent->id]);
        $platform = $this->createPlatformBundle();

        $this->actingAs($buyer)->post(route('buyer.orders.store'), [
            'network' => 'MTN',
            'phone_number' => '0244123456',
            'bundle_package_id' => $platform->id,
            'confirm' => true,
        ])->assertSessionHasErrors('bundle_package_id');
    }

    public function test_supplier_can_manage_order_from_buyer_linked_to_agent(): void
    {
        $supplier = $this->supplierUser();
        $agentRole = Role::query()->where('slug', Role::SLUG_AGENT)->firstOrFail();
        $buyerRole = Role::query()->where('slug', Role::SLUG_BUYER)->firstOrFail();

        $agent = User::factory()->create([
            'role_id' => $agentRole->id,
            'status' => 'active',
        ]);

        $buyer = User::factory()->create([
            'role_id' => $buyerRole->id,
            'status' => 'active',
            'agent_id' => $agent->id,
        ]);

        Wallet::query()->create([
            'user_id' => $buyer->id,
            'balance' => '100.00',
            'is_frozen' => false,
        ]);

        $bundle = $this->createAgentBundle($agent);

        $this->actingAs($buyer)->post(route('buyer.orders.store'), [
            'network' => 'MTN',
            'phone_number' => '0244123456',
            'bundle_package_id' => $bundle->id,
            'confirm' => true,
        ])->assertRedirect();

        $order = Order::query()->where('user_id', $buyer->id)->firstOrFail();

        $this->actingAs($supplier)->get(route('admin.orders.show', $order))->assertOk();
        $this->actingAs($supplier)->patch(route('admin.orders.status', $order), [
            'status' => 'PROCESSING',
        ])->assertRedirect();
        $this->assertSame('PROCESSING', $order->fresh()->status);
    }

    public function test_admin_and_agent_orders_index_show_package_column(): void
    {
        $supplier = $this->supplierUser();
        $agentRole = Role::query()->where('slug', Role::SLUG_AGENT)->firstOrFail();
        $agent = User::factory()->create([
            'role_id' => $agentRole->id,
            'status' => 'active',
            'shop_slug' => 'pkg-ag',
        ]);
        Wallet::query()->create([
            'user_id' => $agent->id,
            'balance' => '100.00',
            'is_frozen' => false,
        ]);

        $bundle = $this->createPlatformBundle();

        $this->actingAs($agent)->post(route('agent.orders.store'), [
            'items' => [[
                'network' => 'MTN',
                'phone_number' => '0244999888',
                'bundle_package_id' => $bundle->id,
            ]],
            'confirm' => true,
        ])->assertRedirect();

        $this->actingAs($supplier)->get(route('admin.orders.index'))
            ->assertOk()
            ->assertSeeText('Test Bundle')
            ->assertSeeText('1GB');

        $this->actingAs($agent)->get(route('agent.orders.index'))
            ->assertOk()
            ->assertSeeText('Test Bundle')
            ->assertSeeText('1GB');
    }
}
