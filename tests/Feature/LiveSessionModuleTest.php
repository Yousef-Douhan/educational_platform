<?php

namespace Tests\Feature;

use App\Exceptions\LiveSessionException;
use App\Models\Course;
use App\Models\Enrollment;
use App\Models\LiveSession;
use App\Models\User;
use App\Services\LiveSessionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class LiveSessionModuleTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        app(PermissionRegistrar::class)->forgetCachedPermissions();
        $this->withoutVite();

        foreach (['live-sessions.view', 'live-sessions.create', 'live-sessions.edit', 'live-sessions.delete', 'courses.manage-all'] as $name) {
            Permission::findOrCreate($name, 'web');
        }
    }

    /* ---------- أدوات ---------- */

    private function sessions(): LiveSessionService
    {
        return app(LiveSessionService::class);
    }

    private function admin(string ...$permissions): User
    {
        $u = User::factory()->create();
        $u->givePermissionTo($permissions ?: Permission::all());

        return $u;
    }

    private function course(array $o = []): Course
    {
        return Course::factory()->published()->create($o);
    }

    private function data(Course $course, array $o = []): array
    {
        return array_merge([
            'course_id'        => $course->id,
            'title_ar'         => 'جلسة تجريبية',
            'title_en'         => 'Demo session',
            'meeting_url'      => 'https://meet.example.com/room-1',
            'scheduled_at'     => now()->addDays(3)->setTime(10, 0)->toDateTimeString(),
            'duration_minutes' => 60,
        ], $o);
    }

    private function make(Course $course, string $start, int $minutes = 60): LiveSession
    {
        return LiveSession::factory()->at(now()->addDays(3)->setTimeFromTimeString($start), $minutes)->create(['course_id' => $course->id]);
    }

    private function assertRejected(string $reason, callable $fn): void
    {
        try {
            $fn();
        } catch (LiveSessionException $e) {
            $this->assertSame($reason, $e->reason);

            return;
        }

        $this->fail("Expected LiveSessionException: {$reason}");
    }

    private function student(Course $course, string $status = 'active', $expires = null): User
    {
        $u = User::factory()->create();
        Enrollment::factory()->create(['user_id' => $u->id, 'course_id' => $course->id, 'status' => $status, 'expires_at' => $expires]);

        return $u;
    }

    /* ---------- الإنشاء ---------- */

    public function test_create_sets_status_end_time_and_creator(): void
    {
        $admin   = $this->admin();
        $course  = $this->course();
        $session = $this->sessions()->create($admin, $this->data($course, ['duration_minutes' => 90]));

        $this->assertSame('scheduled', $session->status);
        $this->assertSame($admin->id, $session->created_by);
        $this->assertTrue($session->ends_at->equalTo($session->scheduled_at->copy()->addMinutes(90)));
    }

    public function test_status_cannot_be_mass_assigned(): void
    {
        // الحقول الحساسة خارج fillable، فتُتجاهل بصمت عند الملء الجماعي
        $session = new LiveSession(['title_en' => 'x', 'status' => 'ended', 'created_by' => 99, 'cancel_reason' => 'x']);

        $this->assertSame('x', $session->title_en);
        $this->assertNull($session->status);
        $this->assertNull($session->created_by);
        $this->assertNull($session->cancel_reason);
    }

    public function test_end_time_follows_the_start_and_duration_on_save(): void
    {
        $session = LiveSession::factory()->create();
        $session->duration_minutes = 120;
        $session->save();

        $this->assertTrue($session->fresh()->ends_at->equalTo($session->scheduled_at->copy()->addMinutes(120)));
    }

    public function test_archived_and_deleted_courses_cannot_get_sessions(): void
    {
        $archived = Course::factory()->create(['status' => 'archived']);
        $deleted  = $this->course();
        $deleted->delete();

        $this->assertRejected('course_unavailable', fn () => $this->sessions()->create($this->admin(), $this->data($archived)));
        $this->assertRejected('course_unavailable', fn () => $this->sessions()->create($this->admin(), $this->data($deleted)));
    }

    /* ---------- التعارض ---------- */

    public function test_overlap_in_the_same_course_is_rejected(): void
    {
        $course = $this->course();
        $this->make($course, '10:00', 60);

        $this->assertRejected('overlap', fn () => $this->sessions()->create($this->admin(), $this->data($course, [
            'scheduled_at' => now()->addDays(3)->setTime(10, 30)->toDateTimeString(),
        ])));
    }

    public function test_overlap_across_courses_of_the_same_instructor_is_rejected(): void
    {
        $teacher = User::factory()->create();
        $a = $this->course(['instructor_id' => $teacher->id]);
        $b = $this->course(['instructor_id' => $teacher->id]);
        $this->make($a, '10:00', 60);

        $this->assertRejected('overlap', fn () => $this->sessions()->create($this->admin(), $this->data($b, [
            'scheduled_at' => now()->addDays(3)->setTime(10, 15)->toDateTimeString(),
        ])));
    }

    public function test_different_instructors_may_overlap(): void
    {
        $a = $this->course();
        $b = $this->course();
        $this->make($a, '10:00', 60);

        $session = $this->sessions()->create($this->admin(), $this->data($b));

        $this->assertNotNull($session->id);
    }

    public function test_back_to_back_sessions_are_allowed(): void
    {
        $course = $this->course();
        $this->make($course, '10:00', 60);

        $session = $this->sessions()->create($this->admin(), $this->data($course, [
            'scheduled_at' => now()->addDays(3)->setTime(11, 0)->toDateTimeString(),
        ]));

        $this->assertNotNull($session->id);
    }

    public function test_cancelled_and_deleted_sessions_do_not_block(): void
    {
        $course = $this->course();
        $this->make($course, '10:00', 60)->forceFill(['status' => 'cancelled'])->save();
        $this->make($course, '12:00', 60)->delete();

        $this->sessions()->create($this->admin(), $this->data($course, ['scheduled_at' => now()->addDays(3)->setTime(10, 0)->toDateTimeString()]));
        $this->sessions()->create($this->admin(), $this->data($course, ['scheduled_at' => now()->addDays(3)->setTime(12, 0)->toDateTimeString()]));

        $this->assertSame(4, LiveSession::withTrashed()->count());
    }

    public function test_update_ignores_itself_and_still_checks_others(): void
    {
        $course = $this->course();
        $first  = $this->make($course, '10:00', 60);
        $this->make($course, '12:00', 60);

        $updated = $this->sessions()->update($first, $this->data($course, [
            'duration_minutes' => 90, 'scheduled_at' => $first->scheduled_at->toDateTimeString(),
        ]));
        $this->assertSame(90, $updated->duration_minutes);

        $this->assertRejected('overlap', fn () => $this->sessions()->update($first, $this->data($course, [
            'duration_minutes' => 180, 'scheduled_at' => $first->scheduled_at->toDateTimeString(),
        ])));
    }

    /* ---------- التعديل والإلغاء ---------- */

    public function test_ended_and_cancelled_sessions_cannot_be_edited_or_cancelled(): void
    {
        $course = $this->course();
        $ended  = LiveSession::factory()->past()->create(['course_id' => $course->id]);
        $gone   = LiveSession::factory()->cancelled()->create(['course_id' => $course->id]);

        $this->assertRejected('not_editable', fn () => $this->sessions()->update($ended, $this->data($course)));
        $this->assertRejected('not_editable', fn () => $this->sessions()->update($gone, $this->data($course)));
        $this->assertRejected('not_cancellable', fn () => $this->sessions()->cancel($ended, 'late'));
        $this->assertRejected('not_cancellable', fn () => $this->sessions()->cancel($gone, 'again'));
    }

    public function test_update_cannot_move_a_session_to_another_course(): void
    {
        $a = $this->course();
        $b = $this->course();
        $session = $this->make($a, '10:00');

        $updated = $this->sessions()->update($session, $this->data($b, ['scheduled_at' => $session->scheduled_at->toDateTimeString()]));

        $this->assertSame($a->id, $updated->course_id);
    }

    public function test_cancel_records_reason_and_time(): void
    {
        $session = LiveSession::factory()->create();

        $cancelled = $this->sessions()->cancel($session, 'Instructor is sick');

        $this->assertSame('cancelled', $cancelled->status);
        $this->assertSame('Instructor is sick', $cancelled->cancel_reason);
        $this->assertNotNull($cancelled->cancelled_at);
        $this->assertSame('cancelled', $cancelled->phase());
    }

    public function test_a_running_session_can_be_cancelled(): void
    {
        $session = LiveSession::factory()->live()->create();

        $this->assertSame('live', $session->phase());
        $this->assertSame('cancelled', $this->sessions()->cancel($session, 'Technical issue')->status);
    }

    /* ---------- الاسترجاع والإغلاق ---------- */

    public function test_restore_is_blocked_when_it_would_overlap(): void
    {
        $course = $this->course();
        $old    = $this->make($course, '10:00', 60);
        $old->delete();
        $this->make($course, '10:30', 60);

        $this->assertRejected('overlap', fn () => $this->sessions()->restore($old->id));
        $this->assertTrue(LiveSession::withTrashed()->find($old->id)->trashed());
    }

    public function test_restore_works_without_overlap(): void
    {
        $session = LiveSession::factory()->create();
        $session->delete();

        $this->sessions()->restore($session->id);

        $this->assertFalse(LiveSession::withTrashed()->find($session->id)->trashed());
    }

    public function test_close_ended_marks_only_finished_scheduled_sessions(): void
    {
        $past     = LiveSession::factory()->past()->create();
        $running  = LiveSession::factory()->live()->create();
        $upcoming = LiveSession::factory()->create();
        $gone     = LiveSession::factory()->past()->cancelled()->create();

        $this->assertSame(1, $this->sessions()->closeEnded());

        $this->assertSame('ended', $past->fresh()->status);
        $this->assertSame('scheduled', $running->fresh()->status);
        $this->assertSame('scheduled', $upcoming->fresh()->status);
        $this->assertSame('cancelled', $gone->fresh()->status);
    }

    public function test_the_close_command_runs(): void
    {
        LiveSession::factory()->past()->create();

        $this->artisan('live-sessions:close-ended')->expectsOutput('Closed 1 live session(s).')->assertExitCode(0);
    }

    public function test_phase_is_computed_from_time_and_matches_the_sql_scope(): void
    {
        $up   = LiveSession::factory()->create();
        $live = LiveSession::factory()->live()->create();
        $end  = LiveSession::factory()->past()->create();   // لم تُغلق بعد، لكن الوقت انتهى
        $can  = LiveSession::factory()->cancelled()->create();

        $this->assertSame('upcoming', $up->phase());
        $this->assertSame('live', $live->phase());
        $this->assertSame('ended', $end->phase());
        $this->assertSame('cancelled', $can->phase());

        foreach (['upcoming' => $up, 'live' => $live, 'ended' => $end, 'cancelled' => $can] as $phase => $expected) {
            $this->assertSame([$expected->id], LiveSession::withPhase($phase)->pluck('id')->all(), $phase);
        }
    }

    /* ---------- رابط الطالب ---------- */

    public function test_enrolled_student_gets_the_link_inside_the_window(): void
    {
        $course  = $this->course();
        $student = $this->student($course);
        $running = LiveSession::factory()->live()->create(['course_id' => $course->id]);
        $soon    = LiveSession::factory()->at(now()->addMinutes(10))->create(['course_id' => $course->id]);

        $this->assertSame($running->meeting_url, $this->sessions()->joinUrl($student, $running));
        $this->assertSame($soon->meeting_url, $this->sessions()->joinUrl($student, $soon));
    }

    public function test_the_link_stays_hidden_too_early_after_the_end_and_when_cancelled(): void
    {
        $course  = $this->course();
        $student = $this->student($course);

        $early = LiveSession::factory()->at(now()->addMinutes(40))->create(['course_id' => $course->id]);
        $over  = LiveSession::factory()->past()->create(['course_id' => $course->id]);
        $gone  = LiveSession::factory()->live()->cancelled()->create(['course_id' => $course->id]);

        $this->assertRejected('not_started', fn () => $this->sessions()->joinUrl($student, $early));
        $this->assertRejected('session_ended', fn () => $this->sessions()->joinUrl($student, $over));
        $this->assertRejected('session_cancelled', fn () => $this->sessions()->joinUrl($student, $gone));
    }

    public function test_the_link_requires_an_active_enrollment(): void
    {
        $course  = $this->course();
        $session = LiveSession::factory()->live()->create(['course_id' => $course->id]);

        $stranger  = User::factory()->create();
        $cancelled = $this->student($course, 'cancelled');
        $expired   = $this->student($course, 'active', now()->subDay());
        $other     = $this->student($this->course());

        foreach ([$stranger, $cancelled, $other, $expired] as $u) {
            $this->assertRejected('not_enrolled', fn () => $this->sessions()->joinUrl($u, $session));
        }
    }

    public function test_a_completed_enrollment_still_gets_the_link(): void
    {
        $course  = $this->course();
        $student = $this->student($course, 'completed');
        $session = LiveSession::factory()->live()->create(['course_id' => $course->id]);

        $this->assertSame($session->meeting_url, $this->sessions()->joinUrl($student, $session));
    }

    public function test_the_link_is_hidden_for_deleted_sessions_unpublished_courses_and_deleted_students(): void
    {
        $course  = $this->course();
        $student = $this->student($course);
        $session = LiveSession::factory()->live()->create(['course_id' => $course->id]);

        $gone = LiveSession::factory()->live()->create(['course_id' => $course->id]);
        $gone->delete();
        $this->assertRejected('session_unavailable', fn () => $this->sessions()->joinUrl($student, $gone));

        $course->forceFill(['status' => 'draft'])->save();
        $this->assertRejected('session_unavailable', fn () => $this->sessions()->joinUrl($student, $session));

        $course->forceFill(['status' => 'published'])->save();
        $student->delete();
        $this->assertRejected('student_inactive', fn () => $this->sessions()->joinUrl($student, $session));
    }

    /* ---------- قاعدة البيانات ---------- */

    public function test_deleting_a_course_forever_removes_its_sessions(): void
    {
        $session = LiveSession::factory()->create();
        Course::find($session->course_id)->forceDelete();

        $this->assertSame(0, LiveSession::withTrashed()->count());
    }

    /* ---------- الشاشة والـ API ---------- */

    private function payload(Course $course, array $o = []): array
    {
        return array_merge($this->data($course), [
            'scheduled_at' => now()->addDays(3)->setTime(10, 0)->format('Y-m-d\TH:i'),
        ], $o);
    }

    public function test_screens_require_permissions(): void
    {
        $session = LiveSession::factory()->create();
        $nobody  = User::factory()->create();

        $this->actingAs($nobody)->get(route('cms.admin.live-sessions.index'))->assertForbidden();
        $this->postJson(route('cms.admin.live-sessions.store'), [])->assertForbidden();
        $this->deleteJson(route('cms.admin.live-sessions.destroy', $session->id))->assertForbidden();

        $viewer = $this->admin('live-sessions.view', 'courses.manage-all');
        $this->actingAs($viewer)->get(route('cms.admin.live-sessions.index'))->assertOk();
        $this->getJson(route('cms.admin.live-sessions.show', $session->id))->assertOk();
        $this->postJson(route('cms.admin.live-sessions.store'), [])->assertForbidden();
        $this->postJson(route('cms.admin.live-sessions.cancel', $session->id), ['reason' => 'x y z'])->assertForbidden();
        $this->deleteJson(route('cms.admin.live-sessions.destroy', $session->id))->assertForbidden();
    }

    public function test_index_renders_and_escapes_titles(): void
    {
        LiveSession::factory()->create(['title_ar' => '<script>alert(1)</script>', 'title_en' => '<script>alert(1)</script>']);

        $this->actingAs($this->admin())->get(route('cms.admin.live-sessions.index'))
            ->assertOk()
            ->assertDontSee('<script>alert(1)</script>', false)
            ->assertSee('&lt;script&gt;', false);
    }

    public function test_index_does_not_expose_meeting_links_in_the_table(): void
    {
        LiveSession::factory()->create(['meeting_url' => 'https://secret.example.com/abc123']);

        $html = $this->actingAs($this->admin())->getJson(route('cms.admin.live-sessions.index'))->assertOk()->json('html');

        $this->assertStringNotContainsString('secret.example.com', $html);
    }

    public function test_store_through_the_api(): void
    {
        $course = $this->course();

        $this->actingAs($this->admin())
            ->postJson(route('cms.admin.live-sessions.store'), $this->payload($course))
            ->assertCreated()
            ->assertJsonPath('data.phase', 'upcoming')
            ->assertJsonPath('stats.total', 1);

        $this->assertDatabaseHas('live_sessions', ['course_id' => $course->id, 'status' => 'scheduled']);
    }

    public function test_store_validation(): void
    {
        $course = $this->course();
        $admin  = $this->admin();

        $bad = [
            ['meeting_url' => 'http://meet.example.com/x'],
            ['meeting_url' => 'javascript:alert(1)'],
            ['meeting_url' => 'https://user:pass@meet.example.com/x'],
            ['meeting_url' => 'https://meet.example.com/a b'],
            ['meeting_url' => 'https://localhost'],
            ['scheduled_at' => now()->subHour()->format('Y-m-d\TH:i')],
            ['duration_minutes' => 4],
            ['duration_minutes' => 721],
            ['title_ar' => 'ab'],
        ];

        foreach ($bad as $i => $override) {
            $this->actingAs($admin)->postJson(route('cms.admin.live-sessions.store'), $this->payload($course, $override))
                ->assertStatus(422, "case {$i}");
        }

        $this->postJson(route('cms.admin.live-sessions.store'), array_diff_key($this->payload($course), ['course_id' => 1]))
            ->assertUnprocessable()->assertJsonValidationErrors('course_id');

        $this->assertSame(0, LiveSession::count());
    }

    public function test_store_overlap_returns_a_field_error(): void
    {
        $course = $this->course();
        $this->make($course, '10:00', 60);

        $this->actingAs($this->admin())
            ->postJson(route('cms.admin.live-sessions.store'), $this->payload($course, [
                'scheduled_at' => now()->addDays(3)->setTime(10, 30)->format('Y-m-d\TH:i'),
            ]))
            ->assertUnprocessable()->assertJsonValidationErrors('scheduled_at');
    }

    public function test_update_and_cancel_through_the_api(): void
    {
        $course  = $this->course();
        $session = $this->make($course, '10:00');
        $admin   = $this->admin();

        $this->actingAs($admin)->putJson(route('cms.admin.live-sessions.update', $session->id), $this->payload($course, [
            'title_en' => 'Renamed', 'scheduled_at' => $session->scheduled_at->format('Y-m-d\TH:i'),
        ]))->assertOk()->assertJsonPath('data.title_en', 'Renamed');

        $this->postJson(route('cms.admin.live-sessions.cancel', $session->id), ['reason' => ''])->assertUnprocessable();
        $this->postJson(route('cms.admin.live-sessions.cancel', $session->id), ['reason' => 'Not needed'])
            ->assertOk()->assertJsonPath('data.phase', 'cancelled');

        $this->putJson(route('cms.admin.live-sessions.update', $session->id), $this->payload($course, [
            'scheduled_at' => $session->scheduled_at->format('Y-m-d\TH:i'),
        ]))->assertStatus(409);
        $this->postJson(route('cms.admin.live-sessions.cancel', $session->id), ['reason' => 'Twice'])->assertStatus(409);
    }

    public function test_update_cannot_change_the_course_through_the_api(): void
    {
        $a = $this->course();
        $b = $this->course();
        $session = $this->make($a, '10:00');

        $this->actingAs($this->admin())->putJson(route('cms.admin.live-sessions.update', $session->id), $this->payload($b, [
            'scheduled_at' => $session->scheduled_at->format('Y-m-d\TH:i'),
        ]))->assertUnprocessable()->assertJsonValidationErrors('course_id');
    }

    public function test_trash_restore_and_force_delete(): void
    {
        $session = LiveSession::factory()->create();
        $admin   = $this->admin();

        $this->actingAs($admin)->deleteJson(route('cms.admin.live-sessions.destroy', $session->id))
            ->assertOk()->assertJsonPath('stats.trashed', 1);
        $this->getJson(route('cms.admin.live-sessions.show', $session->id))->assertNotFound();
        $this->getJson(route('cms.admin.live-sessions.trashed'))->assertOk()->assertJsonPath('stats.total', 0);

        $this->patchJson(route('cms.admin.live-sessions.restore', $session->id))->assertOk();
        $this->assertFalse(LiveSession::withTrashed()->find($session->id)->trashed());

        $this->patchJson(route('cms.admin.live-sessions.restore', $session->id))->assertNotFound();
        $this->deleteJson(route('cms.admin.live-sessions.force-delete', $session->id))->assertNotFound();

        $session->delete();
        $this->deleteJson(route('cms.admin.live-sessions.force-delete', $session->id))->assertOk();
        $this->assertSame(0, LiveSession::withTrashed()->count());
    }

    public function test_restore_overlap_returns_422_with_a_message(): void
    {
        $course = $this->course();
        $old    = $this->make($course, '10:00');
        $old->delete();
        $this->make($course, '10:30');

        $this->actingAs($this->admin())->patchJson(route('cms.admin.live-sessions.restore', $old->id))
            ->assertUnprocessable()->assertJsonValidationErrors('scheduled_at');
    }

    public function test_index_filters_and_sort_whitelist(): void
    {
        $a = $this->course();
        // العنوان بالعربية والإنجليزية متطابق حتى لا يعتمد الاختبار على لغة التطبيق
        foreach ([['alpha', 'Alpha Talk'], ['beta', 'Beta Talk'], ['gamma', 'Gamma Talk'], ['delta', 'Delta Talk']] as [, $t]) {
            $titles[$t] = ['title_en' => $t, 'title_ar' => $t];
        }
        LiveSession::factory()->create(['course_id' => $a->id] + $titles['Alpha Talk']);
        LiveSession::factory()->live()->create($titles['Beta Talk']);
        LiveSession::factory()->past()->create($titles['Gamma Talk']);
        LiveSession::factory()->cancelled()->create($titles['Delta Talk']);
        $admin = $this->admin();

        $html = fn (array $q) => $this->actingAs($admin)->getJson(route('cms.admin.live-sessions.index', $q))->assertOk()->json('html');

        $this->assertStringContainsString('Alpha Talk', $html(['search' => 'Alpha']));
        $this->assertStringNotContainsString('Beta Talk', $html(['search' => 'Alpha']));
        $this->assertStringContainsString('Beta Talk', $html(['phase' => 'live']));
        $this->assertStringNotContainsString('Alpha Talk', $html(['phase' => 'live']));
        $this->assertStringContainsString('Gamma Talk', $html(['phase' => 'ended']));
        $this->assertStringContainsString('Delta Talk', $html(['phase' => 'cancelled']));
        $this->assertStringContainsString('Alpha Talk', $html(['course_id' => $a->id]));
        $this->assertStringNotContainsString('Beta Talk', $html(['course_id' => $a->id]));
        // البحث بحرف % لا يتحول إلى wildcard
        $this->assertStringNotContainsString('Alpha Talk', $html(['search' => '%']));

        $this->getJson(route('cms.admin.live-sessions.index', ['sort' => 'drop table', 'dir' => 'x']))->assertOk();
    }

    public function test_stats_count_each_phase(): void
    {
        LiveSession::factory()->create();
        LiveSession::factory()->live()->create();
        LiveSession::factory()->past()->create();
        LiveSession::factory()->cancelled()->create();
        LiveSession::factory()->create()->delete();

        $this->actingAs($this->admin())->getJson(route('cms.admin.live-sessions.index'))
            ->assertOk()
            ->assertJsonPath('stats.total', 4)
            ->assertJsonPath('stats.upcoming', 1)
            ->assertJsonPath('stats.live', 1)
            ->assertJsonPath('stats.ended', 1)
            ->assertJsonPath('stats.cancelled', 1)
            ->assertJsonPath('stats.trashed', 1);
    }

    public function test_instructors_only_manage_sessions_of_their_own_courses(): void
    {
        $teacher = $this->admin('live-sessions.view', 'live-sessions.create', 'live-sessions.edit', 'live-sessions.delete');
        $mine    = $this->course(['instructor_id' => $teacher->id]);
        $others  = $this->course();

        $own     = LiveSession::factory()->at(now()->addDays(10)->setTime(10, 0))
            ->create(['course_id' => $mine->id, 'title_en' => 'My Session', 'title_ar' => 'My Session']);
        $foreign = LiveSession::factory()->create(['course_id' => $others->id, 'title_en' => 'Foreign Session', 'title_ar' => 'Foreign Session']);
        $foreignGone = LiveSession::factory()->create(['course_id' => $others->id]);
        $foreignGone->delete();

        $this->actingAs($teacher);

        $html = $this->getJson(route('cms.admin.live-sessions.index'))->assertOk()->json('html');
        $this->assertStringContainsString('My Session', $html);
        $this->assertStringNotContainsString('Foreign Session', $html);

        $this->getJson(route('cms.admin.live-sessions.show', $own->id))->assertOk();
        $this->getJson(route('cms.admin.live-sessions.show', $foreign->id))->assertNotFound();
        $this->getJson(route('cms.admin.live-sessions.edit', $foreign->id))->assertNotFound();
        $this->putJson(route('cms.admin.live-sessions.update', $foreign->id), $this->payload($others, [
            'scheduled_at' => $foreign->scheduled_at->format('Y-m-d\TH:i'),
        ]))->assertNotFound();
        $this->postJson(route('cms.admin.live-sessions.cancel', $foreign->id), ['reason' => 'nope nope'])->assertNotFound();
        $this->deleteJson(route('cms.admin.live-sessions.destroy', $foreign->id))->assertNotFound();
        $this->patchJson(route('cms.admin.live-sessions.restore', $foreignGone->id))->assertNotFound();
        $this->deleteJson(route('cms.admin.live-sessions.force-delete', $foreignGone->id))->assertNotFound();

        // لا يستطيع إنشاء جلسة لدورة غيره، ولا تظهر دورته غيره في خيارات الإنشاء
        $this->postJson(route('cms.admin.live-sessions.store'), $this->payload($others))->assertUnprocessable()->assertJsonValidationErrors('course_id');
        $ids = collect($this->getJson(route('cms.admin.live-sessions.create'))->assertOk()->json('options.courses'))->pluck('id')->all();
        $this->assertSame([$mine->id], $ids);

        $this->postJson(route('cms.admin.live-sessions.store'), $this->payload($mine))->assertCreated();
    }

    public function test_create_options_exclude_archived_courses(): void
    {
        $open     = $this->course();
        $archived = Course::factory()->create(['status' => 'archived']);

        $ids = collect($this->actingAs($this->admin())->getJson(route('cms.admin.live-sessions.create'))->json('options.courses'))->pluck('id')->all();

        $this->assertContains($open->id, $ids);
        $this->assertNotContains($archived->id, $ids);
    }

    public function test_deleted_courses_still_show_in_the_index(): void
    {
        $course = $this->course(['title_en' => 'Vanished Course', 'title_ar' => 'Vanished Course']);
        LiveSession::factory()->create(['course_id' => $course->id]);
        $course->delete();

        $this->actingAs($this->admin())->get(route('cms.admin.live-sessions.index'))->assertOk()->assertSee('Vanished Course');
    }
}
