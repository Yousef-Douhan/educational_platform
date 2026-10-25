<?php

namespace Tests\Feature;

use App\Models\Course;
use App\Models\CourseSection;
use App\Models\Lesson;
use App\Models\LessonAttachment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class CurriculumTest extends TestCase
{
    use RefreshDatabase;

    private const JSON = ['Accept' => 'application/json'];

    protected function setUp(): void
    {
        parent::setUp();
        app(PermissionRegistrar::class)->forgetCachedPermissions();
        Storage::fake('local');
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

    private function section(Course $c, array $o = []): CourseSection
    {
        return CourseSection::factory()->create(array_merge(['course_id' => $c->id], $o));
    }

    private function lesson(CourseSection $s, array $o = []): Lesson
    {
        return Lesson::factory()->create(array_merge(['section_id' => $s->id], $o));
    }

    private function lessonPayload(array $o = []): array
    {
        return array_merge([
            'title_ar' => 'درس',
            'title_en' => 'Lesson',
            'content_type' => 'article',
            'content_ar' => 'نص الدرس',
        ], $o);
    }

    private function upload(Lesson $lesson, ?User $by = null, ?UploadedFile $file = null)
    {
        return $this->actingAs($by ?? $this->admin())->post(
            route('cms.admin.lessons.attachments.store', $lesson),
            ['file' => $file ?? UploadedFile::fake()->create('guide.pdf', 50, 'application/pdf')],
            self::JSON
        );
    }

    /* ---------- الشجرة ---------- */

    public function test_tree_returns_ordered_sections_with_totals(): void
    {
        $course = Course::factory()->create();
        $second = $this->section($course, ['sort_order' => 2, 'title_en' => 'Second']);
        $first  = $this->section($course, ['sort_order' => 1, 'title_en' => 'First']);
        $this->lesson($first, ['duration_minutes' => 10, 'is_published' => true]);
        $this->lesson($first, ['duration_minutes' => 5]);
        $this->lesson($second)->delete();

        $this->actingAs($this->admin())
            ->getJson(route('cms.admin.courses.curriculum', $course))
            ->assertOk()
            ->assertJsonPath('sections.0.title_en', 'First')
            ->assertJsonPath('sections.1.title_en', 'Second')
            ->assertJsonPath('totals.sections', 2)
            ->assertJsonPath('totals.lessons', 2)
            ->assertJsonPath('totals.published', 1)
            ->assertJsonPath('totals.minutes', 15)
            ->assertJsonPath('totals.trashed', 1)
            ->assertJsonPath('can_force_delete', true);
    }

    public function test_instructor_sees_own_tree_but_not_a_foreign_one(): void
    {
        $me  = $this->teacher();
        $own = Course::factory()->create(['instructor_id' => $me->id]);
        $foreign = Course::factory()->create();

        $this->actingAs($me)->getJson(route('cms.admin.courses.curriculum', $own))->assertOk();
        $this->getJson(route('cms.admin.courses.curriculum', $foreign))->assertForbidden();
    }

    public function test_tree_of_trashed_course_is_not_found(): void
    {
        $course = Course::factory()->create();
        $course->delete();

        $this->actingAs($this->admin())->getJson(route('cms.admin.courses.curriculum', $course->id))->assertNotFound();
    }

    /* ---------- الأقسام ---------- */

    public function test_store_section_appends_sort_order_and_validates(): void
    {
        $course = Course::factory()->create();
        $admin  = $this->admin();

        $this->actingAs($admin)->postJson(route('cms.admin.courses.sections.store', $course), ['title_ar' => 'أ', 'title_en' => 'A'])->assertCreated();
        $this->postJson(route('cms.admin.courses.sections.store', $course), ['title_ar' => 'ب', 'title_en' => 'B'])->assertCreated();
        $this->postJson(route('cms.admin.courses.sections.store', $course), [])
            ->assertUnprocessable()->assertJsonValidationErrors(['title_ar', 'title_en']);

        $this->assertEquals([1, 2], CourseSection::where('course_id', $course->id)->orderBy('id')->pluck('sort_order')->all());
    }

    public function test_instructor_cannot_add_section_to_foreign_course(): void
    {
        $course = Course::factory()->create();

        $this->actingAs($this->teacher())
            ->postJson(route('cms.admin.courses.sections.store', $course), ['title_ar' => 'أ', 'title_en' => 'A'])
            ->assertForbidden();
    }

    public function test_update_section(): void
    {
        $section = $this->section(Course::factory()->create());

        $this->actingAs($this->admin())
            ->putJson(route('cms.admin.sections.update', $section), ['title_ar' => 'جديد', 'title_en' => 'Renamed'])
            ->assertOk();

        $this->assertDatabaseHas('course_sections', ['id' => $section->id, 'title_en' => 'Renamed']);
    }

    public function test_delete_empty_section_and_block_when_lessons_exist(): void
    {
        $course = Course::factory()->create();
        $empty  = $this->section($course);
        $full   = $this->section($course);
        $this->lesson($full)->delete(); // محذوف مؤقتًا يمنع الحذف أيضًا

        $admin = $this->admin();
        $this->actingAs($admin)->deleteJson(route('cms.admin.sections.destroy', $empty))->assertOk();
        $this->deleteJson(route('cms.admin.sections.destroy', $full))->assertStatus(409);

        $this->assertDatabaseMissing('course_sections', ['id' => $empty->id]);
        $this->assertDatabaseHas('course_sections', ['id' => $full->id]);
    }

    public function test_reorder_sections(): void
    {
        $course = Course::factory()->create();
        $a = $this->section($course, ['sort_order' => 1]);
        $b = $this->section($course, ['sort_order' => 2]);

        $admin = $this->admin();
        $this->actingAs($admin)
            ->postJson(route('cms.admin.courses.sections.reorder', $course), ['ids' => [$b->id, $a->id]])
            ->assertOk();

        $this->assertSame(1, $b->fresh()->sort_order);
        $this->assertSame(2, $a->fresh()->sort_order);

        $this->postJson(route('cms.admin.courses.sections.reorder', $course), ['ids' => [$a->id]])->assertUnprocessable();
        $this->postJson(route('cms.admin.courses.sections.reorder', $course), ['ids' => [$a->id, 9999]])->assertUnprocessable();
    }

    /* ---------- الدروس ---------- */

    public function test_store_article_lesson_and_requires_arabic_content(): void
    {
        $section = $this->section(Course::factory()->create());
        $admin   = $this->admin();

        $this->actingAs($admin)->postJson(route('cms.admin.sections.lessons.store', $section), $this->lessonPayload([
            'is_free_preview' => true,
            'is_published' => true,
            'duration_minutes' => 12,
        ]))->assertCreated();

        $this->assertDatabaseHas('lessons', [
            'section_id' => $section->id,
            'is_free_preview' => 1,
            'is_published' => 1,
            'duration_minutes' => 12,
            'sort_order' => 1,
        ]);

        $this->postJson(route('cms.admin.sections.lessons.store', $section), $this->lessonPayload(['content_ar' => '']))
            ->assertUnprocessable()->assertJsonValidationErrors('content_ar');
    }

    public function test_lesson_flags_default_to_false_and_next_lesson_appends(): void
    {
        $section = $this->section(Course::factory()->create());
        $admin   = $this->admin();

        $this->actingAs($admin)->postJson(route('cms.admin.sections.lessons.store', $section), $this->lessonPayload())->assertCreated();
        $this->postJson(route('cms.admin.sections.lessons.store', $section), $this->lessonPayload())->assertCreated();

        $lessons = Lesson::where('section_id', $section->id)->orderBy('id')->get();
        $this->assertFalse($lessons[0]->is_published);
        $this->assertFalse($lessons[0]->is_free_preview);
        $this->assertEquals([1, 2], $lessons->pluck('sort_order')->all());
    }

    public function test_video_lesson_requires_provider_and_url(): void
    {
        $section = $this->section(Course::factory()->create());

        $this->actingAs($this->admin())
            ->postJson(route('cms.admin.sections.lessons.store', $section), $this->lessonPayload(['content_type' => 'video', 'content_ar' => null]))
            ->assertUnprocessable()->assertJsonValidationErrors(['video_provider', 'video_url']);
    }

    public function test_video_url_rules_per_provider(): void
    {
        $section = $this->section(Course::factory()->create());
        $admin   = $this->admin();
        $url     = route('cms.admin.sections.lessons.store', $section);
        $video   = fn(string $provider, string $link) => $this->lessonPayload([
            'content_type' => 'video',
            'content_ar' => null,
            'video_provider' => $provider,
            'video_url' => $link,
        ]);

        $this->actingAs($admin);

        $this->postJson($url, $video('youtube', 'https://www.youtube.com/watch?v=abc'))->assertCreated();
        $this->postJson($url, $video('youtube', 'https://youtu.be/abc'))->assertCreated();
        $this->postJson($url, $video('external', 'https://videos.example.com/v.mp4'))->assertCreated();
        $this->postJson($url, $video('s3', 'courses/1/intro.mp4'))->assertCreated();

        $this->postJson($url, $video('youtube', 'https://evil.com/watch?v=abc'))->assertJsonValidationErrors('video_url');
        $this->postJson($url, $video('youtube', 'https://notyoutube.com/x'))->assertJsonValidationErrors('video_url');
        $this->postJson($url, $video('youtube', 'http://www.youtube.com/watch?v=abc'))->assertJsonValidationErrors('video_url');
        $this->postJson($url, $video('external', 'javascript:alert(1)'))->assertJsonValidationErrors('video_url');
        $this->postJson($url, $video('s3', '../secret.mp4'))->assertJsonValidationErrors('video_url');
        $this->postJson($url, $video('s3', 'a/../../b.mp4'))->assertJsonValidationErrors('video_url');
        $this->postJson($url, $video('s3', 'https://bucket.s3.amazonaws.com/x.mp4'))->assertJsonValidationErrors('video_url');
    }

    public function test_changing_type_clears_irrelevant_fields(): void
    {
        $lesson = $this->lesson($this->section(Course::factory()->create()), [
            'content_type' => 'video',
            'video_provider' => 'youtube',
            'video_url' => 'https://youtu.be/x',
            'content_ar' => null,
        ]);

        $this->actingAs($this->admin())
            ->putJson(route('cms.admin.lessons.update', $lesson), $this->lessonPayload())
            ->assertOk();

        $lesson->refresh();
        $this->assertSame('article', $lesson->content_type);
        $this->assertNull($lesson->video_provider);
        $this->assertNull($lesson->video_url);
        $this->assertSame('نص الدرس', $lesson->content_ar);
    }

    public function test_show_returns_lesson_attachments_and_course_sections(): void
    {
        $course  = Course::factory()->create();
        $section = $this->section($course);
        $other   = $this->section($course);
        $lesson  = $this->lesson($section);
        LessonAttachment::create(['lesson_id' => $lesson->id, 'title' => 'Guide', 'file_path' => 'x/y.pdf', 'file_size' => 10]);

        $this->actingAs($this->admin())
            ->getJson(route('cms.admin.lessons.show', $lesson))
            ->assertOk()
            ->assertJsonPath('data.id', $lesson->id)
            ->assertJsonPath('attachments.0.title', 'Guide')
            ->assertJsonCount(2, 'sections')
            ->assertJsonStructure(['options' => ['content_types', 'video_providers']]);
    }

    public function test_lesson_can_move_within_course_only(): void
    {
        $course   = Course::factory()->create();
        $a        = $this->section($course);
        $b        = $this->section($course);
        $this->lesson($b, ['sort_order' => 4]);
        $lesson   = $this->lesson($a);
        $foreign  = $this->section(Course::factory()->create());

        $admin = $this->admin();
        $this->actingAs($admin)
            ->putJson(route('cms.admin.lessons.update', $lesson), $this->lessonPayload(['section_id' => $b->id]))
            ->assertOk();

        $lesson->refresh();
        $this->assertSame($b->id, $lesson->section_id);
        $this->assertSame(5, $lesson->sort_order);

        $this->putJson(route('cms.admin.lessons.update', $lesson), $this->lessonPayload(['section_id' => $foreign->id]))
            ->assertUnprocessable()->assertJsonValidationErrors('section_id');
    }

    public function test_instructor_cannot_touch_foreign_lessons(): void
    {
        $foreignSection = $this->section(Course::factory()->create());
        $lesson         = $this->lesson($foreignSection);
        $me             = $this->teacher();

        $this->actingAs($me);
        $this->getJson(route('cms.admin.lessons.show', $lesson))->assertForbidden();
        $this->postJson(route('cms.admin.sections.lessons.store', $foreignSection), $this->lessonPayload())->assertForbidden();
        $this->putJson(route('cms.admin.lessons.update', $lesson), $this->lessonPayload())->assertForbidden();
        $this->deleteJson(route('cms.admin.lessons.destroy', $lesson))->assertForbidden();
    }

    public function test_co_instructor_can_edit_lessons(): void
    {
        $co     = $this->teacher();
        $course = Course::factory()->create();
        $course->coInstructors()->attach($co->id);
        $lesson = $this->lesson($this->section($course));

        $this->actingAs($co)
            ->putJson(route('cms.admin.lessons.update', $lesson), $this->lessonPayload(['title_en' => 'ByCo']))
            ->assertOk();

        $this->assertDatabaseHas('lessons', ['id' => $lesson->id, 'title_en' => 'ByCo']);
    }

    public function test_reorder_lessons(): void
    {
        $section = $this->section(Course::factory()->create());
        $a = $this->lesson($section, ['sort_order' => 1]);
        $b = $this->lesson($section, ['sort_order' => 2]);

        $admin = $this->admin();
        $this->actingAs($admin)
            ->postJson(route('cms.admin.sections.lessons.reorder', $section), ['ids' => [$b->id, $a->id]])
            ->assertOk();

        $this->assertSame(1, $b->fresh()->sort_order);

        $this->postJson(route('cms.admin.sections.lessons.reorder', $section), ['ids' => [$a->id]])->assertUnprocessable();
    }

    /* ---------- الحذف المؤقت ---------- */

    public function test_soft_delete_list_restore_flow(): void
    {
        $course = Course::factory()->create();
        $lesson = $this->lesson($this->section($course), ['title_en' => 'GoneLessonQ']);
        $admin  = $this->admin();

        $this->actingAs($admin)->deleteJson(route('cms.admin.lessons.destroy', $lesson))->assertOk();
        $this->assertSoftDeleted('lessons', ['id' => $lesson->id]);

        $this->getJson(route('cms.admin.courses.lessons.trashed', $course))
            ->assertOk()->assertJsonPath('data.0.title_en', 'GoneLessonQ');

        $this->getJson(route('cms.admin.lessons.show', $lesson->id))->assertNotFound();

        $this->patchJson(route('cms.admin.lessons.restore', $lesson->id))->assertOk();
        $this->assertNotSoftDeleted('lessons', ['id' => $lesson->id]);
    }

    public function test_restore_and_force_delete_return_404_for_active_lesson(): void
    {
        $lesson = $this->lesson($this->section(Course::factory()->create()));
        $admin  = $this->admin();

        $this->actingAs($admin)->patchJson(route('cms.admin.lessons.restore', $lesson->id))->assertNotFound();
        $this->deleteJson(route('cms.admin.lessons.force-delete', $lesson->id))->assertNotFound();
    }

    public function test_force_delete_is_admin_only_and_removes_files(): void
    {
        $owner   = $this->teacher();
        $course  = Course::factory()->create(['instructor_id' => $owner->id]);
        $lesson  = $this->lesson($this->section($course));

        $path = $this->upload($lesson, $owner)->assertCreated()->json('data.id');
        $stored = LessonAttachment::find($path)->file_path;
        Storage::disk('local')->assertExists($stored);

        $lesson->delete();

        $this->actingAs($owner)->deleteJson(route('cms.admin.lessons.force-delete', $lesson->id))->assertForbidden();

        $this->actingAs($this->admin())->deleteJson(route('cms.admin.lessons.force-delete', $lesson->id))->assertOk();

        $this->assertDatabaseMissing('lessons', ['id' => $lesson->id]);
        $this->assertDatabaseMissing('lesson_attachments', ['lesson_id' => $lesson->id]);
        Storage::disk('local')->assertMissing($stored);
    }

    /* ---------- المرفقات ---------- */

    public function test_upload_stores_on_private_disk_with_random_name(): void
    {
        $lesson = $this->lesson($this->section(Course::factory()->create()));

        $id = $this->upload($lesson)->assertCreated()->assertJsonPath('data.title', 'guide')->json('data.id');

        $att = LessonAttachment::find($id);
        $this->assertStringNotContainsString('guide', $att->file_path);
        $this->assertGreaterThan(0, $att->file_size);
        Storage::disk('local')->assertExists($att->file_path);
        Storage::disk('public')->assertMissing($att->file_path);
    }

    public function test_upload_rejects_dangerous_oversized_and_missing_files(): void
    {
        $lesson = $this->lesson($this->section(Course::factory()->create()));
        $admin  = $this->admin();

        $this->upload($lesson, $admin, UploadedFile::fake()->create('shell.php', 1))
            ->assertUnprocessable()->assertJsonValidationErrors('file');
        $this->upload($lesson, $admin, UploadedFile::fake()->create('run.exe', 1))
            ->assertUnprocessable()->assertJsonValidationErrors('file');
        $this->upload($lesson, $admin, UploadedFile::fake()->create('big.pdf', 25000, 'application/pdf'))
            ->assertUnprocessable()->assertJsonValidationErrors('file');

        $this->actingAs($admin)->postJson(route('cms.admin.lessons.attachments.store', $lesson), [])
            ->assertUnprocessable()->assertJsonValidationErrors('file');
    }

    public function test_upload_limit_per_lesson(): void
    {
        $lesson = $this->lesson($this->section(Course::factory()->create()));
        for ($i = 0; $i < 20; $i++) {
            LessonAttachment::create(['lesson_id' => $lesson->id, 'title' => "f{$i}", 'file_path' => "x/{$i}.pdf", 'file_size' => 1]);
        }

        $this->upload($lesson)->assertStatus(409);
        $this->assertSame(20, $lesson->attachments()->count());
    }

    public function test_instructor_cannot_upload_to_foreign_lesson(): void
    {
        $lesson = $this->lesson($this->section(Course::factory()->create()));

        $this->upload($lesson, $this->teacher())->assertForbidden();
    }

    public function test_download_requires_access_and_streams_file(): void
    {
        $owner  = $this->teacher();
        $course = Course::factory()->create(['instructor_id' => $owner->id]);
        $lesson = $this->lesson($this->section($course));
        $id     = $this->upload($lesson, $owner)->json('data.id');
        $att    = LessonAttachment::find($id);

        $this->actingAs($owner)->get(route('cms.admin.attachments.download', $att))->assertOk()->assertDownload();
        $this->actingAs($this->teacher())->get(route('cms.admin.attachments.download', $att))->assertForbidden();

        Storage::disk('local')->delete($att->file_path);
        $this->actingAs($owner)->get(route('cms.admin.attachments.download', $att))->assertNotFound();
    }

    public function test_delete_attachment_removes_row_and_file(): void
    {
        $lesson = $this->lesson($this->section(Course::factory()->create()));
        $admin  = $this->admin();
        $att    = LessonAttachment::find($this->upload($lesson, $admin)->json('data.id'));

        $this->actingAs($admin)->deleteJson(route('cms.admin.attachments.destroy', $att))->assertOk();

        $this->assertDatabaseMissing('lesson_attachments', ['id' => $att->id]);
        Storage::disk('local')->assertMissing($att->file_path);
    }

    /* ---------- العلاقات والصلاحيات ---------- */

    public function test_relations_resolve(): void
    {
        $course  = Course::factory()->create();
        $section = $this->section($course);
        $lesson  = $this->lesson($section);

        $this->assertTrue($section->course->is($course));
        $this->assertTrue($course->sections->first()->is($section));
        $this->assertTrue($course->lessons->first()->is($lesson));
        $this->assertTrue($lesson->owningCourse()->is($course));
    }

    public function test_user_without_permissions_is_forbidden(): void
    {
        $course = Course::factory()->create();

        $this->actingAs(User::factory()->create())
            ->getJson(route('cms.admin.courses.curriculum', $course))
            ->assertForbidden();
    }
    public function test_tree_reports_edit_capability(): void
    {
        $course = Course::factory()->create();
        $viewer = User::factory()->create();
        $viewer->givePermissionTo(['courses.view', 'courses.manage-all']); // يرى ولا يملك courses.edit

        $this->actingAs($viewer)
            ->getJson(route('cms.admin.courses.curriculum', $course))
            ->assertOk()->assertJsonPath('can_edit', false);

        $this->actingAs($this->admin())
            ->getJson(route('cms.admin.courses.curriculum', $course))
            ->assertOk()->assertJsonPath('can_edit', true);
    }

    public function test_courses_table_exposes_curriculum_action(): void
    {
        Course::factory()->create();

        $html = $this->actingAs($this->admin())
            ->getJson(route('cms.admin.courses.index'))->json('html');

        $this->assertStringContainsString('data-action="curriculum"', $html);
    }
}
