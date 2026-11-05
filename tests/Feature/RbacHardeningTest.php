<?php

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Passport\Passport;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

/**
 * إدارة الأدوار والصلاحيات: كانت مسارات roles وpermissions في الـCMS بلا فحص صلاحية إطلاقًا،
 * ومنطق الأدوار يعتمد على الحارس الافتراضي (يتحول إلى api في طلبات Passport).
 */
class RbacHardeningTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    private function staff(array $perms): User
    {
        $u = User::factory()->create();
        foreach ($perms as $p) {
            $u->givePermissionTo(Permission::findOrCreate($p, 'web'));
        }

        return $u;
    }

    /** مدير أعلى حقيقي: الدور وصلاحياته كما يزرعها الـseeder */
    private function superAdmin(): User
    {
        $this->seed(RolesAndPermissionsSeeder::class);
        $u = User::factory()->create();
        $u->assignRole(Role::findByName('super-admin', 'web'));

        return $u;
    }

    private function api(User $u): void
    {
        Passport::actingAs($u, ['student', 'admin'], 'api');
    }

    /* ---------- الـCMS: لا وصول بلا صلاحية ---------- */

    public function test_a_student_cannot_touch_roles_or_permissions(): void
    {
        $student = User::factory()->create();
        $role    = Role::findOrCreate('student', 'web');
        $perm    = Permission::findOrCreate('users.delete', 'web');

        $this->actingAs($student);

        $this->getJson('/cms/admin/roles')->assertForbidden();
        $this->postJson('/cms/admin/roles', ['name' => 'evil', 'permissions' => [$perm->id]])->assertForbidden();
        $this->putJson("/cms/admin/roles/{$role->id}", ['name' => 'student', 'permissions' => [$perm->id]])->assertForbidden();
        $this->deleteJson("/cms/admin/roles/{$role->id}")->assertForbidden();

        $this->getJson('/cms/admin/permissions')->assertForbidden();
        $this->postJson('/cms/admin/permissions', ['name' => 'evil.perm'])->assertForbidden();
        $this->deleteJson("/cms/admin/permissions/{$perm->id}")->assertForbidden();

        $this->assertFalse($role->fresh()->hasPermissionTo('users.delete'));
    }

    public function test_view_permission_does_not_allow_editing(): void
    {
        $viewer = $this->staff(['roles.view', 'permissions.view']);
        $role   = Role::findOrCreate('editor', 'web');

        $this->actingAs($viewer);
        $this->getJson('/cms/admin/roles')->assertOk();
        $this->getJson("/cms/admin/roles/{$role->id}")->assertOk();
        $this->putJson("/cms/admin/roles/{$role->id}", ['name' => 'editor', 'permissions' => []])->assertForbidden();
        $this->postJson('/cms/admin/permissions', ['name' => 'x.y'])->assertForbidden();
    }

    /* ---------- لا تصعيد صلاحيات ---------- */

    public function test_cannot_grant_a_role_permissions_you_do_not_hold(): void
    {
        $actor  = $this->staff(['roles.view', 'roles.edit', 'courses.view']);
        $role   = Role::findOrCreate('editor', 'web');
        $mine   = Permission::findOrCreate('courses.view', 'web');
        $others = Permission::findOrCreate('orders.refund', 'web');

        $this->actingAs($actor);

        $this->putJson("/cms/admin/roles/{$role->id}", ['name' => 'editor', 'permissions' => [$mine->id, $others->id]])
            ->assertForbidden();
        $this->assertFalse($role->fresh()->hasPermissionTo('orders.refund'));

        $this->putJson("/cms/admin/roles/{$role->id}", ['name' => 'editor', 'permissions' => [$mine->id]])->assertOk();
        $this->assertTrue($role->fresh()->hasPermissionTo('courses.view'));
    }

    public function test_cannot_create_a_role_with_permissions_you_do_not_hold(): void
    {
        $actor  = $this->staff(['roles.create']);
        $others = Permission::findOrCreate('users.delete', 'web');

        $this->actingAs($actor)->postJson('/cms/admin/roles', ['name' => 'powerful', 'permissions' => [$others->id]])->assertForbidden();
        $this->assertDatabaseMissing('roles', ['name' => 'powerful']);
    }

    public function test_super_admin_role_is_protected_from_non_super_admins(): void
    {
        $super = Role::findOrCreate('super-admin', 'web');
        $actor = $this->staff(['roles.view', 'roles.edit', 'roles.delete']);

        $this->actingAs($actor);
        $this->getJson("/cms/admin/roles/{$super->id}/edit")->assertForbidden();
        $this->putJson("/cms/admin/roles/{$super->id}", ['name' => 'super-admin', 'permissions' => []])->assertForbidden();
        $this->deleteJson("/cms/admin/roles/{$super->id}")->assertForbidden();
    }

    public function test_the_super_admin_role_can_never_be_deleted(): void
    {
        $admin = $this->superAdmin();
        $super = Role::findByName('super-admin', 'web');

        $this->actingAs($admin)->deleteJson("/cms/admin/roles/{$super->id}")->assertForbidden();
        $this->assertNotNull(Role::find($super->id));
    }

    public function test_super_admin_can_manage_roles(): void
    {
        $admin = $this->superAdmin();
        $p     = Permission::findByName('orders.refund', 'web');

        $this->actingAs($admin)->postJson('/cms/admin/roles', ['name' => 'finance', 'permissions' => [$p->id]])->assertCreated();
        $this->assertTrue(Role::findByName('finance', 'web')->hasPermissionTo('orders.refund'));
    }

    /* ---------- الـAPI: الحارس والأدوار ---------- */

    public function test_api_user_update_keeps_roles_when_the_field_is_omitted(): void
    {
        $actor  = $this->staff(['users.view', 'users.edit', 'users.assign-roles']);
        $editor = Role::findOrCreate('editor', 'web');
        $target = User::factory()->create();
        $target->assignRole($editor);

        $this->api($actor);
        $base = ['name' => 'Renamed', 'email' => $target->email, 'status' => 'active'];

        $this->putJson("/api/v1/admin/users/{$target->id}", $base)->assertOk();
        $this->assertTrue($target->fresh()->hasRole('editor'));
        $this->assertSame('Renamed', $target->fresh()->name);

        $this->putJson("/api/v1/admin/users/{$target->id}", $base + ['roles' => []])->assertOk();
        $this->assertFalse($target->fresh()->hasRole('editor'));

        $this->putJson("/api/v1/admin/users/{$target->id}", $base + ['roles' => [$editor->id]])->assertOk();
        $this->assertTrue($target->fresh()->hasRole('editor'));
    }

    public function test_api_non_super_admin_cannot_assign_the_super_admin_role(): void
    {
        $actor  = $this->staff(['users.view', 'users.edit', 'users.assign-roles']);
        $super  = Role::findOrCreate('super-admin', 'web');
        $target = User::factory()->create();

        $this->api($actor);
        $this->putJson("/api/v1/admin/users/{$target->id}", [
            'name' => 'T', 'email' => $target->email, 'status' => 'active', 'roles' => [$super->id],
        ])->assertUnprocessable();
        $this->assertFalse($target->fresh()->hasRole('super-admin'));
    }

    public function test_api_super_admin_users_are_protected_and_self_delete_is_blocked(): void
    {
        $admin  = $this->superAdmin();
        $helper = $this->staff(['users.view', 'users.edit', 'users.delete']);

        $this->api($helper);
        $this->putJson("/api/v1/admin/users/{$admin->id}", ['name' => 'X', 'email' => $admin->email, 'status' => 'active'])->assertStatus(409);
        $this->deleteJson("/api/v1/admin/users/{$admin->id}")->assertStatus(409);

        $this->api($admin);
        $this->deleteJson("/api/v1/admin/users/{$admin->id}")->assertStatus(409);
    }

    public function test_api_lists_work_without_an_accept_header_and_need_permissions(): void
    {
        $actor = $this->staff(['users.view', 'roles.view', 'permissions.view']);
        Role::findOrCreate('editor', 'web');

        $this->api($actor);
        $this->withHeaders(['Accept' => 'text/html'])->get('/api/v1/admin/users')->assertOk()->assertJsonStructure(['data']);
        $this->getJson('/api/v1/admin/roles')->assertOk()->assertJsonStructure(['data', 'links', 'meta']);
        $this->getJson('/api/v1/admin/permissions?search=users')->assertOk();

        $noPerm = $this->staff(['courses.view']);
        $this->api($noPerm);
        $this->getJson('/api/v1/admin/users')->assertForbidden();
        $this->getJson('/api/v1/admin/roles')->assertForbidden();
        $this->getJson('/api/v1/admin/permissions')->assertForbidden();
    }

    public function test_api_roles_are_created_with_the_web_guard(): void
    {
        $actor = $this->staff(['roles.create', 'roles.view']);
        $this->api($actor);

        $this->postJson('/api/v1/admin/roles', ['name' => 'moderator'])->assertCreated();
        $this->assertDatabaseHas('roles', ['name' => 'moderator', 'guard_name' => 'web']);
        $this->assertDatabaseMissing('roles', ['name' => 'moderator', 'guard_name' => 'api']);
    }
}
