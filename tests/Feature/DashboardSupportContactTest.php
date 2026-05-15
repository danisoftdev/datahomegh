<?php

namespace Tests\Feature;

use App\Models\Role;
use App\Models\User;
use App\Models\Wallet;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DashboardSupportContactTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);
    }

    public function test_buyer_without_agent_sees_supplier_whatsapp_on_dashboard(): void
    {
        $supplierRole = Role::query()->where('slug', Role::SLUG_SUPPLIER)->firstOrFail();
        $buyerRole = Role::query()->where('slug', Role::SLUG_BUYER)->firstOrFail();

        User::factory()->create([
            'role_id' => $supplierRole->id,
            'username' => 'supplier_wa',
            'status' => 'active',
            'phone' => '0200000001',
            'whatsapp_number' => '0551111111',
            'whatsapp_channel' => null,
        ]);

        $buyer = User::factory()->create([
            'role_id' => $buyerRole->id,
            'username' => 'buyer_no_agent',
            'status' => 'active',
            'agent_id' => null,
            'phone' => '0200000002',
        ]);
        Wallet::query()->create([
            'user_id' => $buyer->id,
            'balance' => '10.00',
            'is_frozen' => false,
        ]);

        $response = $this->actingAs($buyer)->get(route('buyer.dashboard'));
        $response->assertOk();
        $response->assertSee('wa.me/233551111111', false);
        $response->assertSeeText(__('Platform support'));
        $response->assertSeeText(__('WhatsApp chat'));
    }

    public function test_buyer_linked_to_agent_sees_agent_whatsapp_not_supplier(): void
    {
        $supplierRole = Role::query()->where('slug', Role::SLUG_SUPPLIER)->firstOrFail();
        $agentRole = Role::query()->where('slug', Role::SLUG_AGENT)->firstOrFail();
        $buyerRole = Role::query()->where('slug', Role::SLUG_BUYER)->firstOrFail();

        User::factory()->create([
            'role_id' => $supplierRole->id,
            'username' => 'supplier_two',
            'status' => 'active',
            'phone' => '0200000003',
            'whatsapp_number' => '0552222222',
        ]);

        $agent = User::factory()->create([
            'role_id' => $agentRole->id,
            'username' => 'agent_shop',
            'status' => 'active',
            'phone' => '0200000004',
            'whatsapp_number' => '0553333333',
            'shop_slug' => 'agnt1',
        ]);

        $buyer = User::factory()->create([
            'role_id' => $buyerRole->id,
            'username' => 'buyer_with_agent',
            'status' => 'active',
            'agent_id' => $agent->id,
            'phone' => '0200000005',
        ]);
        Wallet::query()->create([
            'user_id' => $buyer->id,
            'balance' => '10.00',
            'is_frozen' => false,
        ]);

        $response = $this->actingAs($buyer)->get(route('buyer.dashboard'));
        $response->assertOk();
        $response->assertSee('wa.me/233553333333', false);
        $response->assertDontSee('wa.me/233552222222', false);
        $response->assertSeeText(__('Your agent & shop'));
    }

    public function test_buyer_linked_to_agent_falls_back_to_supplier_when_agent_has_no_contact_links(): void
    {
        $supplierRole = Role::query()->where('slug', Role::SLUG_SUPPLIER)->firstOrFail();
        $agentRole = Role::query()->where('slug', Role::SLUG_AGENT)->firstOrFail();
        $buyerRole = Role::query()->where('slug', Role::SLUG_BUYER)->firstOrFail();

        User::factory()->create([
            'role_id' => $supplierRole->id,
            'username' => 'supplier_fb',
            'status' => 'active',
            'phone' => '0200000006',
            'whatsapp_number' => '0554444444',
        ]);

        $agent = User::factory()->create([
            'role_id' => $agentRole->id,
            'username' => 'agent_no_wa',
            'status' => 'active',
            'phone' => '0200000010',
            'whatsapp_number' => null,
            'whatsapp_channel' => null,
            'shop_slug' => 'agnt2',
        ]);

        $buyer = User::factory()->create([
            'role_id' => $buyerRole->id,
            'username' => 'buyer_fb',
            'status' => 'active',
            'agent_id' => $agent->id,
            'phone' => '0200000007',
        ]);
        Wallet::query()->create([
            'user_id' => $buyer->id,
            'balance' => '10.00',
            'is_frozen' => false,
        ]);

        $response = $this->actingAs($buyer)->get(route('buyer.dashboard'));
        $response->assertOk();
        $response->assertSee('wa.me/233554444444', false);
        $response->assertSeeText(__('Platform support'));
    }

    public function test_agent_dashboard_shows_supplier_platform_support(): void
    {
        $supplierRole = Role::query()->where('slug', Role::SLUG_SUPPLIER)->firstOrFail();
        $agentRole = Role::query()->where('slug', Role::SLUG_AGENT)->firstOrFail();

        User::factory()->create([
            'role_id' => $supplierRole->id,
            'username' => 'supplier_agent_dash',
            'status' => 'active',
            'phone' => '0200000008',
            'whatsapp_channel' => 'https://example.com/wa-channel',
        ]);

        $agent = User::factory()->create([
            'role_id' => $agentRole->id,
            'username' => 'agent_dash',
            'status' => 'active',
            'phone' => '0200000009',
            'shop_slug' => 'agnt3',
        ]);

        $response = $this->actingAs($agent)->get(route('agent.dashboard'));
        $response->assertOk();
        $response->assertSee('https://example.com/wa-channel', false);
        $response->assertSeeText(__('Platform support'));
        $response->assertSeeText(__('WhatsApp channel'));
    }
}
