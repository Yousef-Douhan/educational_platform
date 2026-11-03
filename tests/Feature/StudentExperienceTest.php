<?php

namespace Tests\Feature;

use App\Models\Course;
use App\Models\Enrollment;
use App\Models\LiveSession;
use App\Models\User;
use App\Notifications\PlatformNotification;
use App\Services\Notifier;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

/** اللغة، الإشعارات، الجلسات المباشرة وصفحة الدخول في واجهة الطالب */
class StudentExperienceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        app()->setLocale('ar');
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    private function enrolled(Course $course, ?User $user = null, string $status = 'active'): User
    {
        $user ??= User::factory()->create();
        Enrollment::factory()->create(['user_id' => $user->id, 'course_id' => $course->id, 'status' => $status]);

        return $user;
    }

    /* ---------- اللغة ---------- */

    public function test_locale_switch_sets_a_cookie_and_rejects_unknown_locales(): void
    {
        $this->from('/')->post(route('locale.update', 'en'))->assertRedirect()->assertCookie('locale', 'en');
        $this->post('/locale/fr')->assertNotFound();
    }

    public function test_the_chosen_locale_changes_direction_and_text(): void
    {
        $this->withCookie('locale', 'en')->get(route('student.home'))->assertOk()->assertSee('dir="ltr"', false)->assertSee('Courses');
        $this->withCookie('locale', 'ar')->get(route('student.home'))->assertOk()->assertSee('dir="rtl"', false);
    }

    public function test_a_tampered_locale_cookie_is_ignored(): void
    {
        app()->setLocale('ar');
        $this->withCookie('locale', '../../etc')->get(route('student.home'))->assertOk()->assertSee('dir="rtl"', false);
    }

    public function test_redirect_never_leaves_the_site(): void
    {
        $res = $this->from('https://evil.example/x')->post(route('locale.update', 'en'));

        $res->assertRedirect(route('student.home'));
    }

    public function test_layout_has_the_theme_toggle_and_login_is_localized(): void
    {
        $this->get(route('login'))->assertOk()->assertSee('data-theme-toggle', false)->assertSee('تسجيل الدخول');
        $this->withCookie('locale', 'en')->get(route('login'))->assertOk()->assertSee('Sign in')->assertDontSee('Educational CMS');
    }

    /* ---------- الإشعارات ---------- */

    public function test_student_sees_only_his_notifications_and_can_manage_them(): void
    {
        $me = User::factory()->create();
        $other = User::factory()->create();
        $course = Course::factory()->published()->create();
        app(Notifier::class)->toUser($me, PlatformNotification::enrollmentActivated($course));
        app(Notifier::class)->toUser($other, PlatformNotification::enrollmentActivated($course));

        $mine = $me->notifications()->firstOrFail();
        $theirs = $other->notifications()->firstOrFail();

        $this->actingAs($me)->get(route('student.notifications.index'))->assertOk()->assertSee('تم تسجيلك في دورة');

        $this->actingAs($me)->post(route('student.notifications.read', $theirs->id))->assertNotFound();
        $this->actingAs($me)->delete(route('student.notifications.destroy', $theirs->id))->assertNotFound();
        $this->assertNull($theirs->fresh()->read_at);

        $this->actingAs($me)->post(route('student.notifications.read', $mine->id))->assertRedirect();
        $this->assertNotNull($mine->fresh()->read_at);

        $this->actingAs($me)->delete(route('student.notifications.destroy', $mine->id))->assertRedirect();
        $this->assertSame(0, $me->notifications()->count());
        $this->assertSame(1, $other->notifications()->count());
    }

    public function test_mark_all_read_touches_only_my_notifications(): void
    {
        $me = User::factory()->create();
        $other = User::factory()->create();
        $course = Course::factory()->published()->create();
        app(Notifier::class)->toUser($me, PlatformNotification::enrollmentActivated($course));
        app(Notifier::class)->toUser($other, PlatformNotification::enrollmentActivated($course));

        $this->actingAs($me)->post(route('student.notifications.read-all'))->assertRedirect();

        $this->assertSame(0, $me->unreadNotifications()->count());
        $this->assertSame(1, $other->unreadNotifications()->count());
    }

    public function test_notifications_need_login(): void
    {
        $this->get(route('student.notifications.index'))->assertRedirect(route('login'));
    }

    public function test_rejecting_a_receipt_notifies_the_student_with_the_reason(): void
    {
        Storage::fake('local');
        config(['payments.manual.methods.palpay.account' => '0599000111']);
        $student = User::factory()->create();
        app(\App\Services\CartService::class)->add($student, Course::factory()->published()->paid(100)->create());
        $order = app(\App\Services\OrderService::class)->checkout($student);

        $receipt = app(\App\Services\ReceiptService::class)->submit($student, $order, 'palpay', 'Ali Ahmad', 'TX-777777', UploadedFile::fake()->image('r.jpg', 300, 300));
        app(\App\Services\ReceiptService::class)->reject($receipt, User::factory()->create(), 'المبلغ غير مطابق');

        $this->actingAs($student)->get(route('student.notifications.index'))
            ->assertOk()->assertSee('تم رفض إشعار التحويل')->assertSee('المبلغ غير مطابق')->assertSee($order->order_number);
    }

    /* ---------- الجلسات المباشرة ---------- */

    public function test_live_page_lists_only_sessions_of_my_courses(): void
    {
        $mine = Course::factory()->published()->create();
        $theirs = Course::factory()->published()->create();
        $student = $this->enrolled($mine);
        LiveSession::factory()->create(['course_id' => $mine->id, 'title_ar' => 'جلستي المميزة']);
        LiveSession::factory()->create(['course_id' => $theirs->id, 'title_ar' => 'جلسة غيري']);

        $this->actingAs($student)->get(route('student.live.index'))
            ->assertOk()->assertSee('جلستي المميزة')->assertDontSee('جلسة غيري')
            ->assertDontSee('meet.example.com');
    }

    public function test_join_is_closed_before_the_window_and_open_inside_it(): void
    {
        $course = Course::factory()->published()->create();
        $student = $this->enrolled($course);
        $early = LiveSession::factory()->at(now()->addHours(3), 60)->create(['course_id' => $course->id]);
        $soon  = LiveSession::factory()->at(now()->addMinutes(5), 60)->create(['course_id' => $course->id, 'meeting_url' => 'https://meet.example.com/ok']);

        $this->actingAs($student)->get(route('student.live.join', $early->id))->assertRedirect(route('student.live.index'))->assertSessionHasErrors('live');
        $this->actingAs($student)->get(route('student.live.join', $soon->id))->assertRedirect('https://meet.example.com/ok');
    }

    public function test_join_is_blocked_for_cancelled_ended_foreign_and_unsafe_sessions(): void
    {
        $course = Course::factory()->published()->create();
        $student = $this->enrolled($course);

        $cancelled = LiveSession::factory()->live()->cancelled()->create(['course_id' => $course->id]);
        $ended = LiveSession::factory()->past()->create(['course_id' => $course->id]);
        $foreign = LiveSession::factory()->live()->create();
        $http = LiveSession::factory()->live()->create(['course_id' => $course->id]);
        $http->forceFill(['meeting_url' => 'http://insecure.example.com/x'])->saveQuietly();
        $creds = LiveSession::factory()->live()->create(['course_id' => $course->id]);
        $creds->forceFill(['meeting_url' => 'https://user:pass@evil.example.com/x'])->saveQuietly();

        $this->actingAs($student)->get(route('student.live.join', $cancelled->id))->assertSessionHasErrors('live');
        $this->actingAs($student)->get(route('student.live.join', $ended->id))->assertSessionHasErrors('live');
        $this->actingAs($student)->get(route('student.live.join', $foreign->id))->assertNotFound();
        $this->actingAs($student)->get(route('student.live.join', $http->id))->assertSessionHasErrors('live');
        $this->actingAs($student)->get(route('student.live.join', $creds->id))->assertSessionHasErrors('live');
    }

    public function test_expired_enrollment_cannot_join(): void
    {
        $course = Course::factory()->published()->create();
        $student = $this->enrolled($course, null, 'expired');
        $live = LiveSession::factory()->live()->create(['course_id' => $course->id]);

        $this->actingAs($student)->get(route('student.live.join', $live->id))->assertNotFound();
    }

    public function test_live_pages_need_login(): void
    {
        $this->get(route('student.live.index'))->assertRedirect(route('login'));
        $this->get(route('student.live.join', 1))->assertRedirect(route('login'));
    }
}
