<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class RolesAndPermissionsSeeder extends Seeder
{
    public function run(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $resources = ['users', 'user-profiles', 'roles', 'permissions', 'categories', 'courses'];
        $actions   = ['view', 'create', 'edit', 'delete'];

        foreach ($resources as $resource) {
            foreach ($actions as $action) {
                Permission::findOrCreate("{$resource}.{$action}", 'web');
            }
        }

        foreach (['courses.manage-all', 'courses.publish'] as $name) {
            Permission::findOrCreate($name, 'web');
        }

        // المدير الأعلى: كل الصلاحيات (يجب أن يُنفَّذ بعد إنشائها كلها)
        Role::findOrCreate('super-admin', 'web')->syncPermissions(Permission::all());

        // المدرّس: يدير دوراته فقط. givePermissionTo لا يمسح تعديلاتك اليدوية لاحقًا
        Role::findOrCreate('instructor', 'web')->givePermissionTo([
            'courses.view',
            'courses.create',
            'courses.edit',
            'courses.delete',
        ]);

        Role::findOrCreate('editor', 'web')->givePermissionTo([
            'users.view',
            'user-profiles.view',
            'user-profiles.create',
            'user-profiles.edit',
        ]);
    }
}
