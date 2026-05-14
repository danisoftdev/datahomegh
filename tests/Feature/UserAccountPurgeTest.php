<?php

namespace Tests\Feature;

use App\Models\Role;
use App\Models\User;
use App\Models\Wallet;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UserAccountPurgeTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_delete_buyer_hard_deletes_and_username_can_register_again(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);
        $supplierRole = Role::query()->where('slug', Role::SLUG_SUPPLIER)->firstOrFail();
        $buyerRole = Role::query()->where('slug', Role::SLUG_BUYER)->firstOrFail();
        $supplier = User::factory()->create(['role_id' => $supplierRole->id, 'status' => 'active']);
        $buyer = User::factory()->create([
            'role_id' => $buyerRole->id,
            'username' => 'reuse_me',
            'phone' => '0244111222',
            'status' => 'active',
        ]);
        Wallet::query()->create([
            'user_id' => $buyer->id,
            'balance' => 0,
            'is_frozen' => false,
        ]);

        $this->actingAs($supplier)->delete(route('admin.users.destroy', $buyer))->assertRedirect();

        $this->assertDatabaseMissing('users', ['id' => $buyer->id]);
        $this->assertNull(User::withTrashed()->find($buyer->id));

        $this->post(route('logout'));

        $this->post(route('register'), [
            'account_type' => 'buyer',
            'username' => 'reuse_me',
            'name' => 'Again',
            'phone' => '0244333444',
            'password' => 'Password123!',
            'password_confirmation' => 'Password123!',
        ]);

        $this->assertAuthenticated();
        $this->assertDatabaseHas('users', ['username' => 'reuse_me', 'name' => 'Again']);
    }

    public function test_buyer_can_delete_own_account_and_register_again_with_same_username(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);
        $buyerRole = Role::query()->where('slug', Role::SLUG_BUYER)->firstOrFail();
        $user = User::factory()->create([
            'role_id' => $buyerRole->id,
            'username' => 'selfpurge',
            'password' => 'Password123!',
            'status' => 'active',
        ]);
        Wallet::query()->create([
            'user_id' => $user->id,
            'balance' => 0,
            'is_frozen' => false,
        ]);

        $this->assertSame(1, User::query()->count());

        $oldId = $user->id;

        $response = $this->actingAs($user)->from(route('buyer.profile.edit'))->post(route('buyer.profile.destroy'), [
            'current_password' => 'Password123!',
            'delete_account' => '1',
        ]);
        $response->assertSessionDoesntHaveErrors();
        $response->assertSessionHas('status');
        $response->assertRedirect(route('login'));

        $this->assertGuest();
        $this->assertSame(0, User::query()->count());
        $this->assertFalse(\Illuminate\Support\Facades\DB::table('users')->where('id', $oldId)->exists());

        $this->post(route('register'), [
            'account_type' => 'buyer',
            'username' => 'selfpurge',
            'name' => 'Back again',
            'phone' => '0244555666',
            'password' => 'Password123!',
            'password_confirmation' => 'Password123!',
        ]);

        $this->assertAuthenticated();
        $this->assertDatabaseHas('users', ['username' => 'selfpurge', 'name' => 'Back again']);
    }

    public function test_purge_service_force_deletes_user_row(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);
        $buyerRole = Role::query()->where('slug', Role::SLUG_BUYER)->firstOrFail();
        $user = User::factory()->create([
            'role_id' => $buyerRole->id,
            'username' => 'purge_only',
            'status' => 'active',
        ]);
        Wallet::query()->create([
            'user_id' => $user->id,
            'balance' => 0,
            'is_frozen' => false,
        ]);
        $id = $user->id;

        app(\App\Services\UserAccountPurgeService::class)->permanentlyDelete($user);

        $this->assertNull(User::query()->find($id));
    }
}
