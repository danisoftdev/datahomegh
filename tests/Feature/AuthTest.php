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
            'via_agent_shop' => '1',
            'account_type' => 'buyer',
            'username' => 'newbuyer',
            'name' => 'New Buyer',
            'email' => 'newbuyer@example.com',
            'phone' => '0244111222',
            'password' => 'Password123!',
            'password_confirmation' => 'Password123!',
            'agent_slug' => 'agent-shop',
        ]);

        $response->assertRedirect(route('buyer.dashboard'));

        $this->assertDatabaseHas('users', [
            'username' => 'newbuyer',
            'status' => 'active',
            'agent_id' => $agent->id,
        ]);

        $this->assertAuthenticated();
    }

    public function test_buyer_register_without_agent_link_is_active_and_logged_in(): void
    {
        $this->post(route('register'), [
            'account_type' => 'buyer',
            'username' => 'solo_buyer',
            'name' => 'Solo Buyer',
            'email' => 'solo@example.com',
            'phone' => '0244222333',
            'password' => 'Password123!',
            'password_confirmation' => 'Password123!',
        ])->assertRedirect(route('buyer.dashboard'));

        $this->assertDatabaseHas('users', [
            'username' => 'solo_buyer',
            'status' => 'active',
        ]);
        $this->assertAuthenticated();
    }

    public function test_agent_register_is_pending_and_redirects_to_login(): void
    {
        $this->post(route('register'), [
            'account_type' => 'agent',
            'username' => 'newagent',
            'name' => 'New Agent',
            'email' => 'newagent@example.com',
            'phone' => '0244333444',
            'password' => 'Password123!',
            'password_confirmation' => 'Password123!',
            'shop_name' => 'Agent Business',
        ])->assertRedirect(route('login'));

        $row = [
            'username' => 'newagent',
            'status' => 'pending',
        ];
        $this->assertDatabaseHas('users', $row);

        $agent = User::query()->where('username', 'newagent')->firstOrFail();
        $this->assertNotNull($agent->shop_slug);
        $this->assertSame(5, strlen((string) $agent->shop_slug));
        $this->assertMatchesRegularExpression('/^[A-Za-z0-9]{5}$/', (string) $agent->shop_slug);
        $this->assertMatchesRegularExpression('/[A-Za-z]/', (string) $agent->shop_slug);
        $this->assertMatchesRegularExpression('/\d/', (string) $agent->shop_slug);
        $this->assertGuest();
    }

    public function test_buyer_register_without_full_name_defaults_to_username(): void
    {
        $this->post(route('register'), [
            'account_type' => 'buyer',
            'username' => 'buyer_noname',
            'phone' => '0244666777',
            'password' => 'Password123!',
            'password_confirmation' => 'Password123!',
        ])->assertRedirect(route('buyer.dashboard'));

        $u = User::query()->where('username', 'buyer_noname')->firstOrFail();
        $this->assertSame('buyer_noname', $u->name);
        $this->assertAuthenticated();
    }

    public function test_agent_register_requires_email_and_shop_name(): void
    {
        $this->post(route('register'), [
            'account_type' => 'agent',
            'username' => 'badagent',
            'name' => 'Bad Agent',
            'phone' => '0244555666',
            'password' => 'Password123!',
            'password_confirmation' => 'Password123!',
        ])->assertSessionHasErrors(['email', 'shop_name']);
    }

    public function test_agent_register_requires_full_name(): void
    {
        $this->post(route('register'), [
            'account_type' => 'agent',
            'username' => 'badagent2',
            'email' => 'bad2@example.com',
            'phone' => '0244555666',
            'password' => 'Password123!',
            'password_confirmation' => 'Password123!',
            'shop_name' => 'Has Shop',
        ])->assertSessionHasErrors(['name']);
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

    public function test_login_fails_for_declined_agent(): void
    {
        $agentRole = Role::query()->where('slug', Role::SLUG_AGENT)->firstOrFail();
        User::factory()->create([
            'role_id' => $agentRole->id,
            'username' => 'declinedagent',
            'password' => 'SecretPass1!',
            'status' => 'declined',
        ]);

        $response = $this->from(route('login'))->post(route('login'), [
            'username' => 'declinedagent',
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
