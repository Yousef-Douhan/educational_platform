<?php

namespace Tests\Feature;

use App\Models\Certificate;
use App\Models\CertificateTemplate;
use App\Models\Course;
use App\Models\Enrollment;
use App\Models\User;
use App\Services\CertificateService;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class CertificateModuleTest extends TestCase
{
    use RefreshDatabase;

    private const JSON = ['Accept' => 'application/json'];

    protected function setUp(): void
    {
        parent::setUp();
        app(PermissionRegistrar::class)->forgetCachedPermissions();
        Storage::fake('public');
        Storage::fake('local');

        foreach (['view', 'create', 'edit', 'delete'] as $a) {
            Permission::findOrCreate("courses.{$a}", 'web');
            Permission::findOrCreate("certificate-templates.{$a}", 'web');
        }
        foreach (['view', 'create', 'edit'] as $a) {
            Permission::findOrCreate("certificates.{$a}", 'web');
        }
        foreach (['courses.manage-all', 'courses.publish'] as $n) {
            Permission::findOrCreate($n, 'web');
        }

        Role::findOrCreate('instructor', 'web')->givePermissionTo([
            'courses.view',
            'courses.create',
            'courses.edit',
            'courses.delete',
            'certificates.view',
        ]);
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

    private function completed(?Course $course = null): Enrollment
    {
        return Enrollment::factory()->status('completed')->create(array_filter(['course_id' => $course?->id]));
    }

    private function issue(Enrollment $e, ?User $by = null): Certificate
    {
        return app(CertificateService::class)->issue($e->user, $e->course, null, $by);
    }

    private function upload(array $o = [])
    {
        return $this->post(route('cms.admin.certificate-templates.store'), array_merge([
            'name' => 'Classic',
            'design' => UploadedFile::fake()->image('bg.png', 1400, 990),
        ], $o), self::JSON);
    }

    /* ---------- قوالب الشهادات ---------- */

    public function test_templates_index_json_returns_real_stats(): void
    {
        $used = CertificateTemplate::factory()->create();
        CertificateTemplate::factory()->create();
        CertificateTemplate::factory()->create()->delete();
        Certificate::factory()->create(['template_id' => $used->id]);

        $this->actingAs($this->admin())->getJson(route('cms.admin.certificate-templates.index'))
            ->assertOk()
            ->assertJsonPath('stats.total', 2)
            ->assertJsonPath('stats.used', 1)
            ->assertJsonPath('stats.unused', 1)
            ->assertJsonPath('stats.trashed', 1);
    }

    public function test_template_routes_are_forbidden_for_instructors(): void
    {
        $this->actingAs($this->teacher())->getJson(route('cms.admin.certificate-templates.index'))->assertForbidden();
    }

    public function test_store_template_saves_file_and_first_template_becomes_default(): void
    {
        $id = $this->actingAs($this->admin())->upload()->assertCreated()->assertJsonPath('data.is_default', true)->json('data.id');

        Storage::disk('public')->assertExists(CertificateTemplate::find($id)->design_path);
    }

    public function test_store_template_validates_name_and_design(): void
    {
        $this->actingAs($this->admin());

        $this->postJson(route('cms.admin.certificate-templates.store'), [])
            ->assertUnprocessable()->assertJsonValidationErrors(['name', 'design']);

        $this->upload(['design' => UploadedFile::fake()->create('x.pdf', 10, 'application/pdf')])
            ->assertUnprocessable()->assertJsonValidationErrors('design');
        $this->upload(['design' => UploadedFile::fake()->image('portrait.png', 700, 1400)])
            ->assertUnprocessable()->assertJsonValidationErrors('design');
        $this->upload(['design' => UploadedFile::fake()->image('small.png', 800, 500)])
            ->assertUnprocessable()->assertJsonValidationErrors('design');
    }

    public function test_only_one_template_is_default(): void
    {
        $this->actingAs($this->admin());

        $a = $this->upload(['name' => 'A'])->json('data.id');
        $plain = $this->upload(['name' => 'Plain'])->json('data.id');
        $b = $this->upload(['name' => 'B', 'is_default' => true])->json('data.id');

        $this->assertFalse(CertificateTemplate::find($a)->is_default);
        $this->assertFalse(CertificateTemplate::find($plain)->is_default);
        $this->assertTrue(CertificateTemplate::find($b)->is_default);
        $this->assertSame(1, CertificateTemplate::where('is_default', true)->count());
    }

    public function test_update_template_replaces_image_and_can_switch_default(): void
    {
        $this->actingAs($this->admin());
        $first  = $this->upload(['name' => 'First'])->json('data.id');
        $second = $this->upload(['name' => 'Second'])->json('data.id');
        $old    = CertificateTemplate::find($second)->design_path;

        $this->post(route('cms.admin.certificate-templates.update', $second), [
            '_method' => 'PUT',
            'name' => 'Renamed',
            'is_default' => true,
            'design' => UploadedFile::fake()->image('new.png', 1400, 990),
        ], self::JSON)->assertOk();

        $second = CertificateTemplate::find($second);
        $this->assertSame('Renamed', $second->name);
        $this->assertTrue($second->is_default);
        $this->assertFalse(CertificateTemplate::find($first)->is_default);
        Storage::disk('public')->assertMissing($old);
        Storage::disk('public')->assertExists($second->design_path);
    }

    public function test_default_template_cannot_be_deleted_but_others_are_soft_deleted(): void
    {
        $this->actingAs($this->admin());
        $default = $this->upload(['name' => 'Default'])->json('data.id');
        $other   = $this->upload(['name' => 'Other'])->json('data.id');

        $this->deleteJson(route('cms.admin.certificate-templates.destroy', $default))->assertStatus(409);
        $this->deleteJson(route('cms.admin.certificate-templates.destroy', $other))->assertOk();
        $this->assertSoftDeleted('certificate_templates', ['id' => $other]);
    }

    public function test_template_trash_restore_and_force_delete(): void
    {
        $this->actingAs($this->admin());
        $this->upload(['name' => 'Keep']);
        $id   = $this->upload(['name' => 'GoneTemplateQ'])->json('data.id');
        $path = CertificateTemplate::find($id)->design_path;

        $this->deleteJson(route('cms.admin.certificate-templates.destroy', $id))->assertOk();
        $html = $this->getJson(route('cms.admin.certificate-templates.trashed'))->assertOk()->json('html');
        $this->assertStringContainsString('GoneTemplateQ', $html);

        $this->patchJson(route('cms.admin.certificate-templates.restore', $id))->assertOk();
        $this->assertNotSoftDeleted('certificate_templates', ['id' => $id]);

        CertificateTemplate::find($id)->delete();
        $this->deleteJson(route('cms.admin.certificate-templates.force-delete', $id))->assertOk();
        $this->assertDatabaseMissing('certificate_templates', ['id' => $id]);
        Storage::disk('public')->assertMissing($path);
    }

    public function test_template_force_delete_is_blocked_while_certificates_use_it(): void
    {
        $template = CertificateTemplate::factory()->create();
        Certificate::factory()->create(['template_id' => $template->id]);
        $template->delete();

        $this->actingAs($this->admin())
            ->deleteJson(route('cms.admin.certificate-templates.force-delete', $template->id))->assertStatus(409);
        $this->assertDatabaseHas('certificate_templates', ['id' => $template->id]);
    }

    /* ---------- الاستحقاق والإصدار ---------- */

    public function test_create_lists_courses_with_eligible_students_and_templates(): void
    {
        $withStudent = $this->completed()->course;
        Enrollment::factory()->create();                                  // نشط: غير مستحق
        $issued = $this->completed();
        $this->issue($issued);                                            // صدرت شهادته
        CertificateTemplate::factory()->default()->create(['name' => 'Gold']);

        $res = $this->actingAs($this->admin())->getJson(route('cms.admin.certificates.create'))->assertOk();

        $courseIds = collect($res->json('options.courses'))->pluck('id');
        $this->assertTrue($courseIds->contains($withStudent->id));
        $this->assertFalse($courseIds->contains($issued->course_id));
        $res->assertJsonPath('options.templates.0.name', 'Gold');
    }

    public function test_eligible_lists_only_completed_enrollments_without_certificate(): void
    {
        $course = Course::factory()->published()->create();
        $ok     = $this->completed($course);
        $ok->user->update(['name' => 'Zebra Graduate']);
        $this->completed($course)->user->update(['name' => 'Quokka Graduate']);
        Enrollment::factory()->create(['course_id' => $course->id]);       // نشط
        $this->issue($this->completed($course));                           // لديه شهادة

        $admin = $this->admin();
        $all   = $this->actingAs($admin)->getJson(route('cms.admin.certificates.eligible', ['course_id' => $course->id]))->assertOk();
        $this->assertCount(2, $all->json('data'));

        $names = collect($this->getJson(route('cms.admin.certificates.eligible', ['course_id' => $course->id, 'search' => 'Zebra']))->json('data'))->pluck('name');
        $this->assertEquals(['Zebra Graduate'], $names->all());

        $this->getJson(route('cms.admin.certificates.eligible'))->assertUnprocessable()->assertJsonValidationErrors('course_id');
    }

    public function test_issue_creates_certificate_with_snapshot_code_and_default_template(): void
    {
        $default = CertificateTemplate::factory()->default()->create();
        $e       = $this->completed();
        $admin   = $this->admin();

        $res = $this->actingAs($admin)->postJson(route('cms.admin.certificates.store'), [
            'user_id' => $e->user_id,
            'course_id' => $e->course_id,
        ])->assertCreated()->assertJsonPath('data.status', 'valid');

        $cert = Certificate::first();
        $this->assertMatchesRegularExpression('/^CERT-[A-HJ-NP-Z2-9]{4}-[A-HJ-NP-Z2-9]{4}-[A-HJ-NP-Z2-9]{4}$/', $cert->certificate_code);
        $this->assertSame($e->user->name, $cert->student_name);
        $this->assertSame($e->course->title_ar, $cert->course_title_ar);
        $this->assertSame($e->course->title_en, $cert->course_title_en);
        $this->assertSame($default->id, $cert->template_id);
        $this->assertSame($admin->id, $cert->issued_by);
        $res->assertJsonPath('data.verification_url', route('certificates.verify', ['code' => $cert->certificate_code]));
    }

    public function test_issue_can_use_an_explicit_template_or_none_at_all(): void
    {
        $chosen = CertificateTemplate::factory()->create();
        $e1 = $this->completed();
        $e2 = $this->completed();
        $this->actingAs($this->admin());

        $this->postJson(route('cms.admin.certificates.store'), ['user_id' => $e1->user_id, 'course_id' => $e1->course_id, 'template_id' => $chosen->id])->assertCreated();
        $this->postJson(route('cms.admin.certificates.store'), ['user_id' => $e2->user_id, 'course_id' => $e2->course_id])->assertCreated();

        $this->assertSame($chosen->id, Certificate::where('user_id', $e1->user_id)->value('template_id'));
        $this->assertNull(Certificate::where('user_id', $e2->user_id)->value('template_id'));
    }

    public function test_issue_requires_a_completed_enrollment(): void
    {
        $active    = Enrollment::factory()->create();
        $cancelled = Enrollment::factory()->status('cancelled')->create();
        $none      = ['user_id' => User::factory()->create()->id, 'course_id' => Course::factory()->published()->create()->id];
        $this->actingAs($this->admin());

        foreach (
            [
                ['user_id' => $active->user_id, 'course_id' => $active->course_id],
                ['user_id' => $cancelled->user_id, 'course_id' => $cancelled->course_id],
                $none
            ] as $payload
        ) {
            $this->postJson(route('cms.admin.certificates.store'), $payload)
                ->assertUnprocessable()->assertJsonValidationErrors('user_id');
        }

        $this->assertSame(0, Certificate::count());
    }

    public function test_issue_rejects_duplicates_and_unavailable_records(): void
    {
        $e = $this->completed();
        $this->issue($e);
        $admin = $this->admin();

        $this->actingAs($admin)->postJson(route('cms.admin.certificates.store'), ['user_id' => $e->user_id, 'course_id' => $e->course_id])
            ->assertUnprocessable()->assertJsonValidationErrors('user_id');
        $this->assertSame(1, Certificate::count());

        $trashedUser = $this->completed();
        $trashedUser->user->delete();
        $this->postJson(route('cms.admin.certificates.store'), ['user_id' => $trashedUser->user_id, 'course_id' => $trashedUser->course_id])
            ->assertUnprocessable()->assertJsonValidationErrors('user_id');

        $trashedCourse = $this->completed();
        $trashedCourse->course->delete();
        $this->postJson(route('cms.admin.certificates.store'), ['user_id' => $trashedCourse->user_id, 'course_id' => $trashedCourse->course_id])
            ->assertUnprocessable()->assertJsonValidationErrors('course_id');

        $gone = CertificateTemplate::factory()->create();
        $gone->delete();
        $fresh = $this->completed();
        $this->postJson(route('cms.admin.certificates.store'), ['user_id' => $fresh->user_id, 'course_id' => $fresh->course_id, 'template_id' => $gone->id])
            ->assertUnprocessable()->assertJsonValidationErrors('template_id');
    }

    public function test_instructors_cannot_issue_or_lookup_eligible_students(): void
    {
        $e = $this->completed();
        $this->actingAs($this->teacher());

        $this->postJson(route('cms.admin.certificates.store'), ['user_id' => $e->user_id, 'course_id' => $e->course_id])->assertForbidden();
        $this->getJson(route('cms.admin.certificates.eligible', ['course_id' => $e->course_id]))->assertForbidden();
    }

    public function test_snapshot_survives_renaming_the_user_and_the_course(): void
    {
        $e    = $this->completed();
        $cert = $this->issue($e);
        $name = $e->user->name;
        $title = $e->course->title_en;

        $e->user->update(['name' => 'Someone Else']);
        $e->course->update(['title_en' => 'Totally New Title']);

        $cert->refresh();
        $this->assertSame($name, $cert->student_name);
        $this->assertSame($title, $cert->course_title_en);
    }

    public function test_database_enforces_one_certificate_per_student_and_course_and_unique_codes(): void
    {
        $e = $this->completed();
        $this->issue($e);

        $this->expectException(QueryException::class);
        Certificate::factory()->create(['user_id' => $e->user_id, 'course_id' => $e->course_id]);
    }

    public function test_generated_codes_are_unique(): void
    {
        $codes = collect(range(1, 30))->map(fn() => Certificate::generateCode());

        $this->assertSame(30, $codes->unique()->count());
    }

    public function test_user_with_certificates_cannot_be_force_deleted_from_database(): void
    {
        $cert = Certificate::factory()->create();

        $this->expectException(QueryException::class);
        $cert->user->forceDelete();
    }

    public function test_there_is_no_certificate_delete_route(): void
    {
        $this->assertFalse(Route::has('cms.admin.certificates.destroy'));
    }

    /* ---------- القائمة والعرض ---------- */

    public function test_index_json_returns_real_stats_and_filters(): void
    {
        $a = Certificate::factory()->create();
        Certificate::factory()->revoked()->create();
        $admin = $this->admin();

        $this->actingAs($admin)->getJson(route('cms.admin.certificates.index'))
            ->assertOk()
            ->assertJsonPath('stats.total', 2)
            ->assertJsonPath('stats.valid', 1)
            ->assertJsonPath('stats.revoked', 1)
            ->assertJsonPath('stats.this_month', 2);

        $valid = $this->getJson(route('cms.admin.certificates.index', ['status' => 'valid']))->json('html');
        $this->assertStringContainsString($a->certificate_code, $valid);

        $byCode = $this->getJson(route('cms.admin.certificates.index', ['search' => $a->certificate_code]))->json('html');
        $this->assertStringContainsString($a->certificate_code, $byCode);

        $this->getJson(route('cms.admin.certificates.index', ['sort' => 'id; drop table certificates']))->assertOk();
    }

    public function test_instructor_sees_only_certificates_of_own_and_shared_courses(): void
    {
        $me      = $this->teacher();
        $own     = Certificate::factory()->create(['course_id' => Course::factory()->published()->create(['instructor_id' => $me->id])->id]);
        $shared  = Course::factory()->published()->create();
        $shared->coInstructors()->attach($me->id);
        $sharedC = Certificate::factory()->create(['course_id' => $shared->id]);
        $foreign = Certificate::factory()->create();

        $html = $this->actingAs($me)->getJson(route('cms.admin.certificates.index'))->assertOk()->json('html');

        $this->assertStringContainsString($own->certificate_code, $html);
        $this->assertStringContainsString($sharedC->certificate_code, $html);
        $this->assertStringNotContainsString($foreign->certificate_code, $html);

        $this->getJson(route('cms.admin.certificates.show', $foreign))->assertForbidden();
        $this->getJson(route('cms.admin.certificates.show', $own))->assertOk();
    }

    public function test_show_returns_full_details(): void
    {
        $admin = $this->admin();
        $cert  = $this->issue($this->completed(), $admin);

        $this->actingAs($admin)->getJson(route('cms.admin.certificates.show', $cert))
            ->assertOk()
            ->assertJsonPath('data.certificate_code', $cert->certificate_code)
            ->assertJsonPath('data.issued_by', $admin->name)
            ->assertJsonPath('data.status', 'valid')
            ->assertJsonPath('data.student.name', $cert->student_name);
    }

    /* ---------- الإلغاء وإعادة التفعيل ---------- */

    public function test_revoke_requires_a_reason_and_clears_the_cached_pdf(): void
    {
        $cert = $this->issue($this->completed());
        Storage::disk('local')->put('certificates/cached.pdf', 'x');
        $cert->update(['pdf_url' => 'certificates/cached.pdf']);
        $admin = $this->admin();
        $url   = route('cms.admin.certificates.revoke', $cert);

        $this->actingAs($admin)->postJson($url, [])->assertUnprocessable()->assertJsonValidationErrors('reason');

        $this->postJson($url, ['reason' => 'Issued by mistake'])
            ->assertOk()->assertJsonPath('data.status', 'revoked')->assertJsonPath('stats.revoked', 1);

        $cert->refresh();
        $this->assertTrue($cert->is_revoked);
        $this->assertSame('Issued by mistake', $cert->revoked_reason);
        $this->assertNull($cert->pdf_url);
        Storage::disk('local')->assertMissing('certificates/cached.pdf');

        $this->postJson($url, ['reason' => 'again'])->assertStatus(409);
    }

    public function test_reinstate_restores_validity(): void
    {
        $cert  = Certificate::factory()->revoked()->create();
        $admin = $this->admin();

        $this->actingAs($admin)->postJson(route('cms.admin.certificates.reinstate', $cert))
            ->assertOk()->assertJsonPath('data.status', 'valid');

        $cert->refresh();
        $this->assertFalse($cert->is_revoked);
        $this->assertNull($cert->revoked_reason);

        $this->postJson(route('cms.admin.certificates.reinstate', $cert))->assertStatus(409);
    }

    public function test_instructors_cannot_revoke_or_reinstate(): void
    {
        $me   = $this->teacher();
        $cert = Certificate::factory()->create(['course_id' => Course::factory()->published()->create(['instructor_id' => $me->id])->id]);

        $this->actingAs($me);
        $this->postJson(route('cms.admin.certificates.revoke', $cert), ['reason' => 'x'])->assertForbidden();
        $this->postJson(route('cms.admin.certificates.reinstate', $cert))->assertForbidden();
    }

    /* ---------- صفحة التحقق العامة ---------- */

    public function test_public_verification_shows_a_valid_certificate_without_private_data(): void
    {
        $this->withoutVite();
        $cert = $this->issue($this->completed());

        $this->get(route('certificates.verify', ['code' => $cert->certificate_code]))
            ->assertOk()
            ->assertSee('data-state="valid"', false)
            ->assertSee($cert->student_name)
            ->assertSee($cert->course_title_ar)
            ->assertDontSee($cert->user->email)
            ->assertHeader('X-Robots-Tag', 'noindex, nofollow');
    }

    public function test_public_verification_normalizes_the_code_case(): void
    {
        $this->withoutVite();
        $cert = Certificate::factory()->create();

        $this->get(route('certificates.verify', ['code' => strtolower($cert->certificate_code)]))
            ->assertOk()->assertSee('data-state="valid"', false);
    }

    public function test_public_verification_reports_revoked_and_unknown_codes(): void
    {
        $this->withoutVite();
        $revoked = Certificate::factory()->revoked('Secret internal reason')->create();

        $this->get(route('certificates.verify', ['code' => $revoked->certificate_code]))
            ->assertOk()->assertSee('data-state="revoked"', false)->assertDontSee('Secret internal reason');

        $this->get(route('certificates.verify', ['code' => 'CERT-AAAA-BBBB-CCCC']))
            ->assertNotFound()->assertSee('data-state="notfound"', false);
    }

    public function test_verification_form_and_redirect(): void
    {
        $this->withoutVite();

        $this->get(route('certificates.verify.form'))->assertOk()->assertSee('data-state="form"', false);
        $this->get(route('certificates.verify.form', ['code' => ' cert-aaaa ']))
            ->assertRedirect(route('certificates.verify', ['code' => 'CERT-AAAA']));
    }

    public function test_verification_is_rate_limited(): void
    {
        $this->withoutVite();

        for ($i = 0; $i < 30; $i++) {
            $this->get(route('certificates.verify', ['code' => 'CERT-AAAA-BBBB-CCCC']))->assertNotFound();
        }

        $this->get(route('certificates.verify', ['code' => 'CERT-AAAA-BBBB-CCCC']))->assertStatus(429);
    }

    /* ---------- ملف PDF (يحتاج mpdf/mpdf) ---------- */

    public function test_download_returns_a_pdf_and_caches_it_on_the_private_disk(): void
    {
        if (! class_exists(\Mpdf\Mpdf::class)) {
            $this->markTestSkipped('mpdf/mpdf is not installed.');
        }

        $cert  = $this->issue($this->completed());
        $admin = $this->admin();

        $res = $this->actingAs($admin)->get(route('cms.admin.certificates.download', $cert))->assertOk()->assertDownload("certificate-{$cert->certificate_code}.pdf");

        $this->assertStringStartsWith('%PDF', $res->streamedContent());
        $cert->refresh();
        $this->assertSame("certificates/{$cert->certificate_code}.pdf", $cert->pdf_url);
        Storage::disk('local')->assertExists($cert->pdf_url);
        Storage::disk('public')->assertMissing($cert->pdf_url);
    }

    public function test_revoked_certificates_download_with_watermark_and_are_not_cached(): void
    {
        if (! class_exists(\Mpdf\Mpdf::class)) {
            $this->markTestSkipped('mpdf/mpdf is not installed.');
        }

        $cert = Certificate::factory()->revoked()->create();

        $res = $this->actingAs($this->admin())->get(route('cms.admin.certificates.download', $cert))->assertOk();

        $this->assertStringStartsWith('%PDF', $res->streamedContent());
        $this->assertNull($cert->fresh()->pdf_url);
    }

    public function test_instructors_cannot_download_foreign_certificates(): void
    {
        $foreign = Certificate::factory()->create();

        $this->actingAs($this->teacher())->get(route('cms.admin.certificates.download', $foreign))->assertForbidden();
    }

    public function test_templates_index_page_renders(): void
    {
        $this->actingAs($this->admin())->get(route('cms.admin.certificate-templates.index'))
            ->assertOk()->assertViewIs('cms.certificate-templates.index');
    }

    public function test_certificates_index_page_renders(): void
    {
        $this->actingAs($this->admin())->get(route('cms.admin.certificates.index'))
            ->assertOk()->assertViewIs('cms.certificates.index');
    }

    public function test_template_layout_is_saved_and_cleaned(): void
    {
        $this->actingAs($this->admin());

        $id = $this->upload(['name' => 'Laid', 'layout' => [
            'area_left' => '25',
            'area_width' => '185',
            'middle_top' => '84',
            'hide_header' => '1',
            'text_color' => '#112233',
        ]])->assertCreated()->json('data.id');

        $layout = CertificateTemplate::find($id)->layout;
        $this->assertEquals(25, $layout['area_left']);
        $this->assertEquals(185, $layout['area_width']);
        $this->assertTrue($layout['hide_header']);
        $this->assertFalse($layout['hide_qr']);
        $this->assertSame('#112233', $layout['text_color']);
        $this->assertEquals(84, CertificateTemplate::find($id)->resolvedLayout()['middle_top']);
        $this->assertEquals(142, CertificateTemplate::find($id)->resolvedLayout()['qr_top']);
    }

    public function test_template_layout_validation(): void
    {
        $this->actingAs($this->admin());

        $this->upload(['layout' => ['area_left' => '200', 'area_width' => '200']])
            ->assertUnprocessable()->assertJsonValidationErrors('layout.area_width');
        $this->upload(['layout' => ['area_width' => '50']])
            ->assertUnprocessable()->assertJsonValidationErrors('layout.area_width');
        $this->upload(['layout' => ['text_color' => 'red']])
            ->assertUnprocessable()->assertJsonValidationErrors('layout.text_color');
        $this->upload(['layout' => ['middle_top' => 'abc']])
            ->assertUnprocessable()->assertJsonValidationErrors('layout.middle_top');
    }

    public function test_updating_a_template_clears_cached_pdfs_of_its_certificates(): void
    {
        $template = CertificateTemplate::factory()->create();
        Storage::disk('local')->put('certificates/old.pdf', 'x');
        $cert = Certificate::factory()->create(['template_id' => $template->id, 'pdf_url' => 'certificates/old.pdf']);

        $this->actingAs($this->admin())->post(route('cms.admin.certificate-templates.update', $template), [
            '_method' => 'PUT',
            'name' => 'Renamed',
        ], self::JSON)->assertOk();

        $this->assertNull($cert->fresh()->pdf_url);
        Storage::disk('local')->assertMissing('certificates/old.pdf');
    }

    public function test_template_preview_returns_a_pdf(): void
    {
        if (! class_exists(\Mpdf\Mpdf::class)) {
            $this->markTestSkipped('mpdf/mpdf is not installed.');
        }

        $this->actingAs($this->admin());
        $id  = $this->upload(['name' => 'Preview me', 'layout' => ['hide_header' => '1', 'area_width' => '200']])->json('data.id');
        $res = $this->get(route('cms.admin.certificate-templates.preview', $id))->assertOk();

        $this->assertSame('application/pdf', $res->headers->get('Content-Type'));
        $this->assertStringStartsWith('%PDF', $res->getContent());
    }

    public function test_instructors_cannot_preview_templates(): void
    {
        $template = CertificateTemplate::factory()->create();

        $this->actingAs($this->teacher())->get(route('cms.admin.certificate-templates.preview', $template))->assertForbidden();
    }

    public function test_download_supports_both_languages_or_a_single_one(): void
    {
        if (! class_exists(\Mpdf\Mpdf::class)) {
            $this->markTestSkipped('mpdf/mpdf is not installed.');
        }

        $cert = $this->issue($this->completed());
        $code = $cert->certificate_code;
        $this->actingAs($this->admin());

        $both = $this->get(route('cms.admin.certificates.download', $cert))->assertOk()->assertDownload("certificate-{$code}.pdf");
        $ar   = $this->get(route('cms.admin.certificates.download', [$cert, 'lang' => 'ar']))->assertOk()->assertDownload("certificate-{$code}-ar.pdf");
        $en   = $this->get(route('cms.admin.certificates.download', [$cert, 'lang' => 'en']))->assertOk()->assertDownload("certificate-{$code}-en.pdf");

        $this->assertStringContainsString('/Count 2', $both->streamedContent());
        $this->assertStringContainsString('/Count 1', $ar->streamedContent());
        $this->assertStringContainsString('/Count 1', $en->streamedContent());

        // لغة غير معروفة تعود إلى الملف الكامل
        $this->get(route('cms.admin.certificates.download', [$cert, 'lang' => 'fr']))
            ->assertOk()->assertDownload("certificate-{$code}.pdf");
    }
}
