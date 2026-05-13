<?php

namespace Tests\Feature;

use App\Models\Notification;
use App\Models\Role;
use App\Models\User;
use App\Services\NotificationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
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
}
