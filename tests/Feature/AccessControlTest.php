<?php

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class AccessControlTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    private function userWith(string ...$permissions): User
    {
        $user = User::factory()->create();
        $user->givePermissionTo($permissions);

        return $user;
    }

    private function superAdmin(): User
    {
        $user = User::factory()->create();
        $user->assignRole(config('cms.super_admin_role'));

        return $user;
    }

    public function test_guest_is_rejected(): void
    {
        $this->getJson(route('cms.admin.users.index'))->assertUnauthorized();
    }

    public function test_user_without_permission_gets_403(): void
    {
        $this->actingAs(User::factory()->create())
            ->getJson(route('cms.admin.users.index'))
            ->assertForbidden();
    }

    public function test_view_permission_allows_listing_but_not_deleting(): void
    {
        $actor = $this->userWith('users.view');
        $other = User::factory()->create();

        $this->actingAs($actor)->getJson(route('cms.admin.users.index'))->assertOk();
        $this->actingAs($actor)->deleteJson(route('cms.admin.users.destroy', $other))->assertForbidden();
    }

    public function test_super_admin_passes_every_gate(): void
    {
        $this->actingAs($this->superAdmin())
            ->getJson(route('cms.admin.roles.index'))
            ->assertOk();
    }

    public function test_user_cannot_delete_themselves(): void
    {
        $actor = $this->userWith('users.delete');

        $this->actingAs($actor)
            ->deleteJson(route('cms.admin.users.destroy', $actor))
            ->assertStatus(409);

        $this->assertNotSoftDeleted($actor);
    }

    public function test_non_super_admin_cannot_delete_a_super_admin(): void
    {
        $target = $this->superAdmin();

        $this->actingAs($this->userWith('users.delete'))
            ->deleteJson(route('cms.admin.users.destroy', $target))
            ->assertStatus(409);

        $this->assertNotSoftDeleted($target);
    }

    public function test_assign_roles_is_ignored_without_the_permission(): void
    {
        $role = Role::create(['name' => 'editor']);
        $actor = $this->userWith('users.create'); // بدون users.assign-roles

        $this->actingAs($actor)->postJson(route('cms.admin.users.store'), [
            'name' => 'New',
            'email' => 'new@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'status' => 'active',
            'roles' => [$role->id],
        ])->assertOk();

        $this->assertFalse(User::where('email', 'new@example.com')->first()->hasRole('editor'));
    }

    public function test_non_super_admin_cannot_assign_super_admin_role(): void
    {
        $superRoleId = Role::where('name', config('cms.super_admin_role'))->value('id');
        $actor = $this->userWith('users.create', 'users.assign-roles');

        $this->actingAs($actor)->postJson(route('cms.admin.users.store'), [
            'name' => 'New',
            'email' => 'new@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'status' => 'active',
            'roles' => [$superRoleId],
        ])->assertUnprocessable()->assertJsonValidationErrors('roles.0');
    }

    public function test_last_super_admin_cannot_lose_the_role(): void
    {
        $only = $this->superAdmin();

        $this->actingAs($only)->putJson(route('cms.admin.users.update', $only), [
            'name' => $only->name,
            'email' => $only->email,
            'status' => 'active',
            'roles' => [],
        ])->assertStatus(409);

        $this->assertTrue($only->fresh()->hasRole(config('cms.super_admin_role')));
    }
}
