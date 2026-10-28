<?php

namespace Tests\Feature;

use App\Models\Course;
use App\Models\CourseSection;
use App\Models\Lesson;
use App\Models\Question;
use App\Models\QuestionOption;
use App\Models\Quiz;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class QuizModuleTest extends TestCase
{
    use RefreshDatabase;

    private bool $createdAttemptsTable = false;

    protected function setUp(): void
    {
        parent::setUp();
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        foreach (['courses', 'quizzes'] as $res) {
            foreach (['view', 'create', 'edit', 'delete'] as $a) {
                Permission::findOrCreate("{$res}.{$a}", 'web');
            }
        }
        foreach (['courses.manage-all', 'courses.publish'] as $n) {
            Permission::findOrCreate($n, 'web');
        }

        Role::findOrCreate('instructor', 'web')->givePermissionTo([
            'courses.view',
            'courses.create',
            'courses.edit',
            'courses.delete',
            'quizzes.view',
            'quizzes.create',
            'quizzes.edit',
            'quizzes.delete',
        ]);
    }

    protected function tearDown(): void
    {
        if ($this->createdAttemptsTable) {
            Schema::dropIfExists('quiz_attempts');
        }
        parent::tearDown();
    }

    /* ---------- أدوات ---------- */

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

    private function quizLesson(?Course $course = null, array $o = []): Lesson
    {
        $course ??= Course::factory()->published()->create();
        $section = CourseSection::factory()->create(['course_id' => $course->id]);

        return Lesson::factory()->create(array_merge(
            ['section_id' => $section->id, 'content_type' => 'quiz', 'content_ar' => null],
            $o
        ));
    }

    private function quiz(?Course $course = null, array $o = []): Quiz
    {
        return Quiz::factory()->create(array_merge(['lesson_id' => $this->quizLesson($course)->id], $o));
    }

    /** سؤال اختيار من متعدد: A صحيح، B، C */
    private function mcq(Quiz $quiz, array $o = []): Question
    {
        $q = Question::factory()->create(array_merge(['quiz_id' => $quiz->id], $o));
        foreach (['A' => true, 'B' => false, 'C' => false] as $i => $correct) {
            QuestionOption::factory()->create([
                'question_id' => $q->id,
                'option_text' => $i,
                'is_correct' => $correct,
                'sort_order' => ord($i) - 64,
            ]);
        }

        return $q;
    }

    /** يحاكي وجود محاولات (الجدول الحقيقي يُبنى في وحدة لاحقة) */
    private function attempt(Quiz $quiz): void
    {
        if (! Schema::hasTable('quiz_attempts')) {
            Schema::create('quiz_attempts', function ($t) {
                $t->id();
                $t->unsignedBigInteger('quiz_id');
            });
            $this->createdAttemptsTable = true;
        }

        DB::table('quiz_attempts')->insert(['quiz_id' => $quiz->id]);
    }

    private function quizPayload(Lesson $lesson, array $o = []): array
    {
        return array_merge([
            'lesson_id' => $lesson->id,
            'title_ar' => 'اختبار',
            'title_en' => 'Quiz',
            'pass_mark' => 60,
            'time_limit_minutes' => 30,
            'attempts_allowed' => 2,
        ], $o);
    }

    private function mcqPayload(array $o = []): array
    {
        return array_merge([
            'question_text' => 'What is 2+2?',
            'question_type' => 'multiple_choice',
            'points' => 2,
            'options' => [
                ['option_text' => '3', 'is_correct' => false],
                ['option_text' => '4', 'is_correct' => true],
                ['option_text' => '5', 'is_correct' => false],
            ],
        ], $o);
    }

    /* ---------- قائمة الاختبارات ---------- */

    public function test_index_json_returns_real_stats(): void
    {
        $this->mcq($this->quiz());
        $this->quiz();
        $this->quiz()->delete();

        $this->actingAs($this->admin())->getJson(route('cms.admin.quizzes.index'))
            ->assertOk()
            ->assertJsonPath('stats.total', 2)
            ->assertJsonPath('stats.ready', 1)
            ->assertJsonPath('stats.empty', 1)
            ->assertJsonPath('stats.trashed', 1);
    }

    public function test_instructor_sees_only_own_and_shared_quizzes(): void
    {
        $me      = $this->teacher();
        $own     = Course::factory()->published()->create(['instructor_id' => $me->id]);
        $shared  = Course::factory()->published()->create();
        $shared->coInstructors()->attach($me->id);

        $this->quiz($own, ['title_en' => 'OwnQuizQ']);
        $this->quiz($shared, ['title_en' => 'SharedQuizQ']);
        $this->quiz(null, ['title_en' => 'ForeignQuizQ']);

        $html = $this->actingAs($me)->getJson(route('cms.admin.quizzes.index'))->assertOk()->json('html');

        $this->assertStringContainsString('OwnQuizQ', $html);
        $this->assertStringContainsString('SharedQuizQ', $html);
        $this->assertStringNotContainsString('ForeignQuizQ', $html);
    }

    public function test_index_filters_by_search_course_and_readiness(): void
    {
        $courseA = Course::factory()->published()->create();
        $ready   = $this->quiz($courseA, ['title_en' => 'ReadyAlpha']);
        $this->mcq($ready);
        $this->quiz(null, ['title_en' => 'EmptyBeta']);
        $admin = $this->admin();

        $search = $this->actingAs($admin)->getJson(route('cms.admin.quizzes.index', ['search' => 'Alpha']))->json('html');
        $this->assertStringContainsString('ReadyAlpha', $search);
        $this->assertStringNotContainsString('EmptyBeta', $search);

        $byCourse = $this->getJson(route('cms.admin.quizzes.index', ['course_id' => $courseA->id]))->json('html');
        $this->assertStringContainsString('ReadyAlpha', $byCourse);
        $this->assertStringNotContainsString('EmptyBeta', $byCourse);

        $empty = $this->getJson(route('cms.admin.quizzes.index', ['readiness' => 'empty']))->json('html');
        $this->assertStringContainsString('EmptyBeta', $empty);
        $this->assertStringNotContainsString('ReadyAlpha', $empty);
    }

    public function test_index_ignores_invalid_sort_column(): void
    {
        $this->quiz();

        $this->actingAs($this->admin())
            ->getJson(route('cms.admin.quizzes.index', ['sort' => 'pass_mark; drop table quizzes']))->assertOk();
    }

    public function test_user_without_permissions_is_forbidden(): void
    {
        $this->actingAs(User::factory()->create())->getJson(route('cms.admin.quizzes.index'))->assertForbidden();
    }

    /* ---------- إنشاء الاختبار ---------- */

    public function test_create_lists_only_free_quiz_lessons_in_visible_courses(): void
    {
        $free   = $this->quizLesson(null, ['title_en' => 'FreeQuizLesson']);
        $taken  = $this->quiz();
        $gone   = $this->quiz();
        $gone->delete();
        Lesson::factory()->create(['title_en' => 'ArticleLesson']);

        $titles = collect($this->actingAs($this->admin())->getJson(route('cms.admin.quizzes.create'))
            ->assertOk()->json('options.lessons'))->pluck('title_en');

        $this->assertTrue($titles->contains('FreeQuizLesson'));
        $this->assertFalse($titles->contains($taken->lesson->title_en));
        $this->assertFalse($titles->contains($gone->lesson->title_en));
        $this->assertFalse($titles->contains('ArticleLesson'));
    }

    public function test_instructor_create_list_excludes_foreign_courses(): void
    {
        $me = $this->teacher();
        $this->quizLesson(Course::factory()->published()->create(['instructor_id' => $me->id]), ['title_en' => 'MineLesson']);
        $this->quizLesson(null, ['title_en' => 'ForeignLesson']);

        $titles = collect($this->actingAs($me)->getJson(route('cms.admin.quizzes.create'))->json('options.lessons'))->pluck('title_en');

        $this->assertTrue($titles->contains('MineLesson'));
        $this->assertFalse($titles->contains('ForeignLesson'));
    }

    public function test_store_creates_quiz(): void
    {
        $lesson = $this->quizLesson();

        $this->actingAs($this->admin())->postJson(route('cms.admin.quizzes.store'), $this->quizPayload($lesson))
            ->assertCreated()->assertJsonPath('data.pass_mark', 60);

        $this->assertDatabaseHas('quizzes', ['lesson_id' => $lesson->id, 'attempts_allowed' => 2, 'time_limit_minutes' => 30]);
    }

    public function test_store_validates_fields_and_bounds(): void
    {
        $lesson = $this->quizLesson();
        $admin  = $this->admin();

        $this->actingAs($admin)->postJson(route('cms.admin.quizzes.store'), [])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['lesson_id', 'title_ar', 'title_en', 'pass_mark', 'time_limit_minutes', 'attempts_allowed']);

        $this->postJson(route('cms.admin.quizzes.store'), $this->quizPayload($lesson, ['pass_mark' => 101]))
            ->assertUnprocessable()->assertJsonValidationErrors('pass_mark');
        $this->postJson(route('cms.admin.quizzes.store'), $this->quizPayload($lesson, ['time_limit_minutes' => -1]))
            ->assertUnprocessable()->assertJsonValidationErrors('time_limit_minutes');
    }

    public function test_store_rejects_non_quiz_lesson_and_lesson_that_already_has_quiz(): void
    {
        $article = Lesson::factory()->create();
        $taken   = $this->quiz();
        $gone    = $this->quiz();
        $gone->delete();
        $admin   = $this->admin();

        $this->actingAs($admin)->postJson(route('cms.admin.quizzes.store'), $this->quizPayload($article))
            ->assertUnprocessable()->assertJsonValidationErrors('lesson_id');
        $this->postJson(route('cms.admin.quizzes.store'), $this->quizPayload($taken->lesson))
            ->assertUnprocessable()->assertJsonValidationErrors('lesson_id');
        $this->postJson(route('cms.admin.quizzes.store'), $this->quizPayload($gone->lesson))
            ->assertUnprocessable()->assertJsonValidationErrors('lesson_id');
    }

    public function test_instructor_cannot_create_quiz_for_foreign_lesson(): void
    {
        $foreign = $this->quizLesson();

        $this->actingAs($this->teacher())->postJson(route('cms.admin.quizzes.store'), $this->quizPayload($foreign))
            ->assertUnprocessable()->assertJsonValidationErrors('lesson_id');
    }

    public function test_viewer_without_create_permission_is_forbidden(): void
    {
        $viewer = User::factory()->create();
        $viewer->givePermissionTo('quizzes.view');

        $this->actingAs($viewer)->postJson(route('cms.admin.quizzes.store'), $this->quizPayload($this->quizLesson()))
            ->assertForbidden();
    }

    /* ---------- العرض والتعديل ---------- */

    public function test_show_and_edit_return_summary(): void
    {
        $quiz = $this->quiz();
        $this->mcq($quiz, ['points' => 3]);

        $this->actingAs($this->admin())->getJson(route('cms.admin.quizzes.show', $quiz))
            ->assertOk()
            ->assertJsonPath('data.questions_count', 1)
            ->assertJsonPath('data.total_points', 3)
            ->assertJsonPath('data.has_attempts', false);

        $this->getJson(route('cms.admin.quizzes.edit', $quiz))->assertOk()->assertJsonPath('data.id', $quiz->id);
    }

    public function test_instructor_cannot_view_or_edit_foreign_quiz(): void
    {
        $foreign = $this->quiz();

        $this->actingAs($this->teacher());
        $this->getJson(route('cms.admin.quizzes.show', $foreign))->assertForbidden();
        $this->putJson(route('cms.admin.quizzes.update', $foreign), $this->quizPayload($foreign->lesson))->assertForbidden();
    }

    public function test_update_changes_settings_but_not_the_lesson(): void
    {
        $quiz  = $this->quiz();
        $other = $this->quizLesson();

        $this->actingAs($this->admin())->putJson(
            route('cms.admin.quizzes.update', $quiz),
            $this->quizPayload($other, ['title_en' => 'Renamed', 'pass_mark' => 80])
        )->assertOk();

        $quiz->refresh();
        $this->assertSame('Renamed', $quiz->title_en);
        $this->assertSame(80, $quiz->pass_mark);
        $this->assertNotSame($other->id, $quiz->lesson_id);
    }

    /* ---------- الحذف المؤقت ---------- */

    public function test_soft_delete_trashed_restore_and_force_delete_flow(): void
    {
        $quiz = $this->quiz();
        $q    = $this->mcq($quiz);
        $admin = $this->admin();

        $this->actingAs($admin)->deleteJson(route('cms.admin.quizzes.destroy', $quiz))->assertOk()->assertJsonPath('stats.trashed', 1);
        $this->assertSoftDeleted('quizzes', ['id' => $quiz->id]);

        $html = $this->getJson(route('cms.admin.quizzes.trashed'))->assertOk()->json('html');
        $this->assertStringContainsString($quiz->title_en, $html);

        $this->patchJson(route('cms.admin.quizzes.restore', $quiz->id))->assertOk();
        $this->assertNotSoftDeleted('quizzes', ['id' => $quiz->id]);

        $quiz->delete();
        $this->deleteJson(route('cms.admin.quizzes.force-delete', $quiz->id))->assertOk();
        $this->assertDatabaseMissing('quizzes', ['id' => $quiz->id]);
        $this->assertDatabaseMissing('questions', ['id' => $q->id]);
        $this->assertDatabaseMissing('question_options', ['question_id' => $q->id]);
    }

    public function test_restore_is_blocked_while_lesson_is_trashed(): void
    {
        $quiz = $this->quiz();
        $quiz->delete();
        $quiz->lesson->delete();

        $this->actingAs($this->admin())->patchJson(route('cms.admin.quizzes.restore', $quiz->id))->assertStatus(409);
    }

    public function test_restore_and_force_delete_return_404_for_active_quiz(): void
    {
        $quiz  = $this->quiz();
        $admin = $this->admin();

        $this->actingAs($admin)->patchJson(route('cms.admin.quizzes.restore', $quiz->id))->assertNotFound();
        $this->deleteJson(route('cms.admin.quizzes.force-delete', $quiz->id))->assertNotFound();
    }

    public function test_force_delete_is_admin_only(): void
    {
        $owner = $this->teacher();
        $quiz  = $this->quiz(Course::factory()->published()->create(['instructor_id' => $owner->id]));
        $quiz->delete();

        $this->actingAs($owner)->deleteJson(route('cms.admin.quizzes.force-delete', $quiz->id))->assertForbidden();
    }

    public function test_force_delete_is_blocked_when_attempts_exist(): void
    {
        $quiz = $this->quiz();
        $this->attempt($quiz);
        $quiz->delete();

        $this->actingAs($this->admin())->deleteJson(route('cms.admin.quizzes.force-delete', $quiz->id))->assertStatus(409);
        $this->assertDatabaseHas('quizzes', ['id' => $quiz->id]);
    }

    public function test_lesson_and_course_force_delete_are_blocked_when_quiz_has_attempts(): void
    {
        $quiz = $this->quiz();
        $this->attempt($quiz);
        $lesson = $quiz->lesson;
        $course = $quiz->owningCourse();
        $lesson->delete();

        $admin = $this->admin();
        $this->actingAs($admin)->deleteJson(route('cms.admin.lessons.force-delete', $lesson->id))->assertStatus(409);

        $course->delete();
        $this->deleteJson(route('cms.admin.courses.force-delete', $course->id))->assertStatus(409);
    }

    /* ---------- منشئ الأسئلة ---------- */

    public function test_builder_index_returns_ordered_questions_with_totals(): void
    {
        $quiz = $this->quiz();
        $second = $this->mcq($quiz, ['question_text' => 'Second', 'sort_order' => 2, 'points' => 3]);
        $this->mcq($quiz, ['question_text' => 'First', 'sort_order' => 1, 'points' => 2]);
        $this->mcq($quiz)->delete();

        $this->actingAs($this->admin())->getJson(route('cms.admin.quizzes.questions.index', $quiz))
            ->assertOk()
            ->assertJsonPath('questions.0.question_text', 'First')
            ->assertJsonPath('questions.1.id', $second->id)
            ->assertJsonPath('questions.0.options.0.option_text', 'A')
            ->assertJsonPath('totals.questions', 2)
            ->assertJsonPath('totals.points', 5)
            ->assertJsonPath('totals.trashed', 1)
            ->assertJsonPath('locked', false)
            ->assertJsonPath('can_edit', true);
    }

    public function test_instructor_cannot_use_builder_of_foreign_quiz(): void
    {
        $foreign = $this->quiz();
        $q       = $this->mcq($foreign);

        $this->actingAs($this->teacher());
        $this->getJson(route('cms.admin.quizzes.questions.index', $foreign))->assertForbidden();
        $this->postJson(route('cms.admin.quizzes.questions.store', $foreign), $this->mcqPayload())->assertForbidden();
        $this->putJson(route('cms.admin.questions.update', $q), $this->mcqPayload())->assertForbidden();
        $this->deleteJson(route('cms.admin.questions.destroy', $q))->assertForbidden();
    }

    public function test_store_multiple_choice_question_with_ordered_options(): void
    {
        $quiz = $this->quiz();

        $this->actingAs($this->admin())->postJson(route('cms.admin.quizzes.questions.store', $quiz), $this->mcqPayload())
            ->assertCreated()
            ->assertJsonPath('data.sort_order', 1)
            ->assertJsonPath('data.options.1.is_correct', true)
            ->assertJsonPath('data.options.2.sort_order', 3);

        $this->postJson(route('cms.admin.quizzes.questions.store', $quiz), $this->mcqPayload())
            ->assertCreated()->assertJsonPath('data.sort_order', 2);
    }

    public function test_multiple_choice_rules(): void
    {
        $quiz = $this->quiz();
        $url  = route('cms.admin.quizzes.questions.store', $quiz);
        $this->actingAs($this->admin());

        $one   = $this->mcqPayload(['options' => [['option_text' => 'Only', 'is_correct' => true]]]);
        $none  = $this->mcqPayload(['options' => [['option_text' => 'a', 'is_correct' => false], ['option_text' => 'b', 'is_correct' => false]]]);
        $two   = $this->mcqPayload(['options' => [['option_text' => 'a', 'is_correct' => true], ['option_text' => 'b', 'is_correct' => true]]]);
        $dup   = $this->mcqPayload(['options' => [['option_text' => 'Same', 'is_correct' => true], ['option_text' => ' same ', 'is_correct' => false]]]);
        $empty = $this->mcqPayload(['options' => [['option_text' => '', 'is_correct' => true], ['option_text' => 'b', 'is_correct' => false]]]);

        foreach ([$one, $none, $two, $dup] as $bad) {
            $this->postJson($url, $bad)->assertUnprocessable()->assertJsonValidationErrors('options');
        }
        $this->postJson($url, $empty)->assertUnprocessable()->assertJsonValidationErrors('options.0.option_text');
        $this->assertSame(0, Question::count());
    }

    public function test_store_true_false_question_builds_two_fixed_options(): void
    {
        $quiz = $this->quiz();

        $res = $this->actingAs($this->admin())->postJson(route('cms.admin.quizzes.questions.store', $quiz), [
            'question_text' => 'The sky is blue.',
            'question_type' => 'true_false',
            'points' => 1,
            'tf_answer' => 'false',
        ])->assertCreated();

        $res->assertJsonPath('data.options.0.option_text', 'true')
            ->assertJsonPath('data.options.0.is_correct', false)
            ->assertJsonPath('data.options.1.option_text', 'false')
            ->assertJsonPath('data.options.1.is_correct', true);

        $this->postJson(route('cms.admin.quizzes.questions.store', $quiz), [
            'question_text' => 'X',
            'question_type' => 'true_false',
            'points' => 1,
        ])->assertUnprocessable()->assertJsonValidationErrors('tf_answer');
    }

    public function test_store_text_question_accepts_answers_and_marks_them_all_correct(): void
    {
        $quiz = $this->quiz();
        $url  = route('cms.admin.quizzes.questions.store', $quiz);
        $this->actingAs($this->admin());

        $this->postJson($url, [
            'question_text' => 'Capital of France?',
            'question_type' => 'text',
            'points' => 1,
            'options' => [['option_text' => 'Paris'], ['option_text' => 'paris, france', 'is_correct' => false]],
        ])->assertCreated()
            ->assertJsonPath('data.options.0.is_correct', true)
            ->assertJsonPath('data.options.1.is_correct', true);

        $this->postJson($url, ['question_text' => 'Q', 'question_type' => 'text', 'points' => 1, 'options' => []])
            ->assertUnprocessable()->assertJsonValidationErrors('options');
        $this->postJson($url, [
            'question_text' => 'Q',
            'question_type' => 'text',
            'points' => 1,
            'options' => [['option_text' => 'Paris'], ['option_text' => ' PARIS ']]
        ])
            ->assertUnprocessable()->assertJsonValidationErrors('options');
    }

    public function test_question_field_validation(): void
    {
        $quiz = $this->quiz();
        $url  = route('cms.admin.quizzes.questions.store', $quiz);
        $this->actingAs($this->admin());

        $this->postJson($url, [])->assertUnprocessable()
            ->assertJsonValidationErrors(['question_text', 'question_type', 'points']);
        $this->postJson($url, $this->mcqPayload(['points' => 0]))->assertUnprocessable()->assertJsonValidationErrors('points');
        $this->postJson($url, $this->mcqPayload(['points' => 101]))->assertUnprocessable()->assertJsonValidationErrors('points');
        $this->postJson($url, $this->mcqPayload(['question_type' => 'essay']))->assertUnprocessable()->assertJsonValidationErrors('question_type');
    }

    public function test_update_syncs_options_by_id_and_keeps_removed_ones_as_history(): void
    {
        $quiz = $this->quiz();
        $q    = $this->mcq($quiz);
        [$a, $b, $c] = $q->options()->orderBy('id')->get()->all();

        $this->actingAs($this->admin())->putJson(route('cms.admin.questions.update', $q), [
            'question_text' => 'Renamed',
            'question_type' => 'multiple_choice',
            'points' => 4,
            'options' => [
                ['id' => $a->id, 'option_text' => 'Alpha', 'is_correct' => true],
                ['option_text' => 'NewOne', 'is_correct' => false],
            ],
        ])->assertOk();

        $q->refresh();
        $this->assertSame(4, $q->points);
        $this->assertEquals(['Alpha', 'NewOne'], $q->options()->pluck('option_text')->all());
        $this->assertSame('Alpha', $a->fresh()->option_text);

        // الخياران المحذوفان يبقيان مؤقتًا في القاعدة (سجل تاريخي)
        $this->assertSoftDeleted('question_options', ['id' => $b->id]);
        $this->assertSoftDeleted('question_options', ['id' => $c->id]);
    }

    public function test_update_can_change_type_when_no_attempts(): void
    {
        $q = $this->mcq($this->quiz());

        $this->actingAs($this->admin())->putJson(route('cms.admin.questions.update', $q), [
            'question_text' => 'Now text',
            'question_type' => 'text',
            'points' => 1,
            'options' => [['option_text' => 'answer']],
        ])->assertOk()->assertJsonPath('data.options.0.is_correct', true);

        $this->assertSame('text', $q->fresh()->question_type);
        $this->assertCount(1, $q->options);
    }

    public function test_locked_quiz_allows_text_fixes_but_not_structure_changes(): void
    {
        $quiz = $this->quiz();
        $q    = $this->mcq($quiz);
        $this->attempt($quiz);
        $opts = $q->options()->orderBy('id')->get();

        $same = fn(array $over = []) => array_merge([
            'question_text' => 'Fixed typo',
            'question_type' => 'multiple_choice',
            'points' => $q->points,
            'options' => $opts->map(fn($o) => ['id' => $o->id, 'option_text' => $o->option_text . '!', 'is_correct' => (bool) $o->is_correct])->all(),
        ], $over);

        $this->actingAs($this->admin());
        $this->putJson(route('cms.admin.questions.update', $q), $same())->assertOk();
        $this->assertSame('Fixed typo', $q->fresh()->question_text);

        $this->putJson(route('cms.admin.questions.update', $q), $same(['points' => $q->points + 5]))->assertStatus(409);

        $swapped = $opts->values()->map(fn($o, $i) => ['id' => $o->id, 'option_text' => $o->option_text, 'is_correct' => $i === 1])->all();
        $this->putJson(route('cms.admin.questions.update', $q), $same(['options' => $swapped]))->assertStatus(409);

        $this->putJson(route('cms.admin.questions.update', $q), [
            'question_text' => 'x',
            'question_type' => 'text',
            'points' => $q->points,
            'options' => [['option_text' => 'y']],
        ])->assertStatus(409);

        $withNew = $same();
        $withNew['options'][] = ['option_text' => 'D', 'is_correct' => false];
        $this->putJson(route('cms.admin.questions.update', $q), $withNew)->assertStatus(409);
    }

    public function test_locked_quiz_still_allows_adding_and_soft_deleting_questions(): void
    {
        $quiz = $this->quiz();
        $q    = $this->mcq($quiz);
        $this->attempt($quiz);
        $admin = $this->admin();

        $this->actingAs($admin)->postJson(route('cms.admin.quizzes.questions.store', $quiz), $this->mcqPayload())->assertCreated();
        $this->deleteJson(route('cms.admin.questions.destroy', $q))->assertOk();
        $this->assertSoftDeleted('questions', ['id' => $q->id]);

        $this->deleteJson(route('cms.admin.questions.force-delete', $q->id))->assertStatus(409);
        $this->assertDatabaseHas('questions', ['id' => $q->id]);
    }

    public function test_question_trash_restore_and_force_delete(): void
    {
        $quiz = $this->quiz();
        $q    = $this->mcq($quiz, ['question_text' => 'GoneQuestionQ']);
        $admin = $this->admin();

        $this->actingAs($admin)->deleteJson(route('cms.admin.questions.destroy', $q))->assertOk();
        $this->getJson(route('cms.admin.quizzes.questions.trashed', $quiz))
            ->assertOk()->assertJsonPath('data.0.question_text', 'GoneQuestionQ');

        $this->patchJson(route('cms.admin.questions.restore', $q->id))->assertOk();
        $this->assertNotSoftDeleted('questions', ['id' => $q->id]);

        $q->delete();
        $this->deleteJson(route('cms.admin.questions.force-delete', $q->id))->assertOk();
        $this->assertDatabaseMissing('questions', ['id' => $q->id]);
        $this->assertDatabaseMissing('question_options', ['question_id' => $q->id]);
    }

    public function test_question_restore_is_blocked_when_quiz_is_trashed_and_returns_404_for_active(): void
    {
        $quiz = $this->quiz();
        $q    = $this->mcq($quiz);
        $q->delete();
        $active = $this->mcq($this->quiz());
        $admin  = $this->admin();

        $quiz->delete();
        $this->actingAs($admin)->patchJson(route('cms.admin.questions.restore', $q->id))->assertStatus(409);
        $this->patchJson(route('cms.admin.questions.restore', $active->id))->assertNotFound();
    }

    public function test_instructor_cannot_force_delete_questions(): void
    {
        $owner = $this->teacher();
        $quiz  = $this->quiz(Course::factory()->published()->create(['instructor_id' => $owner->id]));
        $q     = $this->mcq($quiz);
        $q->delete();

        $this->actingAs($owner)->deleteJson(route('cms.admin.questions.force-delete', $q->id))->assertForbidden();
    }

    public function test_reorder_questions(): void
    {
        $quiz = $this->quiz();
        $a = $this->mcq($quiz, ['sort_order' => 1]);
        $b = $this->mcq($quiz, ['sort_order' => 2]);
        $url = route('cms.admin.quizzes.questions.reorder', $quiz);

        $this->actingAs($this->admin())->postJson($url, ['ids' => [$b->id, $a->id]])->assertOk();

        $this->assertSame(1, $b->fresh()->sort_order);
        $this->assertSame(2, $a->fresh()->sort_order);

        $this->postJson($url, ['ids' => [$a->id]])->assertUnprocessable();
        $this->postJson($url, ['ids' => [$a->id, 9999]])->assertUnprocessable();
    }

    /* ---------- العلاقات ---------- */

    public function test_relations_resolve(): void
    {
        $quiz = $this->quiz();
        $q    = $this->mcq($quiz);

        $this->assertTrue($quiz->questions->first()->is($q));
        $this->assertCount(3, $q->options);
        $this->assertTrue($q->quiz->is($quiz));
        $this->assertTrue($quiz->lesson->quiz->is($quiz));
        $this->assertNotNull($quiz->owningCourse());
    }


    public function test_index_page_renders(): void
    {
        $this->actingAs($this->admin())->get(route('cms.admin.quizzes.index'))
            ->assertOk()->assertViewIs('cms.quizzes.index');
    }

    public function test_quizzes_table_exposes_questions_action(): void
    {
        $this->quiz();

        $html = $this->actingAs($this->admin())->getJson(route('cms.admin.quizzes.index'))->json('html');

        $this->assertStringContainsString('data-action="questions"', $html);
    }
}
