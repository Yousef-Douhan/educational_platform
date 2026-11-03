<?php

namespace Tests\Feature;

use App\Exceptions\QuizAttemptException;
use App\Models\Course;
use App\Models\CourseSection;
use App\Models\Enrollment;
use App\Models\Lesson;
use App\Models\Question;
use App\Models\QuestionOption;
use App\Models\Quiz;
use App\Models\QuizAnswer;
use App\Models\QuizAttempt;
use App\Models\User;
use App\Services\QuizAttemptService;
use Illuminate\Database\Eloquent\MassAssignmentException;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class QuizAttemptModuleTest extends TestCase
{
    use RefreshDatabase;

    /** quiz id => الدورة التي ينتمي لها (لتفادي التحميل الكسول في الاختبارات) */
    private array $courses = [];

    protected function setUp(): void
    {
        parent::setUp();
        app(PermissionRegistrar::class)->forgetCachedPermissions();
        $this->withoutVite();

        foreach (['quiz-attempts.view', 'quiz-attempts.void', 'courses.manage-all'] as $name) {
            Permission::findOrCreate($name, 'web');
        }
    }

    /* ---------- أدوات ---------- */

    private function attempts(): QuizAttemptService
    {
        return app(QuizAttemptService::class);
    }

    private function admin(string ...$permissions): User
    {
        $u = User::factory()->create();
        $u->givePermissionTo($permissions ?: Permission::all());

        return $u;
    }

    private function quiz(array $o = [], ?Course $course = null, bool $published = true): Quiz
    {
        $course ??= Course::factory()->published()->create();
        $section = CourseSection::factory()->create(['course_id' => $course->id]);
        $lesson  = Lesson::factory()->create([
            'section_id' => $section->id, 'content_type' => 'quiz', 'content_ar' => null, 'is_published' => $published,
        ]);

        $quiz = Quiz::factory()->create(array_merge([
            'lesson_id' => $lesson->id, 'pass_mark' => 50, 'attempts_allowed' => 3, 'time_limit_minutes' => 0,
        ], $o));

        $this->courses[$quiz->id] = $course;

        return $quiz;
    }

    private function enroll(User $student, Quiz $quiz, string $status = 'active'): Enrollment
    {
        return Enrollment::factory()->create([
            'user_id' => $student->id, 'course_id' => $this->courses[$quiz->id]->id, 'status' => $status,
        ]);
    }

    /** طالب مسجَّل في دورة اختبار فيه سؤال واحد على الأقل */
    private function ready(array $quizOptions = []): array
    {
        $student = User::factory()->create();
        $quiz    = $this->quiz($quizOptions);
        $this->enroll($student, $quiz);

        return [$student, $quiz];
    }

    /** اختيار من متعدد: A صحيح، B، C */
    private function mcq(Quiz $quiz, int $points = 1): Question
    {
        $q = Question::factory()->create(['quiz_id' => $quiz->id, 'question_type' => 'multiple_choice', 'points' => $points]);
        foreach (['A' => true, 'B' => false, 'C' => false] as $text => $correct) {
            QuestionOption::factory()->create(['question_id' => $q->id, 'option_text' => $text, 'is_correct' => $correct, 'sort_order' => ord($text) - 64]);
        }

        return $q;
    }

    private function tf(Quiz $quiz, bool $answer = true, int $points = 1): Question
    {
        $q = Question::factory()->create(['quiz_id' => $quiz->id, 'question_type' => 'true_false', 'points' => $points]);
        QuestionOption::factory()->create(['question_id' => $q->id, 'option_text' => 'true', 'is_correct' => $answer, 'sort_order' => 1]);
        QuestionOption::factory()->create(['question_id' => $q->id, 'option_text' => 'false', 'is_correct' => ! $answer, 'sort_order' => 2]);

        return $q;
    }

    private function textQ(Quiz $quiz, array $accepted = ['Paris', 'باريس'], int $points = 1): Question
    {
        $q = Question::factory()->create(['quiz_id' => $quiz->id, 'question_type' => 'text', 'points' => $points]);
        foreach ($accepted as $i => $text) {
            QuestionOption::factory()->create(['question_id' => $q->id, 'option_text' => $text, 'is_correct' => true, 'sort_order' => $i + 1]);
        }

        return $q;
    }

    /** معرّف خيار الصحيح أو الخاطئ الأول للسؤال */
    private function opt(Question $q, bool $correct = true): int
    {
        return (int) QuestionOption::where('question_id', $q->id)->where('is_correct', $correct)->orderBy('id')->value('id');
    }

    private function assertRejected(string $reason, callable $fn): void
    {
        try {
            $fn();
        } catch (QuizAttemptException $e) {
            $this->assertSame($reason, $e->reason);

            return;
        }

        $this->fail("Expected QuizAttemptException: {$reason}");
    }

    /* ---------- بدء المحاولة ---------- */

    public function test_start_creates_an_open_attempt_with_snapshots(): void
    {
        [$student, $quiz] = $this->ready(['pass_mark' => 70, 'time_limit_minutes' => 20]);
        $this->mcq($quiz, 2);
        $this->mcq($quiz, 3);

        $a = $this->attempts()->start($student, $quiz);

        $this->assertSame('in_progress', $a->status);
        $this->assertSame(1, $a->attempt_number);
        $this->assertTrue($a->is_open);
        $this->assertSame(5, $a->points_total);
        $this->assertSame(70, $a->pass_mark);
        $this->assertFalse($a->is_passed);
        $this->assertNotNull($a->expires_at);
        $this->assertEqualsWithDelta(20 * 60, $a->started_at->diffInSeconds($a->expires_at), 2);
    }

    public function test_attempt_without_time_limit_has_no_deadline(): void
    {
        [$student, $quiz] = $this->ready();
        $this->mcq($quiz);

        $this->assertNull($this->attempts()->start($student, $quiz)->expires_at);
    }

    public function test_start_resumes_the_open_attempt_instead_of_creating_another(): void
    {
        [$student, $quiz] = $this->ready(['time_limit_minutes' => 30]);
        $this->mcq($quiz);

        $first  = $this->attempts()->start($student, $quiz);
        $second = $this->attempts()->start($student, $quiz);

        $this->assertSame($first->id, $second->id);
        $this->assertSame(1, QuizAttempt::count());
    }

    public function test_start_requires_active_enrollment(): void
    {
        $quiz = $this->quiz();
        $this->mcq($quiz);

        $stranger = User::factory()->create();
        $this->assertRejected('not_enrolled', fn () => $this->attempts()->start($stranger, $quiz));

        foreach (['cancelled', 'expired'] as $status) {
            $other = User::factory()->create();
            $this->enroll($other, $quiz, $status);
            $this->assertRejected('not_enrolled', fn () => $this->attempts()->start($other, $quiz));
        }

        $lapsed = User::factory()->create();
        Enrollment::factory()->expiresAt(now()->subDay())->create(['user_id' => $lapsed->id, 'course_id' => $this->courses[$quiz->id]->id]);
        $this->assertRejected('not_enrolled', fn () => $this->attempts()->start($lapsed, $quiz));

        $this->assertSame(0, QuizAttempt::count());
    }

    public function test_completed_enrollment_can_still_take_the_quiz(): void
    {
        $student = User::factory()->create();
        $quiz    = $this->quiz();
        $this->mcq($quiz);
        $this->enroll($student, $quiz, 'completed');

        $this->assertSame('in_progress', $this->attempts()->start($student, $quiz)->status);
    }

    public function test_start_rejects_unavailable_quizzes(): void
    {
        $student = User::factory()->create();

        $draft = $this->quiz([], null, false);
        $this->mcq($draft);
        $this->enroll($student, $draft);
        $this->assertRejected('quiz_unavailable', fn () => $this->attempts()->start($student, $draft));

        $trashed = $this->quiz();
        $this->mcq($trashed);
        $this->enroll($student, $trashed);
        $trashed->delete();
        $this->assertRejected('quiz_unavailable', fn () => $this->attempts()->start($student, $trashed));

        $lessonGone = $this->quiz();
        $this->mcq($lessonGone);
        $this->enroll($student, $lessonGone);
        Lesson::whereKey($lessonGone->lesson_id)->first()->delete();
        $this->assertRejected('quiz_unavailable', fn () => $this->attempts()->start($student, $lessonGone));

        $courseGone = $this->quiz();
        $this->mcq($courseGone);
        $this->enroll($student, $courseGone);
        $this->courses[$courseGone->id]->delete();
        $this->assertRejected('quiz_unavailable', fn () => $this->attempts()->start($student, $courseGone));
    }

    public function test_start_requires_questions_and_an_active_account(): void
    {
        [$student, $quiz] = $this->ready();
        $this->assertRejected('no_questions', fn () => $this->attempts()->start($student, $quiz));

        $this->mcq($quiz);
        $gone = User::factory()->create();
        $this->enroll($gone, $quiz);
        $gone->delete();
        $this->assertRejected('student_inactive', fn () => $this->attempts()->start($gone, $quiz));
    }

    public function test_attempt_limit_is_enforced_and_zero_means_unlimited(): void
    {
        [$student, $quiz] = $this->ready(['attempts_allowed' => 2]);
        $this->mcq($quiz);

        foreach ([1, 2] as $n) {
            $a = $this->attempts()->start($student, $quiz);
            $this->assertSame($n, $a->attempt_number);
            $this->attempts()->submit($student, $a);
        }

        $this->assertRejected('attempts_exhausted', fn () => $this->attempts()->start($student, $quiz));

        [$s2, $q2] = $this->ready(['attempts_allowed' => 0]);
        $this->mcq($q2);
        foreach (range(1, 6) as $n) {
            $a = $this->attempts()->start($s2, $q2);
            $this->attempts()->submit($s2, $a);
        }
        $this->assertSame(6, QuizAttempt::where('user_id', $s2->id)->count());
    }

    public function test_voided_attempt_frees_a_slot_and_numbers_keep_increasing(): void
    {
        [$student, $quiz] = $this->ready(['attempts_allowed' => 1]);
        $this->mcq($quiz);
        $admin = $this->admin();

        $first = $this->attempts()->start($student, $quiz);
        $this->attempts()->submit($student, $first);
        $this->assertRejected('attempts_exhausted', fn () => $this->attempts()->start($student, $quiz));

        $this->attempts()->void($first, $admin, 'cheating');
        $second = $this->attempts()->start($student, $quiz);

        $this->assertSame(2, $second->attempt_number);
    }

    public function test_one_open_attempt_per_student_and_quiz_is_enforced_by_the_database(): void
    {
        $quiz = $this->quiz();
        $user = User::factory()->create();

        QuizAttempt::factory()->inProgress()->number(1)->create(['user_id' => $user->id, 'quiz_id' => $quiz->id]);

        $this->expectException(UniqueConstraintViolationException::class);
        QuizAttempt::factory()->inProgress()->number(2)->create(['user_id' => $user->id, 'quiz_id' => $quiz->id]);
    }

    public function test_attempt_number_and_answer_per_question_are_unique(): void
    {
        $quiz = $this->quiz();
        $user = User::factory()->create();
        QuizAttempt::factory()->number(1)->create(['user_id' => $user->id, 'quiz_id' => $quiz->id]);

        try {
            QuizAttempt::factory()->number(1)->create(['user_id' => $user->id, 'quiz_id' => $quiz->id]);
            $this->fail('Duplicate attempt number accepted');
        } catch (UniqueConstraintViolationException) {
            $this->assertSame(1, QuizAttempt::count());
        }

        $attempt  = QuizAttempt::first();
        $question = Question::factory()->create(['quiz_id' => $quiz->id]);
        QuizAnswer::factory()->create(['attempt_id' => $attempt->id, 'question_id' => $question->id]);

        $this->expectException(UniqueConstraintViolationException::class);
        QuizAnswer::factory()->create(['attempt_id' => $attempt->id, 'question_id' => $question->id]);
    }

    public function test_models_are_not_mass_assignable(): void
    {
        $this->expectException(MassAssignmentException::class);
        QuizAttempt::create(['user_id' => 1, 'quiz_id' => 1, 'score' => 100, 'is_passed' => true]);
    }

    /* ---------- الإجابات ---------- */

    public function test_saving_an_answer_never_grades_it(): void
    {
        [$student, $quiz] = $this->ready();
        $q = $this->mcq($quiz);
        $a = $this->attempts()->start($student, $quiz);

        $row = $this->attempts()->saveAnswer($student, $a, $q->id, $this->opt($q));

        $this->assertSame($this->opt($q), $row->option_id);
        $this->assertFalse($row->is_correct);
        $this->assertSame(0, $row->points_awarded);
    }

    public function test_changing_an_answer_replaces_it(): void
    {
        [$student, $quiz] = $this->ready();
        $q = $this->mcq($quiz);
        $a = $this->attempts()->start($student, $quiz);

        $this->attempts()->saveAnswer($student, $a, $q->id, $this->opt($q, false));
        $this->attempts()->saveAnswer($student, $a, $q->id, $this->opt($q, true));

        $this->assertSame(1, QuizAnswer::where('attempt_id', $a->id)->count());
        $this->assertSame($this->opt($q), QuizAnswer::where('attempt_id', $a->id)->value('option_id'));
    }

    public function test_invalid_answers_are_rejected(): void
    {
        [$student, $quiz] = $this->ready();
        $q     = $this->mcq($quiz);
        $other = $this->mcq($quiz);
        $text  = $this->textQ($quiz);
        $a     = $this->attempts()->start($student, $quiz);

        // خيار يخص سؤالًا آخر
        $this->assertRejected('answer_invalid', fn () => $this->attempts()->saveAnswer($student, $a, $q->id, $this->opt($other)));
        // بلا خيار
        $this->assertRejected('answer_invalid', fn () => $this->attempts()->saveAnswer($student, $a, $q->id, null));
        // نص فارغ أو أطول من الحد
        $this->assertRejected('answer_invalid', fn () => $this->attempts()->saveAnswer($student, $a, $text->id, null, '   '));
        $this->assertRejected('answer_invalid', fn () => $this->attempts()->saveAnswer($student, $a, $text->id, null, str_repeat('x', 1001)));

        // سؤال من اختبار آخر
        $foreign = $this->mcq($this->quiz());
        $this->assertRejected('question_not_in_quiz', fn () => $this->attempts()->saveAnswer($student, $a, $foreign->id, $this->opt($foreign)));

        // سؤال محذوف مؤقتًا
        $q->delete();
        $this->assertRejected('question_not_in_quiz', fn () => $this->attempts()->saveAnswer($student, $a, $q->id, $this->opt($q)));

        $this->assertSame(0, QuizAnswer::count());
    }

    public function test_only_the_owner_can_answer_or_submit(): void
    {
        [$student, $quiz] = $this->ready();
        $q = $this->mcq($quiz);
        $a = $this->attempts()->start($student, $quiz);
        $intruder = User::factory()->create();

        $this->assertRejected('not_your_attempt', fn () => $this->attempts()->saveAnswer($intruder, $a, $q->id, $this->opt($q)));
        $this->assertRejected('not_your_attempt', fn () => $this->attempts()->submit($intruder, $a));
        $this->assertSame('in_progress', $a->fresh()->status);
    }

    public function test_closed_attempts_accept_no_more_answers(): void
    {
        [$student, $quiz] = $this->ready();
        $q = $this->mcq($quiz);
        $a = $this->attempts()->start($student, $quiz);
        $this->attempts()->submit($student, $a);

        $this->assertRejected('attempt_closed', fn () => $this->attempts()->saveAnswer($student, $a, $q->id, $this->opt($q)));
    }

    /* ---------- التصحيح ---------- */

    public function test_submit_grades_every_question_type_on_the_server(): void
    {
        [$student, $quiz] = $this->ready(['pass_mark' => 50]);
        $q1 = $this->mcq($quiz, 2);
        $q2 = $this->tf($quiz, true, 1);
        $q3 = $this->textQ($quiz, ['Paris', 'باريس'], 1);
        $q4 = $this->mcq($quiz, 1);   // بلا إجابة

        $a = $this->attempts()->start($student, $quiz);
        $this->attempts()->saveAnswer($student, $a, $q1->id, $this->opt($q1));
        $this->attempts()->saveAnswer($student, $a, $q2->id, $this->opt($q2));
        $this->attempts()->saveAnswer($student, $a, $q3->id, null, '  PARIS ');

        $done = $this->attempts()->submit($student, $a);

        $this->assertSame('submitted', $done->status);
        $this->assertNull($done->is_open);
        $this->assertSame(4, $done->points_earned);
        $this->assertSame(5, $done->points_total);
        $this->assertEquals(80, (float) $done->score);
        $this->assertTrue($done->is_passed);
        $this->assertNotNull($done->submitted_at);

        $this->assertSame(4, QuizAnswer::where('attempt_id', $a->id)->count());
        $this->assertDatabaseHas('quiz_answers', ['attempt_id' => $a->id, 'question_id' => $q1->id, 'is_correct' => true, 'points_awarded' => 2]);
        $this->assertDatabaseHas('quiz_answers', ['attempt_id' => $a->id, 'question_id' => $q3->id, 'is_correct' => true, 'points_awarded' => 1]);
        $this->assertDatabaseHas('quiz_answers', ['attempt_id' => $a->id, 'question_id' => $q4->id, 'option_id' => null, 'is_correct' => false, 'points_awarded' => 0]);
    }

    public function test_wrong_answers_score_zero_and_fail(): void
    {
        [$student, $quiz] = $this->ready(['pass_mark' => 50]);
        $q1 = $this->mcq($quiz);
        $q2 = $this->textQ($quiz);
        $a  = $this->attempts()->start($student, $quiz);

        $this->attempts()->saveAnswer($student, $a, $q1->id, $this->opt($q1, false));
        $this->attempts()->saveAnswer($student, $a, $q2->id, null, 'London');

        $done = $this->attempts()->submit($student, $a);

        $this->assertSame(0, $done->points_earned);
        $this->assertEquals(0, (float) $done->score);
        $this->assertFalse($done->is_passed);
    }

    public function test_pass_mark_boundary_is_inclusive(): void
    {
        [$student, $quiz] = $this->ready(['pass_mark' => 50]);
        $q1 = $this->mcq($quiz);
        $q2 = $this->mcq($quiz);
        $a  = $this->attempts()->start($student, $quiz);
        $this->attempts()->saveAnswer($student, $a, $q1->id, $this->opt($q1));
        $this->attempts()->saveAnswer($student, $a, $q2->id, $this->opt($q2, false));

        $done = $this->attempts()->submit($student, $a);

        $this->assertEquals(50, (float) $done->score);
        $this->assertTrue($done->is_passed);
    }

    public function test_pass_mark_changes_do_not_affect_attempts_already_started(): void
    {
        [$student, $quiz] = $this->ready(['pass_mark' => 50]);
        $q = $this->mcq($quiz);
        $a = $this->attempts()->start($student, $quiz);
        $this->attempts()->saveAnswer($student, $a, $q->id, $this->opt($q, false));

        // تخفيف الشرط بعد البدء لا يُنجح محاولة بدأت بشرط 50
        Quiz::whereKey($quiz->id)->update(['pass_mark' => 0]);

        $done = $this->attempts()->submit($student, $a);

        $this->assertSame(50, $done->pass_mark);
        $this->assertFalse($done->is_passed);
    }

    public function test_submit_is_idempotent(): void
    {
        [$student, $quiz] = $this->ready();
        $q = $this->mcq($quiz);
        $a = $this->attempts()->start($student, $quiz);
        $this->attempts()->saveAnswer($student, $a, $q->id, $this->opt($q));

        $first  = $this->attempts()->submit($student, $a);
        $at     = $first->submitted_at;
        $second = $this->attempts()->submit($student, $a);

        $this->assertSame($first->id, $second->id);
        $this->assertEquals($at, $second->submitted_at);
        $this->assertEquals(100, (float) $second->score);
        $this->assertSame(1, QuizAnswer::where('attempt_id', $a->id)->count());
    }

    public function test_answers_to_questions_deleted_meanwhile_are_not_scored(): void
    {
        [$student, $quiz] = $this->ready();
        $q1 = $this->mcq($quiz, 1);
        $q2 = $this->mcq($quiz, 1);
        $a  = $this->attempts()->start($student, $quiz);
        $this->attempts()->saveAnswer($student, $a, $q1->id, $this->opt($q1));
        $this->attempts()->saveAnswer($student, $a, $q2->id, $this->opt($q2));

        $q2->delete();
        $done = $this->attempts()->submit($student, $a);

        $this->assertSame(1, $done->points_total);
        $this->assertSame(1, $done->points_earned);
        $this->assertDatabaseHas('quiz_answers', ['attempt_id' => $a->id, 'question_id' => $q2->id, 'points_awarded' => 0, 'is_correct' => false]);
    }

    public function test_a_quiz_whose_questions_were_all_deleted_cannot_be_passed(): void
    {
        [$student, $quiz] = $this->ready(['pass_mark' => 0]);
        $q = $this->mcq($quiz);
        $a = $this->attempts()->start($student, $quiz);
        $q->delete();

        $done = $this->attempts()->submit($student, $a);

        $this->assertSame(0, $done->points_total);
        $this->assertFalse($done->is_passed);
    }

    /* ---------- الوقت ---------- */

    public function test_submit_within_the_grace_period_is_still_submitted(): void
    {
        [$student, $quiz] = $this->ready(['time_limit_minutes' => 10]);
        $q = $this->mcq($quiz);
        $a = $this->attempts()->start($student, $quiz);
        $this->attempts()->saveAnswer($student, $a, $q->id, $this->opt($q));

        $this->travelTo(now()->addMinutes(10)->addSeconds(10));

        $this->assertSame('submitted', $this->attempts()->submit($student, $a)->status);
    }

    public function test_late_submit_is_graded_but_marked_expired(): void
    {
        [$student, $quiz] = $this->ready(['time_limit_minutes' => 10]);
        $q = $this->mcq($quiz);
        $a = $this->attempts()->start($student, $quiz);
        $this->attempts()->saveAnswer($student, $a, $q->id, $this->opt($q));

        $this->travelTo(now()->addMinutes(11));
        $done = $this->attempts()->submit($student, $a);

        $this->assertSame('expired', $done->status);
        $this->assertEquals(100, (float) $done->score);
    }

    public function test_answering_after_the_deadline_closes_the_attempt(): void
    {
        [$student, $quiz] = $this->ready(['time_limit_minutes' => 10]);
        $q1 = $this->mcq($quiz);
        $q2 = $this->mcq($quiz);
        $a  = $this->attempts()->start($student, $quiz);
        $this->attempts()->saveAnswer($student, $a, $q1->id, $this->opt($q1));

        $this->travelTo(now()->addMinutes(10)->addSeconds(1));
        $this->assertRejected('attempt_expired', fn () => $this->attempts()->saveAnswer($student, $a, $q2->id, $this->opt($q2)));

        $fresh = $a->fresh();
        $this->assertSame('expired', $fresh->status);
        $this->assertEquals(50, (float) $fresh->score);
        $this->assertSame(0, QuizAnswer::where('attempt_id', $a->id)->where('question_id', $q2->id)->where('option_id', '!=', null)->count());
    }

    public function test_starting_again_after_an_overdue_attempt_closes_it_and_counts_it(): void
    {
        [$student, $quiz] = $this->ready(['time_limit_minutes' => 10, 'attempts_allowed' => 2]);
        $this->mcq($quiz);
        $old = $this->attempts()->start($student, $quiz);

        $this->travelTo(now()->addMinutes(15));
        $new = $this->attempts()->start($student, $quiz);

        $this->assertNotSame($old->id, $new->id);
        $this->assertSame('expired', $old->fresh()->status);
        $this->assertSame(2, $new->attempt_number);
    }

    public function test_expire_overdue_closes_only_attempts_past_their_deadline(): void
    {
        $quiz = $this->quiz(['time_limit_minutes' => 10]);
        $this->mcq($quiz);
        $open = $this->quiz(['time_limit_minutes' => 120]);
        $this->mcq($open);
        $free = $this->quiz(['time_limit_minutes' => 0]);
        $this->mcq($free);

        $a = User::factory()->create(); $this->enroll($a, $quiz);
        $b = User::factory()->create(); $this->enroll($b, $open);
        $c = User::factory()->create(); $this->enroll($c, $free);

        $overdue = $this->attempts()->start($a, $quiz);
        $roomy   = $this->attempts()->start($b, $open);
        $nolimit = $this->attempts()->start($c, $free);

        $this->travelTo(now()->addMinutes(30));

        $this->assertSame(1, $this->attempts()->expireOverdue());
        $this->assertSame('expired', $overdue->fresh()->status);
        $this->assertSame('in_progress', $roomy->fresh()->status);
        $this->assertSame('in_progress', $nolimit->fresh()->status);
        $this->assertSame(0, $this->attempts()->expireOverdue());
    }

    public function test_expire_command_runs(): void
    {
        $this->artisan('quizzes:expire-attempts')->expectsOutputToContain('Closed 0')->assertSuccessful();
    }

    /* ---------- الإلغاء ---------- */

    public function test_void_records_who_why_and_removes_the_pass(): void
    {
        [$student, $quiz] = $this->ready();
        $q     = $this->mcq($quiz);
        $admin = $this->admin();
        $a     = $this->attempts()->start($student, $quiz);
        $this->attempts()->saveAnswer($student, $a, $q->id, $this->opt($q));
        $this->attempts()->submit($student, $a);

        $voided = $this->attempts()->void($a, $admin, '  Copied answers  ');

        $this->assertSame('voided', $voided->status);
        $this->assertFalse($voided->is_passed);
        $this->assertEquals(100, (float) $voided->score);
        $this->assertSame('Copied answers', $voided->void_reason);
        $this->assertSame($admin->id, $voided->voided_by);
        $this->assertNotNull($voided->voided_at);
    }

    public function test_voiding_twice_or_submitting_a_voided_attempt_is_rejected(): void
    {
        [$student, $quiz] = $this->ready();
        $this->mcq($quiz);
        $admin = $this->admin();
        $a     = $this->attempts()->start($student, $quiz);

        $this->attempts()->void($a, $admin, 'test');

        $this->assertRejected('already_voided', fn () => $this->attempts()->void($a, $admin, 'again'));
        $this->assertRejected('attempt_closed', fn () => $this->attempts()->submit($student, $a));
        $this->assertNull($a->fresh()->is_open);
    }

    public function test_voiding_an_open_attempt_lets_the_student_start_a_new_one(): void
    {
        [$student, $quiz] = $this->ready();
        $this->mcq($quiz);
        $a = $this->attempts()->start($student, $quiz);

        $this->attempts()->void($a, $this->admin(), 'reset');
        $next = $this->attempts()->start($student, $quiz);

        $this->assertNotSame($a->id, $next->id);
        $this->assertSame('in_progress', $next->status);
    }

    /* ---------- ما يراه الطالب ---------- */

    public function test_student_payload_never_reveals_correct_answers(): void
    {
        [$student, $quiz] = $this->ready(['time_limit_minutes' => 30]);
        $q = $this->mcq($quiz);
        $this->textQ($quiz, ['SecretAnswer']);
        $a = $this->attempts()->start($student, $quiz);
        $this->attempts()->saveAnswer($student, $a, $q->id, $this->opt($q, false));

        $payload = $this->attempts()->presentForStudent($a);
        $json    = json_encode($payload);

        $this->assertStringNotContainsString('is_correct', $json);
        $this->assertStringNotContainsString('SecretAnswer', $json);
        $this->assertCount(2, $payload['questions']);
        $this->assertCount(3, $payload['questions'][0]['options']);
        $this->assertSame([], $payload['questions'][1]['options']);
        $this->assertSame($this->opt($q, false), $payload['questions'][0]['answer']['option_id']);
        $this->assertGreaterThan(0, $payload['remaining_seconds']);
    }

    public function test_quiz_structure_is_locked_once_attempts_exist(): void
    {
        $quiz = $this->quiz();
        $this->assertFalse($quiz->hasAttempts());

        QuizAttempt::factory()->create(['quiz_id' => $quiz->id]);

        $this->assertTrue($quiz->hasAttempts());
        $this->assertTrue(Quiz::attemptsExistForLesson($quiz->lesson_id));
        $this->assertTrue(Quiz::attemptsExistForCourse($this->courses[$quiz->id]->id));
    }

    /* ---------- شاشة الإدارة: الصلاحيات والمسارات ---------- */

    public function test_screens_require_permissions(): void
    {
        $attempt = QuizAttempt::factory()->create();
        $nobody  = User::factory()->create();

        $this->actingAs($nobody)->get(route('cms.admin.quiz-attempts.index'))->assertForbidden();
        $this->getJson(route('cms.admin.quiz-attempts.show', $attempt->id))->assertForbidden();
        $this->postJson(route('cms.admin.quiz-attempts.void', $attempt->id), ['reason' => 'abc'])->assertForbidden();

        $viewer = $this->admin('quiz-attempts.view', 'courses.manage-all');
        $this->actingAs($viewer)->get(route('cms.admin.quiz-attempts.index'))->assertOk();
        $this->getJson(route('cms.admin.quiz-attempts.show', $attempt->id))->assertOk();
        $this->postJson(route('cms.admin.quiz-attempts.void', $attempt->id), ['reason' => 'abc'])->assertForbidden();
        $this->assertSame('submitted', $attempt->fresh()->status);
    }

    public function test_guests_are_redirected(): void
    {
        $this->get(route('cms.admin.quiz-attempts.index'))->assertRedirect();
    }

    public function test_there_are_no_create_edit_or_delete_routes(): void
    {
        $attempt = QuizAttempt::factory()->create();
        $this->actingAs($this->admin());

        $this->postJson(route('cms.admin.quiz-attempts.index'), [])->assertStatus(405);
        $this->putJson(route('cms.admin.quiz-attempts.show', $attempt->id), ['score' => 100])->assertStatus(405);
        $this->deleteJson(route('cms.admin.quiz-attempts.show', $attempt->id))->assertStatus(405);
        $this->assertSame(1, QuizAttempt::count());
    }

    public function test_index_renders_html_and_escapes_names(): void
    {
        $evil = User::factory()->create(['name' => '<script>alert(1)</script>']);
        QuizAttempt::factory()->create(['user_id' => $evil->id]);
        $gone = User::factory()->create(['name' => 'Gone Student']);
        QuizAttempt::factory()->create(['user_id' => $gone->id]);
        $gone->delete();

        $res = $this->actingAs($this->admin())->get(route('cms.admin.quiz-attempts.index'))->assertOk();

        $res->assertDontSee('<script>alert(1)</script>', false)
            ->assertSee('&lt;script&gt;', false)
            ->assertSee('Gone Student');
    }

    public function test_index_json_returns_table_and_stats(): void
    {
        QuizAttempt::factory()->create();

        $this->actingAs($this->admin())->getJson(route('cms.admin.quiz-attempts.index'))
            ->assertOk()->assertJsonStructure(['html', 'stats' => ['total', 'in_progress', 'passed', 'failed', 'average']]);
    }

    public function test_filters_by_status_result_quiz_search_and_dates(): void
    {
        $quizA = Quiz::factory()->create(['title_ar' => 'Alpha Quiz', 'title_en' => 'Alpha Quiz']);
        $quizB = Quiz::factory()->create(['title_ar' => 'Beta Quiz', 'title_en' => 'Beta Quiz']);
        $ann   = User::factory()->create(['name' => 'Ann Student']);
        $bob   = User::factory()->create(['name' => 'Bob Student']);

        QuizAttempt::factory()->create(['user_id' => $ann->id, 'quiz_id' => $quizA->id]);                           // ناجحة
        QuizAttempt::factory()->failed()->create(['user_id' => $bob->id, 'quiz_id' => $quizB->id]);                 // راسبة
        QuizAttempt::factory()->inProgress()->create(['user_id' => $ann->id, 'quiz_id' => $quizB->id]);             // جارية
        QuizAttempt::factory()->status('voided')->create(['user_id' => $bob->id, 'quiz_id' => $quizA->id, 'is_passed' => false]);
        QuizAttempt::factory()->create(['started_at' => now()->subDays(10)->setTime(12, 0)]);

        $this->actingAs($this->admin());
        $html = fn (array $q) => $this->getJson(route('cms.admin.quiz-attempts.index', $q))->assertOk()->json('html');

        $this->assertStringContainsString('Ann Student', $html(['search' => 'Ann Student']));
        $this->assertStringNotContainsString('Bob Student', $html(['search' => 'Ann Student']));
        $this->assertStringContainsString('Alpha Quiz', $html(['search' => 'Alpha']));

        $this->assertStringContainsString('Bob Student', $html(['result' => 'failed']));
        $this->assertStringNotContainsString('Ann Student', $html(['result' => 'failed']));

        $this->assertStringNotContainsString('Bob Student', $html(['status' => 'in_progress']));
        $this->assertStringContainsString('Ann Student', $html(['status' => 'in_progress']));

        $this->assertStringContainsString('Beta Quiz', $html(['quiz_id' => $quizB->id]));
        $this->assertStringNotContainsString('Alpha Quiz', $html(['quiz_id' => $quizB->id]));

        $recent = $html(['from' => now()->subDay()->toDateString(), 'to' => now()->toDateString()]);
        $this->assertSame(4, substr_count($recent, '<tr data-id='));

        // قيمة تاريخ غير صالحة تُتجاهل بدل أن تكسر الاستعلام
        $this->getJson(route('cms.admin.quiz-attempts.index', ['from' => 'not-a-date', 'sort' => 'drop table']))->assertOk();
    }

    public function test_stats_count_each_state(): void
    {
        QuizAttempt::factory()->create(['score' => 80]);
        QuizAttempt::factory()->create(['score' => 60]);
        QuizAttempt::factory()->failed()->status('expired')->create();
        QuizAttempt::factory()->status('voided')->create(['score' => 90, 'is_passed' => false]);
        QuizAttempt::factory()->inProgress()->create();

        $stats = $this->actingAs($this->admin())->getJson(route('cms.admin.quiz-attempts.index'))->json('stats');

        $this->assertSame(5, $stats['total']);
        $this->assertSame(1, $stats['in_progress']);
        $this->assertSame(2, $stats['passed']);
        $this->assertSame(1, $stats['failed']);
        $this->assertSame('56.7', $stats['average']);
    }

    public function test_show_returns_graded_answers_with_the_key(): void
    {
        [$student, $quiz] = $this->ready();
        $q1 = $this->mcq($quiz);
        $q2 = $this->textQ($quiz, ['Paris']);
        $a  = $this->attempts()->start($student, $quiz);
        $this->attempts()->saveAnswer($student, $a, $q1->id, $this->opt($q1, false));
        $this->attempts()->saveAnswer($student, $a, $q2->id, null, 'Paris');
        $this->attempts()->submit($student, $a);

        $res = $this->actingAs($this->admin())->getJson(route('cms.admin.quiz-attempts.show', $a->id))->assertOk();

        $res->assertJsonPath('data.status', 'submitted')
            ->assertJsonPath('data.graded', true)
            ->assertJsonPath('data.points.earned', 1)
            ->assertJsonPath('data.points.total', 2)
            ->assertJsonPath('data.answers.0.is_correct', false)
            ->assertJsonPath('data.answers.0.correct', ['A'])
            ->assertJsonPath('data.answers.0.given', 'B')
            ->assertJsonPath('data.answers.1.is_correct', true)
            ->assertJsonPath('data.answers.1.given', 'Paris')
            ->assertJsonPath('data.can.void', true);
    }

    public function test_show_does_not_report_correctness_for_an_ungraded_attempt(): void
    {
        [$student, $quiz] = $this->ready();
        $q = $this->mcq($quiz);
        $a = $this->attempts()->start($student, $quiz);
        $this->attempts()->saveAnswer($student, $a, $q->id, $this->opt($q));

        $this->actingAs($this->admin())->getJson(route('cms.admin.quiz-attempts.show', $a->id))
            ->assertOk()->assertJsonPath('data.graded', false)->assertJsonPath('data.answers.0.is_correct', null);
    }

    public function test_void_endpoint_voids_and_validates_the_reason(): void
    {
        $attempt = QuizAttempt::factory()->create();
        $admin   = $this->admin();
        $this->actingAs($admin);

        $this->postJson(route('cms.admin.quiz-attempts.void', $attempt->id), [])->assertStatus(422)->assertJsonValidationErrors('reason');
        $this->postJson(route('cms.admin.quiz-attempts.void', $attempt->id), ['reason' => 'ab'])->assertStatus(422);
        $this->postJson(route('cms.admin.quiz-attempts.void', $attempt->id), ['reason' => str_repeat('x', 501)])->assertStatus(422);
        $this->assertSame('submitted', $attempt->fresh()->status);

        $this->postJson(route('cms.admin.quiz-attempts.void', $attempt->id), ['reason' => 'Suspicious timing'])
            ->assertOk()->assertJsonStructure(['message', 'stats']);

        $fresh = $attempt->fresh();
        $this->assertSame('voided', $fresh->status);
        $this->assertSame('Suspicious timing', $fresh->void_reason);
        $this->assertSame($admin->id, $fresh->voided_by);

        $this->postJson(route('cms.admin.quiz-attempts.void', $attempt->id), ['reason' => 'Again please'])->assertStatus(409);
    }

    public function test_voided_attempt_shows_who_and_why(): void
    {
        $attempt = QuizAttempt::factory()->create();
        $admin   = $this->admin();
        $this->actingAs($admin)->postJson(route('cms.admin.quiz-attempts.void', $attempt->id), ['reason' => 'Shared screen'])->assertOk();

        $this->getJson(route('cms.admin.quiz-attempts.show', $attempt->id))
            ->assertJsonPath('data.status', 'voided')
            ->assertJsonPath('data.void_reason', 'Shared screen')
            ->assertJsonPath('data.voided_by', $admin->name)
            ->assertJsonPath('data.can.void', false);
    }

    public function test_instructors_only_see_attempts_of_their_own_courses(): void
    {
        $teacher = User::factory()->create();
        $teacher->givePermissionTo(['quiz-attempts.view', 'quiz-attempts.void']);

        $mine   = $this->quiz(['title_ar' => 'Mine Quiz', 'title_en' => 'Mine Quiz'], Course::factory()->published()->create(['instructor_id' => $teacher->id]));
        $theirs = $this->quiz(['title_ar' => 'Theirs Quiz', 'title_en' => 'Theirs Quiz']);
        $own    = QuizAttempt::factory()->create(['quiz_id' => $mine->id]);
        $alien  = QuizAttempt::factory()->create(['quiz_id' => $theirs->id]);

        $this->actingAs($teacher);

        $html = $this->getJson(route('cms.admin.quiz-attempts.index'))->assertOk()->json('html');
        $this->assertStringContainsString('Mine Quiz', $html);
        $this->assertStringNotContainsString('Theirs Quiz', $html);
        $this->assertSame(1, $this->getJson(route('cms.admin.quiz-attempts.index'))->json('stats.total'));

        $this->getJson(route('cms.admin.quiz-attempts.show', $own->id))->assertOk();
        $this->getJson(route('cms.admin.quiz-attempts.show', $alien->id))->assertNotFound();
        $this->postJson(route('cms.admin.quiz-attempts.void', $alien->id), ['reason' => 'not mine'])->assertNotFound();
        $this->assertSame('submitted', $alien->fresh()->status);

        $this->postJson(route('cms.admin.quiz-attempts.void', $own->id), ['reason' => 'my course'])->assertOk();
    }

    public function test_deleted_quizzes_and_students_still_appear_for_admins(): void
    {
        $quiz    = Quiz::factory()->create(['title_ar' => 'Retired Quiz', 'title_en' => 'Retired Quiz']);
        $student = User::factory()->create();
        $attempt = QuizAttempt::factory()->create(['quiz_id' => $quiz->id, 'user_id' => $student->id]);
        $quiz->delete();
        $student->delete();

        $this->actingAs($this->admin())->getJson(route('cms.admin.quiz-attempts.show', $attempt->id))
            ->assertOk()->assertJsonPath('data.user.deleted', true);
        $this->assertStringContainsString('Retired Quiz', $this->getJson(route('cms.admin.quiz-attempts.index'))->json('html'));
    }
}
