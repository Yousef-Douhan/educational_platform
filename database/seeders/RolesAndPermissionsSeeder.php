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

        $resources = ['users', 'user-profiles', 'roles', 'permissions'];
        $actions   = ['view', 'create', 'edit', 'delete'];

        foreach ($resources as $resource) {
            foreach ($actions as $action) {
                Permission::findOrCreate("{$resource}.{$action}", 'web');
            }
        }

        // المدير الأعلى يأخذ كل الصلاحيات
        Role::findOrCreate('super-admin', 'web')->syncPermissions(Permission::all());

        // مثال لدور محدود
        Role::findOrCreate('editor', 'web')->syncPermissions([
            'users.view',
            'user-profiles.view',
            'user-profiles.create',
            'user-profiles.edit',
        ]);
    }
}
