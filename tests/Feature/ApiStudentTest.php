<?php

namespace Tests\Feature;

use App\Models\Course;
use App\Models\CourseSection;
use App\Models\Enrollment;
use App\Models\Lesson;
use App\Models\LiveSession;
use App\Models\Order;
use App\Models\Question;
use App\Models\QuestionOption;
use App\Models\Quiz;
use App\Models\User;
use App\Notifications\PlatformNotification;
use App\Services\Notifier;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Passport\Passport;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

/** واجهة الطالب في الـAPI: الملكية، الوصول للمحتوى، السلة والطلبات، الاختبارات، الإشعارات. */
class ApiStudentTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    private function student(array $scopes = ['student']): User
    {
        $u = User::factory()->create();
        Passport::actingAs($u, $scopes, 'api');

        return $u;
    }

    /** @return array{0: Course, 1: Lesson[]} */
    private function course(int $lessons = 2, array $attrs = []): array
    {
        $course  = Course::factory()->published()->create($attrs);
        $section = CourseSection::factory()->create(['course_id' => $course->id]);
        $list    = [];
        for ($i = 1; $i <= $lessons; $i++) {
            $list[] = Lesson::factory()->create(['section_id' => $section->id, 'is_published' => true, 'sort_order' => $i, 'content_type' => 'article']);
        }

        return [$course, $list];
    }

    private function enroll(User $u, Course $c): void
    {
        Enrollment::factory()->create(['user_id' => $u->id, 'course_id' => $c->id, 'status' => 'active']);
    }

    /* ---------- الصلاحيات ---------- */

    public function test_student_routes_need_a_token_and_the_student_scope(): void
    {
        $this->getJson('/api/v1/my-courses')->assertUnauthorized();

        $this->student(['admin']);
        $this->getJson('/api/v1/my-courses')->assertForbidden()->assertJsonPath('code', 'insufficient_scope');
    }

    /* ---------- دوراتي والتعلم ---------- */

    public function test_my_courses_lists_only_own_active_enrollments_with_progress(): void
    {
        $me = $this->student();
        [$mine] = $this->course();
        [$other] = $this->course();
        $this->enroll($me, $mine);
        $this->enroll(User::factory()->create(), $other);

        $this->getJson('/api/v1/my-courses')->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.course.slug', $mine->slug)
            ->assertJsonPath('data.0.progress.percent', 0);
    }

    public function test_free_enroll_works_and_paid_enroll_is_refused(): void
    {
        $this->student();
        [$free] = $this->course(1, ['price' => 0, 'discount_price' => null]);
        [$paid] = $this->course(1, ['price' => 50, 'discount_price' => null]);

        $this->postJson("/api/v1/courses/{$free->slug}/enroll")->assertCreated();
        $this->postJson("/api/v1/courses/{$free->slug}/enroll")->assertUnprocessable();
        $this->postJson("/api/v1/courses/{$paid->slug}/enroll")->assertUnprocessable()->assertJsonPath('code', 'not_free');
    }

    public function test_learning_requires_enrollment_and_hides_content_from_the_curriculum(): void
    {
        $me = $this->student();
        [$course, $lessons] = $this->course(2);

        $this->getJson("/api/v1/learn/{$course->slug}")->assertForbidden();
        $this->getJson("/api/v1/learn/{$course->slug}/lessons/{$lessons[0]->id}")->assertForbidden();

        $this->enroll($me, $course);

        $res = $this->getJson("/api/v1/learn/{$course->slug}")->assertOk()
            ->assertJsonPath('data.next_lesson_id', $lessons[0]->id);
        $this->assertStringNotContainsString('video_url', $res->getContent());
        $this->assertStringNotContainsString('content_ar', $res->getContent());

        $this->getJson("/api/v1/learn/{$course->slug}/lessons/{$lessons[0]->id}")->assertOk()
            ->assertJsonPath('data.id', $lessons[0]->id)
            ->assertJsonPath('data.enrolled', true)
            ->assertJsonPath('data.next_lesson_id', $lessons[1]->id);
    }

    public function test_free_preview_lesson_is_open_without_enrollment(): void
    {
        $this->student();
        [$course, $lessons] = $this->course(2);
        $lessons[0]->update(['is_free_preview' => true]);

        $this->getJson("/api/v1/learn/{$course->slug}/lessons/{$lessons[0]->id}")->assertOk()->assertJsonPath('data.enrolled', false);
        $this->getJson("/api/v1/learn/{$course->slug}/lessons/{$lessons[1]->id}")->assertForbidden();
    }

    public function test_complete_and_uncomplete_update_progress(): void
    {
        $me = $this->student();
        [$course, $lessons] = $this->course(2);
        $this->enroll($me, $course);

        $this->postJson("/api/v1/learn/{$course->slug}/lessons/{$lessons[0]->id}/complete")->assertOk()
            ->assertJsonPath('progress.completed', 1)->assertJsonPath('progress.total', 2)->assertJsonPath('progress.percent', 50);

        $this->deleteJson("/api/v1/learn/{$course->slug}/lessons/{$lessons[0]->id}/complete")->assertOk()
            ->assertJsonPath('progress.completed', 0);
    }

    public function test_cannot_complete_a_lesson_of_a_course_not_enrolled(): void
    {
        $this->student();
        [$course, $lessons] = $this->course(1);

        $this->postJson("/api/v1/learn/{$course->slug}/lessons/{$lessons[0]->id}/complete")->assertUnprocessable();
    }

    /* ---------- السلة والطلبات ---------- */

    public function test_cart_add_checkout_and_order_privacy(): void
    {
        $me = $this->student();
        [$paid] = $this->course(1, ['price' => 80, 'discount_price' => null]);
        [$free] = $this->course(1, ['price' => 0, 'discount_price' => null]);

        $this->postJson('/api/v1/cart', ['course_id' => $free->id])->assertUnprocessable()->assertJsonPath('code', 'free_course');
        $this->postJson('/api/v1/cart', ['course_id' => $paid->id])->assertCreated()->assertJsonPath('data.subtotal', '80.00');
        $this->postJson('/api/v1/cart', ['course_id' => $paid->id])->assertUnprocessable()->assertJsonPath('code', 'already_in_cart');
        $this->postJson('/api/v1/cart', [])->assertUnprocessable();

        $res = $this->postJson('/api/v1/checkout')->assertCreated()
            ->assertJsonPath('data.status', 'pending')->assertJsonPath('data.total_amount', '80.00');
        $number = $res->json('data.order_number');

        $this->getJson('/api/v1/orders')->assertOk()->assertJsonCount(1, 'data');
        $this->getJson("/api/v1/orders/{$number}")->assertOk()->assertJsonPath('data.items.0.course_id', $paid->id);

        // طلب غيري: 404 لا 403
        $this->actingAsOther();
        $this->getJson("/api/v1/orders/{$number}")->assertNotFound();
        $this->postJson("/api/v1/orders/{$number}/pay")->assertNotFound();
        $this->getJson('/api/v1/orders')->assertJsonCount(0, 'data');

        $this->assertSame(1, Order::where('user_id', $me->id)->count());
    }

    public function test_checkout_with_an_empty_cart_is_refused(): void
    {
        $this->student();

        $this->postJson('/api/v1/checkout')->assertUnprocessable();
    }

    public function test_remove_from_cart(): void
    {
        $this->student();
        [$paid] = $this->course(1, ['price' => 30, 'discount_price' => null]);

        $this->postJson('/api/v1/cart', ['course_id' => $paid->id])->assertCreated();
        $this->deleteJson("/api/v1/cart/{$paid->id}")->assertOk()->assertJsonCount(0, 'data.items');
    }

    public function test_pay_without_stripe_configured_is_a_clean_503(): void
    {
        $this->student();
        config(['payments.stripe.secret' => null]);
        [$paid] = $this->course(1, ['price' => 30, 'discount_price' => null]);

        $this->postJson('/api/v1/cart', ['course_id' => $paid->id])->assertCreated();
        $number = $this->postJson('/api/v1/checkout')->json('data.order_number');

        $this->postJson("/api/v1/orders/{$number}/pay")->assertStatus(503)->assertJsonPath('code', 'not_configured');
    }

    public function test_receipt_upload_validates_input(): void
    {
        $this->student();
        [$paid] = $this->course(1, ['price' => 30, 'discount_price' => null]);
        $this->postJson('/api/v1/cart', ['course_id' => $paid->id]);
        $number = $this->postJson('/api/v1/checkout')->json('data.order_number');

        $this->postJson("/api/v1/orders/{$number}/receipts", [])->assertUnprocessable()
            ->assertJsonValidationErrors(['method', 'sender_name', 'reference_number', 'receipt']);
    }

    /* ---------- الاختبارات ---------- */

    public function test_quiz_attempt_never_exposes_correct_answers_and_grades_on_the_server(): void
    {
        $me = $this->student();
        [$course, $lessons] = $this->course(1);
        $this->enroll($me, $course);

        $lesson = Lesson::factory()->create(['section_id' => $lessons[0]->section_id, 'content_type' => 'quiz', 'content_ar' => null, 'is_published' => true, 'sort_order' => 9]);
        $quiz   = Quiz::factory()->create(['lesson_id' => $lesson->id, 'pass_mark' => 50, 'attempts_allowed' => 5]);
        $q      = Question::factory()->create(['quiz_id' => $quiz->id, 'question_type' => 'multiple_choice', 'points' => 1]);
        $right  = QuestionOption::factory()->create(['question_id' => $q->id, 'option_text' => 'A', 'is_correct' => true]);
        QuestionOption::factory()->create(['question_id' => $q->id, 'option_text' => 'B', 'is_correct' => false]);

        $start = $this->postJson("/api/v1/quizzes/{$quiz->id}/attempts")->assertCreated();
        $this->assertStringNotContainsString('is_correct', $start->getContent());
        $attempt = $start->json('data.attempt_id');

        $this->getJson("/api/v1/attempts/{$attempt}")->assertOk()->assertJsonCount(1, 'data.questions');

        $this->postJson("/api/v1/attempts/{$attempt}/submit", ['answers' => [$q->id => (string) $right->id]])
            ->assertOk()->assertJsonPath('data.is_passed', true)->assertJsonPath('data.status', 'submitted');

        // محاولة غيري: 404
        $this->actingAsOther();
        $this->getJson("/api/v1/attempts/{$attempt}")->assertNotFound();
    }

    public function test_cannot_start_a_quiz_without_enrollment(): void
    {
        $this->student();
        [$course, $lessons] = $this->course(1);
        $lesson = Lesson::factory()->create(['section_id' => $lessons[0]->section_id, 'content_type' => 'quiz', 'content_ar' => null, 'is_published' => true]);
        $quiz   = Quiz::factory()->create(['lesson_id' => $lesson->id]);

        $this->postJson("/api/v1/quizzes/{$quiz->id}/attempts")->assertUnprocessable()->assertJsonPath('code', 'not_enrolled');
    }

    /* ---------- الشهادات والإشعارات والجلسات ---------- */

    public function test_certificates_list_is_empty_and_claim_needs_completion(): void
    {
        $me = $this->student();
        [$course] = $this->course(1);
        $this->enroll($me, $course);

        $this->getJson('/api/v1/certificates')->assertOk()->assertJsonCount(0, 'data.certificates');
        $this->postJson("/api/v1/courses/{$course->slug}/certificate")->assertUnprocessable();
    }

    public function test_notifications_are_private_and_can_be_marked_read(): void
    {
        $me = $this->student();
        [$course] = $this->course(1);
        app(Notifier::class)->toUser($me, PlatformNotification::enrollmentActivated($course));

        $res = $this->getJson('/api/v1/notifications')->assertOk()->assertJsonPath('meta.unread', 1)->assertJsonCount(1, 'data');
        $id  = $res->json('data.0.id');

        $this->actingAsOther();
        $this->postJson("/api/v1/notifications/{$id}/read")->assertNotFound();
        $this->deleteJson("/api/v1/notifications/{$id}")->assertNotFound();

        Passport::actingAs($me, ['student'], 'api');
        $this->postJson('/api/v1/notifications/read-all')->assertOk();
        $this->getJson('/api/v1/notifications')->assertJsonPath('meta.unread', 0);
    }

    public function test_live_sessions_hide_the_meeting_url_until_join_opens(): void
    {
        $me = $this->student();
        [$course] = $this->course(1);
        $this->enroll($me, $course);

        $future = LiveSession::forceCreate([
            'course_id' => $course->id, 'title_ar' => 'جلسة', 'title_en' => 'Session',
            'meeting_url' => 'https://meet.example.com/secret-room', 'scheduled_at' => now()->addDays(2),
            'duration_minutes' => 60, 'status' => 'scheduled', 'created_by' => $course->instructor_id,
        ]);
        $soon = LiveSession::forceCreate([
            'course_id' => $course->id, 'title_ar' => 'قريبة', 'title_en' => 'Soon',
            'meeting_url' => 'https://meet.example.com/soon-room', 'scheduled_at' => now()->addMinutes(5),
            'duration_minutes' => 60, 'status' => 'scheduled', 'created_by' => $course->instructor_id,
        ]);

        $list = $this->getJson('/api/v1/live-sessions')->assertOk();
        $this->assertStringNotContainsString('secret-room', $list->getContent());
        $this->assertStringNotContainsString('meeting_url', $list->getContent());

        $this->postJson("/api/v1/live-sessions/{$future->id}/join")->assertUnprocessable()->assertJsonPath('code', 'not_open');
        $this->postJson("/api/v1/live-sessions/{$soon->id}/join")->assertOk()->assertJsonPath('data.meeting_url', 'https://meet.example.com/soon-room');

        // غير مسجّل في الدورة: 404
        $this->actingAsOther();
        $this->postJson("/api/v1/live-sessions/{$soon->id}/join")->assertNotFound();
    }

    public function test_unknown_resources_return_a_clean_json_404_without_model_names(): void
    {
        $this->student();

        $res = $this->getJson('/api/v1/orders/ORD-NOPE')->assertNotFound();
        $this->assertStringNotContainsString('App\\Models', $res->getContent());
        $res->assertJsonPath('code', 'not_found');
    }

    private function actingAsOther(): User
    {
        $u = User::factory()->create();
        Passport::actingAs($u, ['student'], 'api');

        return $u;
    }
}
