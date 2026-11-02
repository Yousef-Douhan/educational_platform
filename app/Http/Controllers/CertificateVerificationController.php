<?php

namespace App\Http\Controllers;

use App\Models\Certificate;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class CertificateVerificationController extends Controller
{
    /** نموذج إدخال الرمز، أو تحويل إلى صفحة النتيجة إن أُرسل رمز */
    public function form(Request $request): Response|RedirectResponse
    {
        if ($request->filled('code')) {
            return redirect()->route('certificates.verify', ['code' => $this->normalize($request->string('code'))]);
        }

        return $this->page('form');
    }

    public function show(string $code): Response
    {
        $code        = $this->normalize($code);
        $certificate = Certificate::where('certificate_code', $code)->first();

        if (! $certificate) {
            return $this->page('notfound', ['code' => $code], 404);
        }

        return $this->page($certificate->is_revoked ? 'revoked' : 'valid', ['certificate' => $certificate, 'code' => $code]);
    }

    private function normalize(string $code): string
    {
        return strtoupper(trim($code));
    }

    /** لا نكشف إلا الاسم والدورة والتاريخ، ولا نسمح بفهرسة الصفحة */
    private function page(string $state, array $data = [], int $status = 200): Response
    {
        return response()
            ->view('certificates.verify', ['state' => $state, ...$data], $status)
            ->header('X-Robots-Tag', 'noindex, nofollow')
            ->header('Cache-Control', 'no-store');
    }
}
