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

class AdminAgentApprovalTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);
    }

    public function test_approve_pending_agent_sends_email_when_email_present(): void
    {
        Mail::fake();

        $supplierRole = Role::query()->where('slug', Role::SLUG_SUPPLIER)->firstOrFail();
        $agentRole = Role::query()->where('slug', Role::SLUG_AGENT)->firstOrFail();

        $supplier = User::factory()->create([
            'role_id' => $supplierRole->id,
            'username' => 'adminuser',
            'status' => 'active',
        ]);

        $pending = User::factory()->create([
            'role_id' => $agentRole->id,
            'username' => 'pendingagent',
            'status' => 'pending',
            'shop_name' => 'Pending Shop',
            'email' => 'agent-mail@example.com',
        ]);

        Wallet::query()->create([
            'user_id' => $pending->id,
            'balance' => 0,
            'is_frozen' => false,
        ]);

        $response = $this->actingAs($supplier)->post(route('admin.users.approve-agent', $pending));

        $response->assertSessionHas('status');
        $pending->refresh();
        $this->assertSame('active', $pending->status);
        $this->assertNotNull($pending->shop_slug);

        Mail::assertSent(AgentAccountApprovedMail::class, function (AgentAccountApprovedMail $mail) use ($pending): bool {
            return $mail->hasTo('agent-mail@example.com')
                && $mail->user->is($pending);
        });
    }

    public function test_approve_fails_when_agent_has_no_email(): void
    {
        Mail::fake();

        $supplierRole = Role::query()->where('slug', Role::SLUG_SUPPLIER)->firstOrFail();
        $agentRole = Role::query()->where('slug', Role::SLUG_AGENT)->firstOrFail();

        $supplier = User::factory()->create([
            'role_id' => $supplierRole->id,
            'status' => 'active',
        ]);

        $pending = User::factory()->create([
            'role_id' => $agentRole->id,
            'status' => 'pending',
            'shop_name' => 'No Email Shop',
            'email' => null,
        ]);

        Wallet::query()->create([
            'user_id' => $pending->id,
            'balance' => 0,
            'is_frozen' => false,
        ]);

        $response = $this->actingAs($supplier)->post(route('admin.users.approve-agent', $pending));

        $response->assertSessionHas('error');
        $this->assertSame('pending', $pending->fresh()->status);
        Mail::assertNothingSent();
    }

    public function test_decline_sets_status_declined(): void
    {
        $supplierRole = Role::query()->where('slug', Role::SLUG_SUPPLIER)->firstOrFail();
        $agentRole = Role::query()->where('slug', Role::SLUG_AGENT)->firstOrFail();

        $supplier = User::factory()->create([
            'role_id' => $supplierRole->id,
            'status' => 'active',
        ]);

        $pending = User::factory()->create([
            'role_id' => $agentRole->id,
            'status' => 'pending',
            'shop_name' => 'Decline Shop',
            'email' => 'x@example.com',
        ]);

        Wallet::query()->create([
            'user_id' => $pending->id,
            'balance' => 0,
            'is_frozen' => false,
        ]);

        $this->actingAs($supplier)->post(route('admin.users.decline-agent', $pending))
            ->assertSessionHas('status');

        $this->assertSame('declined', $pending->fresh()->status);
    }
}
