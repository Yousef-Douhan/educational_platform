<?php

namespace Tests\Feature;

use App\Models\Certificate;
use App\Models\Course;
use App\Models\Enrollment;
use App\Models\LiveSession;
use App\Models\Order;
use App\Models\User;
use App\Models\UserNotification;
use App\Notifications\PlatformNotification;
use App\Services\CartService;
use App\Services\CertificateService;
use App\Services\LiveSessionService;
use App\Services\Notifier;
use App\Services\NotificationPresenter;
use App\Services\OrderService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class NotificationModuleTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        app(PermissionRegistrar::class)->forgetCachedPermissions();
        $this->withoutVite();

        foreach (['notifications.view', 'enrollments.create', 'enrollments.view'] as $name) {
            Permission::findOrCreate($name, 'web');
        }
    }

    /* ---------- أدوات ---------- */

    private function notifier(): Notifier
    {
        return app(Notifier::class);
    }

    private function admin(string ...$permissions): User
    {
        $u = User::factory()->create();
        $u->givePermissionTo($permissions ?: Permission::all());

        return $u;
    }

    private function course(array $o = []): Course
    {
        return Course::factory()->published()->paid()->create($o);
    }

    private function enroll(User $user, Course $course, string $status = 'active', $expires = null): Enrollment
    {
        return Enrollment::factory()->create([
            'user_id' => $user->id, 'course_id' => $course->id, 'status' => $status, 'expires_at' => $expires,
        ]);
    }

    private function kindsOf(User $user): array
    {
        return UserNotification::where('notifiable_id', $user->id)->orderBy('created_at')->pluck('type')->all();
    }

    private function paidOrder(User $student, array $courses): Order
    {
        foreach ($courses as $c) {
            app(CartService::class)->add($student, $c);
        }

        return app(OrderService::class)->checkout($student);
    }

    /* ====================================================================
     |  الجدول والنموذج
     * ==================================================================== */

    public function test_table_has_the_erd_columns(): void
    {
        foreach (['id', 'type', 'notifiable_type', 'notifiable_id', 'data', 'read_at', 'created_at', 'updated_at'] as $col) {
            $this->assertTrue(Schema::hasColumn('notifications', $col), $col);
        }
    }

    public function test_the_id_is_a_uuid_and_data_is_cast_to_an_array(): void
    {
        $user = User::factory()->create();
        $n    = UserNotification::factory()->forUser($user)->create();

        $fresh = UserNotification::find($n->id);

        $this->assertTrue(Str::isUuid($fresh->id));
        $this->assertIsArray($fresh->data);
        $this->assertSame('enrollment.activated', $fresh->data['kind']);
    }

    public function test_the_notification_is_not_queued_and_uses_only_the_database_channel(): void
    {
        $n = new PlatformNotification('order.paid');

        $this->assertNotInstanceOf(ShouldQueue::class, $n);
        $this->assertSame(['database'], $n->via(new User));
    }

    /* ====================================================================
     |  Notifier
     * ==================================================================== */

    public function test_notifier_stores_a_row_with_the_kind_as_type(): void
    {
        $user   = User::factory()->create();
        $course = $this->course(['title_ar' => 'دورة', 'title_en' => 'Course']);

        $this->assertTrue($this->notifier()->toUser($user, PlatformNotification::enrollmentActivated($course)));

        $row = UserNotification::where('notifiable_id', $user->id)->sole();
        $this->assertSame('enrollment.activated', $row->type);
        $this->assertSame(User::class, $row->notifiable_type);
        $this->assertNull($row->read_at);
        $this->assertSame('Course', $row->data['params']['course_title_en']);
    }

    public function test_notifier_accepts_a_user_id(): void
    {
        $user = User::factory()->create();

        $this->assertTrue($this->notifier()->toUser($user->id, new PlatformNotification('order.paid', ['order_number' => 'ORD-1'])));
        $this->assertSame(1, UserNotification::where('notifiable_id', $user->id)->count());
    }

    public function test_notifier_skips_deleted_and_missing_users(): void
    {
        $gone = User::factory()->create();
        $gone->delete();

        $this->assertFalse($this->notifier()->toUser($gone, new PlatformNotification('order.paid')));
        $this->assertFalse($this->notifier()->toUser(999999, new PlatformNotification('order.paid')));
        $this->assertFalse($this->notifier()->toUser(null, new PlatformNotification('order.paid')));
        $this->assertSame(0, UserNotification::count());
    }

    public function test_a_failing_notification_never_breaks_the_caller_or_its_transaction(): void
    {
        $user = User::factory()->create();

        $broken = new class ('order.paid') extends PlatformNotification {
            public function toDatabase(object $notifiable): array
            {
                throw new \RuntimeException('boom');
            }
        };

        DB::transaction(function () use ($user, $broken) {
            $this->assertFalse($this->notifier()->toUser($user, $broken));
            // المعاملة الخارجية ما زالت سليمة وتقبل الكتابة بعد الفشل
            $this->notifier()->toUser($user, new PlatformNotification('order.paid'));
        });

        $this->assertSame(1, UserNotification::where('notifiable_id', $user->id)->count());
    }

    public function test_course_students_only_reach_people_with_access_now(): void
    {
        $course = $this->course();
        $other  = $this->course();

        $active    = User::factory()->create();
        $completed = User::factory()->create();
        $expired   = User::factory()->create();
        $past      = User::factory()->create();
        $cancelled = User::factory()->create();
        $deleted   = User::factory()->create();
        $elsewhere = User::factory()->create();

        $this->enroll($active, $course);
        $this->enroll($completed, $course, 'completed');
        $this->enroll($expired, $course, 'expired');
        $this->enroll($past, $course, 'active', now()->subDay());
        $this->enroll($cancelled, $course, 'cancelled');
        $this->enroll($deleted, $course);
        $this->enroll($elsewhere, $other);
        $deleted->delete();

        $sent = $this->notifier()->toCourseStudents($course->id, new PlatformNotification('live_session.cancelled'));

        $this->assertSame(2, $sent);
        $this->assertSame(2, UserNotification::count());
        $this->assertSame(
            collect([$active->id, $completed->id])->sort()->values()->all(),
            UserNotification::pluck('notifiable_id')->map(fn ($i) => (int) $i)->sort()->values()->all()
        );
    }

    public function test_every_recipient_gets_its_own_uuid(): void
    {
        $course = $this->course();
        foreach (User::factory()->count(3)->create() as $u) {
            $this->enroll($u, $course);
        }

        $this->notifier()->toCourseStudents($course->id, new PlatformNotification('live_session.rescheduled'));

        $this->assertSame(3, UserNotification::pluck('id')->unique()->count());
    }

    public function test_params_are_trimmed_to_scalar_text(): void
    {
        $session = LiveSession::factory()->create();
        $session->cancel_reason = str_repeat('س', 600);

        $n = PlatformNotification::liveSessionCancelled($session);

        $this->assertLessThanOrEqual(255, mb_strlen($n->params['reason']));
        foreach ($n->params as $v) {
            $this->assertIsString($v);
        }
    }

    /* ====================================================================
     |  العرض (Presenter)
     * ==================================================================== */

    private function row(string $kind, array $params): DatabaseNotification
    {
        return UserNotification::factory()->kind($kind, $params)->create();
    }

    public function test_it_renders_in_the_reader_locale(): void
    {
        $row = $this->row('enrollment.activated', ['course_title_ar' => 'دورة لارافيل', 'course_title_en' => 'Laravel']);

        $this->assertStringContainsString('دورة لارافيل', NotificationPresenter::present($row, 'ar')['body']);
        $this->assertStringContainsString('Laravel', NotificationPresenter::present($row, 'en')['body']);
        $this->assertStringNotContainsString('دورة لارافيل', NotificationPresenter::present($row, 'en')['body']);
    }

    public function test_a_missing_translation_falls_back_to_the_other_language(): void
    {
        $row = $this->row('enrollment.activated', ['course_title_ar' => '', 'course_title_en' => 'Only English']);

        $this->assertStringContainsString('Only English', NotificationPresenter::present($row, 'ar')['body']);
    }

    public function test_money_combines_amount_and_currency(): void
    {
        $row = $this->row('order.paid', ['order_number' => 'ORD-AB12', 'amount' => '49.00', 'currency' => 'USD']);

        $this->assertStringContainsString('ORD-AB12', NotificationPresenter::present($row, 'en')['body']);
        $this->assertStringContainsString('49.00 USD', NotificationPresenter::present($row, 'en')['body']);
    }

    public function test_values_are_never_expanded_as_placeholders(): void
    {
        $row = $this->row('certificate.issued', ['course_title_ar' => ':code', 'course_title_en' => ':code', 'code' => 'ABC-123']);

        $body = NotificationPresenter::present($row, 'en')['body'];

        $this->assertStringContainsString('":code"', $body);
        $this->assertStringContainsString('code ABC-123', $body);
    }

    public function test_cancellation_shows_the_reason_only_when_there_is_one(): void
    {
        $with    = $this->row('live_session.cancelled', ['title_en' => 'Intro', 'title_ar' => 'مقدمة', 'when' => '2026-12-01 10:00', 'reason' => 'Sick instructor']);
        $without = $this->row('live_session.cancelled', ['title_en' => 'Intro', 'title_ar' => 'مقدمة', 'when' => '2026-12-01 10:00']);

        $this->assertStringContainsString('Sick instructor', NotificationPresenter::present($with, 'en')['body']);
        $this->assertStringNotContainsString('Reason', NotificationPresenter::present($without, 'en')['body']);
    }

    public function test_unknown_or_broken_rows_fall_back_instead_of_failing(): void
    {
        $legacy = UserNotification::factory()->create(['type' => 'App\\Old\\Thing', 'data' => ['whatever' => 1]]);
        $broken = UserNotification::factory()->create(['type' => 'x', 'data' => ['kind' => ['array'], 'params' => 'oops']]);

        foreach ([$legacy, $broken] as $row) {
            $view = NotificationPresenter::present($row, 'en');
            $this->assertFalse($view['known']);
            $this->assertSame('Notification', $view['title']);
        }
    }

    public function test_every_kind_has_text_in_both_languages(): void
    {
        foreach (PlatformNotification::KINDS as $kind) {
            foreach (['ar', 'en'] as $locale) {
                foreach (['name', 'title', 'body'] as $part) {
                    $key = "notifications.kinds.{$kind}.{$part}";
                    $this->assertTrue(\Illuminate\Support\Facades\Lang::has($key, $locale), "{$locale}:{$key}");
                }
            }
        }
    }

    /* ====================================================================
     |  الأحداث الحقيقية
     * ==================================================================== */

    public function test_a_paid_order_notifies_the_payment_and_each_enrollment(): void
    {
        $student = User::factory()->create();
        $a = $this->course();
        $b = $this->course();
        $order = $this->paidOrder($student, [$a, $b]);

        app(OrderService::class)->markPaid($order, 'manual', 'REF-1');

        $kinds = $this->kindsOf($student);
        $this->assertSame(1, collect($kinds)->filter(fn ($k) => $k === 'order.paid')->count());
        $this->assertSame(2, collect($kinds)->filter(fn ($k) => $k === 'enrollment.activated')->count());
        $this->assertSame($order->order_number, UserNotification::where('type', 'order.paid')->sole()->data['params']['order_number']);
    }

    public function test_repeating_the_payment_does_not_notify_twice(): void
    {
        $student = User::factory()->create();
        $order   = $this->paidOrder($student, [$this->course()]);

        app(OrderService::class)->markPaid($order, 'manual', 'REF-1');
        app(OrderService::class)->markPaid($order, 'manual', 'REF-1');

        $this->assertSame(1, UserNotification::where('type', 'order.paid')->count());
        $this->assertSame(1, UserNotification::where('type', 'enrollment.activated')->count());
    }

    public function test_a_failed_checkout_state_does_not_notify(): void
    {
        $student = User::factory()->create();
        $order   = $this->paidOrder($student, [$this->course()]);

        app(OrderService::class)->cancel($order);

        $this->assertSame(0, UserNotification::count());
    }

    public function test_a_refund_notifies_the_student(): void
    {
        $student = User::factory()->create();
        $order   = $this->paidOrder($student, [$this->course()]);
        $order   = app(OrderService::class)->markPaid($order, 'manual', 'REF-9');

        app(OrderService::class)->refund($order, 'Requested by student');

        $this->assertSame(1, UserNotification::where('type', 'order.refunded')->where('notifiable_id', $student->id)->count());
    }

    public function test_issuing_and_revoking_a_certificate_notifies_the_student(): void
    {
        $student = User::factory()->create();
        $course  = $this->course(['title_en' => 'Certified course']);
        $this->enroll($student, $course, 'completed');

        $certificate = app(CertificateService::class)->issue($student, $course);

        $issued = UserNotification::where('type', 'certificate.issued')->sole();
        $this->assertSame($student->id, (int) $issued->notifiable_id);
        $this->assertSame($certificate->certificate_code, $issued->data['params']['code']);

        app(CertificateService::class)->revoke($certificate, 'Mistake');

        $this->assertSame(1, UserNotification::where('type', 'certificate.revoked')->where('notifiable_id', $student->id)->count());
    }

    public function test_a_failed_certificate_issue_does_not_notify(): void
    {
        $student = User::factory()->create();
        $course  = $this->course();
        $this->enroll($student, $course, 'active');

        try {
            app(CertificateService::class)->issue($student, $course);
        } catch (\Throwable) {
        }

        $this->assertSame(0, UserNotification::count());
    }

    public function test_cancelling_a_session_notifies_enrolled_students_with_the_reason(): void
    {
        $course  = Course::factory()->published()->create();
        $in      = User::factory()->create();
        $out     = User::factory()->create();
        $this->enroll($in, $course);
        $session = LiveSession::factory()->create(['course_id' => $course->id]);

        app(LiveSessionService::class)->cancel($session, 'Instructor is sick');

        $row = UserNotification::where('type', 'live_session.cancelled')->sole();
        $this->assertSame($in->id, (int) $row->notifiable_id);
        $this->assertSame('Instructor is sick', $row->data['params']['reason']);
        $this->assertSame(0, UserNotification::where('notifiable_id', $out->id)->count());
    }

    public function test_only_a_time_change_counts_as_rescheduling(): void
    {
        $course = Course::factory()->published()->create();
        $this->enroll(User::factory()->create(), $course);
        $session = LiveSession::factory()->at(now()->addDays(3)->setTime(10, 0), 60)->create(['course_id' => $course->id]);

        $data = [
            'course_id' => $course->id, 'title_ar' => 'عنوان جديد', 'title_en' => 'New title',
            'meeting_url' => $session->meeting_url, 'scheduled_at' => $session->scheduled_at->toDateTimeString(),
            'duration_minutes' => 60,
        ];

        app(LiveSessionService::class)->update($session, $data);
        $this->assertSame(0, UserNotification::count(), 'تغيير العنوان وحده لا يُرسل شيئًا');

        app(LiveSessionService::class)->update($session->fresh(), array_merge($data, [
            'scheduled_at' => now()->addDays(4)->setTime(15, 0)->toDateTimeString(),
        ]));
        $this->assertSame(1, UserNotification::where('type', 'live_session.rescheduled')->count());
    }

    public function test_manual_enrollment_by_an_admin_notifies_the_student(): void
    {
        $admin   = $this->admin('enrollments.create');
        $student = User::factory()->create();
        $course  = Course::factory()->published()->create();

        $this->actingAs($admin)
            ->postJson(route('cms.admin.enrollments.store'), ['user_id' => $student->id, 'course_id' => $course->id])
            ->assertCreated();

        $this->assertSame(['enrollment.activated'], $this->kindsOf($student));
    }

    /* ====================================================================
     |  إشعاراتي
     * ==================================================================== */

    private function mine(User $user, int $count = 1): void
    {
        UserNotification::factory()->count($count)->forUser($user)->create();
    }

    public function test_my_notifications_requires_login(): void
    {
        $this->getJson(route('cms.admin.my-notifications.index'))->assertUnauthorized();
        $this->postJson(route('cms.admin.my-notifications.read-all'))->assertUnauthorized();
    }

    public function test_the_list_only_contains_my_notifications_with_counts(): void
    {
        $me = User::factory()->create();
        $other = User::factory()->create();
        $this->mine($me, 3);
        UserNotification::factory()->forUser($me)->read()->create();
        $this->mine($other, 5);

        $res = $this->actingAs($me)->getJson(route('cms.admin.my-notifications.index'))->assertOk();

        $this->assertSame(4, $res->json('stats.total'));
        $this->assertSame(3, $res->json('stats.unread'));
        $this->assertSame(4, substr_count($res->json('html'), '<tr data-id='));
    }

    public function test_the_unread_filter_hides_read_ones(): void
    {
        $me = User::factory()->create();
        $this->mine($me, 2);
        UserNotification::factory()->forUser($me)->read()->create();

        $res = $this->actingAs($me)->getJson(route('cms.admin.my-notifications.index', ['state' => 'unread']))->assertOk();

        $this->assertSame(2, substr_count($res->json('html'), '<tr data-id='));
    }

    public function test_mark_as_read_is_idempotent_and_updates_the_counter(): void
    {
        $me = User::factory()->create();
        $this->mine($me, 2);
        $n = UserNotification::where('notifiable_id', $me->id)->first();

        $this->actingAs($me)->postJson(route('cms.admin.my-notifications.read', $n->id))
            ->assertOk()->assertJsonPath('stats.unread', 1);
        $first = $n->fresh()->read_at;
        $this->assertNotNull($first);

        $this->actingAs($me)->postJson(route('cms.admin.my-notifications.read', $n->id))
            ->assertOk()->assertJsonPath('stats.unread', 1);
        $this->assertEquals($first, $n->fresh()->read_at);
    }

    public function test_mark_all_read_touches_only_my_rows(): void
    {
        $me = User::factory()->create();
        $other = User::factory()->create();
        $this->mine($me, 3);
        $this->mine($other, 2);

        $this->actingAs($me)->postJson(route('cms.admin.my-notifications.read-all'))
            ->assertOk()->assertJsonPath('stats.unread', 0);

        $this->assertSame(0, UserNotification::where('notifiable_id', $me->id)->whereNull('read_at')->count());
        $this->assertSame(2, UserNotification::where('notifiable_id', $other->id)->whereNull('read_at')->count());
    }

    public function test_i_can_delete_my_own_notification(): void
    {
        $me = User::factory()->create();
        $this->mine($me, 2);
        $n = UserNotification::where('notifiable_id', $me->id)->first();

        $this->actingAs($me)->deleteJson(route('cms.admin.my-notifications.destroy', $n->id))
            ->assertOk()->assertJsonPath('stats.total', 1);

        $this->assertNull(UserNotification::find($n->id));
    }

    public function test_nobody_can_read_mark_or_delete_someone_elses_notification(): void
    {
        $me = User::factory()->create();
        $victim = User::factory()->create();
        $this->mine($victim);
        $theirs = UserNotification::where('notifiable_id', $victim->id)->sole();

        $this->actingAs($me)->postJson(route('cms.admin.my-notifications.read', $theirs->id))->assertNotFound();
        $this->actingAs($me)->deleteJson(route('cms.admin.my-notifications.destroy', $theirs->id))->assertNotFound();

        $theirs->refresh();
        $this->assertNull($theirs->read_at);
        $this->assertNotNull(UserNotification::find($theirs->id));
    }

    public function test_even_a_super_admin_cannot_delete_another_users_notification_here(): void
    {
        $admin = $this->admin();
        $victim = User::factory()->create();
        $this->mine($victim);
        $theirs = UserNotification::where('notifiable_id', $victim->id)->sole();

        $this->actingAs($admin)->deleteJson(route('cms.admin.my-notifications.destroy', $theirs->id))->assertNotFound();
    }

    public function test_a_malformed_id_is_a_404_not_an_error(): void
    {
        $me = User::factory()->create();

        $this->actingAs($me)->postJson('/cms/admin/my-notifications/not-a-uuid/read')->assertNotFound();
        $this->actingAs($me)->postJson('/cms/admin/my-notifications/' . Str::uuid() . '/read')->assertNotFound();
    }

    public function test_html_in_notification_text_is_escaped_on_every_page(): void
    {
        $me = User::factory()->create();
        UserNotification::factory()->forUser($me)->kind('enrollment.activated', [
            'course_title_ar' => '<script>alert(1)</script>', 'course_title_en' => '<script>alert(1)</script>',
        ])->create();

        $html = $this->actingAs($me)->getJson(route('cms.admin.my-notifications.index'))->json('html');
        $this->assertStringNotContainsString('<script>alert(1)', $html);
        $this->assertStringContainsString('&lt;script&gt;', $html);

        $page = $this->actingAs($me)->get(route('cms.admin.my-notifications.index'))->assertOk()->getContent();
        $this->assertStringNotContainsString('<script>alert(1)', $page);
    }

    public function test_the_header_badge_shows_the_unread_count(): void
    {
        $me = User::factory()->create();
        $this->mine($me, 3);

        $this->actingAs($me)->get(route('cms.admin.my-notifications.index'))
            ->assertOk()->assertSee('data-notif-badge', false);
    }

    /* ====================================================================
     |  الشاشة الإدارية (قراءة فقط)
     * ==================================================================== */

    public function test_admin_screen_needs_the_permission(): void
    {
        // الزائر أولًا: حالة الدخول تبقى بين طلبات الاختبار الواحد
        $this->getJson(route('cms.admin.notifications.index'))->assertUnauthorized();

        $plain = User::factory()->create();
        $this->actingAs($plain)->getJson(route('cms.admin.notifications.index'))->assertForbidden();

        $n = UserNotification::factory()->forUser($plain)->create();
        $this->actingAs($plain)->getJson(route('cms.admin.notifications.show', $n->id))->assertForbidden();
    }

    public function test_admin_sees_everyones_notifications_and_stats(): void
    {
        $admin = $this->admin('notifications.view');
        $a = User::factory()->create(['name' => 'Alice Student']);
        $b = User::factory()->create(['name' => 'Bob Student']);
        $this->mine($a, 2);
        UserNotification::factory()->forUser($b)->read()->create();

        $res = $this->actingAs($admin)->getJson(route('cms.admin.notifications.index'))->assertOk();

        $this->assertSame(3, $res->json('stats.total'));
        $this->assertSame(2, $res->json('stats.unread'));
        $this->assertSame(1, $res->json('stats.read'));
        $this->assertSame(3, $res->json('stats.today'));
        $this->assertStringContainsString('Alice Student', $res->json('html'));
    }

    public function test_admin_page_renders(): void
    {
        $admin = $this->admin('notifications.view');
        $this->mine(User::factory()->create());

        $this->actingAs($admin)->get(route('cms.admin.notifications.index'))->assertOk()->assertSee('notificationsApp', false);
    }

    public function test_admin_filters(): void
    {
        $admin = $this->admin('notifications.view');
        $alice = User::factory()->create(['name' => 'Alice Zed', 'email' => 'alice@example.com']);
        $bob   = User::factory()->create(['name' => 'Bob Yan', 'email' => 'bob@example.com']);

        UserNotification::factory()->forUser($alice)->kind('order.paid', ['order_number' => 'A1'])->create();
        UserNotification::factory()->forUser($bob)->kind('certificate.issued', ['code' => 'B1'])->read()->create();
        UserNotification::factory()->forUser($bob)->kind('order.paid', ['order_number' => 'B2'])->at(now()->subDays(10))->create();

        $count = fn (array $q) => substr_count(
            $this->actingAs($admin)->getJson(route('cms.admin.notifications.index', $q))->assertOk()->json('html'),
            '<tr data-id='
        );

        $this->assertSame(3, $count([]));
        $this->assertSame(1, $count(['search' => 'alice']));
        $this->assertSame(2, $count(['search' => 'bob@example']));
        $this->assertSame(2, $count(['kind' => 'order.paid']));
        $this->assertSame(3, $count(['kind' => 'nonsense']));
        $this->assertSame(1, $count(['state' => 'read']));
        $this->assertSame(2, $count(['state' => 'unread']));
        $this->assertSame(2, $count(['from' => now()->subDay()->toDateString()]));
        $this->assertSame(1, $count(['to' => now()->subDays(5)->toDateString()]));
    }

    public function test_search_treats_percent_and_underscore_literally(): void
    {
        $admin = $this->admin('notifications.view');
        $this->mine(User::factory()->create(['name' => 'Plain Name']));

        $res = $this->actingAs($admin)->getJson(route('cms.admin.notifications.index', ['search' => '%']))->assertOk();

        $this->assertSame(0, substr_count($res->json('html'), '<tr data-id='));
    }

    public function test_search_finds_deleted_recipients_too(): void
    {
        $admin = $this->admin('notifications.view');
        $gone  = User::factory()->create(['name' => 'Ghost Person']);
        $this->mine($gone);
        $gone->delete();

        $res = $this->actingAs($admin)->getJson(route('cms.admin.notifications.index', ['search' => 'Ghost']))->assertOk();

        $this->assertSame(1, substr_count($res->json('html'), '<tr data-id='));
        $this->assertStringContainsString('Ghost Person', $res->json('html'));
    }

    public function test_admin_sort_is_whitelisted(): void
    {
        $admin = $this->admin('notifications.view');
        $this->mine(User::factory()->create(), 2);

        $this->actingAs($admin)->getJson(route('cms.admin.notifications.index', ['sort' => 'data; drop table users', 'dir' => 'sideways']))->assertOk();
        $this->actingAs($admin)->getJson(route('cms.admin.notifications.index', ['sort' => 'read_at', 'dir' => 'asc']))->assertOk();
    }

    public function test_admin_show_returns_the_rendered_text_and_stored_params(): void
    {
        $admin = $this->admin('notifications.view');
        $user  = User::factory()->create(['name' => 'Carol', 'email' => 'carol@example.com']);
        $n = UserNotification::factory()->forUser($user)->kind('order.paid', ['order_number' => 'ORD-Z', 'amount' => '10.00', 'currency' => 'USD'])->create();

        $this->actingAs($admin)->getJson(route('cms.admin.notifications.show', $n->id))
            ->assertOk()
            ->assertJsonPath('data.kind', 'order.paid')
            ->assertJsonPath('data.recipient_name', 'Carol')
            ->assertJsonPath('data.recipient_email', 'carol@example.com')
            ->assertJsonPath('data.is_read', false)
            ->assertJsonPath('data.params.order_number', 'ORD-Z');
    }

    public function test_admin_show_works_for_a_deleted_recipient_and_empty_params(): void
    {
        $admin = $this->admin('notifications.view');
        $user  = User::factory()->create(['name' => 'Dan']);
        $n = UserNotification::factory()->forUser($user)->kind('order.paid', [])->create();
        $user->delete();

        $res = $this->actingAs($admin)->getJson(route('cms.admin.notifications.show', $n->id))->assertOk();

        $this->assertSame('Dan', $res->json('data.recipient_name'));
        $this->assertSame([], $res->json('data.params'));
    }

    public function test_admin_has_no_write_routes(): void
    {
        $admin = $this->admin();
        $n = UserNotification::factory()->forUser(User::factory()->create())->create();

        $this->actingAs($admin)->deleteJson('/cms/admin/notifications/' . $n->id)->assertStatus(405);
        $this->actingAs($admin)->putJson('/cms/admin/notifications/' . $n->id, ['data' => []])->assertStatus(405);
        $this->actingAs($admin)->postJson('/cms/admin/notifications', ['type' => 'x'])->assertStatus(405);
        $this->assertNotNull(UserNotification::find($n->id));
    }

    /* ====================================================================
     |  التنظيف المجدول
     * ==================================================================== */

    public function test_prune_removes_old_read_and_very_old_unread_only(): void
    {
        $user = User::factory()->create();

        $oldRead       = UserNotification::factory()->forUser($user)->read()->at(now()->subDays(91))->create();
        $recentRead    = UserNotification::factory()->forUser($user)->read()->at(now()->subDays(30))->create();
        $oldUnread     = UserNotification::factory()->forUser($user)->at(now()->subDays(200))->create();
        $ancientUnread = UserNotification::factory()->forUser($user)->at(now()->subDays(366))->create();

        $this->artisan('notifications:prune')->expectsOutput('Deleted 2 notification(s).')->assertSuccessful();

        $this->assertNull(UserNotification::find($oldRead->id));
        $this->assertNull(UserNotification::find($ancientUnread->id));
        $this->assertNotNull(UserNotification::find($recentRead->id));
        $this->assertNotNull(UserNotification::find($oldUnread->id), 'غير المقروء يبقى سنة كاملة');
    }

    public function test_prune_is_scheduled_daily(): void
    {
        Artisan::call('schedule:list');
        $out = Artisan::output();

        $this->assertStringContainsString('notifications:prune', $out);
        $this->assertMatchesRegularExpression('/0\s+4\s+\*\s+\*\s+\*\s+php artisan notifications:prune/', $out);
    }

    public function test_deleting_a_user_forever_does_not_break_the_screen(): void
    {
        $admin = $this->admin('notifications.view');
        $user  = User::factory()->create();
        $this->mine($user);
        $user->forceDelete();

        $res = $this->actingAs($admin)->getJson(route('cms.admin.notifications.index'))->assertOk();

        $this->assertSame(1, substr_count($res->json('html'), '<tr data-id='));
    }
}
