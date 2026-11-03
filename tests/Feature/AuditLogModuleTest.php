<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\Coupon;
use App\Models\Course;
use App\Models\LiveSession;
use App\Models\QuizAttempt;
use App\Models\User;
use App\Services\AuditLogger;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Auth\Events\Failed;
use Illuminate\Auth\Events\Login;
use Illuminate\Auth\Events\Logout;
use Illuminate\Database\Eloquent\MassAssignmentException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use LogicException;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class AuditLogModuleTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        app(PermissionRegistrar::class)->forgetCachedPermissions();
        $this->withoutVite();

        foreach (['audit-logs.view', 'coupons.create', 'courses.manage-all'] as $name) {
            Permission::findOrCreate($name, 'web');
        }
    }

    /* ---------- أدوات ---------- */

    private function logger(): AuditLogger
    {
        return app(AuditLogger::class);
    }

    private function admin(string ...$permissions): User
    {
        $u = User::factory()->create();
        $u->givePermissionTo($permissions ?: Permission::all());

        return $u;
    }

    /** نبدأ بسجل نظيف بعد تجهيز المستخدمين */
    private function actingAdmin(): User
    {
        $admin = $this->admin();
        $this->actingAs($admin);
        AuditLog::query()->toBase()->delete();

        return $admin;
    }

    private function logCount(string $action): int
    {
        return AuditLog::where('action', $action)->count();
    }

    /* ---------- الخدمة ---------- */

    public function test_record_stores_who_what_and_a_snapshot_of_the_actor(): void
    {
        $actor  = User::factory()->create(['name' => 'Alice Admin', 'email' => 'alice@example.com']);
        $course = Course::factory()->create(['title_en' => 'Laravel 101']);

        $log = $this->logger()->record('course', 'updated', $course, ['title_en' => 'A'], ['title_en' => 'B'], 'note', $actor);

        $this->assertSame('course.updated', $log->action);
        $this->assertSame('course', $log->subject_type);
        $this->assertSame('updated', $log->event);
        $this->assertSame($course->id, $log->subject_id);
        $this->assertSame('Laravel 101', $log->subject_label);
        $this->assertSame($actor->id, $log->user_id);
        $this->assertSame('Alice Admin', $log->user_name);
        $this->assertSame('alice@example.com', $log->user_email);
        $this->assertSame(['title_en' => 'A'], $log->old_values);
        $this->assertSame(['title_en' => 'B'], $log->new_values);
        $this->assertSame('note', $log->description);
        $this->assertNotNull($log->created_at);
    }

    public function test_console_actions_without_an_actor_are_skipped_unless_forced(): void
    {
        $this->assertNull($this->logger()->record('course', 'created'));
        $this->assertSame(0, AuditLog::count());

        $forced = $this->logger()->record('course', 'created', null, [], [], null, null, true);

        $this->assertNotNull($forced);
        $this->assertNull($forced->user_id);
    }

    public function test_sensitive_fields_are_never_stored(): void
    {
        $log = $this->logger()->record('user', 'updated', null, [], [
            'password'         => 'secret123',
            'remember_token'   => 'tok',
            'api_token'        => 'abc',
            'gateway_response' => '{"card":"4111111111111111"}',
            'meeting_url'      => 'https://meet.example.com/private-room',
            'title'            => 'visible',
        ], null, User::factory()->create());

        $this->assertSame('visible', $log->new_values['title']);
        foreach (['password', 'remember_token', 'api_token', 'gateway_response', 'meeting_url'] as $key) {
            $this->assertSame(AuditLogger::REDACTED, $log->new_values[$key], $key);
        }

        $raw = json_encode(AuditLog::query()->toBase()->get());
        foreach (['secret123', '4111111111111111', 'private-room'] as $needle) {
            $this->assertStringNotContainsString($needle, $raw);
        }
    }

    public function test_values_are_normalised_and_trimmed(): void
    {
        $clean = $this->logger()->clean([
            'long'   => str_repeat('x', 900),
            'array'  => ['a' => 1],
            'date'   => now()->setDate(2026, 1, 2)->setTime(3, 4, 5),
            'flag'   => true,
            'number' => 5,
            'none'   => null,
        ]);

        $this->assertSame(500, mb_strlen($clean['long']));
        $this->assertSame('{"a":1}', $clean['array']);
        $this->assertSame('2026-01-02 03:04:05', $clean['date']);
        $this->assertTrue($clean['flag']);
        $this->assertSame(5, $clean['number']);
        $this->assertNull($clean['none']);

        $this->assertCount(60, $this->logger()->clean(array_fill_keys(range(1, 100), 'v')));
    }

    public function test_entries_are_append_only(): void
    {
        $log = AuditLog::factory()->create();

        $log->subject_label = 'tampered';
        try {
            $log->save();
            $this->fail('An audit entry was edited');
        } catch (LogicException) {
            $this->addToAssertionCount(1);
        }

        try {
            $log->delete();
            $this->fail('An audit entry was deleted');
        } catch (LogicException) {
            $this->addToAssertionCount(1);
        }

        $this->assertNotSame('tampered', $log->fresh()->subject_label);

        $this->expectException(MassAssignmentException::class);
        AuditLog::create(['action' => 'x.y', 'event' => 'y']);
    }

    public function test_a_logging_failure_never_breaks_the_original_action(): void
    {
        $this->actingAdmin();
        Schema::drop('audit_logs');

        $course = Course::factory()->create(['title_en' => 'Still works']);

        $this->assertDatabaseHas('courses', ['id' => $course->id, 'title_en' => 'Still works']);
    }

    public function test_the_log_survives_deleting_its_user(): void
    {
        $user = User::factory()->create(['name' => 'Gone Person', 'email' => 'gone@example.com']);
        $log  = $this->logger()->record('auth', 'login', null, [], [], null, $user);

        $user->forceDelete();

        $log = $log->fresh();
        $this->assertNull($log->user_id);
        $this->assertSame('gone@example.com', $log->user_email);
        $this->assertSame('Gone Person', $log->user_name);
    }

    /* ---------- التسجيل التلقائي ---------- */

    public function test_changes_without_an_actor_in_console_are_not_logged(): void
    {
        Course::factory()->create();

        $this->assertSame(0, AuditLog::count());
    }

    public function test_course_lifecycle_is_logged(): void
    {
        $admin = $this->actingAdmin();

        $course = Course::factory()->create(['title_en' => 'Hooked']);
        $created = AuditLog::where('action', 'course.created')->firstOrFail();
        $this->assertSame($admin->id, $created->user_id);
        $this->assertSame('Hooked', $created->subject_label);
        $this->assertSame('Hooked', $created->new_values['title_en']);
        $this->assertArrayNotHasKey('created_at', $created->new_values);
        $this->assertArrayNotHasKey('updated_at', $created->new_values);

        $course->update(['title_en' => 'Changed']);
        $updated = AuditLog::where('action', 'course.updated')->firstOrFail();
        $this->assertSame(['title_en' => 'Hooked'], $updated->old_values);
        $this->assertSame(['title_en' => 'Changed'], $updated->new_values);

        $course->delete();
        $this->assertSame(1, $this->logCount('course.deleted'));

        $course->restore();
        $this->assertSame(1, $this->logCount('course.restored'));
        $this->assertSame(1, $this->logCount('course.updated'), 'restoring must not look like an edit');

        $course->forceDelete();
        $this->assertSame(1, $this->logCount('course.force_deleted'));
        $this->assertSame(1, $this->logCount('course.deleted'));
    }

    public function test_saves_that_change_nothing_are_not_logged(): void
    {
        $this->actingAdmin();
        $course = Course::factory()->create();
        AuditLog::query()->toBase()->delete();

        $course->save();
        $course->touch();

        $this->assertSame(0, $this->logCount('course.updated'));
    }

    public function test_user_passwords_never_reach_the_log(): void
    {
        $this->actingAdmin();

        $user = User::factory()->create(['password' => 'PlainPass#9999']);
        $created = AuditLog::where('action', 'user.created')->where('subject_id', $user->id)->firstOrFail();
        $this->assertSame(AuditLogger::REDACTED, $created->new_values['password']);

        $user->update(['password' => 'AnotherPass#8888']);
        $updated = AuditLog::where('action', 'user.updated')->where('subject_id', $user->id)->firstOrFail();
        $this->assertSame(AuditLogger::REDACTED, $updated->new_values['password']);
        $this->assertSame($user->name, $updated->subject_label);

        $raw = json_encode(AuditLog::query()->toBase()->get());
        $this->assertStringNotContainsString('PlainPass#9999', $raw);
        $this->assertStringNotContainsString('AnotherPass#8888', $raw);
        $this->assertStringNotContainsString($user->fresh()->password, $raw);
    }

    public function test_coupon_changes_record_old_and_new_values(): void
    {
        $this->actingAdmin();

        $coupon = Coupon::factory()->create(['code' => 'SAVE-ONE']);
        $this->assertSame('SAVE-ONE', AuditLog::where('action', 'coupon.created')->firstOrFail()->subject_label);

        $coupon->update(['discount_value' => 25]);
        $log = AuditLog::where('action', 'coupon.updated')->firstOrFail();
        $this->assertEquals(10, $log->old_values['discount_value']);
        $this->assertEquals(25, $log->new_values['discount_value']);
    }

    public function test_only_voided_quiz_attempts_are_logged(): void
    {
        $this->actingAdmin();
        $attempt = QuizAttempt::factory()->create(['status' => 'in_progress', 'is_open' => true]);
        AuditLog::query()->toBase()->delete();

        $attempt->forceFill(['status' => 'submitted', 'is_open' => null])->save();
        $this->assertSame(0, $this->logCount('quiz_attempt.updated'));

        $attempt->forceFill(['status' => 'voided', 'void_reason' => 'cheating'])->save();
        $log = AuditLog::where('action', 'quiz_attempt.updated')->firstOrFail();
        $this->assertSame('voided', $log->new_values['status']);
        $this->assertSame('submitted', $log->old_values['status']);
    }

    public function test_live_session_meeting_links_are_hidden(): void
    {
        $this->actingAdmin();

        LiveSession::factory()->create(['meeting_url' => 'https://meet.example.com/very-private']);

        $log = AuditLog::where('action', 'live_session.created')->firstOrFail();
        $this->assertSame(AuditLogger::REDACTED, $log->new_values['meeting_url']);
        $this->assertStringNotContainsString('very-private', json_encode($log->getAttributes()));
    }

    public function test_roles_are_logged(): void
    {
        $this->actingAdmin();

        $role = Role::create(['name' => 'auditor', 'guard_name' => 'web']);
        $role->update(['name' => 'senior-auditor']);

        $this->assertSame(1, $this->logCount('role.created'));
        $this->assertSame('senior-auditor', AuditLog::where('action', 'role.updated')->firstOrFail()->new_values['name']);
    }

    public function test_web_requests_record_ip_and_browser(): void
    {
        $admin = $this->actingAdmin();

        $this->withHeader('User-Agent', 'TestBrowser/1.0')
            ->postJson(route('cms.admin.coupons.store'), ['code' => 'AUDIT10', 'discount_type' => 'percentage', 'discount_value' => 10, 'is_active' => 1])
            ->assertCreated();

        $log = AuditLog::where('action', 'coupon.created')->firstOrFail();
        $this->assertSame($admin->id, $log->user_id);
        $this->assertSame('TestBrowser/1.0', $log->user_agent);
        $this->assertNotNull($log->ip_address);
    }

    /* ---------- الدخول والخروج ---------- */

    public function test_sign_in_and_sign_out_are_logged(): void
    {
        $user = User::factory()->create();

        event(new Login('web', $user, false));
        event(new Logout('web', $user));

        $this->assertSame(1, $this->logCount('auth.login'));
        $this->assertSame(1, $this->logCount('auth.logout'));
        $this->assertSame($user->id, AuditLog::where('action', 'auth.login')->firstOrFail()->user_id);
    }

    public function test_failed_sign_in_records_the_email_but_never_the_password(): void
    {
        $user = User::factory()->create(['email' => 'target@example.com']);

        event(new Failed('web', $user, ['email' => 'target@example.com', 'password' => 'TopSecret!77']));

        $log = AuditLog::where('action', 'auth.login_failed')->firstOrFail();
        $this->assertSame('email: target@example.com', $log->description);
        $this->assertStringNotContainsString('TopSecret!77', json_encode(AuditLog::query()->toBase()->get()));
    }

    /* ---------- الشاشة ---------- */

    public function test_screen_requires_the_permission(): void
    {
        $log = AuditLog::factory()->create();

        $this->get(route('cms.admin.audit-logs.index'))->assertRedirect();

        $nobody = User::factory()->create();
        $this->actingAs($nobody)->get(route('cms.admin.audit-logs.index'))->assertForbidden();
        $this->getJson(route('cms.admin.audit-logs.show', $log->id))->assertForbidden();

        // صلاحيات الدورات والجلسات وحدها لا تكفي
        $teacher = $this->admin('courses.manage-all');
        $this->actingAs($teacher)->get(route('cms.admin.audit-logs.index'))->assertForbidden();

        $viewer = $this->admin('audit-logs.view');
        $this->actingAs($viewer)->get(route('cms.admin.audit-logs.index'))->assertOk();
        $this->getJson(route('cms.admin.audit-logs.show', $log->id))->assertOk();
    }

    public function test_there_is_no_way_to_write_through_http(): void
    {
        $log   = AuditLog::factory()->create();
        $admin = $this->admin();
        $index = route('cms.admin.audit-logs.index');
        $show  = route('cms.admin.audit-logs.show', $log->id);

        $this->actingAs($admin);
        $this->postJson($index, [])->assertStatus(405);
        $this->putJson($show, [])->assertStatus(405);
        $this->patchJson($show, [])->assertStatus(405);
        $this->deleteJson($show)->assertStatus(405);

        $this->assertSame(1, AuditLog::count());
    }

    public function test_index_renders_and_escapes_everything(): void
    {
        AuditLog::factory()->create([
            'user_name' => '<script>alert(1)</script>', 'subject_label' => '<img src=x onerror=1>',
            'description' => '<b>bold</b>',
        ]);

        $this->actingAs($this->admin())->get(route('cms.admin.audit-logs.index'))
            ->assertOk()
            ->assertDontSee('<script>alert(1)</script>', false)
            ->assertDontSee('<img src=x onerror=1>', false)
            ->assertDontSee('<b>bold</b>', false)
            ->assertSee('&lt;script&gt;', false);
    }

    public function test_filters(): void
    {
        AuditLog::factory()->of('course', 'created')->create(['subject_label' => 'Course Alpha', 'user_email' => 'alpha@example.com', 'ip_address' => '198.51.100.7']);
        AuditLog::factory()->of('coupon', 'deleted')->create(['subject_label' => 'Coupon Beta']);
        AuditLog::factory()->of('auth', 'login_failed')->create(['subject_label' => null, 'description' => 'email: gamma@example.com']);
        AuditLog::factory()->create(['subject_label' => 'Old Delta', 'created_at' => now()->subDays(40)]);
        $admin = $this->admin();

        $html = fn (array $q) => $this->actingAs($admin)->getJson(route('cms.admin.audit-logs.index', $q))->assertOk()->json('html');

        $this->assertStringContainsString('Course Alpha', $html(['search' => 'Alpha']));
        $this->assertStringNotContainsString('Coupon Beta', $html(['search' => 'Alpha']));
        $this->assertStringContainsString('Course Alpha', $html(['search' => 'alpha@example.com']));
        $this->assertStringContainsString('Course Alpha', $html(['search' => '198.51.100']));
        $this->assertStringContainsString('Coupon Beta', $html(['type' => 'coupon']));
        $this->assertStringNotContainsString('Course Alpha', $html(['type' => 'coupon']));
        $this->assertStringContainsString('Coupon Beta', $html(['event' => 'deleted']));
        $this->assertStringContainsString('gamma@example.com', $html(['event' => 'login_failed']));
        $this->assertStringNotContainsString('Old Delta', $html(['from' => now()->subDays(7)->toDateString()]));
        $this->assertStringContainsString('Old Delta', $html(['to' => now()->subDays(30)->toDateString()]));
        $this->assertStringNotContainsString('Course Alpha', $html(['to' => now()->subDays(30)->toDateString()]));

        // قيم غير معروفة لا تكسر شيئًا ولا تُفلتر
        $this->assertStringContainsString('Course Alpha', $html(['type' => 'nonsense', 'event' => 'nonsense']));
        // % حرفية لا wildcard
        $this->assertStringNotContainsString('Course Alpha', $html(['search' => '%']));

        $this->getJson(route('cms.admin.audit-logs.index', ['sort' => 'drop table', 'dir' => 'x']))->assertOk();
    }

    public function test_stats(): void
    {
        AuditLog::factory()->create();
        AuditLog::factory()->of('course', 'deleted')->create();
        AuditLog::factory()->of('course', 'force_deleted')->create();
        AuditLog::factory()->of('auth', 'login_failed')->create();
        AuditLog::factory()->create(['created_at' => now()->subDays(3)]);
        AuditLog::factory()->create(['created_at' => now()->subDays(30)]);

        $this->actingAs($this->admin())->getJson(route('cms.admin.audit-logs.index'))
            ->assertOk()
            ->assertJsonPath('stats.total', 6)
            ->assertJsonPath('stats.today', 4)
            ->assertJsonPath('stats.week', 5)
            ->assertJsonPath('stats.deletions', 2)
            ->assertJsonPath('stats.failed_logins', 1);
    }

    public function test_show_returns_the_changes_and_404_for_unknown_ids(): void
    {
        $log = AuditLog::factory()->create(['old_values' => ['title_en' => 'Old'], 'new_values' => ['title_en' => 'New'], 'user_agent' => 'Agent/1']);
        $bare = AuditLog::factory()->create(['old_values' => null, 'new_values' => null]);
        $admin = $this->admin();

        $this->actingAs($admin)->getJson(route('cms.admin.audit-logs.show', $log->id))
            ->assertOk()
            ->assertJsonPath('data.old_values.title_en', 'Old')
            ->assertJsonPath('data.new_values.title_en', 'New')
            ->assertJsonPath('data.user_agent', 'Agent/1')
            ->assertJsonPath('data.action', 'course.updated');

        $this->getJson(route('cms.admin.audit-logs.show', $bare->id))
            ->assertOk()->assertJsonPath('data.old_values', [])->assertJsonPath('data.new_values', []);

        $this->getJson(route('cms.admin.audit-logs.show', 999999))->assertNotFound();
    }

    /* ---------- الصلاحيات واللغات ---------- */

    public function test_seeder_gives_the_audit_permission_to_nobody_but_super_admin(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);

        $this->assertTrue(Role::findByName('super-admin', 'web')->hasPermissionTo('audit-logs.view'));
        $this->assertFalse(Role::findByName('instructor', 'web')->hasPermissionTo('audit-logs.view'));
        $this->assertFalse(Role::findByName('editor', 'web')->hasPermissionTo('audit-logs.view'));
        $this->assertSame(0, AuditLog::count(), 'seeding is not a human action');
    }

    public function test_every_area_and_event_has_a_label_in_both_languages(): void
    {
        foreach (['ar', 'en'] as $locale) {
            foreach (AuditLogger::typeKeys() as $type) {
                $this->assertNotSame("audit_logs.types.{$type}", trans("audit_logs.types.{$type}", [], $locale), "{$locale} {$type}");
            }
            foreach (AuditLogger::EVENTS as $event) {
                $this->assertNotSame("audit_logs.events.{$event}", trans("audit_logs.events.{$event}", [], $locale), "{$locale} {$event}");
            }
        }
    }
}
