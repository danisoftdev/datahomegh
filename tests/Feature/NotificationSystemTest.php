<?php

namespace Tests\Feature;

use App\Models\Notification;
use App\Models\Role;
use App\Models\User;
use App\Notifications\BroadcastAnnouncementNotification;
use App\Services\NotificationService;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification as NotificationFacade;
use Tests\TestCase;

class NotificationSystemTest extends TestCase
{
    use RefreshDatabase;

    public function test_notify_creates_personal_notification(): void
    {
        $role = Role::factory()->create(['slug' => Role::SLUG_BUYER]);
        $user = User::factory()->create(['role_id' => $role->id]);

        $svc = app(NotificationService::class);
        $svc->notify($user->id, 'T', 'M', 'order');

        $this->assertDatabaseCount('notifications', 1);
        $this->assertSame(1, $svc->unreadCount($user->id, Role::SLUG_BUYER));
    }

    public function test_broadcast_with_roles_targets_audience_only(): void
    {
        NotificationFacade::fake();

        $buyerRole = Role::factory()->create(['slug' => Role::SLUG_BUYER]);
        $agentRole = Role::factory()->create(['slug' => Role::SLUG_AGENT]);
        $buyer = User::factory()->create(['role_id' => $buyerRole->id]);
        $agent = User::factory()->create(['role_id' => $agentRole->id]);

        $svc = app(NotificationService::class);
        $svc->broadcast('Hi', 'Body', [Role::SLUG_BUYER], 'broadcast');

        $buyerVisible = Notification::query()->forUser($buyer->id, Role::SLUG_BUYER)->count();
        $agentVisible = Notification::query()->forUser($agent->id, Role::SLUG_AGENT)->count();

        $this->assertSame(1, $buyerVisible);
        $this->assertSame(0, $agentVisible);

        NotificationFacade::assertSentTo($buyer, BroadcastAnnouncementNotification::class);
        NotificationFacade::assertNotSentTo($agent, BroadcastAnnouncementNotification::class);
    }

    public function test_mark_read_clears_unread(): void
    {
        $role = Role::factory()->create(['slug' => Role::SLUG_BUYER]);
        $user = User::factory()->create(['role_id' => $role->id]);

        $svc = app(NotificationService::class);
        $svc->notify($user->id, 'T', 'M', 'x');

        $this->assertSame(1, $svc->unreadCount($user->id, Role::SLUG_BUYER));

        $svc->markRead($user->id, Role::SLUG_BUYER);

        $this->assertSame(0, $svc->unreadCount($user->id, Role::SLUG_BUYER));
    }

    public function test_notification_routes_require_auth(): void
    {
        $this->getJson(route('notifications.unread-count'))->assertUnauthorized();
    }

    public function test_supplier_inbox_excludes_broadcast_rows(): void
    {
        NotificationFacade::fake();
        $this->seed(RolesAndPermissionsSeeder::class);
        $supplierRole = Role::query()->where('slug', Role::SLUG_SUPPLIER)->firstOrFail();
        $buyerRole = Role::query()->where('slug', Role::SLUG_BUYER)->firstOrFail();
        $supplier = User::factory()->create(['role_id' => $supplierRole->id, 'status' => 'active']);
        $buyer = User::factory()->create(['role_id' => $buyerRole->id, 'status' => 'active']);

        $svc = app(NotificationService::class);
        $svc->broadcast('All', 'Hello', [], 'broadcast');

        $supplierVisible = Notification::query()->forUser((int) $supplier->id, Role::SLUG_SUPPLIER)->count();
        $buyerVisible = Notification::query()->forUser((int) $buyer->id, Role::SLUG_BUYER)->count();

        $this->assertSame(0, $supplierVisible);
        $this->assertSame(1, $buyerVisible);
    }

    public function test_global_broadcast_emails_active_buyers_and_agents_with_valid_email(): void
    {
        NotificationFacade::fake();
        $this->seed(RolesAndPermissionsSeeder::class);
        $buyerRole = Role::query()->where('slug', Role::SLUG_BUYER)->firstOrFail();
        $agentRole = Role::query()->where('slug', Role::SLUG_AGENT)->firstOrFail();
        $buyer = User::factory()->create([
            'role_id' => $buyerRole->id,
            'status' => 'active',
            'email' => 'buyer-broadcast@example.com',
        ]);
        $agent = User::factory()->create([
            'role_id' => $agentRole->id,
            'status' => 'active',
            'email' => 'agent-broadcast@example.com',
            'shop_slug' => 'shop-'.uniqid(),
        ]);

        app(NotificationService::class)->broadcast('Subject', 'Hello everyone', [], 'broadcast');

        NotificationFacade::assertSentTo($buyer, BroadcastAnnouncementNotification::class);
        NotificationFacade::assertSentTo($agent, BroadcastAnnouncementNotification::class);
    }

    public function test_broadcast_does_not_email_invalid_or_missing_addresses(): void
    {
        NotificationFacade::fake();
        $buyerRole = Role::factory()->create(['slug' => Role::SLUG_BUYER]);
        User::factory()->create(['role_id' => $buyerRole->id, 'email' => null, 'status' => 'active']);
        User::factory()->create(['role_id' => $buyerRole->id, 'email' => 'not-an-email', 'status' => 'active']);

        app(NotificationService::class)->broadcast('T', 'M', [Role::SLUG_BUYER], 'x');

        NotificationFacade::assertNothingSent();
    }

    public function test_admin_can_delete_entire_broadcast_group(): void
    {
        NotificationFacade::fake();
        $this->seed(RolesAndPermissionsSeeder::class);
        $supplierRole = Role::query()->where('slug', Role::SLUG_SUPPLIER)->firstOrFail();
        $buyerRole = Role::query()->where('slug', Role::SLUG_BUYER)->firstOrFail();
        $supplier = User::factory()->create(['role_id' => $supplierRole->id, 'status' => 'active']);
        User::factory()->create(['role_id' => $buyerRole->id, 'status' => 'active']);

        $svc = app(NotificationService::class);
        $svc->broadcast('T', 'M', [Role::SLUG_BUYER], 'x');

        $this->assertGreaterThan(1, Notification::query()->count());

        $any = Notification::query()->whereNotNull('broadcast_group_id')->firstOrFail();
        $gid = $any->broadcast_group_id;

        $this->actingAs($supplier)->delete(route('admin.notifications.destroy', $any))->assertRedirect();

        $this->assertSame(0, Notification::query()->where('broadcast_group_id', $gid)->count());
    }
}
