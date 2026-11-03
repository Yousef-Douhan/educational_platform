<?php

namespace App\Http\Controllers\Student;

use App\Exceptions\CertificateException;
use App\Http\Controllers\Controller;
use App\Models\Certificate;
use App\Models\Course;
use App\Models\Enrollment;
use App\Services\CertificatePdfGenerator;
use App\Services\CertificateService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;

/**
 * شهادات الطالب نفسه فقط. الإصدار يتحقق منه الخادم (تسجيل مكتمل)، لا يأتي الاستحقاق من الطلب.
 */
class CertificateController extends Controller
{
    public function index(Request $request): View
    {
        $user = $request->user();

        $certificates = Certificate::where('user_id', $user->id)->orderByDesc('issued_at')->get();

        // دورات أكملها ولم يستلم شهادتها بعد
        $claimable = Enrollment::query()
            ->where(['user_id' => $user->id, 'status' => 'completed'])
            ->whereNotIn('course_id', $certificates->pluck('course_id'))
            ->whereHas('course')
            ->with('course')
            ->get();

        return view('student.certificates.index', ['certificates' => $certificates, 'claimable' => $claimable]);
    }

    public function claim(Request $request, CertificateService $certificates, string $slug): RedirectResponse
    {
        $course = Course::where('slug', $slug)->firstOrFail();

        try {
            $certificates->issue($request->user(), $course);
        } catch (CertificateException $e) {
            return redirect()->route('student.certificates.index')->withErrors(['certificate' => $e->getMessage()]);
        }

        return redirect()->route('student.certificates.index')->with('status', __('student.certs.issued'));
    }

    public function download(Request $request, CertificatePdfGenerator $pdf, int $certificate)
    {
        // شهادة الطالب نفسه فقط؛ غيرها أو الملغاة 404
        $cert = Certificate::where('user_id', $request->user()->id)->whereNull('revoked_at')->findOrFail($certificate);

        abort_unless($pdf->available(), 503, __('student.certs.pdf_unavailable'));

        $lang = in_array($request->query('lang'), ['ar', 'en'], true) ? $request->query('lang') : null;
        $name = "certificate-{$cert->certificate_code}" . ($lang ? "-{$lang}" : '') . '.pdf';

        if ($lang) {
            $binary = $pdf->render($cert, $lang);

            return response()->streamDownload(function () use ($binary) {
                echo $binary;
            }, $name, ['Content-Type' => 'application/pdf']);
        }

        return Storage::disk('local')->download($pdf->path($cert), $name, ['Content-Type' => 'application/pdf']);
    }
}
