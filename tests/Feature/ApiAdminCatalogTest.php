<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Course;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Passport\Passport;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

/** API الإدارة: التصنيفات والدورات والمنهج. الصلاحيات تأتي من نفس متحكمات الـCMS. */
class ApiAdminCatalogTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    /** مدير بصلاحيات الكتالوج كلها (الصلاحيات تُمنح فعليًا، لا يوجد تجاوز تلقائي في Gate) */
    private function superAdmin(array $scopes = ['student', 'admin']): User
    {
        $perms = [];
        foreach (['categories', 'courses'] as $m) {
            foreach (['view', 'create', 'edit', 'delete'] as $a) {
                $perms[] = "{$m}.{$a}";
            }
        }

        return $this->staff(array_merge($perms, ['courses.publish', 'courses.manage-all']), $scopes);
    }

    private function staff(array $perms, array $scopes = ['student', 'admin']): User
    {
        $u = User::factory()->create();
        foreach ($perms as $p) {
            $u->givePermissionTo(Permission::findOrCreate($p, 'web'));
        }
        Passport::actingAs($u, $scopes, 'api');

        return $u;
    }

    private function instructor(): User
    {
        $u = User::factory()->create();
        $u->assignRole(Role::findOrCreate('instructor', 'web'));

        return $u;
    }

    /* ---------- بوابة الدخول ---------- */

    public function test_admin_api_needs_token_admin_scope_and_staff_account(): void
    {
        $this->getJson('/api/v1/admin/categories')->assertUnauthorized();

        $this->staff(['categories.view'], ['student']); // رمز طالب فقط
        $this->getJson('/api/v1/admin/categories')->assertForbidden()->assertJsonPath('code', 'insufficient_scope');

        $student = User::factory()->create(); // رمز admin لكن الحساب ليس موظفًا
        Passport::actingAs($student, ['student', 'admin'], 'api');
        $this->getJson('/api/v1/admin/categories')->assertForbidden();
    }

    public function test_permissions_are_enforced_per_action(): void
    {
        $this->staff(['categories.view']);

        $this->getJson('/api/v1/admin/categories')->assertOk();
        $this->postJson('/api/v1/admin/categories', ['name_ar' => 'ت', 'name_en' => 'T'])->assertForbidden();
        $this->getJson('/api/v1/admin/courses')->assertForbidden();
    }

    /* ---------- التصنيفات ---------- */

    public function test_categories_crud_with_soft_delete_cycle(): void
    {
        $this->superAdmin();

        $id = $this->postJson('/api/v1/admin/categories', ['name_ar' => 'برمجة', 'name_en' => 'Programming'])
            ->assertCreated()->json('data.id');

        $this->postJson('/api/v1/admin/categories', ['name_ar' => 'x'])->assertUnprocessable();

        $this->getJson('/api/v1/admin/categories?search=Programming')->assertOk()
            ->assertJsonCount(1, 'data')->assertJsonStructure(['data', 'links', 'meta']);
        $this->getJson("/api/v1/admin/categories/{$id}")->assertOk();

        $this->putJson("/api/v1/admin/categories/{$id}", ['name_ar' => 'برمجة 2', 'name_en' => 'Programming 2'])->assertOk();
        $this->assertSame('Programming 2', Category::find($id)->name_en);

        $this->deleteJson("/api/v1/admin/categories/{$id}")->assertOk();
        $this->getJson('/api/v1/admin/categories')->assertJsonCount(0, 'data');
        $this->getJson('/api/v1/admin/categories/trashed')->assertJsonCount(1, 'data');

        $this->patchJson("/api/v1/admin/categories/{$id}/restore")->assertOk();
        $this->assertNotNull(Category::find($id));
    }

    public function test_category_with_courses_cannot_be_deleted(): void
    {
        $this->superAdmin();
        $cat = Category::factory()->create();
        Course::factory()->create(['category_id' => $cat->id]);

        $this->deleteJson("/api/v1/admin/categories/{$cat->id}")->assertStatus(409);
    }

    /* ---------- الدورات ---------- */

    public function test_courses_create_list_filter_update_and_trash(): void
    {
        $this->superAdmin();
        $cat  = Category::factory()->create();
        $inst = $this->instructor();

        $payload = [
            'instructor_id' => $inst->id, 'category_id' => $cat->id,
            'title_ar' => 'دورة لارافل', 'title_en' => 'Laravel Course',
            'price' => 100, 'discount_price' => 80, 'level' => 'beginner', 'status' => 'published',
            'requirements' => [['requirement_ar' => 'متطلب', 'requirement_en' => 'Req']],
            'learning_outcomes' => [['outcome_ar' => 'نتيجة', 'outcome_en' => 'Outcome']],
        ];

        $id = $this->postJson('/api/v1/admin/courses', $payload)->assertCreated()->json('data.id');
        $this->postJson('/api/v1/admin/courses', ['title_en' => 'x'])->assertUnprocessable();

        $this->getJson('/api/v1/admin/courses?status=published&pricing=paid')->assertOk()->assertJsonCount(1, 'data');
        $this->getJson('/api/v1/admin/courses?status=draft')->assertJsonCount(0, 'data');
        $this->getJson("/api/v1/admin/courses/{$id}")->assertOk()->assertJsonPath('data.title_en', 'Laravel Course');
        $this->getJson("/api/v1/admin/courses/{$id}/edit")->assertOk()->assertJsonStructure(['data', 'options']);

        $this->putJson("/api/v1/admin/courses/{$id}", array_merge($payload, ['title_en' => 'Laravel Pro']))->assertOk();
        $this->assertSame('Laravel Pro', Course::find($id)->title_en);

        $this->deleteJson("/api/v1/admin/courses/{$id}")->assertOk();
        $this->getJson('/api/v1/admin/courses')->assertJsonCount(0, 'data');
        $this->getJson('/api/v1/admin/courses/trashed')->assertJsonCount(1, 'data');
        $this->patchJson("/api/v1/admin/courses/{$id}/restore")->assertOk();
    }

    public function test_instructor_only_sees_and_edits_own_courses(): void
    {
        $mine   = Course::factory()->create(['instructor_id' => ($me = $this->instructor())->id]);
        $theirs = Course::factory()->create();

        Passport::actingAs($me, ['student', 'admin'], 'api');
        $me->givePermissionTo(Permission::findOrCreate('courses.view', 'web'));
        $me->givePermissionTo(Permission::findOrCreate('courses.edit', 'web'));
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $res = $this->getJson('/api/v1/admin/courses')->assertOk();
        $this->assertSame([$mine->id], collect($res->json('data'))->pluck('id')->all());

        $this->getJson("/api/v1/admin/courses/{$theirs->id}")->assertForbidden();
    }

    /* ---------- المنهج ---------- */

    public function test_curriculum_sections_and_lessons(): void
    {
        $this->superAdmin();
        $course = Course::factory()->create();

        $sid = $this->postJson("/api/v1/admin/courses/{$course->id}/sections", ['title_ar' => 'قسم', 'title_en' => 'Section'])
            ->assertCreated()->json('data.id');
        $this->postJson("/api/v1/admin/courses/{$course->id}/sections", ['title_ar' => ''])->assertUnprocessable();

        $lid = $this->postJson("/api/v1/admin/sections/{$sid}/lessons", [
            'title_ar' => 'درس', 'title_en' => 'Lesson', 'content_type' => 'article', 'content_ar' => 'نص', 'is_published' => true,
        ])->assertCreated()->json('data.id');

        // فيديو برابط http غير آمن يُرفض
        $this->postJson("/api/v1/admin/sections/{$sid}/lessons", [
            'title_ar' => 'ف', 'title_en' => 'V', 'content_type' => 'video', 'video_provider' => 'youtube', 'video_url' => 'http://youtube.com/watch?v=1',
        ])->assertUnprocessable();

        $this->getJson("/api/v1/admin/courses/{$course->id}/curriculum")->assertOk()
            ->assertJsonPath('totals.lessons', 1)->assertJsonPath('sections.0.lessons.0.id', $lid);
        $this->getJson("/api/v1/admin/lessons/{$lid}")->assertOk();

        $this->deleteJson("/api/v1/admin/sections/{$sid}")->assertStatus(409); // فيه دروس
        $this->deleteJson("/api/v1/admin/lessons/{$lid}")->assertOk();

        // الدرس المحذوف مؤقتًا ما زال يمنع حذف القسم (الحذف يزيله نهائيًا بالتتالي)
        $this->deleteJson("/api/v1/admin/sections/{$sid}")->assertStatus(409);

        $this->deleteJson("/api/v1/admin/lessons/{$lid}/force-delete")->assertOk();
        $this->deleteJson("/api/v1/admin/sections/{$sid}")->assertOk();
    }
}
