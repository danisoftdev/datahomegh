<?php

namespace Tests\Feature;

use App\Models\BundlePackage;
use App\Models\Role;
use App\Models\User;
use App\Models\Wallet;
use App\Models\WalletLedger;
use App\Services\WalletService;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
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
}
