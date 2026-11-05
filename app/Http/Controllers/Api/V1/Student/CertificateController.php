<?php

namespace App\Http\Controllers\Api\V1\Student;

use App\Exceptions\CertificateException;
use App\Http\Controllers\Api\V1\Concerns\RespondsWithErrors;
use App\Http\Controllers\Controller;
use App\Models\Certificate;
use App\Models\Course;
use App\Models\Enrollment;
use App\Services\CertificatePdfGenerator;
use App\Services\CertificateService;
use App\Support\Loc;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

/** شهادات الطالب نفسه فقط. الاستحقاق يتحقق منه الخادم لا الطلب. */
class CertificateController extends Controller
{
    use RespondsWithErrors;

    public function index(Request $request): JsonResponse
    {
        $user         = $request->user();
        $certificates = Certificate::where('user_id', $user->id)->orderByDesc('issued_at')->get();

        $claimable = Enrollment::query()
            ->where(['user_id' => $user->id, 'status' => 'completed'])
            ->whereNotIn('course_id', $certificates->pluck('course_id'))
            ->whereHas('course')->with('course')->get();

        return response()->json(['data' => [
            'certificates' => $certificates->map(fn (Certificate $c) => [
                'id' => $c->id, 'code' => $c->certificate_code,
                'course_title' => Loc::pick($c->course_title_ar, $c->course_title_en),
                'issued_at' => $c->issued_at?->toIso8601String(),
                'revoked' => $c->revoked_at !== null,
                'download_url' => $c->revoked_at ? null : route('api.certificates.download', $c->id),
            ])->values(),
            'claimable' => $claimable->map(fn ($e) => [
                'course_slug' => $e->course->slug,
                'course_title' => Loc::pick($e->course->title_ar, $e->course->title_en),
            ])->values(),
        ]]);
    }

    public function claim(Request $request, CertificateService $certificates, string $slug): JsonResponse
    {
        $course = Course::where('slug', $slug)->firstOrFail();

        try {
            $cert = $certificates->issue($request->user(), $course);
        } catch (CertificateException $e) {
            return $this->fail($e);
        }

        return response()->json(['message' => __('student.certs.issued'), 'data' => ['id' => $cert->id, 'code' => $cert->certificate_code]], 201);
    }

    public function download(Request $request, CertificatePdfGenerator $pdf, int $certificate)
    {
        $cert = Certificate::where('user_id', $request->user()->id)->whereNull('revoked_at')->findOrFail($certificate);
        abort_unless($pdf->available(), 503, __('student.certs.pdf_unavailable'));

        $lang = in_array($request->query('lang'), ['ar', 'en'], true) ? $request->query('lang') : null;
        $name = "certificate-{$cert->certificate_code}".($lang ? "-{$lang}" : '').'.pdf';

        if ($lang) {
            $binary = $pdf->render($cert, $lang);

            return response()->streamDownload(function () use ($binary) {
                echo $binary;
            }, $name, ['Content-Type' => 'application/pdf']);
        }

        return Storage::disk('local')->download($pdf->path($cert), $name, ['Content-Type' => 'application/pdf']);
    }
}
