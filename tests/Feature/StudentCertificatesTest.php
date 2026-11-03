<?php

namespace Tests\Feature;

use App\Models\Certificate;
use App\Models\Course;
use App\Models\Enrollment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class StudentCertificatesTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        app()->setLocale('ar');
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    private function completed(string $status = 'completed'): array
    {
        $course = Course::factory()->published()->create();
        $user   = User::factory()->create();
        Enrollment::factory()->create(['user_id' => $user->id, 'course_id' => $course->id, 'status' => $status]);

        return [$user, $course];
    }

    public function test_student_claims_a_certificate_for_a_completed_course(): void
    {
        [$user, $course] = $this->completed();

        $this->actingAs($user)->get(route('student.certificates.index'))
            ->assertOk()->assertSee(route('student.certificates.claim', $course->slug), false);

        $this->post(route('student.certificates.claim', $course->slug))
            ->assertRedirect(route('student.certificates.index'))->assertSessionHasNoErrors();

        $cert = Certificate::where(['user_id' => $user->id, 'course_id' => $course->id])->firstOrFail();
        $this->assertSame($user->name, $cert->student_name);
        $this->assertNull($cert->issued_by);

        $this->get(route('student.certificates.index'))->assertSee($cert->certificate_code)
            ->assertDontSee(route('student.certificates.claim', $course->slug), false);
    }

    public function test_claim_is_refused_when_not_completed_or_already_issued(): void
    {
        [$user, $course] = $this->completed('active');

        $this->actingAs($user)->post(route('student.certificates.claim', $course->slug))->assertSessionHasErrors('certificate');
        $this->assertSame(0, Certificate::count());

        Enrollment::where('user_id', $user->id)->update(['status' => 'completed']);
        $this->post(route('student.certificates.claim', $course->slug))->assertSessionHasNoErrors();
        $this->post(route('student.certificates.claim', $course->slug))->assertSessionHasErrors('certificate');
        $this->assertSame(1, Certificate::count());
    }

    public function test_claim_needs_login_and_own_enrollment(): void
    {
        [, $course] = $this->completed();

        $this->post(route('student.certificates.claim', $course->slug))->assertRedirect(route('login'));

        // طالب آخر لم يكمل الدورة لا يستطيع استلام شهادتها
        $this->actingAs(User::factory()->create())->post(route('student.certificates.claim', $course->slug))
            ->assertSessionHasErrors('certificate');
        $this->assertSame(0, Certificate::count());
    }

    public function test_index_lists_only_own_certificates(): void
    {
        $mine   = Certificate::factory()->create();
        $theirs = Certificate::factory()->create();

        $this->actingAs($mine->user)->get(route('student.certificates.index'))
            ->assertSee($mine->certificate_code)->assertDontSee($theirs->certificate_code);
    }

    public function test_download_is_limited_to_the_owner_and_valid_certificates(): void
    {
        $cert    = Certificate::factory()->create();
        $revoked = Certificate::factory()->revoked()->create(['user_id' => $cert->user_id]);

        $this->get(route('student.certificates.download', $cert->id))->assertRedirect(route('login'));

        $this->actingAs(User::factory()->create())->get(route('student.certificates.download', $cert->id))->assertNotFound();

        $this->actingAs($cert->user);
        $this->get(route('student.certificates.download', $revoked->id))->assertNotFound();

        // المالك: ملف PDF أو 503 إن لم يتوفر محرك PDF في البيئة، لكن ليس رفضًا
        $status = $this->get(route('student.certificates.download', $cert->id))->getStatusCode();
        $this->assertContains($status, [200, 503]);
    }

    public function test_revoked_certificate_shows_no_download_links(): void
    {
        $cert = Certificate::factory()->revoked()->create();

        $this->actingAs($cert->user)->get(route('student.certificates.index'))
            ->assertSee(__('student.certs.revoked'))
            ->assertDontSee(route('student.certificates.download', $cert->id), false);
    }

    public function test_verification_page_is_linked_for_everyone(): void
    {
        $this->get(route('student.home'))->assertSee(route('certificates.verify.form'), false);
    }
}
