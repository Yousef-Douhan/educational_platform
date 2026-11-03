<?php

namespace Tests\Feature;

use App\Exceptions\ProgressException;
use App\Models\Course;
use App\Models\CourseProgress;
use App\Models\CourseSection;
use App\Models\Enrollment;
use App\Models\Lesson;
use App\Models\LessonProgress;
use App\Models\Question;
use App\Models\QuestionOption;
use App\Models\Quiz;
use App\Models\QuizAttempt;
use App\Models\User;
use App\Services\ProgressService;
use App\Services\QuizAttemptService;
use Illuminate\Database\Eloquent\MassAssignmentException;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class ProgressModuleTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        app(PermissionRegistrar::class)->forgetCachedPermissions();
        $this->withoutVite();

        foreach (['progress.view', 'progress.recalculate', 'courses.manage-all', 'courses.delete', 'users.force-delete'] as $name) {
            Permission::findOrCreate($name, 'web');
        }
    }

    /* ---------- أدوات ---------- */

    private function progress(): ProgressService
    {
        return app(ProgressService::class);
    }

    private function admin(string ...$permissions): User
    {
        $u = User::factory()->create();
        $u->givePermissionTo($permissions ?: Permission::all());

        return $u;
    }

    /** دورة منشورة بعدد من الدروس المنشورة (مقالات) */
    private function course(int $lessons = 2, ?User $instructor = null): array
    {
        $course  = Course::factory()->published()->create($instructor ? ['instructor_id' => $instructor->id] : []);
        $section = CourseSection::factory()->create(['course_id' => $course->id]);
        $list    = [];

        foreach ($lessons > 0 ? range(1, $lessons) : [] as $i) {
            $list[] = Lesson::factory()->create(['section_id' => $section->id, 'is_published' => true, 'sort_order' => $i]);
        }

        return [$course, $section, $list];
    }

    private function student(Course $course, string $status = 'active'): User
    {
        $u = User::factory()->create();
        Enrollment::factory()->create(['user_id' => $u->id, 'course_id' => $course->id, 'status' => $status]);

        return $u;
    }

    private function assertRejected(string $reason, callable $fn): void
    {
        try {
            $fn();
        } catch (ProgressException $e) {
            $this->assertSame($reason, $e->reason);

            return;
        }

        $this->fail("Expected ProgressException: {$reason}");
    }

    /** درس اختبار بسؤال واحد، وأول خيار صحيح */
    private function quizLesson(Course $course, CourseSection $section): array
    {
        $lesson = Lesson::factory()->create([
            'section_id' => $section->id, 'content_type' => 'quiz', 'content_ar' => null, 'is_published' => true, 'sort_order' => 9,
        ]);
        $quiz = Quiz::factory()->create(['lesson_id' => $lesson->id, 'pass_mark' => 50, 'attempts_allowed' => 5]);
        $q    = Question::factory()->create(['quiz_id' => $quiz->id, 'question_type' => 'multiple_choice', 'points' => 1]);
        foreach (['A' => true, 'B' => false] as $text => $correct) {
            QuestionOption::factory()->create(['question_id' => $q->id, 'option_text' => $text, 'is_correct' => $correct]);
        }

        return [$lesson, $quiz, $q];
    }

    private function takeQuiz(User $student, Quiz $quiz, Question $q, bool $correct): QuizAttempt
    {
        $svc = app(QuizAttemptService::class);
        $a   = $svc->start($student, $quiz);
        $svc->saveAnswer($student, $a, $q->id, (int) QuestionOption::where('question_id', $q->id)->where('is_correct', $correct)->value('id'));

        return $svc->submit($student, $a);
    }

    /* ---------- إكمال الدروس ---------- */

    public function test_completing_a_lesson_records_it_and_updates_the_course(): void
    {
        [$course, , $lessons] = $this->course(2);
        $student = $this->student($course);

        $cp = $this->progress()->complete($student, $lessons[0]);

        $this->assertDatabaseHas('lesson_progress', ['user_id' => $student->id, 'lesson_id' => $lessons[0]->id, 'is_completed' => true]);
        $this->assertSame(1, $cp->completed_lessons_count);
        $this->assertSame(2, $cp->total_lessons_count);
        $this->assertEquals(50, (float) $cp->progress_percentage);
        $this->assertFalse($cp->is_finished);
        $this->assertNull($cp->finished_at);
        $this->assertSame('active', Enrollment::where('user_id', $student->id)->value('status'));
    }

    public function test_completing_twice_changes_nothing(): void
    {
        [$course, , $lessons] = $this->course(2);
        $student = $this->student($course);

        $this->progress()->complete($student, $lessons[0]);
        $first = LessonProgress::first()->completed_at;
        $this->travelTo(now()->addHour());
        $cp = $this->progress()->complete($student, $lessons[0]);

        $this->assertSame(1, LessonProgress::count());
        $this->assertSame(1, CourseProgress::count());
        $this->assertEquals($first, LessonProgress::first()->completed_at);
        $this->assertSame(1, $cp->completed_lessons_count);
    }

    public function test_finishing_the_last_lesson_finishes_the_course_and_completes_the_enrollment(): void
    {
        [$course, , $lessons] = $this->course(2);
        $student = $this->student($course);

        $this->progress()->complete($student, $lessons[0]);
        $cp = $this->progress()->complete($student, $lessons[1]);

        $this->assertTrue($cp->is_finished);
        $this->assertEquals(100, (float) $cp->progress_percentage);
        $this->assertNotNull($cp->finished_at);
        $this->assertSame('completed', Enrollment::where('user_id', $student->id)->value('status'));
        // صار مؤهلًا للشهادة
        $this->assertTrue(Enrollment::eligibleForCertificate()->where('user_id', $student->id)->exists());
    }

    public function test_only_published_existing_lessons_count(): void
    {
        [$course, $section, $lessons] = $this->course(2);
        Lesson::factory()->create(['section_id' => $section->id, 'is_published' => false]);
        $gone = Lesson::factory()->create(['section_id' => $section->id, 'is_published' => true]);
        $gone->delete();
        $student = $this->student($course);

        $this->progress()->complete($student, $lessons[0]);
        $cp = $this->progress()->complete($student, $lessons[1]);

        $this->assertSame(2, $cp->total_lessons_count);
        $this->assertTrue($cp->is_finished);
    }

    public function test_a_course_without_published_lessons_is_never_finished(): void
    {
        [$course] = $this->course(0);
        $student  = $this->student($course);

        $cp = $this->progress()->recalculate($student, $course);

        $this->assertSame(0, $cp->total_lessons_count);
        $this->assertEquals(0, (float) $cp->progress_percentage);
        $this->assertFalse($cp->is_finished);
    }

    public function test_completing_requires_a_valid_learning_context(): void
    {
        [$course, $section, $lessons] = $this->course(1);

        $stranger = User::factory()->create();
        $this->assertRejected('not_enrolled', fn () => $this->progress()->complete($stranger, $lessons[0]));

        foreach (['cancelled', 'expired'] as $status) {
            $other = $this->student($course, $status);
            $this->assertRejected('not_enrolled', fn () => $this->progress()->complete($other, $lessons[0]));
        }

        $lapsed = User::factory()->create();
        Enrollment::factory()->expiresAt(now()->subDay())->create(['user_id' => $lapsed->id, 'course_id' => $course->id]);
        $this->assertRejected('not_enrolled', fn () => $this->progress()->complete($lapsed, $lessons[0]));

        $student = $this->student($course);

        $draft = Lesson::factory()->create(['section_id' => $section->id, 'is_published' => false]);
        $this->assertRejected('lesson_unavailable', fn () => $this->progress()->complete($student, $draft));

        $trashed = Lesson::factory()->create(['section_id' => $section->id, 'is_published' => true]);
        $trashed->delete();
        $this->assertRejected('lesson_unavailable', fn () => $this->progress()->complete($student, $trashed));

        $gone = User::factory()->create();
        Enrollment::factory()->create(['user_id' => $gone->id, 'course_id' => $course->id]);
        $gone->delete();
        $this->assertRejected('student_inactive', fn () => $this->progress()->complete($gone, $lessons[0]));

        $this->assertSame(0, LessonProgress::count());
        $this->assertSame(0, CourseProgress::count());
    }

    public function test_a_deleted_course_blocks_progress(): void
    {
        [$course, , $lessons] = $this->course(1);
        $student = $this->student($course);
        $course->delete();

        $this->assertRejected('lesson_unavailable', fn () => $this->progress()->complete($student, $lessons[0]));
    }

    public function test_completed_enrollments_can_keep_learning(): void
    {
        [$course, , $lessons] = $this->course(2);
        $student = $this->student($course, 'completed');

        $this->assertSame(1, $this->progress()->complete($student, $lessons[0])->completed_lessons_count);
    }

    /* ---------- التراجع والتغييرات ---------- */

    public function test_uncomplete_lowers_progress_but_keeps_a_completed_enrollment(): void
    {
        [$course, , $lessons] = $this->course(2);
        $student = $this->student($course);
        $this->progress()->complete($student, $lessons[0]);
        $this->progress()->complete($student, $lessons[1]);

        $cp = $this->progress()->uncomplete($student, $lessons[1]);

        $this->assertSame(1, $cp->completed_lessons_count);
        $this->assertEquals(50, (float) $cp->progress_percentage);
        $this->assertFalse($cp->is_finished);
        $this->assertNull($cp->finished_at);
        $this->assertDatabaseHas('lesson_progress', ['lesson_id' => $lessons[1]->id, 'is_completed' => false, 'completed_at' => null]);
        // لا نسحب أهلية شهادة صدرت أو ستصدر
        $this->assertSame('completed', Enrollment::where('user_id', $student->id)->value('status'));
    }

    public function test_recalculate_follows_lessons_added_or_removed_later(): void
    {
        [$course, $section, $lessons] = $this->course(2);
        $student = $this->student($course);
        $this->progress()->complete($student, $lessons[0]);
        $this->progress()->complete($student, $lessons[1]);
        $finishedAt = CourseProgress::first()->finished_at;

        Lesson::factory()->create(['section_id' => $section->id, 'is_published' => true]);
        $cp = $this->progress()->recalculate($student, $course);
        $this->assertFalse($cp->is_finished);
        $this->assertNull($cp->finished_at);
        $this->assertSame(3, $cp->total_lessons_count);
        $this->assertEquals(66.67, (float) $cp->progress_percentage);

        // حذف الدرس الجديد يرجّعها منتهية، ووقت الإنهاء يُسجَّل من جديد
        Lesson::where('section_id', $section->id)->where('sort_order', 0)->get()->each->delete();
        $cp = $this->progress()->recalculate($student, $course);
        $this->assertTrue($cp->is_finished);
        $this->assertNotNull($cp->finished_at);
        $this->assertNotNull($finishedAt);
    }

    public function test_finished_at_stays_fixed_across_recalculations(): void
    {
        [$course, , $lessons] = $this->course(1);
        $student = $this->student($course);
        $this->progress()->complete($student, $lessons[0]);
        $first = CourseProgress::first()->finished_at;

        $this->travelTo(now()->addDay());
        $this->progress()->recalculate($student, $course);

        $this->assertEquals($first, CourseProgress::first()->finished_at);
    }

    public function test_recalculate_all_repairs_drift_and_can_target_one_course(): void
    {
        [$courseA, , $la] = $this->course(2);
        [$courseB, , $lb] = $this->course(2);
        $student = User::factory()->create();
        foreach ([$courseA, $courseB] as $c) {
            Enrollment::factory()->create(['user_id' => $student->id, 'course_id' => $c->id]);
        }
        $this->progress()->complete($student, $la[0]);
        $this->progress()->complete($student, $lb[0]);

        CourseProgress::query()->update(['completed_lessons_count' => 9, 'progress_percentage' => 99, 'is_finished' => true]);

        $this->assertSame(1, $this->progress()->recalculateAll($courseA->id));
        $this->assertEquals(1, CourseProgress::where('course_id', $courseA->id)->value('completed_lessons_count'));
        $this->assertEquals(9, CourseProgress::where('course_id', $courseB->id)->value('completed_lessons_count'));

        $this->assertSame(2, $this->progress()->recalculateAll());
        $this->assertEquals(1, CourseProgress::where('course_id', $courseB->id)->value('completed_lessons_count'));
        $this->assertFalse((bool) CourseProgress::where('course_id', $courseB->id)->value('is_finished'));
    }

    public function test_recalculate_command_runs(): void
    {
        $this->artisan('progress:recalculate')->expectsOutputToContain('Recalculated 0')->assertSuccessful();
    }

    /* ---------- دروس الاختبار ---------- */

    public function test_quiz_lessons_cannot_be_completed_or_undone_by_hand(): void
    {
        [$course, $section] = $this->course(1);
        [$lesson]           = $this->quizLesson($course, $section);
        $student            = $this->student($course);

        $this->assertRejected('quiz_not_passed', fn () => $this->progress()->complete($student, $lesson));
        $this->assertRejected('quiz_lesson_locked', fn () => $this->progress()->uncomplete($student, $lesson));
        $this->assertSame(0, LessonProgress::count());
    }

    public function test_passing_the_quiz_completes_its_lesson_automatically(): void
    {
        [$course, $section, $lessons] = $this->course(1);
        [$lesson, $quiz, $q]          = $this->quizLesson($course, $section);
        $student                      = $this->student($course);

        $this->progress()->complete($student, $lessons[0]);
        $attempt = $this->takeQuiz($student, $quiz, $q, true);

        $this->assertTrue($attempt->is_passed);
        $this->assertDatabaseHas('lesson_progress', ['user_id' => $student->id, 'lesson_id' => $lesson->id, 'is_completed' => true]);
        $cp = CourseProgress::where(['user_id' => $student->id, 'course_id' => $course->id])->first();
        $this->assertTrue($cp->is_finished);
        $this->assertSame('completed', Enrollment::where('user_id', $student->id)->value('status'));

        // وبعد النجاح يصبح إكمال اليدوي مسموحًا (مثبت بالنتيجة) وتكراره لا يضاعف
        $this->progress()->complete($student, $lesson);
        $this->assertSame(1, LessonProgress::where('lesson_id', $lesson->id)->count());
    }

    public function test_failing_the_quiz_completes_nothing(): void
    {
        [$course, $section] = $this->course(0);
        [, $quiz, $q]       = $this->quizLesson($course, $section);
        $student            = $this->student($course);

        $attempt = $this->takeQuiz($student, $quiz, $q, false);

        $this->assertFalse($attempt->is_passed);
        $this->assertSame(0, LessonProgress::count());
        $this->assertSame(0, CourseProgress::count());
    }

    public function test_voiding_the_only_pass_takes_the_lesson_back(): void
    {
        [$course, $section] = $this->course(0);
        [$lesson, $quiz, $q] = $this->quizLesson($course, $section);
        $student             = $this->student($course);
        $attempt             = $this->takeQuiz($student, $quiz, $q, true);

        $this->assertTrue(CourseProgress::first()->is_finished);

        app(QuizAttemptService::class)->void($attempt, $this->admin(), 'cheating');

        $this->assertDatabaseHas('lesson_progress', ['lesson_id' => $lesson->id, 'is_completed' => false]);
        $cp = CourseProgress::first();
        $this->assertSame(0, $cp->completed_lessons_count);
        $this->assertFalse($cp->is_finished);
    }

    public function test_voiding_one_of_two_passes_keeps_the_lesson(): void
    {
        [$course, $section] = $this->course(0);
        [$lesson, $quiz, $q] = $this->quizLesson($course, $section);
        $student             = $this->student($course);
        $first               = $this->takeQuiz($student, $quiz, $q, true);
        $this->takeQuiz($student, $quiz, $q, true);

        app(QuizAttemptService::class)->void($first, $this->admin(), 'duplicate');

        $this->assertDatabaseHas('lesson_progress', ['lesson_id' => $lesson->id, 'is_completed' => true]);
    }

    public function test_a_pass_after_enrollment_ended_still_grades_but_records_no_progress(): void
    {
        [$course, $section] = $this->course(0);
        [, $quiz, $q]       = $this->quizLesson($course, $section);
        $student            = $this->student($course);
        $svc                = app(QuizAttemptService::class);

        $a = $svc->start($student, $quiz);
        $svc->saveAnswer($student, $a, $q->id, (int) QuestionOption::where('question_id', $q->id)->where('is_correct', true)->value('id'));
        Enrollment::where('user_id', $student->id)->update(['status' => 'cancelled']);

        $done = $svc->submit($student, $a);

        $this->assertTrue($done->is_passed);
        $this->assertSame(0, LessonProgress::count());
    }

    /* ---------- قيود البيانات ---------- */

    public function test_database_keeps_one_row_per_student_and_lesson_or_course(): void
    {
        [$course, , $lessons] = $this->course(1);
        $user = User::factory()->create();
        LessonProgress::factory()->create(['user_id' => $user->id, 'lesson_id' => $lessons[0]->id]);
        CourseProgress::factory()->create(['user_id' => $user->id, 'course_id' => $course->id]);

        try {
            LessonProgress::factory()->create(['user_id' => $user->id, 'lesson_id' => $lessons[0]->id]);
            $this->fail('Duplicate lesson progress accepted');
        } catch (UniqueConstraintViolationException) {
            $this->assertSame(1, LessonProgress::count());
        }

        $this->expectException(UniqueConstraintViolationException::class);
        CourseProgress::factory()->create(['user_id' => $user->id, 'course_id' => $course->id]);
    }

    public function test_progress_models_are_not_mass_assignable(): void
    {
        try {
            LessonProgress::create(['user_id' => 1, 'lesson_id' => 1, 'is_completed' => true]);
            $this->fail('LessonProgress accepted mass assignment');
        } catch (MassAssignmentException) {
            $this->addToAssertionCount(1);
        }

        $this->expectException(MassAssignmentException::class);
        CourseProgress::create(['user_id' => 1, 'course_id' => 1, 'progress_percentage' => 100, 'is_finished' => true]);
    }

    public function test_a_user_with_progress_cannot_be_deleted_forever(): void
    {
        [$course, , $lessons] = $this->course(1);
        $student = $this->student($course);
        $this->progress()->complete($student, $lessons[0]);
        $student->delete();

        $this->actingAs($this->admin('users.force-delete'))
            ->deleteJson(route('cms.admin.users.force-delete', $student->id))
            ->assertStatus(409);

        $this->assertDatabaseHas('users', ['id' => $student->id]);
    }

    public function test_a_lesson_with_progress_cannot_be_deleted_forever(): void
    {
        [$course, , $lessons] = $this->course(2);
        $student = $this->student($course);
        $this->progress()->complete($student, $lessons[0]);
        $lessons[0]->delete();

        $this->actingAs($this->admin())
            ->deleteJson(route('cms.admin.lessons.force-delete', $lessons[0]->id))
            ->assertStatus(409);
    }

    /* ---------- شاشة الإدارة ---------- */

    public function test_screens_require_permissions(): void
    {
        $row    = CourseProgress::factory()->create();
        $nobody = User::factory()->create();

        $this->actingAs($nobody)->get(route('cms.admin.progress.index'))->assertForbidden();
        $this->getJson(route('cms.admin.progress.show', $row->id))->assertForbidden();
        $this->postJson(route('cms.admin.progress.recalculate', $row->id))->assertForbidden();

        $viewer = $this->admin('progress.view', 'courses.manage-all');
        $this->actingAs($viewer)->get(route('cms.admin.progress.index'))->assertOk();
        $this->getJson(route('cms.admin.progress.show', $row->id))->assertOk();
        $this->postJson(route('cms.admin.progress.recalculate', $row->id))->assertForbidden();
    }

    public function test_guests_are_redirected_and_no_write_routes_exist(): void
    {
        $this->get(route('cms.admin.progress.index'))->assertRedirect();

        $row = CourseProgress::factory()->create();
        $this->actingAs($this->admin());
        $this->postJson(route('cms.admin.progress.index'), [])->assertStatus(405);
        $this->putJson(route('cms.admin.progress.show', $row->id), ['progress_percentage' => 100])->assertStatus(405);
        $this->deleteJson(route('cms.admin.progress.show', $row->id))->assertStatus(405);
        $this->assertSame(1, CourseProgress::count());
    }

    public function test_index_renders_and_escapes_names(): void
    {
        $evil = User::factory()->create(['name' => '<script>alert(1)</script>']);
        CourseProgress::factory()->create(['user_id' => $evil->id]);
        $gone = User::factory()->create(['name' => 'Gone Learner']);
        CourseProgress::factory()->create(['user_id' => $gone->id]);
        $gone->delete();

        $this->actingAs($this->admin())->get(route('cms.admin.progress.index'))
            ->assertOk()
            ->assertDontSee('<script>alert(1)</script>', false)
            ->assertSee('&lt;script&gt;', false)
            ->assertSee('Gone Learner');
    }

    public function test_filters_and_stats(): void
    {
        $courseA = Course::factory()->published()->create(['title_ar' => 'Alpha Course', 'title_en' => 'Alpha Course']);
        $courseB = Course::factory()->published()->create(['title_ar' => 'Beta Course', 'title_en' => 'Beta Course']);
        $ann     = User::factory()->create(['name' => 'Ann Learner']);
        $bob     = User::factory()->create(['name' => 'Bob Learner']);

        CourseProgress::factory()->percent(40)->create(['user_id' => $ann->id, 'course_id' => $courseA->id]);
        CourseProgress::factory()->finished()->create(['user_id' => $bob->id, 'course_id' => $courseB->id]);
        CourseProgress::factory()->percent(10)->create(['user_id' => $bob->id, 'course_id' => $courseA->id]);

        $this->actingAs($this->admin());
        $html = fn (array $q) => $this->getJson(route('cms.admin.progress.index', $q))->assertOk()->json('html');

        $this->assertStringContainsString('Ann Learner', $html(['search' => 'Ann']));
        $this->assertStringNotContainsString('Bob Learner', $html(['search' => 'Ann']));
        $this->assertStringContainsString('Beta Course', $html(['search' => 'Beta']));
        $this->assertStringNotContainsString('Ann Learner', $html(['state' => 'finished']));
        $this->assertStringNotContainsString('Beta Course', $html(['state' => 'in_progress']));
        $this->assertStringNotContainsString('Beta Course', $html(['course_id' => $courseA->id]));
        $this->getJson(route('cms.admin.progress.index', ['sort' => 'drop table', 'dir' => 'x']))->assertOk();

        $stats = $this->getJson(route('cms.admin.progress.index'))->json('stats');
        $this->assertSame(3, $stats['total']);
        $this->assertSame(1, $stats['finished']);
        $this->assertSame(2, $stats['in_progress']);
        $this->assertSame('50.0', $stats['average']);
    }

    public function test_show_lists_lessons_with_completion(): void
    {
        [$course, , $lessons] = $this->course(2);
        $student = $this->student($course);
        $this->progress()->complete($student, $lessons[0]);
        $row = CourseProgress::first();

        $this->actingAs($this->admin())->getJson(route('cms.admin.progress.show', $row->id))
            ->assertOk()
            ->assertJsonPath('data.completed', 1)
            ->assertJsonPath('data.total', 2)
            ->assertJsonPath('data.is_finished', false)
            ->assertJsonPath('data.enrollment', 'active')
            ->assertJsonPath('data.lessons.0.is_completed', true)
            ->assertJsonPath('data.lessons.1.is_completed', false)
            ->assertJsonPath('data.lessons.1.completed_at', null);
    }

    public function test_recalculate_endpoint_repairs_a_record(): void
    {
        [$course, , $lessons] = $this->course(2);
        $student = $this->student($course);
        $this->progress()->complete($student, $lessons[0]);
        $row = CourseProgress::first();
        CourseProgress::query()->update(['progress_percentage' => 99, 'completed_lessons_count' => 7]);

        $this->actingAs($this->admin())->postJson(route('cms.admin.progress.recalculate', $row->id))
            ->assertOk()->assertJsonStructure(['message', 'stats']);

        $this->assertSame(1, $row->fresh()->completed_lessons_count);
        $this->assertEquals(50, (float) $row->fresh()->progress_percentage);
    }

    public function test_instructors_only_see_progress_of_their_own_courses(): void
    {
        $teacher = User::factory()->create();
        $teacher->givePermissionTo(['progress.view', 'progress.recalculate']);

        $mine  = Course::factory()->published()->create(['instructor_id' => $teacher->id, 'title_ar' => 'Mine Course', 'title_en' => 'Mine Course']);
        $other = Course::factory()->published()->create(['title_ar' => 'Theirs Course', 'title_en' => 'Theirs Course']);
        $own   = CourseProgress::factory()->create(['course_id' => $mine->id]);
        $alien = CourseProgress::factory()->create(['course_id' => $other->id]);

        $this->actingAs($teacher);

        $html = $this->getJson(route('cms.admin.progress.index'))->assertOk()->json('html');
        $this->assertStringContainsString('Mine Course', $html);
        $this->assertStringNotContainsString('Theirs Course', $html);
        $this->assertSame(1, $this->getJson(route('cms.admin.progress.index'))->json('stats.total'));

        $this->getJson(route('cms.admin.progress.show', $own->id))->assertOk();
        $this->getJson(route('cms.admin.progress.show', $alien->id))->assertNotFound();
        $this->postJson(route('cms.admin.progress.recalculate', $alien->id))->assertNotFound();
        $this->postJson(route('cms.admin.progress.recalculate', $own->id))->assertOk();
    }

    public function test_deleted_courses_and_students_still_appear_for_admins(): void
    {
        $course  = Course::factory()->published()->create(['title_ar' => 'Retired Course', 'title_en' => 'Retired Course']);
        $student = User::factory()->create();
        $row     = CourseProgress::factory()->create(['course_id' => $course->id, 'user_id' => $student->id]);
        $course->delete();
        $student->delete();

        $this->actingAs($this->admin())->getJson(route('cms.admin.progress.show', $row->id))
            ->assertOk()->assertJsonPath('data.user.deleted', true);
        $this->assertStringContainsString('Retired Course', $this->getJson(route('cms.admin.progress.index'))->json('html'));
    }
}
