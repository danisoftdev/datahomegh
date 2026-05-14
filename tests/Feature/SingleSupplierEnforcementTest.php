<?php

namespace Tests\Feature;

use App\Models\Role;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use RuntimeException;
use Tests\TestCase;

class SingleSupplierEnforcementTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);
    }

    public function test_cannot_create_second_supplier_user(): void
    {
        $supplierRole = Role::query()->where('slug', Role::SLUG_SUPPLIER)->firstOrFail();

        User::factory()->create([
            'role_id' => $supplierRole->id,
            'username' => 'first_supplier',
            'status' => 'active',
        ]);

        $this->expectException(RuntimeException::class);

        User::factory()->create([
            'role_id' => $supplierRole->id,
            'username' => 'second_supplier',
            'status' => 'active',
        ]);
    }

    public function test_supplier_can_open_account_settings(): void
    {
        $supplierRole = Role::query()->where('slug', Role::SLUG_SUPPLIER)->firstOrFail();
        $supplier = User::factory()->create([
            'role_id' => $supplierRole->id,
            'status' => 'active',
        ]);

        $this->actingAs($supplier)->get(route('admin.profile.edit'))->assertOk();
    }

    public function test_buyer_can_open_profile_edit(): void
    {
        $buyerRole = Role::query()->where('slug', Role::SLUG_BUYER)->firstOrFail();
        $buyer = User::factory()->create([
            'role_id' => $buyerRole->id,
            'status' => 'active',
        ]);

        $this->actingAs($buyer)->get(route('buyer.profile.edit'))->assertOk();
    }
}
