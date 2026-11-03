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

        foreach (['view', 'create', 'edit'] as $action) {
            Permission::findOrCreate("enrollments.{$action}", 'web');
        }

        foreach (['courses.manage-all', 'courses.publish', 'users.assign-roles'] as $name) {
            Permission::findOrCreate($name, 'web');
        }

        foreach (['view', 'create', 'edit', 'delete'] as $action) {
            Permission::findOrCreate("certificate-templates.{$action}", 'web');
        }
        foreach (['view', 'create', 'edit'] as $action) {
            Permission::findOrCreate("certificates.{$action}", 'web');
        }
        foreach (['view', 'create', 'edit', 'delete'] as $action) {
            Permission::findOrCreate("coupons.{$action}", 'web');
        }
        foreach (['view', 'edit'] as $action) {
            Permission::findOrCreate("carts.{$action}", 'web');
        }
        // orders: edit = إلغاء، mark-paid = تأكيد دفع يدوي، refund = استرداد (حساسة فمنفصلة)
        foreach (['view', 'edit', 'mark-paid', 'refund'] as $action) {
            Permission::findOrCreate("orders.{$action}", 'web');
        }
        Permission::findOrCreate('payments.view', 'web'); // سجل مالي للقراءة فقط
        // محاولات الاختبارات: مراجعة للقراءة، والإلغاء إجراء حساس فمنفصل
        foreach (['view', 'void'] as $action) {
            Permission::findOrCreate("quiz-attempts.{$action}", 'web');
        }
        // تقدم الطلاب: عرض، وإعادة حساب سجل عند الشك
        foreach (['view', 'recalculate'] as $action) {
            Permission::findOrCreate("progress.{$action}", 'web');
        }
        // سجل التدقيق: قراءة فقط، ولا يُعطى للمدرّس
        Permission::findOrCreate('audit-logs.view', 'web');
        // كل إشعارات المنصة: قراءة فقط للإدارة (إشعارات المستخدم نفسه لا تحتاج صلاحية)
        Permission::findOrCreate('notifications.view', 'web');
        // الجلسات المباشرة: المدرّس يديرها لدوراته فقط (النطاق بـ visibleTo)
        foreach (['view', 'create', 'edit', 'delete'] as $action) {
            Permission::findOrCreate("live-sessions.{$action}", 'web');
        }

        // المدير الأعلى: كل الصلاحيات (يجب أن يُنفَّذ بعد إنشائها كلها)
        Role::findOrCreate('super-admin', 'web')->syncPermissions(Permission::all());

        // المدرّس: يدير دوراته فقط. givePermissionTo لا يمسح تعديلاتك اليدوية لاحقًا
        Role::findOrCreate('instructor', 'web')->givePermissionTo([
            'courses.view',
            'courses.create',
            'courses.edit',
            'courses.delete',
            'live-sessions.view',
            'live-sessions.create',
            'live-sessions.edit',
            'live-sessions.delete',
        ]);

        Role::findOrCreate('editor', 'web')->givePermissionTo([
            'users.view',
            'user-profiles.view',
            'user-profiles.create',
            'user-profiles.edit',
        ]);
    }
}
