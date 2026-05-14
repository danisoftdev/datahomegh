<?php

namespace Tests\Feature;

use App\Models\Role;
use App\Models\User;
use App\Models\Wallet;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AgentBuyerWalletTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);
    }

    public function test_buyer_linked_to_agent_cannot_initialize_paystack_topup(): void
    {
        $buyerRole = Role::query()->where('slug', Role::SLUG_BUYER)->firstOrFail();
        $agentRole = Role::query()->where('slug', Role::SLUG_AGENT)->firstOrFail();

        $agent = User::factory()->create([
            'role_id' => $agentRole->id,
            'status' => 'active',
        ]);

        $buyer = User::factory()->create([
            'role_id' => $buyerRole->id,
            'agent_id' => $agent->id,
            'status' => 'active',
        ]);

        Wallet::query()->create([
            'user_id' => $buyer->id,
            'balance' => '0.00',
            'is_frozen' => false,
        ]);

        $this->actingAs($buyer)->post(route('wallet.topup.initialize'), [
            'amount' => '10',
        ])->assertSessionHasErrors('amount');
    }

    public function test_agent_can_credit_linked_buyer_wallet(): void
    {
        $buyerRole = Role::query()->where('slug', Role::SLUG_BUYER)->firstOrFail();
        $agentRole = Role::query()->where('slug', Role::SLUG_AGENT)->firstOrFail();

        $agent = User::factory()->create([
            'role_id' => $agentRole->id,
            'username' => 'agentcred',
            'status' => 'active',
        ]);

        $buyer = User::factory()->create([
            'role_id' => $buyerRole->id,
            'username' => 'buyercred',
            'agent_id' => $agent->id,
            'status' => 'active',
        ]);

        Wallet::query()->create([
            'user_id' => $buyer->id,
            'balance' => '0.00',
            'is_frozen' => false,
        ]);

        $this->actingAs($agent)->post(route('agent.buyers.wallet-credit', $buyer), [
            'amount' => '25.50',
            'note' => 'MTN MoMo',
        ])->assertSessionHas('status');

        $this->assertSame('25.50', (string) $buyer->wallet->fresh()->balance);

        $this->assertDatabaseHas('wallet_ledger', [
            'user_id' => $buyer->id,
            'type' => 'CREDIT',
            'source' => 'AGENT_CREDIT',
        ]);
    }

    public function test_other_agent_cannot_credit_buyer(): void
    {
        $buyerRole = Role::query()->where('slug', Role::SLUG_BUYER)->firstOrFail();
        $agentRole = Role::query()->where('slug', Role::SLUG_AGENT)->firstOrFail();

        $owner = User::factory()->create(['role_id' => $agentRole->id, 'status' => 'active']);
        $intruder = User::factory()->create(['role_id' => $agentRole->id, 'status' => 'active']);

        $buyer = User::factory()->create([
            'role_id' => $buyerRole->id,
            'agent_id' => $owner->id,
            'status' => 'active',
        ]);

        Wallet::query()->create([
            'user_id' => $buyer->id,
            'balance' => '0.00',
            'is_frozen' => false,
        ]);

        $this->actingAs($intruder)->post(route('agent.buyers.wallet-credit', $buyer), [
            'amount' => '10',
        ])->assertForbidden();
    }
}
