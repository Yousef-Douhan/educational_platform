<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class RoleCrudTest extends TestCase
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
        $this->get(route('cms.admin.roles.index'))->assertOk()->assertViewIs('cms.roles.index');
    }

    public function test_index_json_returns_real_stats(): void
    {
        Role::create(['name' => 'empty']);
        Role::create(['name' => 'editor'])->givePermissionTo(Permission::create(['name' => 'posts.edit']));

        $this->getJson(route('cms.admin.roles.index'))
            ->assertOk()
            ->assertJsonPath('stats.total', 2)
            ->assertJsonPath('stats.with_permissions', 1)
            ->assertJsonPath('stats.without_permissions', 1)
            ->assertJsonPath('stats.permissions', 1)
            ->assertJsonStructure(['html', 'stats']);
    }

    public function test_index_search_filters_results(): void
    {
        Role::create(['name' => 'zebra-admin']);
        Role::create(['name' => 'quokka-user']);

        $html = $this->getJson(route('cms.admin.roles.index', ['search' => 'zebra']))->json('html');

        $this->assertStringContainsString('zebra-admin', $html);
        $this->assertStringNotContainsString('quokka-user', $html);
    }

    public function test_index_ignores_invalid_sort_column(): void
    {
        Role::create(['name' => 'editor']);

        $this->getJson(route('cms.admin.roles.index', ['sort' => 'name; drop table roles']))->assertOk();
    }

    public function test_create_returns_grouped_permissions_and_guards(): void
    {
        Permission::create(['name' => 'users.view']);
        Permission::create(['name' => 'users.edit']);
        Permission::create(['name' => 'dashboard']);

        $groups = collect($this->getJson(route('cms.admin.roles.create'))->assertOk()->json('groups'))->pluck('group');

        $this->assertTrue($groups->contains('users'));
        $this->assertTrue($groups->contains('general'));
    }

    public function test_store_creates_role_with_permissions(): void
    {
        $perm = Permission::create(['name' => 'users.view']);

        $this->postJson(route('cms.admin.roles.store'), ['name' => 'editor', 'permissions' => [$perm->id]])
            ->assertCreated();

        $this->assertTrue(Role::findByName('editor')->hasPermissionTo('users.view'));
    }

    public function test_store_requires_name(): void
    {
        $this->postJson(route('cms.admin.roles.store'), [])->assertUnprocessable()->assertJsonValidationErrors('name');
    }

    public function test_store_rejects_duplicate_name_for_same_guard(): void
    {
        Role::create(['name' => 'editor']);

        $this->postJson(route('cms.admin.roles.store'), ['name' => 'editor'])
            ->assertUnprocessable()->assertJsonValidationErrors('name');
    }

    public function test_store_rejects_unknown_permission_id(): void
    {
        $this->postJson(route('cms.admin.roles.store'), ['name' => 'x', 'permissions' => [9999]])
            ->assertUnprocessable()->assertJsonValidationErrors('permissions.0');
    }

    public function test_show_returns_role_with_permissions(): void
    {
        $role = Role::create(['name' => 'editor']);
        $role->givePermissionTo(Permission::create(['name' => 'posts.edit']));

        $this->getJson(route('cms.admin.roles.show', $role))
            ->assertOk()
            ->assertJsonPath('data.name', 'editor')
            ->assertJsonPath('data.permissions.0.name', 'posts.edit');
    }

    public function test_edit_returns_selected_permission_ids(): void
    {
        $role = Role::create(['name' => 'editor']);
        $perm = Permission::create(['name' => 'posts.edit']);
        $role->givePermissionTo($perm);

        $this->getJson(route('cms.admin.roles.edit', $role))
            ->assertOk()
            ->assertJsonPath('permission_ids.0', $perm->id);
    }

    public function test_update_renames_and_syncs_permissions(): void
    {
        $role = Role::create(['name' => 'editor']);
        $role->givePermissionTo(Permission::create(['name' => 'posts.edit']));
        $new = Permission::create(['name' => 'posts.delete']);

        $this->putJson(route('cms.admin.roles.update', $role), ['name' => 'moderator', 'permissions' => [$new->id]])
            ->assertOk();

        $role->refresh();
        $this->assertSame('moderator', $role->name);
        $this->assertEquals(['posts.delete'], $role->permissions->pluck('name')->all());
    }

    public function test_update_can_remove_all_permissions(): void
    {
        $role = Role::create(['name' => 'editor']);
        $role->givePermissionTo(Permission::create(['name' => 'posts.edit']));

        $this->putJson(route('cms.admin.roles.update', $role), ['name' => 'editor'])->assertOk();

        $this->assertCount(0, $role->fresh()->permissions);
    }

    public function test_update_allows_same_name_but_rejects_another_roles_name(): void
    {
        $a = Role::create(['name' => 'a']);
        Role::create(['name' => 'b']);

        $this->putJson(route('cms.admin.roles.update', $a), ['name' => 'a'])->assertOk();
        $this->putJson(route('cms.admin.roles.update', $a), ['name' => 'b'])
            ->assertUnprocessable()->assertJsonValidationErrors('name');
    }

    public function test_destroy_deletes_role_and_pivot_rows(): void
    {
        $role = Role::create(['name' => 'editor']);
        $role->givePermissionTo(Permission::create(['name' => 'posts.edit']));

        $this->deleteJson(route('cms.admin.roles.destroy', $role))->assertOk();

        $this->assertDatabaseMissing('roles', ['id' => $role->id]);
        $this->assertDatabaseMissing('role_has_permissions', ['role_id' => $role->id]);
    }

    public function test_destroy_is_blocked_when_role_is_assigned_to_a_user(): void
    {
        $role = Role::create(['name' => 'editor']);
        User::factory()->create()->assignRole($role);

        $this->deleteJson(route('cms.admin.roles.destroy', $role))->assertStatus(409);

        $this->assertDatabaseHas('roles', ['id' => $role->id]);
    }
}
