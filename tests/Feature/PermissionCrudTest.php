<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class PermissionCrudTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        app(PermissionRegistrar::class)->forgetCachedPermissions();
        $this->actingAs(User::factory()->create());
    }

    public function test_index_page_renders(): void
    {
        $this->get(route('cms.admin.permissions.index'))->assertOk()->assertViewIs('cms.permissions.index');
    }

    public function test_index_json_returns_real_stats(): void
    {
        $used = Permission::create(['name' => 'a.view']);
        Permission::create(['name' => 'a.edit']);
        Role::create(['name' => 'r'])->givePermissionTo($used);

        $this->getJson(route('cms.admin.permissions.index'))
            ->assertOk()
            ->assertJsonPath('stats.total', 2)
            ->assertJsonPath('stats.used', 1)
            ->assertJsonPath('stats.unused', 1)
            ->assertJsonPath('stats.roles', 1);
    }

    public function test_index_filters_by_role(): void
    {
        $role = Role::create(['name' => 'editor']);
        $role->givePermissionTo(Permission::create(['name' => 'posts.edit']));
        Permission::create(['name' => 'orders.view']);

        $html = $this->getJson(route('cms.admin.permissions.index', ['role_id' => $role->id]))->json('html');

        $this->assertStringContainsString('posts.edit', $html);
        $this->assertStringNotContainsString('orders.view', $html);
    }

    public function test_store_creates_permission_with_default_guard(): void
    {
        $this->postJson(route('cms.admin.permissions.store'), ['name' => 'users.create'])->assertCreated();

        $this->assertDatabaseHas('permissions', ['name' => 'users.create', 'guard_name' => 'web']);
    }

    public function test_store_requires_name_and_rejects_duplicates(): void
    {
        $this->postJson(route('cms.admin.permissions.store'), [])->assertUnprocessable()->assertJsonValidationErrors('name');

        Permission::create(['name' => 'users.create']);
        $this->postJson(route('cms.admin.permissions.store'), ['name' => 'users.create'])
            ->assertUnprocessable()->assertJsonValidationErrors('name');
    }

    public function test_store_rejects_unknown_guard(): void
    {
        $this->postJson(route('cms.admin.permissions.store'), ['name' => 'x', 'guard_name' => 'nope'])
            ->assertUnprocessable()->assertJsonValidationErrors('guard_name');
    }

    public function test_show_lists_roles_using_the_permission(): void
    {
        $perm = Permission::create(['name' => 'posts.edit']);
        Role::create(['name' => 'editor'])->givePermissionTo($perm);

        $this->getJson(route('cms.admin.permissions.show', $perm))
            ->assertOk()
            ->assertJsonPath('roles.0.name', 'editor');
    }

    public function test_update_renames_permission(): void
    {
        $perm = Permission::create(['name' => 'old.name']);

        $this->putJson(route('cms.admin.permissions.update', $perm), ['name' => 'new.name'])->assertOk();

        $this->assertDatabaseHas('permissions', ['id' => $perm->id, 'name' => 'new.name']);
    }

    public function test_update_rejects_name_of_another_permission(): void
    {
        $a = Permission::create(['name' => 'a']);
        Permission::create(['name' => 'b']);

        $this->putJson(route('cms.admin.permissions.update', $a), ['name' => 'b'])
            ->assertUnprocessable()->assertJsonValidationErrors('name');
    }

    public function test_destroy_deletes_unused_permission(): void
    {
        $perm = Permission::create(['name' => 'tmp']);

        $this->deleteJson(route('cms.admin.permissions.destroy', $perm))->assertOk();

        $this->assertDatabaseMissing('permissions', ['id' => $perm->id]);
    }

    public function test_destroy_is_blocked_when_used_by_a_role(): void
    {
        $perm = Permission::create(['name' => 'posts.edit']);
        Role::create(['name' => 'editor'])->givePermissionTo($perm);

        $this->deleteJson(route('cms.admin.permissions.destroy', $perm))->assertStatus(409);

        $this->assertDatabaseHas('permissions', ['id' => $perm->id]);
    }
}
