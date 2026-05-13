<?php

namespace Tests\Feature;

use App\Models\Role;
use App\Models\User;
use App\Models\Wallet;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuthTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);
    }

    public function test_buyer_can_register_via_agent_link(): void
    {
        $agentRole = Role::query()->where('slug', Role::SLUG_AGENT)->firstOrFail();
        $agent = User::factory()->create([
            'role_id' => $agentRole->id,
            'shop_slug' => 'agent-shop',
            'shop_name' => 'Agent Shop',
            'status' => 'active',
        ]);

        $response = $this->from(route('register', ['agentSlug' => 'agent-shop']))->post(route('register'), [
            'username' => 'newbuyer',
            'name' => 'New Buyer',
            'email' => 'newbuyer@example.com',
            'phone' => '0244111222',
            'password' => 'Password123!',
            'password_confirmation' => 'Password123!',
            'agent_slug' => 'agent-shop',
        ]);

        $response->assertRedirect(route('pending-approval'));

        $this->assertDatabaseHas('users', [
            'username' => 'newbuyer',
            'status' => 'pending',
            'agent_id' => $agent->id,
        ]);
    }

    public function test_login_with_valid_credentials(): void
    {
        $buyerRole = Role::query()->where('slug', Role::SLUG_BUYER)->firstOrFail();
        $buyer = User::factory()->create([
            'role_id' => $buyerRole->id,
            'username' => 'activebuyer',
            'password' => 'SecretPass1!',
            'status' => 'active',
        ]);

        Wallet::query()->create([
            'user_id' => $buyer->id,
            'balance' => 0,
            'is_frozen' => false,
        ]);

        $response = $this->post(route('login'), [
            'username' => 'activebuyer',
            'password' => 'SecretPass1!',
        ]);

        $response->assertRedirect(route('buyer.dashboard'));
        $this->assertAuthenticatedAs($buyer);
    }

    public function test_login_fails_for_pending_account(): void
    {
        $buyerRole = Role::query()->where('slug', Role::SLUG_BUYER)->firstOrFail();
        User::factory()->create([
            'role_id' => $buyerRole->id,
            'username' => 'pendingbuyer',
            'password' => 'SecretPass1!',
            'status' => 'pending',
        ]);

        $response = $this->from(route('login'))->post(route('login'), [
            'username' => 'pendingbuyer',
            'password' => 'SecretPass1!',
        ]);

        $response->assertSessionHasErrors('username');
        $this->assertGuest();
    }

    public function test_login_fails_for_held_account(): void
    {
        $buyerRole = Role::query()->where('slug', Role::SLUG_BUYER)->firstOrFail();
        User::factory()->create([
            'role_id' => $buyerRole->id,
            'username' => 'heldbuyer',
            'password' => 'SecretPass1!',
            'status' => 'held',
        ]);

        $response = $this->from(route('login'))->post(route('login'), [
            'username' => 'heldbuyer',
            'password' => 'SecretPass1!',
        ]);

        $response->assertSessionHasErrors('username');
        $this->assertGuest();
    }
}
