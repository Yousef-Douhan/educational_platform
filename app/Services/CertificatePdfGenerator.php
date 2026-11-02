<?php

namespace App\Services;

use App\Models\Certificate;
use App\Models\CertificateTemplate;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use Mpdf\Config\ConfigVariables;
use Mpdf\Config\FontVariables;
use Mpdf\HTMLParserMode;
use Mpdf\Mpdf;
use Mpdf\MpdfException;
use Mpdf\Output\Destination;

class CertificatePdfGenerator
{
    public function __construct(private readonly CertificateBoxes $boxes) {}

    public function available(): bool
    {
        return class_exists(Mpdf::class);
    }

    /** $mode: both (عربي ثم إنجليزي) أو ar أو en */
    public function render(Certificate $certificate, string $mode = 'both'): string
    {
        $this->ensureMemory(512);

        $langs  = in_array($mode, ['ar', 'en'], true) ? [$mode] : ['ar', 'en'];
        $custom = $this->hasCustomFont();

        try {
            return $this->build($certificate, $langs, $custom);
        } catch (MpdfException $e) {
            // mPDF قد يرفض خطًا مخصصًا (مثل MarkGlyphSets): نعود للخط المضمّن بدل فشل التنزيل
            if (! $custom) {
                throw $e;
            }

            report($e);

            return $this->build($certificate, $langs, false);
        }
    }

    /** شهادة تجريبية بلا حفظ في القاعدة، لمعاينة التصميم والتخطيط */
    public function renderSample(CertificateTemplate $template, string $mode = 'both'): string
    {
        $sample = new Certificate([
            'certificate_code' => 'CERT-SAMP-LE00-0000',
            'student_name'     => __('certificates.pdf.sample_student'),
            'course_title_ar'  => 'اسم الدورة التدريبية',
            'course_title_en'  => 'Course title',
            'issued_at'        => now(),
        ]);
        $sample->setRelation('template', $template);

        return $this->render($sample, $mode);
    }

    /** مسار الـ PDF المخزّن (بكلتا اللغتين) على القرص الخاص، ويُولَّد عند الحاجة */
    public function path(Certificate $certificate, bool $force = false): string
    {
        $path = $certificate->pdf_url;

        if (! $force && $path && Storage::disk('local')->exists($path)) {
            return $path;
        }

        $path = "certificates/{$certificate->certificate_code}.pdf";
        Storage::disk('local')->put($path, $this->render($certificate));
        $certificate->forceFill(['pdf_url' => $path])->save();

        return $path;
    }

    public function forget(Certificate $certificate): void
    {
        if ($certificate->pdf_url) {
            Storage::disk('local')->delete($certificate->pdf_url);
        }

        $certificate->forceFill(['pdf_url' => null])->save();
    }

    /** تغيير القالب أو تخطيطه يبطل ملفات الـ PDF المخزّنة لشهاداته */
    public function forgetForTemplate(int $templateId): void
    {
        Certificate::where('template_id', $templateId)->whereNotNull('pdf_url')
            ->each(fn(Certificate $c) => $this->forget($c));
    }

    /* ---------- داخلي ---------- */

    private function build(Certificate $certificate, array $langs, bool $custom): string
    {
        $dir = storage_path('app/mpdf');
        File::ensureDirectoryExists($dir);

        $template   = $this->templateFor($certificate);
        $background = $this->backgroundDataUri($template);
        $layout     = $template?->resolvedLayout() ?? CertificateTemplate::LAYOUT_DEFAULTS;
        $verifyUrl  = route('certificates.verify', ['code' => $certificate->certificate_code]);

        // نسخة للعرض فقط (لا تُحفظ): مع الخط المخصص نحذف التشكيل من نصوص المستخدم
        $view = clone $certificate;
        if ($custom) {
            foreach (['student_name', 'course_title_ar', 'course_title_en'] as $field) {
                $view->setAttribute($field, $this->stripMarks((string) $certificate->{$field}));
            }
        }

        $mpdf = new Mpdf($this->config($dir, $custom));
        $mpdf->SetTitle($certificate->certificate_code);
        $mpdf->SetAuthor((string) config('app.name'));

        // صورة الخلفية عبر واجهة mPDF (A4 أفقي كامل) وخلف النص
        if ($background) {
            $mpdf->SetWatermarkImage($background, 1, [297, 210], [0, 0]);
            $mpdf->showWatermarkImage = true;
            $mpdf->watermarkImgBehind = true;
        }

        if ($certificate->is_revoked) {
            $mpdf->SetWatermarkText('REVOKED', 0.12);
            $mpdf->showWatermarkText = true;
        }

        $css = str_replace('__FONT__', $custom ? 'cairo' : 'dejavusans', File::get(resource_path('css/certificates/pdf.css')));
        $mpdf->WriteHTML($css, HTMLParserMode::HEADER_CSS);

        foreach ($langs as $lang) {
            $mpdf->AddPage();

            $boxes = $background
                ? $this->boxes->overlay($view, $lang, $verifyUrl, $layout)
                : $this->boxes->modern($view, $lang, $verifyUrl);

            // كل عنصر بإحداثيات ثابتة على الصفحة، بلا تدفق ولا فواصل صفحات
            foreach ($boxes as [$x, $y, $w, $h, $html]) {
                $mpdf->WriteFixedPosHTML($html, $x, $y, $w, $h, 'visible');
            }
        }

        return $mpdf->Output('', Destination::STRING_RETURN);
    }

    /** حذف علامات التشكيل العربية (الفتحة والضمة والشدة...) */
    private function stripMarks(string $text): string
    {
        return preg_replace('/[\x{064B}-\x{065F}\x{0670}\x{06D6}-\x{06ED}]/u', '', $text) ?? $text;
    }

    private function hasCustomFont(): bool
    {
        $dir = resource_path('fonts/certificates');

        return File::exists("{$dir}/Cairo-Regular.ttf") && File::exists("{$dir}/Cairo-Bold.ttf");
    }

    private function config(string $tempDir, bool $custom): array
    {
        $config = [
            'mode' => 'utf-8',
            'format' => 'A4-L',
            'margin_left' => 0,
            'margin_right' => 0,
            'margin_top' => 0,
            'margin_bottom' => 0,
            'tempDir' => $tempDir,
            'default_font' => 'dejavusans',
            'autoScriptToLang' => true,
            'autoLangToFont' => true,
        ];

        if ($custom) {
            $fontDirs = (new ConfigVariables())->getDefaults()['fontDir'];
            $fontData = (new FontVariables())->getDefaults()['fontdata'];

            $config = array_merge($config, [
                'fontDir'  => array_merge($fontDirs, [resource_path('fonts/certificates')]),
                'fontdata' => $fontData + [
                    'cairo' => ['R' => 'Cairo-Regular.ttf', 'B' => 'Cairo-Bold.ttf', 'useOTL' => 0xFF, 'useKashida' => 75],
                ],
                'default_font'     => 'cairo',
                // لا نترك mPDF يستبدل الخط تلقائيًا بحسب اللغة
                'autoScriptToLang' => false,
                'autoLangToFont'   => false,
            ]);
        }

        return $config;
    }

    /** mPDF مع الخطوط العربية وصورة الخلفية يحتاج أكثر من 128 ميجابايت */
    private function ensureMemory(int $megabytes): void
    {
        $limit = ini_get('memory_limit');

        if ($limit === '-1' || $this->toBytes((string) $limit) >= $megabytes * 1024 * 1024) {
            return;
        }

        @ini_set('memory_limit', $megabytes . 'M');
    }

    private function toBytes(string $value): int
    {
        $value  = trim($value);
        $unit   = strtolower(substr($value, -1));
        $number = (int) $value;

        return match ($unit) {
            'g'     => $number * 1024 ** 3,
            'm'     => $number * 1024 ** 2,
            'k'     => $number * 1024,
            default => $number,
        };
    }

    /** قالب الشهادة، أو الافتراضي إن حُذف ملفه، أو لا قالب (التصميم الجاهز) */
    private function templateFor(Certificate $certificate): ?CertificateTemplate
    {
        $template = $certificate->template;

        if (! $template || ! Storage::disk('public')->exists($template->design_path)) {
            $template = CertificateTemplate::where('is_default', true)->first();
        }

        return $template && Storage::disk('public')->exists($template->design_path) ? $template : null;
    }

    private function backgroundDataUri(?CertificateTemplate $template): ?string
    {
        if (! $template) {
            return null;
        }

        $disk = Storage::disk('public');

        return 'data:' . $disk->mimeType($template->design_path) . ';base64,' . base64_encode($disk->get($template->design_path));
    }
}
