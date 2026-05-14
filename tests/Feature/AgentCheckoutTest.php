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

class AgentCheckoutTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);
    }

    private function activeAgentWithWallet(string $balance = '100.00'): User
    {
        $agentRole = Role::query()->where('slug', Role::SLUG_AGENT)->firstOrFail();
        $agent = User::factory()->create([
            'role_id' => $agentRole->id,
            'status' => 'active',
            'shop_slug' => 'test-agent-shop',
        ]);

        Wallet::query()->create([
            'user_id' => $agent->id,
            'balance' => $balance,
            'is_frozen' => false,
        ]);

        return $agent;
    }

    public function test_agent_batch_checkout_sets_agent_id_and_debits_wallet(): void
    {
        $agent = $this->activeAgentWithWallet('100.00');

        $mtn = BundlePackage::query()->create([
            'agent_id' => null,
            'network' => 'MTN',
            'package_kind' => 'data',
            'name' => 'Platform MTN',
            'size_label' => '1GB',
            'internal_cost' => '5.00',
            'stock_count' => 10,
            'is_available' => true,
        ]);

        $telecel = BundlePackage::query()->create([
            'agent_id' => null,
            'network' => 'Telecel',
            'package_kind' => 'data',
            'name' => 'Platform Telecel',
            'size_label' => '2GB',
            'internal_cost' => '6.00',
            'stock_count' => 10,
            'is_available' => true,
        ]);

        $this->actingAs($agent)->post(route('agent.orders.store'), [
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
        ])->assertRedirect(route('agent.orders.index'));

        $orders = Order::query()->where('user_id', $agent->id)->get();
        $this->assertCount(2, $orders);

        foreach ($orders as $o) {
            $this->assertSame((int) $agent->id, (int) $o->agent_id);
            $this->assertSame((int) $agent->id, (int) $o->user_id);
        }

        $this->assertSame('89.00', (string) $agent->wallet->fresh()->balance);
    }

    public function test_agent_can_order_own_bundle_from_catalog(): void
    {
        $agent = $this->activeAgentWithWallet('100.00');

        $own = BundlePackage::query()->create([
            'agent_id' => $agent->id,
            'network' => 'MTN',
            'package_kind' => 'data',
            'name' => 'My shop bundle',
            'size_label' => '3GB',
            'internal_cost' => '7.00',
            'stock_count' => 5,
            'is_available' => true,
        ]);

        $this->actingAs($agent)->post(route('agent.orders.store'), [
            'confirm' => true,
            'items' => [
                [
                    'network' => 'MTN',
                    'phone_number' => '0551234567',
                    'bundle_package_id' => $own->id,
                ],
            ],
        ])->assertRedirect(route('agent.orders.index'));

        $order = Order::query()->where('user_id', $agent->id)->firstOrFail();
        $this->assertSame((int) $agent->id, (int) $order->agent_id);
        $this->assertSame('93.00', (string) $agent->wallet->fresh()->balance);
    }
}
