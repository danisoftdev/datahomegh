<?php

namespace Tests\Feature;

use App\Models\Notification;
use App\Models\PaystackTransaction;
use App\Models\PlatformSetting;
use App\Models\Role;
use App\Models\User;
use App\Models\Wallet;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class AuthTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);
    }

    public function test_agent_register_redirects_to_paystack_and_creates_pending_payment_user(): void
    {
        PlatformSetting::set(PlatformSetting::KEY_AGENT_SHOP_REGISTRATION_FEE_GHS, '25.00');

        Http::fake([
            'https://api.paystack.co/transaction/initialize' => Http::response([
                'status' => true,
                'data' => [
                    'authorization_url' => 'https://checkout.paystack.example/pay',
                    'reference' => 'ref_agent_reg_1',
                ],
            ], 200),
        ]);

        $response = $this->post(route('register'), [
            'account_type' => 'agent',
            'username' => 'newagent',
            'name' => 'New Agent',
            'email' => 'newagent@example.com',
            'phone' => '0244333444',
            'password' => 'Password123!',
            'password_confirmation' => 'Password123!',
            'shop_name' => 'Agent Business',
        ]);

        $response->assertRedirect('https://checkout.paystack.example/pay');

        $this->assertDatabaseHas('users', [
            'username' => 'newagent',
            'status' => 'pending_payment',
        ]);

        $agent = User::query()->where('username', 'newagent')->firstOrFail();
        $this->assertNotNull($agent->shop_slug);
        $this->assertSame(5, strlen((string) $agent->shop_slug));
        $this->assertMatchesRegularExpression('/^[A-Za-z0-9]{5}$/', (string) $agent->shop_slug);
        $this->assertMatchesRegularExpression('/[A-Za-z]/', (string) $agent->shop_slug);
        $this->assertMatchesRegularExpression('/\d/', (string) $agent->shop_slug);
        $this->assertDatabaseHas('paystack_transactions', [
            'reference' => 'ref_agent_reg_1',
            'user_id' => $agent->id,
            'status' => 'pending',
        ]);

        $this->assertSame(0, Notification::query()->where('type', 'agent_registered')->count());
        $this->assertGuest();
    }

    public function test_agent_register_callback_after_payment_notifies_suppliers_and_sets_pending(): void
    {
        PlatformSetting::set(PlatformSetting::KEY_AGENT_SHOP_REGISTRATION_FEE_GHS, '25.00');

        Http::fake([
            'https://api.paystack.co/transaction/initialize' => Http::response([
                'status' => true,
                'data' => [
                    'authorization_url' => 'https://checkout.paystack.example/pay',
                    'reference' => 'ref_agent_reg_2',
                ],
            ], 200),
        ]);

        $this->post(route('register'), [
            'account_type' => 'agent',
            'username' => 'payagent',
            'name' => 'Pay Agent',
            'email' => 'payagent@example.com',
            'phone' => '0244333555',
            'password' => 'Password123!',
            'password_confirmation' => 'Password123!',
            'shop_name' => 'Agent Business',
        ])->assertRedirect('https://checkout.paystack.example/pay');

        $agent = User::query()->where('username', 'payagent')->firstOrFail();

        $supplierRole = Role::query()->where('slug', Role::SLUG_SUPPLIER)->firstOrFail();
        $supplier = User::factory()->create([
            'role_id' => $supplierRole->id,
            'username' => 'supplier_notify',
            'status' => 'active',
        ]);

        Http::fake([
            'https://api.paystack.co/transaction/verify/ref_agent_reg_2' => Http::response([
                'status' => true,
                'data' => [
                    'status' => 'success',
                    'reference' => 'ref_agent_reg_2',
                    'amount' => 2500,
                    'metadata' => [
                        'user_id' => $agent->id,
                        'type' => 'agent_shop_registration',
                    ],
                    'paid_at' => now()->toIso8601String(),
                    'channel' => 'mobile_money',
                ],
            ], 200),
        ]);

        $this->withSession([
            'agent_shop_registration' => [
                'reference' => 'ref_agent_reg_2',
                'user_id' => $agent->id,
            ],
        ])->get(route('register.agent-fee.callback', ['reference' => 'ref_agent_reg_2']))
            ->assertRedirect(route('login'));

        $this->assertDatabaseHas('users', [
            'id' => $agent->id,
            'status' => 'pending',
        ]);

        $this->assertDatabaseHas('paystack_transactions', [
            'reference' => 'ref_agent_reg_2',
            'status' => 'success',
        ]);

        $this->assertGreaterThanOrEqual(1, Notification::query()
            ->where('type', 'agent_registered')
            ->where('user_id', $supplier->id)
            ->count());
    }

    public function test_agent_register_callback_succeeds_using_transaction_when_paystack_metadata_is_empty_and_session_mismatches(): void
    {
        PlatformSetting::set(PlatformSetting::KEY_AGENT_SHOP_REGISTRATION_FEE_GHS, '25.00');

        Http::fake([
            'https://api.paystack.co/transaction/initialize' => Http::response([
                'status' => true,
                'data' => [
                    'authorization_url' => 'https://checkout.paystack.example/pay',
                    'reference' => 'ref_agent_reg_meta_gap',
                ],
            ], 200),
        ]);

        $this->post(route('register'), [
            'account_type' => 'agent',
            'username' => 'meta_gap_agent',
            'name' => 'Meta Gap Agent',
            'email' => 'metagap@example.com',
            'phone' => '0244333777',
            'password' => 'Password123!',
            'password_confirmation' => 'Password123!',
            'shop_name' => 'Agent Business',
        ])->assertRedirect('https://checkout.paystack.example/pay');

        $agent = User::query()->where('username', 'meta_gap_agent')->firstOrFail();

        Http::fake([
            'https://api.paystack.co/transaction/verify/ref_agent_reg_meta_gap' => Http::response([
                'status' => true,
                'data' => [
                    'status' => 'success',
                    'reference' => 'ref_agent_reg_meta_gap',
                    'amount' => 2500,
                    'metadata' => [],
                    'paid_at' => now()->toIso8601String(),
                    'channel' => 'mobile_money',
                ],
            ], 200),
        ]);

        $this->withSession([
            'agent_shop_registration' => [
                'reference' => 'stale_different_reference',
                'user_id' => $agent->id,
            ],
        ])->get(route('register.agent-fee.callback', ['reference' => 'ref_agent_reg_meta_gap']))
            ->assertRedirect(route('login'))
            ->assertSessionDoesntHaveErrors();

        $this->assertDatabaseHas('users', [
            'id' => $agent->id,
            'status' => 'pending',
        ]);

        $this->assertDatabaseHas('paystack_transactions', [
            'reference' => 'ref_agent_reg_meta_gap',
            'status' => 'success',
        ]);
    }

    public function test_agent_register_callback_accepts_one_pesewa_amount_difference_from_paystack(): void
    {
        PlatformSetting::set(PlatformSetting::KEY_AGENT_SHOP_REGISTRATION_FEE_GHS, '25.00');

        Http::fake([
            'https://api.paystack.co/transaction/initialize' => Http::response([
                'status' => true,
                'data' => [
                    'authorization_url' => 'https://checkout.paystack.example/pay',
                    'reference' => 'ref_agent_reg_pesewa',
                ],
            ], 200),
        ]);

        $this->post(route('register'), [
            'account_type' => 'agent',
            'username' => 'pesewa_agent',
            'name' => 'Pesewa Agent',
            'email' => 'pesewa@example.com',
            'phone' => '0244333888',
            'password' => 'Password123!',
            'password_confirmation' => 'Password123!',
            'shop_name' => 'Agent Business',
        ])->assertRedirect('https://checkout.paystack.example/pay');

        $agent = User::query()->where('username', 'pesewa_agent')->firstOrFail();

        Http::fake([
            'https://api.paystack.co/transaction/verify/ref_agent_reg_pesewa' => Http::response([
                'status' => true,
                'data' => [
                    'status' => 'success',
                    'reference' => 'ref_agent_reg_pesewa',
                    'amount' => 2499,
                    'metadata' => [
                        'user_id' => $agent->id,
                        'type' => 'agent_shop_registration',
                    ],
                    'paid_at' => now()->toIso8601String(),
                    'channel' => 'mobile_money',
                ],
            ], 200),
        ]);

        $this->withSession([
            'agent_shop_registration' => [
                'reference' => 'ref_agent_reg_pesewa',
                'user_id' => $agent->id,
            ],
        ])->get(route('register.agent-fee.callback', ['reference' => 'ref_agent_reg_pesewa']))
            ->assertRedirect(route('login'))
            ->assertSessionDoesntHaveErrors();

        $this->assertSame('pending', (string) $agent->fresh()->status);
    }

    public function test_agent_register_callback_accepts_paystack_surcharge_above_listed_fee(): void
    {
        PlatformSetting::set(PlatformSetting::KEY_AGENT_SHOP_REGISTRATION_FEE_GHS, '25.00');

        Http::fake([
            'https://api.paystack.co/transaction/initialize' => Http::response([
                'status' => true,
                'data' => [
                    'authorization_url' => 'https://checkout.paystack.example/pay',
                    'reference' => 'ref_agent_reg_surcharge',
                ],
            ], 200),
        ]);

        $this->post(route('register'), [
            'account_type' => 'agent',
            'username' => 'surcharge_agent',
            'name' => 'Surcharge Agent',
            'email' => 'surcharge@example.com',
            'phone' => '0244333777',
            'password' => 'Password123!',
            'password_confirmation' => 'Password123!',
            'shop_name' => 'Agent Business',
        ])->assertRedirect('https://checkout.paystack.example/pay');

        $agent = User::query()->where('username', 'surcharge_agent')->firstOrFail();

        Http::fake([
            'https://api.paystack.co/transaction/verify/ref_agent_reg_surcharge' => Http::response([
                'status' => true,
                'data' => [
                    'status' => 'success',
                    'reference' => 'ref_agent_reg_surcharge',
                    'amount' => 3000,
                    'metadata' => [
                        'user_id' => $agent->id,
                        'type' => 'agent_shop_registration',
                    ],
                    'paid_at' => now()->toIso8601String(),
                    'channel' => 'mobile_money',
                ],
            ], 200),
        ]);

        $this->withSession([
            'agent_shop_registration' => [
                'reference' => 'ref_agent_reg_surcharge',
                'user_id' => $agent->id,
            ],
        ])->get(route('register.agent-fee.callback', ['reference' => 'ref_agent_reg_surcharge']))
            ->assertRedirect(route('login'))
            ->assertSessionDoesntHaveErrors();

        $this->assertSame('pending', (string) $agent->fresh()->status);
        $this->assertDatabaseHas('paystack_transactions', [
            'reference' => 'ref_agent_reg_surcharge',
            'status' => 'success',
            'amount' => '30.00',
        ]);
    }

    public function test_agent_register_callback_reconciles_when_paystack_transaction_row_was_missing(): void
    {
        PlatformSetting::set(PlatformSetting::KEY_AGENT_SHOP_REGISTRATION_FEE_GHS, '25.00');

        Http::fake([
            'https://api.paystack.co/transaction/initialize' => Http::response([
                'status' => true,
                'data' => [
                    'authorization_url' => 'https://checkout.paystack.example/pay',
                    'reference' => 'ref_agent_reg_reconcile',
                ],
            ], 200),
        ]);

        $this->post(route('register'), [
            'account_type' => 'agent',
            'username' => 'reconcile_agent',
            'name' => 'Reconcile Agent',
            'email' => 'reconcile@example.com',
            'phone' => '0244333999',
            'password' => 'Password123!',
            'password_confirmation' => 'Password123!',
            'shop_name' => 'Agent Business',
        ])->assertRedirect('https://checkout.paystack.example/pay');

        $agent = User::query()->where('username', 'reconcile_agent')->firstOrFail();

        PaystackTransaction::query()->where('reference', 'ref_agent_reg_reconcile')->delete();
        $this->assertDatabaseMissing('paystack_transactions', ['reference' => 'ref_agent_reg_reconcile']);

        Http::fake([
            'https://api.paystack.co/transaction/verify/ref_agent_reg_reconcile' => Http::response([
                'status' => true,
                'data' => [
                    'status' => 'success',
                    'reference' => 'ref_agent_reg_reconcile',
                    'amount' => 2500,
                    'metadata' => [
                        'user_id' => $agent->id,
                        'type' => 'agent_shop_registration',
                    ],
                    'paid_at' => now()->toIso8601String(),
                    'channel' => 'mobile_money',
                ],
            ], 200),
        ]);

        $this->withSession([
            'agent_shop_registration' => [
                'reference' => 'ref_agent_reg_reconcile',
                'user_id' => $agent->id,
            ],
        ])->get(route('register.agent-fee.callback', ['reference' => 'ref_agent_reg_reconcile']))
            ->assertRedirect(route('login'))
            ->assertSessionDoesntHaveErrors();

        $this->assertDatabaseHas('paystack_transactions', [
            'reference' => 'ref_agent_reg_reconcile',
            'user_id' => $agent->id,
            'status' => 'success',
        ]);
        $this->assertSame('pending', (string) $agent->fresh()->status);
    }

    public function test_agent_register_without_configured_fee_fails_validation(): void
    {
        $this->post(route('register'), [
            'account_type' => 'agent',
            'username' => 'no_fee_agent',
            'name' => 'No Fee',
            'email' => 'nofee@example.com',
            'phone' => '0244333666',
            'password' => 'Password123!',
            'password_confirmation' => 'Password123!',
            'shop_name' => 'Shop',
        ])->assertSessionHasErrors('registration');

        $this->assertDatabaseMissing('users', ['username' => 'no_fee_agent']);
    }

    public function test_login_fails_for_pending_payment_agent(): void
    {
        $agentRole = Role::query()->where('slug', Role::SLUG_AGENT)->firstOrFail();
        User::factory()->create([
            'role_id' => $agentRole->id,
            'username' => 'unpaidagent',
            'password' => 'SecretPass1!',
            'status' => 'pending_payment',
        ]);

        $this->from(route('login'))->post(route('login'), [
            'username' => 'unpaidagent',
            'password' => 'SecretPass1!',
        ])->assertSessionHasErrors('username');

        $this->assertGuest();
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

    public function test_register_via_agent_shop_shows_buyer_flow_and_hides_agent_shop_fields(): void
    {
        $agentRole = Role::query()->where('slug', Role::SLUG_AGENT)->firstOrFail();
        User::factory()->create([
            'role_id' => $agentRole->id,
            'shop_slug' => 'shop-abc',
            'shop_name' => 'Test Shop',
            'status' => 'active',
        ]);

        $this->get(route('register', ['agentSlug' => 'shop-abc']))
            ->assertOk()
            ->assertSee((string) __('Buyer registration'), false)
            ->assertSee('shop-abc', false)
            ->assertDontSee('name="shop_name"', false)
            ->assertDontSee((string) __('Continue to Paystack'), false);
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
        PlatformSetting::set(PlatformSetting::KEY_AGENT_SHOP_REGISTRATION_FEE_GHS, '10.00');

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
        PlatformSetting::set(PlatformSetting::KEY_AGENT_SHOP_REGISTRATION_FEE_GHS, '10.00');

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
