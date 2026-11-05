<?php

namespace Tests\Feature;

use App\Models\Certificate;
use App\Models\CertificateTemplate;
use App\Models\Enrollment;
use App\Models\Question;
use App\Models\Quiz;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Passport\Passport;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

/** API الإدارة — الدفعة 4: التسجيلات والشهادات والجلسات والاختبارات والتقدّم والإشعارات والتدقيق */
class ApiAdminOperationsTest extends TestCase
{
    use RefreshDatabase;

    private const BASE = '/api/v1/admin';

    protected function setUp(): void
    {
        parent::setUp();
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    private function superAdmin(): User
    {
        $this->seed(RolesAndPermissionsSeeder::class);
        $u = User::factory()->create();
        $u->assignRole(Role::findByName('super-admin', 'web'));
        Passport::actingAs($u, ['student', 'admin'], 'api');

        return $u;
    }

    private function staffWith(string ...$perms): User
    {
        $u = User::factory()->create();
        foreach ($perms as $p) {
            $u->givePermissionTo(Permission::findOrCreate($p, 'web'));
        }
        Passport::actingAs($u, ['student', 'admin'], 'api');

        return $u;
    }

    public function test_every_list_denies_staff_without_the_matching_permission(): void
    {
        $this->staffWith('categories.view');

        foreach ([
            'enrollments', 'certificates', 'certificate-templates', 'certificate-templates/trashed', 'live-sessions',
            'live-sessions/trashed', 'quizzes', 'quizzes/trashed', 'quiz-attempts', 'progress', 'notifications',
            'audit-logs',
        ] as $path) {
            $this->getJson(self::BASE.'/'.$path)->assertForbidden();
        }
    }

    public function test_student_scope_token_is_rejected(): void
    {
        Passport::actingAs(User::factory()->create(), ['student'], 'api');

        $this->getJson(self::BASE.'/dashboard')->assertForbidden();
        $this->getJson(self::BASE.'/enrollments')->assertForbidden();
    }

    public function test_all_lists_work_for_super_admin_and_ignore_hostile_params(): void
    {
        $this->superAdmin();

        foreach ([
            'enrollments', 'certificates', 'certificate-templates', 'certificate-templates/trashed', 'live-sessions',
            'live-sessions/trashed', 'quizzes', 'quizzes/trashed', 'quiz-attempts', 'progress', 'notifications', 'audit-logs',
        ] as $path) {
            $this->getJson(self::BASE.'/'.$path.'?sort=x;drop&dir=zz&per_page=100000&page=abc&course_id[]=1&status[]=a')
                ->assertOk()->assertJsonStructure(['data', 'meta' => ['current_page', 'last_page', 'per_page', 'total']])
                ->assertJsonPath('meta.per_page', 100);
        }
    }

    public function test_enrollments_list_filters_and_edit_flow(): void
    {
        $this->superAdmin();
        $e = Enrollment::factory()->create();
        Enrollment::factory()->status('cancelled')->create();

        $this->getJson(self::BASE.'/enrollments')->assertOk()->assertJsonPath('meta.total', 2)->assertJsonPath('stats.cancelled', 1);
        $this->getJson(self::BASE.'/enrollments?status=active')->assertOk()->assertJsonPath('meta.total', 1)
            ->assertJsonPath('data.0.id', $e->id);
        $this->getJson(self::BASE."/enrollments/{$e->id}")->assertOk();
        $this->getJson(self::BASE.'/enrollments/999999')->assertNotFound()->assertJsonPath('code', 'not_found');
    }

    public function test_enrollment_creation_needs_its_own_permission(): void
    {
        $this->staffWith('enrollments.view');
        $this->postJson(self::BASE.'/enrollments', [])->assertForbidden();
        $this->getJson(self::BASE.'/enrollments/lookup/users?q=a')->assertForbidden();
    }

    public function test_certificates_list_show_and_revoke_flow(): void
    {
        $this->superAdmin();
        $c = Certificate::factory()->create();
        Certificate::factory()->revoked()->create();

        $this->getJson(self::BASE.'/certificates')->assertOk()->assertJsonPath('meta.total', 2)->assertJsonPath('stats.revoked', 1);
        $this->getJson(self::BASE.'/certificates?status=valid')->assertOk()->assertJsonPath('meta.total', 1)
            ->assertJsonPath('data.0.is_valid', true);
        $this->getJson(self::BASE."/certificates/{$c->id}")->assertOk();

        $this->postJson(self::BASE."/certificates/{$c->id}/revoke", [])->assertUnprocessable();
        $this->postJson(self::BASE."/certificates/{$c->id}/revoke", ['reason' => 'issued by mistake'])->assertOk();
        $this->assertNotNull($c->fresh()->revoked_at);
        $this->postJson(self::BASE."/certificates/{$c->id}/reinstate")->assertOk();
        $this->assertNull($c->fresh()->revoked_at);
    }

    public function test_revoking_needs_edit_permission(): void
    {
        $c = Certificate::factory()->create();
        $this->staffWith('certificates.view');

        $this->postJson(self::BASE."/certificates/{$c->id}/revoke", ['reason' => 'because'])->assertForbidden();
        $this->postJson(self::BASE."/certificates/{$c->id}/reinstate")->assertForbidden();
    }

    public function test_certificate_templates_list_and_trash(): void
    {
        $this->superAdmin();
        $t = CertificateTemplate::factory()->create();
        CertificateTemplate::factory()->create()->delete();

        $this->getJson(self::BASE.'/certificate-templates')->assertOk()->assertJsonPath('meta.total', 1)
            ->assertJsonPath('data.0.id', $t->id)->assertJsonPath('stats.trashed', 1);
        $this->getJson(self::BASE.'/certificate-templates/trashed')->assertOk()->assertJsonPath('meta.total', 1);
    }

    public function test_quizzes_list_questions_and_hidden_state(): void
    {
        $this->superAdmin();
        $ready = Quiz::factory()->create();
        Question::factory()->create(['quiz_id' => $ready->id]);
        Quiz::factory()->create();

        $this->getJson(self::BASE.'/quizzes')->assertOk()->assertJsonPath('meta.total', 2);
        $this->getJson(self::BASE.'/quizzes?readiness=ready')->assertOk()->assertJsonPath('meta.total', 1)
            ->assertJsonPath('data.0.questions_count', 1);
        $this->getJson(self::BASE."/quizzes/{$ready->id}/questions")->assertOk();
        $this->getJson(self::BASE."/quizzes/{$ready->id}")->assertOk();
    }

    public function test_question_editing_needs_edit_permission(): void
    {
        $quiz = Quiz::factory()->create();
        $q = Question::factory()->create(['quiz_id' => $quiz->id]);
        $this->staffWith('quizzes.view');

        $this->deleteJson(self::BASE."/questions/{$q->id}")->assertForbidden();
        $this->postJson(self::BASE."/quizzes/{$quiz->id}/questions/reorder", ['ids' => [$q->id]])->assertForbidden();
    }

    public function test_dashboard_only_shows_sections_the_user_may_see(): void
    {
        $this->staffWith('orders.view');
        \App\Models\Order::factory()->paid()->create(['total_amount' => 40]);

        $res = $this->getJson(self::BASE.'/dashboard')->assertOk();
        $res->assertJsonPath('data.orders.paid', 1)->assertJsonPath('data.orders.revenue', '40.00');
        $this->assertArrayNotHasKey('users', $res->json('data'));
        $this->assertArrayNotHasKey('receipts', $res->json('data'));

        $this->superAdmin();
        $this->getJson(self::BASE.'/dashboard')->assertOk()->assertJsonStructure(['data' => ['courses', 'enrollments', 'users', 'orders', 'receipts']]);
    }

    public function test_notifications_and_audit_logs_need_their_permissions(): void
    {
        $this->staffWith('notifications.view');
        $this->getJson(self::BASE.'/notifications')->assertOk();
        $this->getJson(self::BASE.'/audit-logs')->assertForbidden();
    }
}
