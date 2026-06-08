<?php

namespace Tests\Feature;

use App\Models\AgentEarningsBalance;
use App\Models\AgentWithdrawalRequest;
use App\Models\BundlePackage;
use App\Models\Order;
use App\Models\ResalePlan;
use App\Models\Role;
use App\Models\RolePrice;
use App\Models\User;
use App\Models\Wallet;
use App\Services\AgentCommissionService;
use App\Services\OrderService;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class AgentShopCommissionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);
    }

    public function test_wallet_order_does_not_credit_agent_commission_on_sent(): void
    {
        [$agent, $buyer, $bundle] = $this->createAgentShopFixtures('15.00', '10.00');

        $order = app(OrderService::class)->placeOrder($buyer->id, [
            'network' => 'MTN',
            'phone_number' => '0241234567',
            'bundle_package_id' => $bundle->id,
        ]);

        $this->assertSame('wallet', $order->payment_method);

        app(OrderService::class)->updateStatus($order->id, 'PROCESSING', $agent->id, null, false);
        app(OrderService::class)->updateStatus($order->id, 'SENT', $agent->id, null, false);

        $order->refresh();
        $this->assertNull($order->agent_commission_status);

        $balance = AgentEarningsBalance::query()->where('agent_id', $agent->id)->first();
        $this->assertNull($balance);
    }

    public function test_paystack_order_credits_agent_commission_when_admin_marks_sent(): void
    {
        [$agent, $buyer, $bundle] = $this->createAgentShopFixtures('15.00', '10.00');

        $order = app(OrderService::class)->placeOrders($buyer->id, [[
            'network' => 'MTN',
            'phone_number' => '0241234567',
            'bundle_package_id' => $bundle->id,
        ]], [
            'payment_method' => 'paystack',
            'paystack_reference' => 'ps_ref_commission_1',
            'skip_wallet_debit' => true,
        ])->first();

        $this->assertSame('paystack', $order->payment_method);
        $this->assertSame('10.00', bcadd((string) $order->agent_cost_amount, '0', 2));
        $this->assertSame('5.00', bcadd((string) $order->agent_commission_amount, '0', 2));
        $this->assertSame('pending', $order->agent_commission_status);

        app(OrderService::class)->updateStatus($order->id, 'PROCESSING', $agent->id, null, false);
        app(OrderService::class)->updateStatus($order->id, 'SENT', $agent->id, null, false);

        $order->refresh();
        $this->assertSame('credited', $order->agent_commission_status);

        $balance = AgentEarningsBalance::query()->where('agent_id', $agent->id)->firstOrFail();
        $this->assertSame('5.00', (string) $balance->balance);
    }

    public function test_commission_reversed_on_refunded_paystack_order(): void
    {
        [$agent, $buyer, $bundle] = $this->createAgentShopFixtures('20.00', '12.00');

        $order = app(OrderService::class)->placeOrders($buyer->id, [[
            'network' => 'MTN',
            'phone_number' => '0247654321',
            'bundle_package_id' => $bundle->id,
        ]], [
            'payment_method' => 'paystack',
            'paystack_reference' => 'ps_ref_commission_2',
            'skip_wallet_debit' => true,
        ])->first();

        app(OrderService::class)->updateStatus($order->id, 'PROCESSING', $agent->id, null, false);
        app(OrderService::class)->updateStatus($order->id, 'SENT', $agent->id, null, false);

        app(OrderService::class)->updateStatus($order->id, 'REFUNDED', $agent->id, 'test refund', false);

        $order->refresh();
        $this->assertSame('reversed', $order->agent_commission_status);
        $this->assertSame('0.00', (string) AgentEarningsBalance::query()->where('agent_id', $agent->id)->value('balance'));
    }

    public function test_agent_shop_buyer_paystack_checkout_redirects_to_paystack(): void
    {
        Config::set('paystack.secret_key', 'sk_test_agent_shop');
        Config::set('paystack.base_url', 'https://api.paystack.co');

        [$agent, $buyer, $bundle] = $this->createAgentShopFixtures('12.00', '8.00');
        $buyer->update(['paystack_checkout_only' => true]);

        Http::fake([
            'https://api.paystack.co/transaction/initialize' => Http::response([
                'status' => true,
                'message' => 'Authorization URL created',
                'data' => [
                    'authorization_url' => 'https://checkout.paystack.com/test-agent-shop',
                    'reference' => 'dhgh_test_ref',
                ],
            ], 200),
        ]);

        $response = $this->actingAs($buyer)->post(route('buyer.orders.store'), [
            'payment_method' => 'paystack',
            'confirm' => true,
            'items' => [
                [
                    'network' => 'MTN',
                    'phone_number' => '0241234567',
                    'bundle_package_id' => $bundle->id,
                ],
            ],
        ]);

        $response->assertRedirect('https://checkout.paystack.com/test-agent-shop');
        $this->assertDatabaseHas('paystack_transactions', [
            'user_id' => $buyer->id,
            'status' => 'pending',
        ]);
        $this->assertDatabaseMissing('orders', ['user_id' => $buyer->id]);
    }

    public function test_wallet_cutoff_switches_buyer_to_paystack_only(): void
    {
        [$agent, $buyer, $bundle] = $this->createAgentShopFixtures('5.00', '3.00');

        $buyer->wallet->update(['balance' => '6.00']);

        app(OrderService::class)->placeOrder($buyer->id, [
            'network' => 'MTN',
            'phone_number' => '0241111111',
            'bundle_package_id' => $bundle->id,
        ]);

        $buyer->refresh();
        $this->assertTrue($buyer->paystack_checkout_only);
        $this->assertSame('1.00', (string) $buyer->wallet->fresh()->balance);
    }

    public function test_agent_cannot_credit_wallet_after_paystack_only_switch(): void
    {
        [$agent, $buyer] = $this->createAgentShopFixtures('8.00', '5.00');
        $buyer->update(['paystack_checkout_only' => true]);

        $this->actingAs($agent)->post(route('agent.buyers.wallet-credit', $buyer), [
            'amount' => '5',
        ])->assertSessionHasErrors('amount');
    }

    public function test_agent_can_request_withdrawal_and_admin_marks_paid(): void
    {
        config(['datahome.agent_shop.withdrawal_min_amount' => 5]);

        [$agent, $buyer, $bundle] = $this->createAgentShopFixtures('15.00', '10.00');
        $supplier = $this->supplierUser();

        $order = app(OrderService::class)->placeOrders($buyer->id, [[
            'network' => 'MTN',
            'phone_number' => '0242222222',
            'bundle_package_id' => $bundle->id,
        ]], [
            'payment_method' => 'paystack',
            'paystack_reference' => 'ps_ref_withdraw_1',
            'skip_wallet_debit' => true,
        ])->first();

        app(OrderService::class)->updateStatus($order->id, 'PROCESSING', $agent->id, null, false);
        app(OrderService::class)->updateStatus($order->id, 'SENT', $agent->id, null, false);

        $this->actingAs($agent)->post(route('agent.earnings.withdraw'), [
            'amount' => '5',
            'payout_method' => 'momo',
            'momo_network' => 'MTN',
            'momo_number' => '0249999999',
            'account_name' => 'Agent Test',
        ])->assertSessionHas('status');

        $request = AgentWithdrawalRequest::query()->where('agent_id', $agent->id)->firstOrFail();
        $this->assertSame(AgentWithdrawalRequest::STATUS_PENDING, $request->status);
        $this->assertSame('0.00', (string) AgentEarningsBalance::query()->where('agent_id', $agent->id)->value('balance'));

        $this->actingAs($supplier)->patch(route('admin.withdrawals.status', $request), [
            'status' => AgentWithdrawalRequest::STATUS_PROCESSING,
        ])->assertSessionHas('status');

        $this->actingAs($supplier)->patch(route('admin.withdrawals.status', $request->fresh()), [
            'status' => AgentWithdrawalRequest::STATUS_PAID,
            'admin_note' => 'Paid via MoMo',
        ])->assertSessionHas('status');

        $this->assertSame(AgentWithdrawalRequest::STATUS_PAID, $request->fresh()->status);
    }

    public function test_resolve_agent_list_price_uses_platform_agent_role_price(): void
    {
        [$agent, , $bundle] = $this->createAgentShopFixtures('15.00', '10.00');

        $price = app(AgentCommissionService::class)->resolveAgentListPrice($bundle);
        $this->assertSame('10.00', $price);
    }

    /**
     * @return array{0: User, 1: User, 2: BundlePackage}
     */
    private function createAgentShopFixtures(string $shopPrice, string $agentListPrice): array
    {
        $buyerRole = Role::query()->where('slug', Role::SLUG_BUYER)->firstOrFail();
        $agentRole = Role::query()->where('slug', Role::SLUG_AGENT)->firstOrFail();

        $agent = User::factory()->create([
            'role_id' => $agentRole->id,
            'status' => 'active',
            'shop_slug' => 'shop-'.uniqid(),
        ]);

        Wallet::query()->create([
            'user_id' => $agent->id,
            'balance' => '100.00',
            'is_frozen' => false,
        ]);

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

        $platformBundle = BundlePackage::query()->create([
            'agent_id' => null,
            'network' => 'MTN',
            'package_kind' => 'data',
            'name' => 'Platform 2GB',
            'size_label' => '2GB',
            'internal_cost' => '8.00',
            'stock_count' => 100,
            'is_available' => true,
        ]);

        RolePrice::query()->create([
            'role_id' => $agentRole->id,
            'bundle_package_id' => $platformBundle->id,
            'price' => $agentListPrice,
        ]);

        $agentBundle = BundlePackage::query()->create([
            'agent_id' => $agent->id,
            'network' => 'MTN',
            'package_kind' => 'data',
            'name' => 'Shop 2GB',
            'size_label' => '2GB',
            'internal_cost' => '12.00',
            'stock_count' => 10,
            'is_available' => true,
        ]);

        ResalePlan::query()->create([
            'bundle_package_id' => $agentBundle->id,
            'agent_id' => $agent->id,
            'price' => $shopPrice,
            'label' => 'Retail',
            'is_active' => true,
        ]);

        return [$agent, $buyer, $agentBundle];
    }

    private function supplierUser(): User
    {
        $role = Role::query()->where('slug', Role::SLUG_SUPPLIER)->firstOrFail();

        return User::factory()->create([
            'role_id' => $role->id,
            'status' => 'active',
        ]);
    }
}
