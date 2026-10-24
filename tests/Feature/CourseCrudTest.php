<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Course;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class CourseCrudTest extends TestCase
{
    use RefreshDatabase;

    private const JSON = ['Accept' => 'application/json'];

    protected function setUp(): void
    {
        parent::setUp();
        app(PermissionRegistrar::class)->forgetCachedPermissions();
        Storage::fake('public');

        foreach (['view', 'create', 'edit', 'delete'] as $a) {
            Permission::findOrCreate("courses.{$a}", 'web');
        }
        Permission::findOrCreate('courses.manage-all', 'web');
        Permission::findOrCreate('courses.publish', 'web');

        Role::findOrCreate('instructor', 'web')
            ->givePermissionTo(['courses.view', 'courses.create', 'courses.edit', 'courses.delete']);
    }

    private function admin(): User
    {
        $u = User::factory()->create();
        $u->givePermissionTo(Permission::all());

        return $u;
    }

    private function teacher(): User
    {
        $u = User::factory()->create();
        $u->assignRole('instructor');

        return $u;
    }

    private function payload(array $over = []): array
    {
        return array_merge([
            'category_id' => Category::factory()->create()->id,
            'title_ar'    => 'دورة جديدة',
            'title_en'    => 'New Course',
            'price'       => 50,
            'level'       => 'beginner',
            'status'      => 'draft',
        ], $over);
    }

    /* ---------- Index ---------- */

    public function test_index_page_renders_for_admin(): void
    {
        $this->actingAs($this->admin())
            ->get(route('cms.admin.courses.index'))
            ->assertOk()->assertViewIs('cms.courses.index');
    }

    public function test_index_json_returns_real_stats(): void
    {
        Course::factory()->published()->paid()->create();
        Course::factory()->create();
        Course::factory()->create()->delete();

        $this->actingAs($this->admin())
            ->getJson(route('cms.admin.courses.index'))
            ->assertOk()
            ->assertJsonPath('stats.total', 2)
            ->assertJsonPath('stats.published', 1)
            ->assertJsonPath('stats.drafts', 1)
            ->assertJsonPath('stats.free', 1)
            ->assertJsonPath('stats.trashed', 1);
    }

    public function test_instructor_sees_only_own_and_shared_courses(): void
    {
        $me = $this->teacher();
        $own = Course::factory()->create(['instructor_id' => $me->id, 'title_en' => 'OwnCourseX']);
        $shared = Course::factory()->create(['title_en' => 'SharedCourseX']);
        $shared->coInstructors()->attach($me->id);
        Course::factory()->create(['title_en' => 'ForeignCourseX']);

        $html = $this->actingAs($me)->getJson(route('cms.admin.courses.index'))->assertOk()->json('html');

        $this->assertStringContainsString('OwnCourseX', $html);
        $this->assertStringContainsString('SharedCourseX', $html);
        $this->assertStringNotContainsString('ForeignCourseX', $html);
    }

    public function test_index_filters_by_status_and_pricing_and_search(): void
    {
        Course::factory()->published()->paid()->create(['title_en' => 'PaidPublishedZ']);
        Course::factory()->create(['title_en' => 'FreeDraftZ']);

        $admin = $this->admin();

        $paid = $this->actingAs($admin)->getJson(route('cms.admin.courses.index', ['pricing' => 'paid']))->json('html');
        $this->assertStringContainsString('PaidPublishedZ', $paid);
        $this->assertStringNotContainsString('FreeDraftZ', $paid);

        $drafts = $this->getJson(route('cms.admin.courses.index', ['status' => 'draft']))->json('html');
        $this->assertStringContainsString('FreeDraftZ', $drafts);
        $this->assertStringNotContainsString('PaidPublishedZ', $drafts);

        $search = $this->getJson(route('cms.admin.courses.index', ['search' => 'PaidPublished']))->json('html');
        $this->assertStringContainsString('PaidPublishedZ', $search);
        $this->assertStringNotContainsString('FreeDraftZ', $search);
    }

    public function test_index_ignores_invalid_sort_column(): void
    {
        Course::factory()->create();

        $this->actingAs($this->admin())
            ->getJson(route('cms.admin.courses.index', ['sort' => 'price; drop table courses']))
            ->assertOk();
    }

    /* ---------- Create / Store ---------- */

    public function test_create_returns_options(): void
    {
        $this->actingAs($this->admin())
            ->getJson(route('cms.admin.courses.create'))
            ->assertOk()
            ->assertJsonStructure(['options' => ['categories', 'instructors', 'levels', 'statuses', 'can_pick_instructor']])
            ->assertJsonPath('options.can_pick_instructor', true);
    }

    public function test_admin_stores_full_course(): void
    {
        $teacher = $this->teacher();
        $co      = $this->teacher();

        $this->actingAs($this->admin())->post(route('cms.admin.courses.store'), $this->payload([
            'instructor_id'     => $teacher->id,
            'co_instructors'    => [$co->id],
            'requirements'      => [['requirement_ar' => 'متطلب', 'requirement_en' => 'Req']],
            'learning_outcomes' => [['outcome_ar' => 'مخرج', 'outcome_en' => 'Outcome']],
            'image'             => UploadedFile::fake()->image('c.jpg', 400, 300),
        ]), self::JSON)->assertCreated();

        $course = Course::firstWhere('instructor_id', $teacher->id);
        $this->assertNotNull($course);
        $this->assertSame('new-course', $course->slug);
        $this->assertTrue($course->coInstructors->contains($co));
        $this->assertCount(1, $course->requirements);
        $this->assertCount(1, $course->learningOutcomes);
        Storage::disk('public')->assertExists($course->image_url);
    }

    public function test_store_generates_unique_slug(): void
    {
        $teacher = $this->teacher();
        $admin   = $this->admin();
        $data    = $this->payload(['instructor_id' => $teacher->id]);

        $this->actingAs($admin)->postJson(route('cms.admin.courses.store'), $data)->assertCreated();
        $this->postJson(route('cms.admin.courses.store'), $data)->assertCreated();

        $this->assertDatabaseHas('courses', ['slug' => 'new-course']);
        $this->assertDatabaseHas('courses', ['slug' => 'new-course-2']);
    }

    public function test_instructor_store_forces_own_instructor_id(): void
    {
        $me    = $this->teacher();
        $other = $this->teacher();

        $this->actingAs($me)->postJson(route('cms.admin.courses.store'), $this->payload(['instructor_id' => $other->id]))
            ->assertCreated();

        $this->assertDatabaseHas('courses', ['title_en' => 'New Course', 'instructor_id' => $me->id]);
    }

    public function test_instructor_cannot_publish_directly(): void
    {
        $this->actingAs($this->teacher())
            ->postJson(route('cms.admin.courses.store'), $this->payload(['status' => 'published']))
            ->assertUnprocessable()->assertJsonValidationErrors('status');
    }

    public function test_store_rejects_non_instructor_as_main_instructor(): void
    {
        $plain = User::factory()->create();

        $this->actingAs($this->admin())
            ->postJson(route('cms.admin.courses.store'), $this->payload(['instructor_id' => $plain->id]))
            ->assertUnprocessable()->assertJsonValidationErrors('instructor_id');
    }

    public function test_store_requires_instructor_for_admin_and_titles_and_category(): void
    {
        $this->actingAs($this->admin())
            ->postJson(route('cms.admin.courses.store'), [])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['instructor_id', 'category_id', 'title_ar', 'title_en', 'price', 'level', 'status']);
    }

    public function test_store_validates_discount_rules(): void
    {
        $teacher = $this->teacher();
        $admin   = $this->admin();

        $this->actingAs($admin)
            ->postJson(route('cms.admin.courses.store'), $this->payload(['instructor_id' => $teacher->id, 'price' => 50, 'discount_price' => 80]))
            ->assertUnprocessable()->assertJsonValidationErrors('discount_price');

        $this->postJson(route('cms.admin.courses.store'), $this->payload(['instructor_id' => $teacher->id, 'price' => 0, 'discount_price' => 0]))
            ->assertUnprocessable()->assertJsonValidationErrors('discount_price');
    }

    public function test_store_rejects_trashed_category_and_bad_image(): void
    {
        $teacher  = $this->teacher();
        $category = Category::factory()->create();
        $category->delete();

        $this->actingAs($this->admin())->post(route('cms.admin.courses.store'), $this->payload([
            'instructor_id' => $teacher->id,
            'category_id'   => $category->id,
            'image'         => UploadedFile::fake()->create('x.pdf', 10, 'application/pdf'),
        ]), self::JSON)->assertUnprocessable()->assertJsonValidationErrors(['category_id', 'image']);
    }

    public function test_store_requires_both_languages_in_rows(): void
    {
        $this->actingAs($this->admin())->postJson(route('cms.admin.courses.store'), $this->payload([
            'instructor_id' => $this->teacher()->id,
            'requirements'  => [['requirement_ar' => 'فقط عربي']],
        ]))->assertUnprocessable()->assertJsonValidationErrors('requirements.0.requirement_en');
    }

    public function test_co_instructors_exclude_main_instructor(): void
    {
        $teacher = $this->teacher();
        $co      = $this->teacher();

        $this->actingAs($this->admin())->postJson(route('cms.admin.courses.store'), $this->payload([
            'instructor_id'  => $teacher->id,
            'co_instructors' => [$teacher->id, $co->id],
        ]))->assertCreated();

        $course = Course::firstWhere('instructor_id', $teacher->id);
        $this->assertEquals([$co->id], $course->coInstructors->pluck('id')->all());
    }

    /* ---------- Show / Edit ---------- */

    public function test_show_returns_relations(): void
    {
        $course = Course::factory()->create();
        $course->requirements()->create(['requirement_ar' => 'أ', 'requirement_en' => 'A']);

        $this->actingAs($this->admin())
            ->getJson(route('cms.admin.courses.show', $course))
            ->assertOk()
            ->assertJsonPath('data.id', $course->id)
            ->assertJsonPath('data.requirements.0.requirement_en', 'A')
            ->assertJsonStructure(['data' => ['instructor', 'category', 'co_instructors', 'learning_outcomes']]);
    }

    public function test_instructor_cannot_view_or_edit_foreign_course(): void
    {
        $foreign = Course::factory()->create();
        $me      = $this->teacher();

        $this->actingAs($me)->getJson(route('cms.admin.courses.show', $foreign))->assertForbidden();
        $this->getJson(route('cms.admin.courses.edit', $foreign))->assertForbidden();
        $this->putJson(route('cms.admin.courses.update', $foreign), $this->payload())->assertForbidden();
    }

    public function test_edit_returns_data_and_selected_co_instructors(): void
    {
        $course = Course::factory()->create();
        $co     = $this->teacher();
        $course->coInstructors()->attach($co->id);

        $this->actingAs($this->admin())
            ->getJson(route('cms.admin.courses.edit', $course))
            ->assertOk()
            ->assertJsonPath('co_instructor_ids.0', $co->id)
            ->assertJsonStructure(['data', 'options']);
    }

    /* ---------- Update ---------- */

    public function test_admin_updates_and_replaces_children(): void
    {
        $course = Course::factory()->create();
        $course->requirements()->create(['requirement_ar' => 'قديم', 'requirement_en' => 'Old']);

        $this->actingAs($this->admin())->putJson(route('cms.admin.courses.update', $course), $this->payload([
            'instructor_id' => $course->instructor_id,
            'title_en'      => 'Renamed',
            'slug'          => '',
            'requirements'  => [['requirement_ar' => 'جديد', 'requirement_en' => 'New']],
        ]))->assertOk();

        $course->refresh();
        $this->assertSame('Renamed', $course->title_en);
        $this->assertNotSame('', $course->slug);
        $this->assertEquals(['New'], $course->requirements->pluck('requirement_en')->all());
    }

    public function test_owner_and_co_instructor_can_update_but_instructor_id_is_kept(): void
    {
        $owner  = $this->teacher();
        $co     = $this->teacher();
        $other  = $this->teacher();
        $course = Course::factory()->create(['instructor_id' => $owner->id]);
        $course->coInstructors()->attach($co->id);

        $this->actingAs($co)->putJson(route('cms.admin.courses.update', $course), $this->payload(['instructor_id' => $other->id, 'title_en' => 'ByCo']))
            ->assertOk();

        $this->assertDatabaseHas('courses', ['id' => $course->id, 'title_en' => 'ByCo', 'instructor_id' => $owner->id]);
    }

    public function test_non_publisher_cannot_change_status_of_published_course(): void
    {
        $owner  = $this->teacher();
        $course = Course::factory()->published()->create(['instructor_id' => $owner->id]);

        $this->actingAs($owner)->putJson(route('cms.admin.courses.update', $course), $this->payload(['title_en' => 'Edited']))
            ->assertOk();

        $this->assertDatabaseHas('courses', ['id' => $course->id, 'title_en' => 'Edited', 'status' => 'published']);
    }

    public function test_update_replaces_image_and_deletes_old_file(): void
    {
        $old    = UploadedFile::fake()->image('old.jpg')->store('courses', 'public');
        $course = Course::factory()->create(['image_url' => $old]);

        $this->actingAs($this->admin())->post(route('cms.admin.courses.update', $course), $this->payload([
            '_method'       => 'PUT',
            'instructor_id' => $course->instructor_id,
            'image'         => UploadedFile::fake()->image('new.jpg'),
        ]), self::JSON)->assertOk();

        $course->refresh();
        $this->assertNotEquals($old, $course->image_url);
        Storage::disk('public')->assertMissing($old);
        Storage::disk('public')->assertExists($course->image_url);
    }

    public function test_update_can_remove_image(): void
    {
        $old    = UploadedFile::fake()->image('old.jpg')->store('courses', 'public');
        $course = Course::factory()->create(['image_url' => $old]);

        $this->actingAs($this->admin())->putJson(route('cms.admin.courses.update', $course), $this->payload([
            'instructor_id' => $course->instructor_id,
            'remove_image'  => true,
        ]))->assertOk();

        $this->assertNull($course->fresh()->image_url);
        Storage::disk('public')->assertMissing($old);
    }

    public function test_update_rejects_duplicate_slug_but_allows_own(): void
    {
        $a = Course::factory()->create(['slug' => 'aaa']);
        Course::factory()->create(['slug' => 'bbb']);
        $admin = $this->admin();

        $this->actingAs($admin)->putJson(route('cms.admin.courses.update', $a), $this->payload(['instructor_id' => $a->instructor_id, 'slug' => 'aaa']))
            ->assertOk();
        $this->putJson(route('cms.admin.courses.update', $a), $this->payload(['instructor_id' => $a->instructor_id, 'slug' => 'bbb']))
            ->assertUnprocessable()->assertJsonValidationErrors('slug');
    }

    /* ---------- Soft delete / Trash ---------- */

    public function test_owner_soft_deletes_but_co_instructor_cannot(): void
    {
        $owner  = $this->teacher();
        $co     = $this->teacher();
        $course = Course::factory()->create(['instructor_id' => $owner->id]);
        $course->coInstructors()->attach($co->id);

        $this->actingAs($co)->deleteJson(route('cms.admin.courses.destroy', $course))->assertForbidden();
        $this->actingAs($owner)->deleteJson(route('cms.admin.courses.destroy', $course))
            ->assertOk()->assertJsonPath('stats.trashed', 1);

        $this->assertSoftDeleted('courses', ['id' => $course->id]);
    }

    public function test_trashed_lists_only_deleted_courses(): void
    {
        $gone = Course::factory()->create(['title_en' => 'GoneCourseQ']);
        $gone->delete();
        Course::factory()->create(['title_en' => 'StayCourseQ']);

        $html = $this->actingAs($this->admin())->getJson(route('cms.admin.courses.trashed'))->assertOk()->json('html');

        $this->assertStringContainsString('GoneCourseQ', $html);
        $this->assertStringNotContainsString('StayCourseQ', $html);
    }

    public function test_restore_works_and_is_blocked_when_category_or_instructor_trashed(): void
    {
        $admin = $this->admin();

        $ok = Course::factory()->create();
        $ok->delete();
        $this->actingAs($admin)->patchJson(route('cms.admin.courses.restore', $ok->id))->assertOk();
        $this->assertNotSoftDeleted('courses', ['id' => $ok->id]);

        $blockedCat = Course::factory()->create();
        $blockedCat->delete();
        $blockedCat->category->delete();
        $this->patchJson(route('cms.admin.courses.restore', $blockedCat->id))->assertStatus(409);

        $blockedUser = Course::factory()->create();
        $blockedUser->delete();
        $blockedUser->instructor->delete();
        $this->patchJson(route('cms.admin.courses.restore', $blockedUser->id))->assertStatus(409);
    }

    public function test_restore_returns_404_for_active_course(): void
    {
        $course = Course::factory()->create();

        $this->actingAs($this->admin())->patchJson(route('cms.admin.courses.restore', $course->id))->assertNotFound();
    }

    public function test_force_delete_is_admin_only_and_removes_image_and_children(): void
    {
        $owner  = $this->teacher();
        $path   = UploadedFile::fake()->image('a.jpg')->store('courses', 'public');
        $course = Course::factory()->create(['instructor_id' => $owner->id, 'image_url' => $path]);
        $course->requirements()->create(['requirement_ar' => 'أ', 'requirement_en' => 'A']);
        $course->delete();

        $this->actingAs($owner)->deleteJson(route('cms.admin.courses.force-delete', $course->id))->assertForbidden();

        $this->actingAs($this->admin())->deleteJson(route('cms.admin.courses.force-delete', $course->id))->assertOk();

        $this->assertDatabaseMissing('courses', ['id' => $course->id]);
        $this->assertDatabaseMissing('course_requirements', ['course_id' => $course->id]);
        Storage::disk('public')->assertMissing($path);
    }

    public function test_force_delete_returns_404_for_active_course(): void
    {
        $course = Course::factory()->create();

        $this->actingAs($this->admin())->deleteJson(route('cms.admin.courses.force-delete', $course->id))->assertNotFound();
    }

    /* ---------- العلاقات والحماية المتبادلة ---------- */

    public function test_relations_still_resolve_when_instructor_or_category_soft_deleted(): void
    {
        $course = Course::factory()->create();
        $course->instructor->delete();
        $course->category->delete();

        $fresh = Course::find($course->id);
        $this->assertNotNull($fresh->instructor);
        $this->assertTrue($fresh->instructor->trashed());
        $this->assertTrue($fresh->category->trashed());
    }

    public function test_price_accessors(): void
    {
        $free = Course::factory()->make(['price' => 0]);
        $this->assertTrue($free->is_free);

        $sale = Course::factory()->make(['price' => 100, 'discount_price' => 70]);
        $this->assertFalse($sale->is_free);
        $this->assertSame('70.00', $sale->final_price);
    }

    public function test_category_with_courses_cannot_be_deleted(): void
    {
        $admin    = $this->admin();
        $admin->givePermissionTo(
            collect(['view', 'create', 'edit', 'delete'])->map(fn($a) => Permission::findOrCreate("categories.{$a}", 'web'))->all()
        );
        $category = Category::factory()->create();
        Course::factory()->create(['category_id' => $category->id]);

        $this->actingAs($admin)->deleteJson(route('cms.admin.categories.destroy', $category))->assertStatus(409);

        $this->assertNotSoftDeleted('categories', ['id' => $category->id]);
    }

    /* ---------- الصلاحيات ---------- */

    public function test_user_without_permissions_is_forbidden(): void
    {
        $this->actingAs(User::factory()->create())
            ->getJson(route('cms.admin.courses.index'))
            ->assertForbidden();
    }
}
