<?php

namespace Tests\Feature;

use App\Models\Course;
use App\Models\Enrollment;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class EnrollmentCrudTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        foreach (['view', 'create', 'edit', 'delete'] as $a) {
            Permission::findOrCreate("courses.{$a}", 'web');
        }
        foreach (['courses.manage-all', 'courses.publish'] as $n) {
            Permission::findOrCreate($n, 'web');
        }
        foreach (['view', 'create', 'edit'] as $a) {
            Permission::findOrCreate("enrollments.{$a}", 'web');
        }

        Role::findOrCreate('instructor', 'web')->givePermissionTo([
            'courses.view',
            'courses.create',
            'courses.edit',
            'courses.delete',
            'enrollments.view',
        ]);
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

    private function published(array $o = []): Course
    {
        return Course::factory()->published()->create($o);
    }

    /* ---------- القائمة ---------- */

    public function test_index_page_renders(): void
    {
        $this->actingAs($this->admin())->get(route('cms.admin.enrollments.index'))
            ->assertOk()->assertViewIs('cms.enrollments.index');
    }

    public function test_index_json_returns_real_stats(): void
    {
        Enrollment::factory()->create();
        Enrollment::factory()->status('completed')->create();
        Enrollment::factory()->status('expired')->create();
        Enrollment::factory()->status('cancelled')->count(2)->create();

        $this->actingAs($this->admin())->getJson(route('cms.admin.enrollments.index'))
            ->assertOk()
            ->assertJsonPath('stats.total', 5)
            ->assertJsonPath('stats.active', 1)
            ->assertJsonPath('stats.completed', 1)
            ->assertJsonPath('stats.expired', 1)
            ->assertJsonPath('stats.cancelled', 2);
    }

    public function test_instructor_sees_only_enrollments_of_own_and_shared_courses(): void
    {
        $me      = $this->teacher();
        $own     = $this->published(['instructor_id' => $me->id, 'title_en' => 'OwnCourseQ']);
        $shared  = $this->published(['title_en' => 'SharedCourseQ']);
        $shared->coInstructors()->attach($me->id);
        $foreign = $this->published(['title_en' => 'ForeignCourseQ']);

        foreach ([$own, $shared, $foreign] as $c) {
            Enrollment::factory()->create(['course_id' => $c->id]);
        }

        $html = $this->actingAs($me)->getJson(route('cms.admin.enrollments.index'))->assertOk()->json('html');

        $this->assertStringContainsString('OwnCourseQ', $html);
        $this->assertStringContainsString('SharedCourseQ', $html);
        $this->assertStringNotContainsString('ForeignCourseQ', $html);
    }

    public function test_index_filters_and_search(): void
    {
        $zed  = User::factory()->create(['name' => 'Zebra Student', 'email' => 'zebra@example.com']);
        $other = User::factory()->create(['name' => 'Quokka Person']);
        Enrollment::factory()->create(['user_id' => $zed->id]);
        Enrollment::factory()->status('cancelled')->create(['user_id' => $other->id]);
        $admin = $this->admin();

        $byName = $this->actingAs($admin)->getJson(route('cms.admin.enrollments.index', ['search' => 'Zebra']))->json('html');
        $this->assertStringContainsString('Zebra Student', $byName);
        $this->assertStringNotContainsString('Quokka Person', $byName);

        $byEmail = $this->getJson(route('cms.admin.enrollments.index', ['search' => 'zebra@example']))->json('html');
        $this->assertStringContainsString('Zebra Student', $byEmail);

        $cancelled = $this->getJson(route('cms.admin.enrollments.index', ['status' => 'cancelled']))->json('html');
        $this->assertStringContainsString('Quokka Person', $cancelled);
        $this->assertStringNotContainsString('Zebra Student', $cancelled);
    }

    public function test_index_ignores_invalid_sort_column(): void
    {
        Enrollment::factory()->create();

        $this->actingAs($this->admin())
            ->getJson(route('cms.admin.enrollments.index', ['sort' => 'status; drop table enrollments']))
            ->assertOk();
    }

    public function test_user_without_permissions_is_forbidden(): void
    {
        $this->actingAs(User::factory()->create())
            ->getJson(route('cms.admin.enrollments.index'))->assertForbidden();
    }

    /* ---------- الإنشاء ---------- */

    public function test_create_returns_only_published_courses(): void
    {
        $pub = $this->published(['title_en' => 'PubCourse']);
        Course::factory()->create(['title_en' => 'DraftCourse']);

        $titles = collect($this->actingAs($this->admin())->getJson(route('cms.admin.enrollments.create'))
            ->assertOk()->json('options.courses'))->pluck('title_en');

        $this->assertTrue($titles->contains('PubCourse'));
        $this->assertFalse($titles->contains('DraftCourse'));
    }

    public function test_lookup_users_needs_two_letters_and_skips_deleted_users(): void
    {
        User::factory()->create(['name' => 'Zedman One']);
        User::factory()->create(['name' => 'Zedman Two'])->delete();
        $admin = $this->admin();

        $this->actingAs($admin)->getJson(route('cms.admin.enrollments.lookup-users', ['search' => 'Z']))
            ->assertOk()->assertJsonCount(0, 'data');

        $names = collect($this->getJson(route('cms.admin.enrollments.lookup-users', ['search' => 'Zedman']))
            ->assertOk()->json('data'))->pluck('name');

        $this->assertTrue($names->contains('Zedman One'));
        $this->assertFalse($names->contains('Zedman Two'));
    }

    public function test_lookup_users_requires_create_permission(): void
    {
        $this->actingAs($this->teacher())
            ->getJson(route('cms.admin.enrollments.lookup-users', ['search' => 'abc']))->assertForbidden();
    }

    public function test_store_enrolls_student_as_active_now(): void
    {
        $user   = User::factory()->create();
        $course = $this->published();

        $this->actingAs($this->admin())->postJson(route('cms.admin.enrollments.store'), [
            'user_id' => $user->id,
            'course_id' => $course->id,
            'expires_at' => now()->addMonth()->format('Y-m-d H:i:s'),
        ])->assertCreated()->assertJsonPath('data.status', 'active')->assertJsonPath('data.has_access', true);

        $row = Enrollment::firstWhere(['user_id' => $user->id, 'course_id' => $course->id]);
        $this->assertNotNull($row->enrolled_at);
        $this->assertTrue($row->expires_at->isFuture());
    }

    public function test_store_allows_no_expiry(): void
    {
        $this->actingAs($this->admin())->postJson(route('cms.admin.enrollments.store'), [
            'user_id' => User::factory()->create()->id,
            'course_id' => $this->published()->id,
        ])->assertCreated();

        $this->assertNull(Enrollment::first()->expires_at);
    }

    public function test_store_rejects_draft_and_trashed_courses_and_trashed_users(): void
    {
        $user    = User::factory()->create();
        $draft   = Course::factory()->create();
        $gone    = $this->published();
        $gone->delete();
        $deleted = User::factory()->create();
        $deleted->delete();
        $admin   = $this->admin();
        $url     = route('cms.admin.enrollments.store');

        $this->actingAs($admin)->postJson($url, ['user_id' => $user->id, 'course_id' => $draft->id])
            ->assertUnprocessable()->assertJsonValidationErrors('course_id');
        $this->postJson($url, ['user_id' => $user->id, 'course_id' => $gone->id])
            ->assertUnprocessable()->assertJsonValidationErrors('course_id');
        $this->postJson($url, ['user_id' => $deleted->id, 'course_id' => $this->published()->id])
            ->assertUnprocessable()->assertJsonValidationErrors('user_id');
    }

    public function test_store_rejects_duplicate_even_when_previous_is_cancelled(): void
    {
        $e = Enrollment::factory()->status('cancelled')->create();

        $this->actingAs($this->admin())->postJson(route('cms.admin.enrollments.store'), [
            'user_id' => $e->user_id,
            'course_id' => $e->course_id,
        ])->assertUnprocessable()->assertJsonValidationErrors('course_id');

        $this->assertSame(1, Enrollment::count());
    }

    public function test_store_rejects_past_expiry_and_missing_fields(): void
    {
        $admin = $this->admin();

        $this->actingAs($admin)->postJson(route('cms.admin.enrollments.store'), [])
            ->assertUnprocessable()->assertJsonValidationErrors(['user_id', 'course_id']);

        $this->postJson(route('cms.admin.enrollments.store'), [
            'user_id' => User::factory()->create()->id,
            'course_id' => $this->published()->id,
            'expires_at' => now()->subDay()->format('Y-m-d H:i:s'),
        ])->assertUnprocessable()->assertJsonValidationErrors('expires_at');
    }

    public function test_instructor_cannot_enroll_students(): void
    {
        $this->actingAs($this->teacher())->postJson(route('cms.admin.enrollments.store'), [
            'user_id' => User::factory()->create()->id,
            'course_id' => $this->published()->id,
        ])->assertForbidden();
    }

    /* ---------- العرض والتعديل ---------- */

    public function test_show_reports_access_and_days_left(): void
    {
        $e = Enrollment::factory()->expiresAt(now()->addDays(10))->create();

        $this->actingAs($this->admin())->getJson(route('cms.admin.enrollments.show', $e))
            ->assertOk()
            ->assertJsonPath('data.has_access', true)
            ->assertJsonPath('data.user.email', $e->user->email)
            ->assertJsonStructure(['data' => ['days_left', 'enrolled_at', 'expires_at', 'course']]);
    }

    public function test_instructor_can_view_own_but_not_foreign_enrollment_and_cannot_edit(): void
    {
        $me      = $this->teacher();
        $own     = Enrollment::factory()->create(['course_id' => $this->published(['instructor_id' => $me->id])->id]);
        $foreign = Enrollment::factory()->create();

        $this->actingAs($me);
        $this->getJson(route('cms.admin.enrollments.show', $own))->assertOk();
        $this->getJson(route('cms.admin.enrollments.show', $foreign))->assertForbidden();
        $this->putJson(route('cms.admin.enrollments.update', $own), ['status' => 'cancelled'])->assertForbidden();
    }

    public function test_edit_returns_statuses(): void
    {
        $e = Enrollment::factory()->create();

        $this->actingAs($this->admin())->getJson(route('cms.admin.enrollments.edit', $e))
            ->assertOk()->assertJsonPath('data.id', $e->id)->assertJsonCount(4, 'statuses');
    }

    public function test_update_changes_status_and_expiry_only(): void
    {
        $e     = Enrollment::factory()->create();
        $other = User::factory()->create();

        $this->actingAs($this->admin())->putJson(route('cms.admin.enrollments.update', $e), [
            'status' => 'cancelled',
            'expires_at' => null,
            'user_id' => $other->id,
            'course_id' => 999,
        ])->assertOk();

        $e->refresh();
        $this->assertSame('cancelled', $e->status);
        $this->assertNotSame($other->id, $e->user_id); // الهوية لا تتغير
        $this->assertNotSame(999, $e->course_id);
    }

    public function test_update_can_reactivate_with_future_expiry(): void
    {
        $e = Enrollment::factory()->status('expired')->expiresAt(now()->subDay())->create();

        $this->actingAs($this->admin())->putJson(route('cms.admin.enrollments.update', $e), [
            'status' => 'active',
            'expires_at' => now()->addMonth()->format('Y-m-d H:i:s'),
        ])->assertOk();

        $this->assertTrue($e->fresh()->grantsAccess());
    }

    public function test_update_rejects_active_with_past_expiry_but_allows_cancelled(): void
    {
        $e    = Enrollment::factory()->create();
        $past = now()->subDay()->format('Y-m-d H:i:s');
        $admin = $this->admin();

        $this->actingAs($admin)->putJson(route('cms.admin.enrollments.update', $e), ['status' => 'active', 'expires_at' => $past])
            ->assertUnprocessable()->assertJsonValidationErrors('expires_at');

        $this->putJson(route('cms.admin.enrollments.update', $e), ['status' => 'cancelled', 'expires_at' => $past])->assertOk();
    }

    public function test_update_rejects_invalid_status(): void
    {
        $e = Enrollment::factory()->create();

        $this->actingAs($this->admin())->putJson(route('cms.admin.enrollments.update', $e), ['status' => 'banned'])
            ->assertUnprocessable()->assertJsonValidationErrors('status');
    }

    /* ---------- منطق الوصول والأمر المجدول ---------- */

    public function test_access_depends_on_status_and_expiry(): void
    {
        $this->assertTrue((new Enrollment(['status' => 'active', 'expires_at' => null]))->grantsAccess());
        $this->assertTrue((new Enrollment(['status' => 'active', 'expires_at' => now()->addDay()]))->grantsAccess());
        $this->assertTrue((new Enrollment(['status' => 'completed', 'expires_at' => null]))->grantsAccess());
        $this->assertFalse((new Enrollment(['status' => 'active', 'expires_at' => now()->subMinute()]))->grantsAccess());
        $this->assertFalse((new Enrollment(['status' => 'expired', 'expires_at' => null]))->grantsAccess());
        $this->assertFalse((new Enrollment(['status' => 'cancelled', 'expires_at' => null]))->grantsAccess());
    }

    public function test_granting_access_scope(): void
    {
        $ok1 = Enrollment::factory()->create();
        $ok2 = Enrollment::factory()->status('completed')->expiresAt(now()->addDay())->create();
        Enrollment::factory()->status('cancelled')->create();
        Enrollment::factory()->expiresAt(now()->subDay())->create();

        $ids = Enrollment::grantingAccess()->pluck('id')->all();

        $this->assertEqualsCanonicalizing([$ok1->id, $ok2->id], $ids);
    }

    public function test_expire_command_only_touches_overdue_active_enrollments(): void
    {
        $overdue   = Enrollment::factory()->expiresAt(now()->subHour())->create();
        $future    = Enrollment::factory()->expiresAt(now()->addDay())->create();
        $lifetime  = Enrollment::factory()->create();
        $completed = Enrollment::factory()->status('completed')->expiresAt(now()->subDay())->create();

        $this->artisan('enrollments:expire')->assertSuccessful();

        $this->assertSame('expired', $overdue->fresh()->status);
        $this->assertSame('active', $future->fresh()->status);
        $this->assertSame('active', $lifetime->fresh()->status);
        $this->assertSame('completed', $completed->fresh()->status);
    }

    /* ---------- النزاهة التاريخية ---------- */

    public function test_there_is_no_delete_route(): void
    {
        $this->assertFalse(Route::has('cms.admin.enrollments.destroy'));
    }

    public function test_user_with_enrollments_cannot_be_force_deleted_from_database(): void
    {
        $e = Enrollment::factory()->create();

        $this->expectException(QueryException::class);
        $e->user->forceDelete();
    }

    public function test_relations_resolve_even_when_user_or_course_is_soft_deleted(): void
    {
        $e = Enrollment::factory()->create();
        $e->user->delete();
        $e->course->delete();

        $fresh = Enrollment::find($e->id);
        $this->assertTrue($fresh->user->trashed());
        $this->assertTrue($fresh->course->trashed());
    }
}
