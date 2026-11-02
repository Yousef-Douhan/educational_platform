<?php

namespace App\Services;

use App\Exceptions\CertificateException;
use App\Models\Certificate;
use App\Models\CertificateTemplate;
use App\Models\Course;
use App\Models\Enrollment;
use App\Models\User;
use Illuminate\Database\UniqueConstraintViolationException;
use RuntimeException;

class CertificateService
{
    public function __construct(private readonly CertificatePdfGenerator $pdf) {}

    /** يُستدعى من الإدارة الآن، ومن إتمام الدورة تلقائيًا لاحقًا */
    public function issue(User $student, Course $course, ?CertificateTemplate $template = null, ?User $by = null): Certificate
    {
        if ($this->existing($student, $course)) {
            throw CertificateException::alreadyIssued();
        }

        $eligible = Enrollment::where(['user_id' => $student->id, 'course_id' => $course->id, 'status' => 'completed'])->exists();

        if (! $eligible) {
            throw CertificateException::notEligible();
        }

        $template ??= CertificateTemplate::where('is_default', true)->first();

        for ($attempt = 0; $attempt < 5; $attempt++) {
            try {
                return Certificate::create([
                    'user_id'          => $student->id,
                    'course_id'        => $course->id,
                    'template_id'      => $template?->id,
                    'certificate_code' => Certificate::generateCode(),
                    'student_name'     => $student->name,
                    'course_title_ar'  => $course->title_ar,
                    'course_title_en'  => $course->title_en,
                    'issued_at'        => now(),
                    'issued_by'        => $by?->id,
                ]);
            } catch (UniqueConstraintViolationException) {
                // سباق على (الطالب، الدورة) أم تصادم رمز؟
                if ($this->existing($student, $course)) {
                    throw CertificateException::alreadyIssued();
                }
            }
        }

        throw new RuntimeException('Could not generate a unique certificate code.');
    }

    public function revoke(Certificate $certificate, string $reason): Certificate
    {
        if ($certificate->is_revoked) {
            throw CertificateException::alreadyRevoked();
        }

        $certificate->update(['revoked_at' => now(), 'revoked_reason' => $reason]);
        $this->pdf->forget($certificate);

        return $certificate;
    }

    public function reinstate(Certificate $certificate): Certificate
    {
        if (! $certificate->is_revoked) {
            throw CertificateException::notRevoked();
        }

        $certificate->update(['revoked_at' => null, 'revoked_reason' => null]);
        $this->pdf->forget($certificate);

        return $certificate;
    }

    private function existing(User $student, Course $course): bool
    {
        return Certificate::where(['user_id' => $student->id, 'course_id' => $course->id])->exists();
    }
}
