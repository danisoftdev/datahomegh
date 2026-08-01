<?php

namespace Tests\Feature;

use App\Models\BundlePackage;
use App\Models\PaystackTransaction;
use App\Models\PlatformSetting;
use App\Models\Role;
use App\Models\User;
use App\Models\Wallet;
use App\Models\WalletLedger;
use App\Services\WalletService;
use App\Support\PaystackPaymentPurpose;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use RuntimeException;
use Tests\TestCase;

class WalletTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);
    }

    public function test_ledger_entry_created_on_debit(): void
    {
        $buyerRole = Role::query()->where('slug', Role::SLUG_BUYER)->firstOrFail();
        $buyer = User::factory()->create([
            'role_id' => $buyerRole->id,
            'status' => 'active',
        ]);

        Wallet::query()->create([
            'user_id' => $buyer->id,
            'balance' => '50.00',
            'is_frozen' => false,
        ]);

        app(WalletService::class)->debit($buyer->id, '10.00', 'TEST', 'ref-1', 'note');

        $this->assertDatabaseHas('wallet_ledger', [
            'user_id' => $buyer->id,
            'type' => 'DEBIT',
            'source' => 'TEST',
            'reference' => 'ref-1',
        ]);

        $this->assertSame('40.00', (string) $buyer->wallet->fresh()->balance);
    }

    public function test_ledger_entries_are_immutable(): void
    {
        $buyerRole = Role::query()->where('slug', Role::SLUG_BUYER)->firstOrFail();
        $buyer = User::factory()->create([
            'role_id' => $buyerRole->id,
            'status' => 'active',
        ]);

        Wallet::query()->create([
            'user_id' => $buyer->id,
            'balance' => '10.00',
            'is_frozen' => false,
        ]);

        app(WalletService::class)->credit($buyer->id, '5.00', 'TEST', 'ref-c', null);

        $ledger = WalletLedger::query()->where('user_id', $buyer->id)->firstOrFail();

        $this->expectException(RuntimeException::class);
        $ledger->note = 'tampered';
        $ledger->save();
    }

    public function test_frozen_wallet_blocks_order(): void
    {
        $buyerRole = Role::query()->where('slug', Role::SLUG_BUYER)->firstOrFail();
        $buyer = User::factory()->create([
            'role_id' => $buyerRole->id,
            'status' => 'active',
        ]);

        Wallet::query()->create([
            'user_id' => $buyer->id,
            'balance' => '100.00',
            'is_frozen' => true,
        ]);

        $bundle = BundlePackage::query()->create([
            'agent_id' => null,
            'network' => 'MTN',
            'package_kind' => 'data',
            'name' => 'Frozen test',
            'size_label' => '1GB',
            'internal_cost' => '5.00',
            'stock_count' => 10,
            'is_available' => true,
        ]);

        $this->actingAs($buyer)->post(route('buyer.orders.store'), [
            'network' => 'MTN',
            'phone_number' => '0244123456',
            'bundle_package_id' => $bundle->id,
            'confirm' => true,
        ])->assertSessionHas('error');
    }

    public function test_paystack_webhook_credits_wallet(): void
    {
        config(['paystack.secret_key' => 'sk_test_secret']);

        $buyerRole = Role::query()->where('slug', Role::SLUG_BUYER)->firstOrFail();
        $buyer = User::factory()->create([
            'role_id' => $buyerRole->id,
            'status' => 'active',
            'agent_id' => null,
        ]);

        Wallet::query()->create([
            'user_id' => $buyer->id,
            'balance' => '0.00',
            'is_frozen' => false,
        ]);

        PaystackTransaction::query()->create([
            'user_id' => $buyer->id,
            'reference' => 'ref_webhook_unit',
            'amount' => '50.00',
            'status' => 'pending',
            'metadata' => ['kind' => PaystackPaymentPurpose::WALLET_TOPUP],
        ]);

        $payload = [
            'event' => 'charge.success',
            'data' => [
                'reference' => 'ref_webhook_unit',
                'amount' => 5000,
                'metadata' => [
                    'user_id' => $buyer->id,
                ],
                'channel' => 'card',
                'paid_at' => now()->toIso8601String(),
            ],
        ];

        $raw = json_encode($payload, JSON_THROW_ON_ERROR);
        $sig = hash_hmac('sha512', $raw, 'sk_test_secret');

        $this->call('POST', route('wallet.paystack.webhook'), [], [], [], [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_X-Paystack-Signature' => $sig,
        ], $raw)->assertOk();

        $this->assertSame('50.00', (string) $buyer->wallet->fresh()->balance);
    }

    public function test_paystack_webhook_credits_initialized_amount_not_paystack_surcharge(): void
    {
        config(['paystack.secret_key' => 'sk_test_secret']);

        $buyerRole = Role::query()->where('slug', Role::SLUG_BUYER)->firstOrFail();
        $buyer = User::factory()->create([
            'role_id' => $buyerRole->id,
            'status' => 'active',
            'agent_id' => null,
        ]);

        Wallet::query()->create([
            'user_id' => $buyer->id,
            'balance' => '18.35',
            'is_frozen' => false,
        ]);

        PaystackTransaction::query()->create([
            'user_id' => $buyer->id,
            'reference' => 'ref_topup_surcharge',
            'amount' => '400.00',
            'status' => 'pending',
            'metadata' => ['kind' => PaystackPaymentPurpose::WALLET_TOPUP],
        ]);

        $payload = [
            'event' => 'charge.success',
            'data' => [
                'reference' => 'ref_topup_surcharge',
                'amount' => 40796,
                'metadata' => [
                    'user_id' => $buyer->id,
                ],
                'channel' => 'mobile_money',
                'paid_at' => now()->toIso8601String(),
            ],
        ];

        $raw = json_encode($payload, JSON_THROW_ON_ERROR);
        $sig = hash_hmac('sha512', $raw, 'sk_test_secret');

        $this->call('POST', route('wallet.paystack.webhook'), [], [], [], [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_X-Paystack-Signature' => $sig,
        ], $raw)->assertOk();

        $this->assertSame('418.35', (string) $buyer->wallet->fresh()->balance);

        $ledger = WalletLedger::query()
            ->where('user_id', $buyer->id)
            ->where('reference', 'ref_topup_surcharge')
            ->firstOrFail();

        $this->assertSame('400.00', (string) $ledger->amount);
    }

    public function test_paystack_webhook_does_not_credit_agent_linked_buyer(): void
    {
        config(['paystack.secret_key' => 'sk_test_secret']);

        $buyerRole = Role::query()->where('slug', Role::SLUG_BUYER)->firstOrFail();
        $agentRole = Role::query()->where('slug', Role::SLUG_AGENT)->firstOrFail();

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
            'balance' => '0.00',
            'is_frozen' => false,
        ]);

        $payload = [
            'event' => 'charge.success',
            'data' => [
                'reference' => 'ref_webhook_agent_buyer',
                'amount' => 5000,
                'metadata' => [
                    'user_id' => $buyer->id,
                ],
                'channel' => 'mobile_money',
                'paid_at' => now()->toIso8601String(),
            ],
        ];

        $raw = json_encode($payload, JSON_THROW_ON_ERROR);
        $sig = hash_hmac('sha512', $raw, 'sk_test_secret');

        $this->call('POST', route('wallet.paystack.webhook'), [], [], [], [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_X-Paystack-Signature' => $sig,
        ], $raw)->assertOk();

        $this->assertSame('0.00', (string) $buyer->wallet->fresh()->balance);
    }

    public function test_paystack_webhook_completes_agent_shop_registration_when_metadata_omits_user_id(): void
    {
        config(['paystack.secret_key' => 'sk_test_secret']);
        PlatformSetting::set(PlatformSetting::KEY_AGENT_SHOP_REGISTRATION_FEE_GHS, '25.00');

        $agentRole = Role::query()->where('slug', Role::SLUG_AGENT)->firstOrFail();
        $agent = User::factory()->create([
            'role_id' => $agentRole->id,
            'status' => 'pending_payment',
            'shop_slug' => 'Xy9z0',
            'shop_name' => 'Pending Shop',
        ]);

        Wallet::query()->create([
            'user_id' => $agent->id,
            'balance' => '0.00',
            'is_frozen' => false,
        ]);

        PaystackTransaction::query()->create([
            'user_id' => $agent->id,
            'reference' => 'ref_agent_webhook_no_meta_uid',
            'amount' => '25.00',
            'status' => 'pending',
            'channel' => null,
            'paid_at' => null,
            'metadata' => [
                'kind' => 'agent_shop_registration',
                'initialized_at' => now()->toIso8601String(),
            ],
        ]);

        $payload = [
            'event' => 'charge.success',
            'data' => [
                'reference' => 'ref_agent_webhook_no_meta_uid',
                'amount' => 2500,
                'metadata' => [],
                'channel' => 'mobile_money',
                'paid_at' => now()->toIso8601String(),
            ],
        ];

        $raw = json_encode($payload, JSON_THROW_ON_ERROR);
        $sig = hash_hmac('sha512', $raw, 'sk_test_secret');

        $this->call('POST', route('wallet.paystack.webhook'), [], [], [], [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_X-Paystack-Signature' => $sig,
        ], $raw)->assertOk();

        $this->assertSame('pending', (string) $agent->fresh()->status);
        $this->assertDatabaseHas('paystack_transactions', [
            'reference' => 'ref_agent_webhook_no_meta_uid',
            'status' => 'success',
        ]);
        $this->assertFalse(WalletLedger::query()
            ->where('user_id', $agent->id)
            ->where('reference', 'ref_agent_webhook_no_meta_uid')
            ->where('source', 'PAYSTACK')
            ->exists());
    }

    public function test_wallet_topup_callback_without_login_credits_wallet_using_transaction_row(): void
    {
        config(['paystack.secret_key' => 'sk_test_secret', 'paystack.base_url' => 'https://api.paystack.co']);

        $buyerRole = Role::query()->where('slug', Role::SLUG_BUYER)->firstOrFail();
        $buyer = User::factory()->create([
            'role_id' => $buyerRole->id,
            'status' => 'active',
            'agent_id' => null,
        ]);

        Wallet::query()->create([
            'user_id' => $buyer->id,
            'balance' => '0.00',
            'is_frozen' => false,
        ]);

        PaystackTransaction::query()->create([
            'user_id' => $buyer->id,
            'reference' => 'ref_guest_topup_cb',
            'amount' => '30.00',
            'status' => 'pending',
            'metadata' => ['kind' => PaystackPaymentPurpose::WALLET_TOPUP],
        ]);

        Http::fake([
            'https://api.paystack.co/transaction/verify/*' => Http::response([
                'status' => true,
                'data' => [
                    'status' => 'success',
                    'reference' => 'ref_guest_topup_cb',
                    'amount' => 3000,
                    'metadata' => [
                        'user_id' => $buyer->id,
                        'type' => PaystackPaymentPurpose::WALLET_TOPUP,
                    ],
                    'paid_at' => now()->toIso8601String(),
                ],
            ]),
        ]);

        $this->get(route('wallet.topup.callback', ['reference' => 'ref_guest_topup_cb']))
            ->assertRedirect(route('login'))
            ->assertSessionHas('status');

        $this->assertSame('30.00', (string) $buyer->wallet->fresh()->balance);
    }

    public function test_repeat_webhook_for_completed_agent_registration_does_not_credit_wallet(): void
    {
        config(['paystack.secret_key' => 'sk_test_secret']);
        PlatformSetting::set(PlatformSetting::KEY_AGENT_SHOP_REGISTRATION_FEE_GHS, '25.00');

        $agentRole = Role::query()->where('slug', Role::SLUG_AGENT)->firstOrFail();
        $agent = User::factory()->create([
            'role_id' => $agentRole->id,
            'status' => 'pending',
            'shop_slug' => 'Ab12c',
        ]);

        Wallet::query()->create([
            'user_id' => $agent->id,
            'balance' => '0.00',
            'is_frozen' => false,
        ]);

        PaystackTransaction::query()->create([
            'user_id' => $agent->id,
            'reference' => 'ref_agent_repeat_wh',
            'amount' => '25.00',
            'status' => 'success',
            'paid_at' => now(),
            'metadata' => PaystackPaymentPurpose::metadataAfterSuccess(
                PaystackPaymentPurpose::AGENT_SHOP_REGISTRATION,
                ['status' => 'success', 'amount' => 2500],
            ),
        ]);

        $payload = [
            'event' => 'charge.success',
            'data' => [
                'reference' => 'ref_agent_repeat_wh',
                'amount' => 2500,
                'metadata' => ['user_id' => $agent->id],
            ],
        ];

        $raw = json_encode($payload, JSON_THROW_ON_ERROR);
        $sig = hash_hmac('sha512', $raw, 'sk_test_secret');

        $this->call('POST', route('wallet.paystack.webhook'), [], [], [], [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_X-Paystack-Signature' => $sig,
        ], $raw)->assertOk();

        $this->assertSame('0.00', (string) $agent->wallet->fresh()->balance);
        $this->assertFalse(WalletLedger::query()->where('user_id', $agent->id)->where('source', 'PAYSTACK')->exists());
    }
}
