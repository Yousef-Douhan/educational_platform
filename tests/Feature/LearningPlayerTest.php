<?php

namespace Tests\Feature;

use App\Models\Course;
use App\Models\CourseSection;
use App\Models\Enrollment;
use App\Models\Lesson;
use App\Models\LessonAttachment;
use App\Models\LessonProgress;
use App\Models\Question;
use App\Models\QuestionOption;
use App\Models\Quiz;
use App\Models\QuizAttempt;
use App\Models\User;
use App\Support\VideoEmbed;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class LearningPlayerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        app()->setLocale('ar');
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    private function course(): Course
    {
        return Course::factory()->published()->create();
    }

    private function section(Course $c): CourseSection
    {
        return CourseSection::factory()->create(['course_id' => $c->id]);
    }

    private function lesson(CourseSection $s, array $o = []): Lesson
    {
        return Lesson::factory()->create(array_merge(['section_id' => $s->id, 'is_published' => true], $o));
    }

    private function enrolled(Course $c): User
    {
        $u = User::factory()->create();
        Enrollment::factory()->create(['user_id' => $u->id, 'course_id' => $c->id, 'status' => 'active']);

        return $u;
    }

    private function quizLesson(CourseSection $s, int $passMark = 50): array
    {
        $lesson = $this->lesson($s, ['content_type' => 'quiz', 'content_ar' => null, 'title_ar' => 'درس-الاختبار']);
        $quiz   = Quiz::factory()->create(['lesson_id' => $lesson->id, 'pass_mark' => $passMark]);
        $q      = Question::factory()->create(['quiz_id' => $quiz->id, 'question_text' => 'سؤال-واحد']);
        $right  = QuestionOption::factory()->create(['question_id' => $q->id, 'option_text' => 'صح-الخيار', 'is_correct' => true]);
        $wrong  = QuestionOption::factory()->create(['question_id' => $q->id, 'option_text' => 'خطأ-الخيار', 'is_correct' => false]);

        return [$lesson, $quiz, $q, $right, $wrong];
    }

    /* ---------- الوصول ---------- */

    public function test_enrolled_student_is_sent_to_the_first_unfinished_lesson_and_sees_the_video(): void
    {
        $course = $this->course();
        $s      = $this->section($course);
        $first  = $this->lesson($s, ['sort_order' => 1, 'title_ar' => 'الدرس-الأول']);
        $second = $this->lesson($s, ['sort_order' => 2, 'content_type' => 'video', 'video_provider' => 'youtube',
            'video_url' => 'https://www.youtube.com/watch?v=dQw4w9WgXcQ', 'title_ar' => 'الدرس-الثاني']);
        $user = $this->enrolled($course);

        $this->actingAs($user)->get(route('student.learn.course', $course->slug))
            ->assertRedirect(route('student.learn.lesson', [$course->slug, $first->id]));

        $this->post(route('student.learn.complete', [$course->slug, $first->id]))
            ->assertRedirect(route('student.learn.lesson', [$course->slug, $second->id]));

        $this->get(route('student.learn.course', $course->slug))
            ->assertRedirect(route('student.learn.lesson', [$course->slug, $second->id]));

        $this->get(route('student.learn.lesson', [$course->slug, $second->id]))->assertOk()
            ->assertSee('https://www.youtube-nocookie.com/embed/dQw4w9WgXcQ', false)
            ->assertSee('الدرس-الأول');
    }

    public function test_non_enrolled_user_cannot_see_lesson_content(): void
    {
        $course = $this->course();
        $s      = $this->section($course);
        $lesson = $this->lesson($s, ['content_ar' => 'نص-سري-جدا']);

        $this->actingAs(User::factory()->create())
            ->get(route('student.learn.lesson', [$course->slug, $lesson->id]))
            ->assertRedirect(route('student.courses.show', $course->slug));

        $this->get(route('student.learn.course', $course->slug))->assertForbidden();
    }

    public function test_guest_is_sent_to_login_except_for_free_previews(): void
    {
        $course  = $this->course();
        $s       = $this->section($course);
        $locked  = $this->lesson($s, ['content_ar' => 'نص-مغلق']);
        $preview = $this->lesson($s, ['is_free_preview' => true, 'content_ar' => 'نص-المعاينة']);

        $this->get(route('student.learn.lesson', [$course->slug, $locked->id]))->assertRedirect(route('login'));
        $this->get(route('student.learn.lesson', [$course->slug, $preview->id]))->assertOk()
            ->assertSee('نص-المعاينة')->assertDontSee('نص-مغلق');
    }

    public function test_unpublished_expired_and_foreign_lessons_are_not_reachable(): void
    {
        $course = $this->course();
        $s      = $this->section($course);
        $draft  = $this->lesson($s, ['is_published' => false]);
        $other  = $this->lesson($this->section($this->course()));
        $user   = $this->enrolled($course);

        $this->actingAs($user);
        $this->get(route('student.learn.lesson', [$course->slug, $draft->id]))->assertNotFound();
        $this->get(route('student.learn.lesson', [$course->slug, $other->id]))->assertNotFound();

        $expired = User::factory()->create();
        Enrollment::factory()->create(['user_id' => $expired->id, 'course_id' => $course->id, 'status' => 'expired']);
        $ok = $this->lesson($s);
        $this->actingAs($expired)->get(route('student.learn.lesson', [$course->slug, $ok->id]))
            ->assertRedirect(route('student.courses.show', $course->slug));
    }

    public function test_draft_course_cannot_be_learned(): void
    {
        $course = Course::factory()->create();
        $s      = $this->section($course);
        $lesson = $this->lesson($s);
        $user   = $this->enrolled($course);

        $this->actingAs($user)->get(route('student.learn.lesson', [$course->slug, $lesson->id]))->assertNotFound();
    }

    /* ---------- الإكمال والتقدّم ---------- */

    public function test_complete_and_uncomplete_update_progress(): void
    {
        $course = $this->course();
        $s      = $this->section($course);
        $a      = $this->lesson($s, ['sort_order' => 1]);
        $b      = $this->lesson($s, ['sort_order' => 2]);
        $user   = $this->enrolled($course);
        $this->actingAs($user);

        $this->post(route('student.learn.complete', [$course->slug, $a->id]));
        $this->assertDatabaseHas('course_progress', ['user_id' => $user->id, 'course_id' => $course->id, 'progress_percentage' => 50]);

        $this->delete(route('student.learn.uncomplete', [$course->slug, $a->id]));
        $this->assertDatabaseHas('course_progress', ['user_id' => $user->id, 'course_id' => $course->id, 'progress_percentage' => 0]);
        $this->assertSame(0, LessonProgress::where(['user_id' => $user->id, 'is_completed' => true])->count());
    }

    public function test_cannot_complete_a_lesson_without_enrollment_or_for_quiz_lessons(): void
    {
        $course = $this->course();
        $s      = $this->section($course);
        $plain  = $this->lesson($s);
        [$quizLesson] = $this->quizLesson($s);

        $this->actingAs(User::factory()->create())
            ->post(route('student.learn.complete', [$course->slug, $plain->id]))->assertSessionHasErrors('learn');
        $this->assertSame(0, LessonProgress::count());

        $user = $this->enrolled($course);
        $this->actingAs($user)->post(route('student.learn.complete', [$course->slug, $quizLesson->id]))
            ->assertSessionHasErrors('learn');
    }

    public function test_complete_needs_login(): void
    {
        $course = $this->course();
        $lesson = $this->lesson($this->section($course));

        $this->post(route('student.learn.complete', [$course->slug, $lesson->id]))->assertRedirect(route('login'));
    }

    /* ---------- الاختبارات ---------- */

    public function test_quiz_pass_flow_hides_correct_answers_and_completes_the_lesson(): void
    {
        $course = $this->course();
        $s      = $this->section($course);
        [$lesson, $quiz, $q, $right] = $this->quizLesson($s);
        $user = $this->enrolled($course);
        $this->actingAs($user);

        $this->get(route('student.learn.lesson', [$course->slug, $lesson->id]))->assertOk()->assertSee(__('student.learn.start_quiz'));

        $this->post(route('student.quizzes.start', $quiz->id))->assertRedirect();
        $attempt = QuizAttempt::where('user_id', $user->id)->firstOrFail();

        $page = $this->get(route('student.attempts.show', $attempt->id))->assertOk()
            ->assertSee('سؤال-واحد')->assertSee('صح-الخيار')->assertSee('خطأ-الخيار');
        $this->assertStringNotContainsString('is_correct', $page->getContent());

        $this->post(route('student.attempts.submit', $attempt->id), ['answers' => [$q->id => (string) $right->id]])
            ->assertRedirect(route('student.attempts.show', $attempt->id));

        $attempt->refresh();
        $this->assertTrue($attempt->is_passed);
        $this->assertSame('submitted', $attempt->status);
        $this->get(route('student.attempts.show', $attempt->id))->assertOk()->assertSee(__('student.learn.result_passed'));
        $this->assertDatabaseHas('lesson_progress', ['user_id' => $user->id, 'lesson_id' => $lesson->id, 'is_completed' => true]);
    }

    public function test_quiz_wrong_answer_fails_and_attempts_are_limited(): void
    {
        $course = $this->course();
        [$lesson, $quiz, $q, $right, $wrong] = $this->quizLesson($this->section($course));
        $quiz->update(['attempts_allowed' => 1]);
        $user = $this->enrolled($course);
        $this->actingAs($user);

        $this->post(route('student.quizzes.start', $quiz->id));
        $attempt = QuizAttempt::firstOrFail();
        $this->post(route('student.attempts.submit', $attempt->id), ['answers' => [$q->id => (string) $wrong->id]]);

        $this->assertFalse((bool) $attempt->fresh()->is_passed);
        $this->assertDatabaseMissing('lesson_progress', ['user_id' => $user->id, 'lesson_id' => $lesson->id]);

        $this->post(route('student.quizzes.start', $quiz->id))->assertSessionHasErrors('quiz');
        $this->assertSame(1, QuizAttempt::count());
    }

    public function test_quiz_rejects_foreign_options_and_non_enrolled_students(): void
    {
        $course = $this->course();
        [, $quiz, $q] = $this->quizLesson($this->section($course));
        $foreign = QuestionOption::factory()->create(['is_correct' => true]);

        $this->actingAs(User::factory()->create())->post(route('student.quizzes.start', $quiz->id))->assertSessionHasErrors('quiz');
        $this->assertSame(0, QuizAttempt::count());

        $user = $this->enrolled($course);
        $this->actingAs($user)->post(route('student.quizzes.start', $quiz->id));
        $attempt = QuizAttempt::firstOrFail();

        $this->post(route('student.attempts.submit', $attempt->id), ['answers' => [$q->id => (string) $foreign->id]])
            ->assertSessionHasErrors('quiz');
        $this->assertSame('in_progress', $attempt->fresh()->status);
    }

    public function test_attempts_are_private_to_their_owner(): void
    {
        $course = $this->course();
        [, $quiz] = $this->quizLesson($this->section($course));
        $owner = $this->enrolled($course);

        $this->actingAs($owner)->post(route('student.quizzes.start', $quiz->id));
        $attempt = QuizAttempt::firstOrFail();

        $this->actingAs($this->enrolled($course));
        $this->get(route('student.attempts.show', $attempt->id))->assertNotFound();
        $this->post(route('student.attempts.submit', $attempt->id), ['answers' => []])->assertNotFound();
    }

    /* ---------- المرفقات ---------- */

    public function test_attachments_download_only_for_enrolled_students(): void
    {
        Storage::fake('local');
        $course = $this->course();
        $lesson = $this->lesson($this->section($course));
        Storage::disk('local')->put('lesson-attachments/1/x.pdf', 'PDFDATA');
        $file = LessonAttachment::create(['lesson_id' => $lesson->id, 'title' => 'ملزمة', 'file_path' => 'lesson-attachments/1/x.pdf', 'file_size' => 7]);

        $this->get(route('student.attachments.download', $file->id))->assertRedirect(route('login'));
        $this->actingAs(User::factory()->create())->get(route('student.attachments.download', $file->id))->assertForbidden();
        $this->actingAs($this->enrolled($course))->get(route('student.attachments.download', $file->id))->assertOk();
    }

    /* ---------- روابط الفيديو ---------- */

    public function test_video_embed_builds_safe_urls_only(): void
    {
        $this->assertSame('https://www.youtube-nocookie.com/embed/dQw4w9WgXcQ?rel=0',
            VideoEmbed::resolve('youtube', 'https://youtu.be/dQw4w9WgXcQ')['src']);
        $this->assertSame('https://www.youtube-nocookie.com/embed/dQw4w9WgXcQ?rel=0',
            VideoEmbed::resolve('youtube', 'https://www.youtube.com/watch?v=dQw4w9WgXcQ&t=5')['src']);
        $this->assertSame('https://drive.google.com/file/d/1AbCdEfGhIjK/preview',
            VideoEmbed::resolve('drive', 'https://drive.google.com/file/d/1AbCdEfGhIjK/view?usp=sharing')['src']);

        $this->assertNull(VideoEmbed::resolve('youtube', 'https://evil.example/watch?v=dQw4w9WgXcQ'));
        $this->assertNull(VideoEmbed::resolve('youtube', 'javascript:alert(1)'));
        $this->assertNull(VideoEmbed::resolve('youtube', 'http://www.youtube.com/watch?v=dQw4w9WgXcQ'));
        $this->assertNull(VideoEmbed::resolve('youtube', 'https://www.youtube.com/watch?v="><script>'));
        $this->assertNull(VideoEmbed::resolve('drive', 'https://evil.example/file/d/1AbCdEfGhIjK'));
        $this->assertNull(VideoEmbed::resolve('s3', '../secret'));
        $this->assertNull(VideoEmbed::resolve('nope', 'https://x.com'));
        $this->assertNull(VideoEmbed::resolve('youtube', ''));

        $this->assertSame('link', VideoEmbed::resolve('external', 'https://example.org/v')['type']);
        $this->assertNull(VideoEmbed::resolve('external', 'javascript:alert(1)'));
    }

    public function test_lesson_page_escapes_text_content(): void
    {
        $course = $this->course();
        $lesson = $this->lesson($this->section($course), ['content_ar' => '<script>alert(1)</script>']);
        $user   = $this->enrolled($course);

        $this->actingAs($user)->get(route('student.learn.lesson', [$course->slug, $lesson->id]))
            ->assertOk()->assertDontSee('<script>alert(1)</script>', false);
    }

    public function test_my_courses_links_into_the_player(): void
    {
        $course = $this->course();
        $user   = $this->enrolled($course);

        $this->actingAs($user)->get(route('student.courses.mine'))
            ->assertSee(route('student.learn.course', $course->slug), false);
    }
}
