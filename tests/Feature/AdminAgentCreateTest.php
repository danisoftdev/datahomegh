<?php

namespace Tests\Feature;

use App\Mail\AgentAccountApprovedMail;
use App\Models\Role;
use App\Models\User;
use App\Models\Wallet;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class AdminAgentCreateTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);
    }

    public function test_supplier_can_create_agent_from_admin_form(): void
    {
        Mail::fake();

        $supplierRole = Role::query()->where('slug', Role::SLUG_SUPPLIER)->firstOrFail();
        $supplier = User::factory()->create([
            'role_id' => $supplierRole->id,
            'username' => 'supplier1',
            'status' => 'active',
        ]);

        $response = $this->actingAs($supplier)->post(route('admin.users.store-agent'), [
            'username' => 'newagent',
            'name' => 'New Agent',
            'email' => 'newagent@example.com',
            'phone' => '0244111222',
            'shop_name' => 'Cool Data Shop',
            'password' => 'Password123!',
            'password_confirmation' => 'Password123!',
        ]);

        $response->assertRedirect();

        $agent = User::query()->where('username', 'newagent')->firstOrFail();
        $this->assertSame(Role::SLUG_AGENT, $agent->role?->slug);
        $this->assertSame('active', $agent->status);
        $this->assertNotNull($agent->shop_slug);
        $this->assertSame('Cool Data Shop', $agent->shop_name);
        $this->assertTrue($agent->wallet()->exists());

        Mail::assertSent(AgentAccountApprovedMail::class, function (AgentAccountApprovedMail $mail) use ($agent): bool {
            return $mail->user->is($agent) && $mail->shopSlug === $agent->shop_slug;
        });
    }

    public function test_non_supplier_cannot_create_agent(): void
    {
        $buyerRole = Role::query()->where('slug', Role::SLUG_BUYER)->firstOrFail();
        $buyer = User::factory()->create([
            'role_id' => $buyerRole->id,
            'status' => 'active',
        ]);
        Wallet::query()->create([
            'user_id' => $buyer->id,
            'balance' => 0,
            'is_frozen' => false,
        ]);

        $this->actingAs($buyer)->get(route('admin.users.create-agent'))->assertRedirect('/login');
    }
}
