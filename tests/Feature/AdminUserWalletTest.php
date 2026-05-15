<?php

namespace Tests\Feature;

use App\Models\Role;
use App\Models\User;
use App\Models\Wallet;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminUserWalletTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);
    }

    public function test_supplier_can_credit_buyer_wallet_from_user_account(): void
    {
        $supplier = $this->supplier();
        $buyer = $this->buyerWithWallet('0.00');

        $this->actingAs($supplier)->post(route('admin.users.wallet-credit', $buyer), [
            'amount' => 50.25,
            'note' => 'MoMo top-up',
        ])->assertRedirect()->assertSessionHas('status');

        $buyer->wallet->refresh();
        $this->assertSame('50.25', (string) $buyer->wallet->balance);

        $this->assertDatabaseHas('wallet_ledger', [
            'user_id' => $buyer->id,
            'type' => 'CREDIT',
            'source' => 'ADMIN_CREDIT',
            'amount' => '50.25',
        ]);
    }

    public function test_supplier_can_credit_agent_wallet_from_user_account(): void
    {
        $supplier = $this->supplier();
        $agent = $this->agentWithWallet('10.00');

        $this->actingAs($supplier)->post(route('admin.users.wallet-credit', $agent), [
            'amount' => 15,
        ])->assertRedirect()->assertSessionHas('status');

        $agent->wallet->refresh();
        $this->assertSame('25.00', (string) $agent->wallet->balance);
    }

    public function test_supplier_can_debit_wallet_from_user_account(): void
    {
        $supplier = $this->supplier();
        $buyer = $this->buyerWithWallet('100.00');

        $this->actingAs($supplier)->post(route('admin.users.wallet-debit', $buyer), [
            'amount' => 40,
        ])->assertRedirect()->assertSessionHas('status');

        $buyer->wallet->refresh();
        $this->assertSame('60.00', (string) $buyer->wallet->balance);
    }

    public function test_non_supplier_cannot_credit_from_user_account(): void
    {
        $buyerRole = Role::query()->where('slug', Role::SLUG_BUYER)->firstOrFail();
        $agentRole = Role::query()->where('slug', Role::SLUG_AGENT)->firstOrFail();

        $buyer = $this->buyerWithWallet('0.00');
        $otherBuyer = User::factory()->create(['role_id' => $buyerRole->id, 'status' => 'active']);
        $agent = User::factory()->create(['role_id' => $agentRole->id, 'status' => 'active']);

        $this->actingAs($otherBuyer)->post(route('admin.users.wallet-credit', $buyer), [
            'amount' => 10,
        ])->assertRedirect('/login');

        $this->actingAs($agent)->post(route('admin.users.wallet-credit', $buyer), [
            'amount' => 10,
        ])->assertRedirect('/login');

        $buyer->wallet->refresh();
        $this->assertSame('0.00', (string) $buyer->wallet->balance);
    }

    public function test_admin_user_show_includes_load_wallet_form(): void
    {
        $supplier = $this->supplier();
        $buyer = $this->buyerWithWallet('5.00');

        $this->actingAs($supplier)->get(route('admin.users.show', $buyer))
            ->assertOk()
            ->assertSeeText(__('Load wallet'))
            ->assertSeeText(__('Credit wallet'));
    }

    private function supplier(): User
    {
        $role = Role::query()->where('slug', Role::SLUG_SUPPLIER)->firstOrFail();

        return User::factory()->create([
            'role_id' => $role->id,
            'status' => 'active',
        ]);
    }

    private function buyerWithWallet(string $balance): User
    {
        $role = Role::query()->where('slug', Role::SLUG_BUYER)->firstOrFail();
        $buyer = User::factory()->create([
            'role_id' => $role->id,
            'status' => 'active',
        ]);

        Wallet::query()->create([
            'user_id' => $buyer->id,
            'balance' => $balance,
            'is_frozen' => false,
        ]);

        return $buyer->fresh(['wallet', 'role']);
    }

    private function agentWithWallet(string $balance): User
    {
        $role = Role::query()->where('slug', Role::SLUG_AGENT)->firstOrFail();
        $agent = User::factory()->create([
            'role_id' => $role->id,
            'status' => 'active',
            'shop_slug' => 'ag1'.uniqid(),
        ]);

        Wallet::query()->create([
            'user_id' => $agent->id,
            'balance' => $balance,
            'is_frozen' => false,
        ]);

        return $agent->fresh(['wallet', 'role']);
    }
}
