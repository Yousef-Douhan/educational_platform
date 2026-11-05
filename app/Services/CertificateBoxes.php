<?php

namespace App\Services;

use App\Models\Certificate;

/**
 * يبني صناديق الشهادة بإحداثيات ثابتة (مم) لـ WriteFixedPosHTML.
 * كل صندوق: [x, y, عرض, ارتفاع, html]، فلا يتأثر بتدفق الصفحة.
 */
class CertificateBoxes
{
    private const NAVY  = '#1e1b4b';
    private const GOLD  = '#b8913a';
    private const LIGHT = '#d9c48f';
    private const IVORY = '#fcfaf4';

    /**
     * التصميم الاحترافي الجاهز: إطار كحلي مزدوج بخطوط ذهبية وزوايا مزخرفة،
     * محتوى في المنتصف، وختم ذهبي بين التاريخ والتوقيع، ورمز QR للتحقق.
     */
    public function modern(Certificate $c, string $lang, string $verifyUrl): array
    {
        $ar  = $lang === 'ar';
        $dir = $ar ? 'rtl' : 'ltr';
        $cx  = 148.5;   // منتصف الصفحة الأفقي

        $name   = (string) $c->student_name;
        $course = (string) ($ar ? $c->course_title_ar : $c->course_title_en);
        $brand  = $this->plain((string) __('brand.name', [], $lang));

        $nameSize   = mb_strlen($name) > 40 ? 24 : (mb_strlen($name) > 28 ? 29 : 34);
        $courseSize = mb_strlen($course) > 60 ? 14 : (mb_strlen($course) > 40 ? 17 : 20);

        $t     = fn(string $key) => e(__("certificates.pdf.{$key}", [], $lang));
        $box   = fn(string $style) => "<div style=\"{$style}\"></div>";
        $fill  = fn(float $w, float $h, string $color, string $extra = '') => $box("background-color: {$color}; width: {$w}mm; height: {$h}mm; {$extra}");
        $line  = fn(float $w, float $h, float $b, string $color) => $box("width: {$w}mm; height: {$h}mm; border: {$b}mm solid {$color};");
        $text  = fn(string $class, string $body, string $style = '') => "<div class=\"{$class}\" dir=\"{$dir}\" style=\"text-align: center; {$style}\">{$body}</div>";

        $boxes = [
            // الخلفية والإطار الكحلي
            [8, 8, 281, 194, $fill(281, 194, self::IVORY)],
            [0, 0, 297, 8, $fill(297, 8, self::NAVY)],
            [0, 202, 297, 8, $fill(297, 8, self::NAVY)],
            [0, 0, 8, 210, $fill(8, 210, self::NAVY)],
            [289, 0, 8, 210, $fill(8, 210, self::NAVY)],
            // خطان ذهبيان داخل الإطار
            [11, 11, 275, 188, $line(273.8, 186.8, 0.6, self::GOLD)],
        ];

        // زوايا مزخرفة على شكل L
        foreach ([[18, 18, 1, 1], [279, 18, -1, 1], [18, 192, 1, -1], [279, 192, -1, -1]] as [$x, $y, $sx, $sy]) {
            $len = 16;
            $th  = 1.2;
            $boxes[] = [$sx > 0 ? $x : $x - $len, $sy > 0 ? $y : $y - $th, $len, $th, $fill($len, $th, self::GOLD)];
            $boxes[] = [$sx > 0 ? $x : $x - $th, $sy > 0 ? $y : $y - $len, $th, $len, $fill($th, $len, self::GOLD)];
            $boxes[] = [$sx > 0 ? $x + 3 : $x - 5, $sy > 0 ? $y + 3 : $y - 5, 2, 2, $fill(2, 2, self::LIGHT, 'border-radius: 1mm;')];
        }

        // اسم المنصة وفاصل زخرفي
        $boxes[] = [40, 22, 217, 8, $text('p-brand', e($brand), $ar ? 'letter-spacing: 0; font-size: 15pt;' : '')];
        $boxes[] = [$cx - 36, 33.6, 30, 0.4, $fill(30, 0.4, self::GOLD)];
        $boxes[] = [$cx + 6, 33.6, 30, 0.4, $fill(30, 0.4, self::GOLD)];
        $boxes[] = [$cx - 1.6, 32.2, 3.2, 3.2, $fill(3.2, 3.2, self::GOLD, 'border-radius: 1.6mm;')];

        // العنوان
        $boxes[] = [30, $ar ? 35 : 38, 237, 20, $text('p-title', $t('title_main'), $ar ? 'font-size: 42pt; letter-spacing: 0;' : '')];
        $boxes[] = [30, $ar ? 62 : 59, 237, 8, $text('p-subtitle', $t('title_sub'))];

        // الطالب والدورة
        $boxes[] = [30, 73, 237, 8, $text('p-lead', $t('certifies'))];
        $boxes[] = [25, 82, 247, 20, $text('p-name', e($name), "font-size: {$nameSize}pt;")];
        $boxes[] = [$cx - 75, 103, 150, 0.5, $fill(150, 0.5, self::GOLD)];
        $boxes[] = [30, 108, 237, 8, $text('p-lead', $t('completed'))];
        $boxes[] = [30, 117, 237, 16, $text('p-course', e($course), "font-size: {$courseSize}pt;")];

        // الختم الذهبي في المنتصف
        $sy = 144;
        $boxes[] = [$cx - 16, $sy, 32, 32, $fill(32, 32, self::GOLD, 'border-radius: 16mm;')];
        $boxes[] = [$cx - 13.4, $sy + 2.9, 27, 27, $fill(26.2, 26.2, self::NAVY, 'border: 0.4mm solid ' . self::LIGHT . '; border-radius: 13.5mm;')];
        $boxes[] = [$cx - 11, $sy + 9.5, 22, 6, '<div class="p-seal-brand" style="text-align: center;">' . e($brand) . '</div>'];
        $boxes[] = [$cx - 11, $sy + 15.5, 22, 6, $text('p-seal', $t('seal'))];

        // التاريخ والتوقيع على جانبي الختم (يتبادلان في العربية)
        $foot = fn(float $x, string $value, string $class, string $label) => [
            $x, 152, 62, 22,
            "<div class=\"{$class}\" style=\"text-align: center;\">{$value}</div>"
                . '<div class="p-foot-line"></div>'
                . "<div class=\"p-foot-label\" dir=\"{$dir}\" style=\"text-align: center;\">{$label}</div>",
        ];
        $left  = 48;
        $right = 297 - 48 - 62;
        $boxes[] = $foot($ar ? $right : $left, $c->issued_at->format('Y/m/d'), 'p-foot-value', $t('date'));
        $boxes[] = $foot($ar ? $left : $right, e($brand), 'p-foot-sign', $t('signature'));

        // رقم الشهادة في زاوية، ورمز QR في الزاوية المقابلة
        $codeX = $ar ? 297 - 24 - 50 : 24;
        $qrX   = $ar ? 24 : 297 - 24 - 24;
        $align = $ar ? 'right' : 'left';
        $boxes[] = [$codeX, 172, 50, 12,
            "<div class=\"p-num-label\" dir=\"{$dir}\" style=\"text-align: {$align};\">" . $t('number') . '</div>'
                . "<div class=\"p-num\" style=\"text-align: {$align};\">" . e($c->certificate_code) . '</div>'];
        $boxes[] = [$qrX, 156, 24, 24, $fill(24, 24, '#ffffff', 'border: 0.3mm solid ' . self::LIGHT . ';')];
        $boxes[] = [$qrX, 157.2, 24, 22, '<div style="text-align: center;"><barcode code="' . e($verifyUrl) . '" type="QR" class="barcode" size="0.72" error="M" disableborder="1" /></div>'];
        $boxes[] = [$qrX - 8, 181, 40, 5, $text('p-scan', $t('scan'))];

        return $boxes;
    }

    /** نص المنصة بلا تشكيل (الخط المخصص لا يدعم علامات التشكيل) */
    private function plain(string $text): string
    {
        return preg_replace('/[\x{064B}-\x{065F}\x{0670}\x{06D6}-\x{06ED}]/u', '', $text) ?? $text;
    }

    /** النص فوق صورة قالب خاصة، بحسب إعدادات التخطيط المحفوظة للقالب */
    public function overlay(Certificate $c, string $lang, string $verifyUrl, array $layout): array
    {
        $ar     = $lang === 'ar';
        $dir    = $ar ? 'rtl' : 'ltr';
        $left   = (float) $layout['area_left'];
        $width  = (float) $layout['area_width'];
        $center = $left + $width / 2;
        $mt     = (float) $layout['middle_top'];
        $qrTop  = (float) $layout['qr_top'];
        // اللون البنفسجي الافتراضي القديم يُستبدل بالكحلي الرسمي
        $accent = strtolower((string) $layout['accent_color']) === '#3730a3' ? self::NAVY : $layout['accent_color'];
        $ink    = $layout['text_color'];

        $name   = (string) $c->student_name;
        $course = (string) ($ar ? $c->course_title_ar : $c->course_title_en);
        $brand  = $this->plain((string) __('brand.name', [], $lang));

        $nameSize   = mb_strlen($name) > 40 ? 22 : (mb_strlen($name) > 28 ? 27 : 32);
        $courseSize = mb_strlen($course) > 60 ? 13 : (mb_strlen($course) > 40 ? 16 : 19);

        // هامش داخلي حتى لا يلامس النص زخارف إطار الصورة
        $in  = 28.0;
        $tl  = $left + $in;
        $tw  = max(80.0, $width - 2 * $in);

        $t    = fn(string $key) => e(__("certificates.pdf.{$key}", [], $lang));
        $fill = fn(float $w, float $h, string $color, string $extra = '') => "<div style=\"background-color: {$color}; width: {$w}mm; height: {$h}mm; {$extra}\"></div>";
        $text = fn(string $class, string $body, string $style = '') => "<div class=\"{$class}\" dir=\"{$dir}\" style=\"text-align: center; {$style}\">{$body}</div>";

        $boxes = [];

        if (! $layout['hide_header']) {
            $boxes[] = [$tl, 30, $tw, 8, $text('p-brand', e($brand), $ar ? 'letter-spacing: 0; font-size: 14pt;' : '')];
            $boxes[] = [$tl, $ar ? 37 : 39, $tw, 18, $text('o-title', $t('title_main'), "color: {$accent};" . ($ar ? ' font-size: 38pt; letter-spacing: 0;' : ''))];
            $boxes[] = [$tl, $ar ? 60 : 58, $tw, 7, $text('p-subtitle', $t('title_sub'), 'font-size: 11pt;')];
            $boxes[] = [$center - 34, 69.6, 28, 0.4, $fill(28, 0.4, self::GOLD)];
            $boxes[] = [$center + 6, 69.6, 28, 0.4, $fill(28, 0.4, self::GOLD)];
            $boxes[] = [$center - 1.5, 68.3, 3, 3, $fill(3, 3, self::GOLD, 'border-radius: 1.5mm;')];
        }

        $boxes[] = [$tl, $mt, $tw, 8, $text('p-lead', $t('certifies'))];
        $boxes[] = [$tl, $mt + 8, $tw, 18, $text('p-name', e($name), "font-size: {$nameSize}pt; color: {$ink};")];
        $boxes[] = [$center - 65, $mt + 27, 130, 0.5, $fill(130, 0.5, self::GOLD)];
        $boxes[] = [$tl, $mt + 31, $tw, 8, $text('p-lead', $t('completed'))];
        $boxes[] = [$tl, $mt + 39, $tw, 14, $text('p-course', e($course), "font-size: {$courseSize}pt; color: {$accent};")];

        // التاريخ والتوقيع على الجانبين (يتبادلان في العربية) ورمز QR في المنتصف
        $fy   = $qrTop + 8;
        $foot = fn(float $x, string $value, string $class, string $label) => [
            $x, $fy, 56, 20,
            "<div class=\"{$class}\" style=\"text-align: center; color: {$ink};\">{$value}</div>"
                . '<div class="p-foot-line"></div>'
                . "<div class=\"p-foot-label\" dir=\"{$dir}\" style=\"text-align: center;\">{$label}</div>",
        ];
        $sideL = $tl + 4;
        $sideR = $tl + $tw - 4 - 56;

        if (! $layout['hide_date']) {
            $boxes[] = $foot($ar ? $sideR : $sideL, $c->issued_at->format('Y/m/d'), 'p-foot-value', $t('date'));
        }

        if (! $layout['hide_signature']) {
            $boxes[] = $foot($ar ? $sideL : $sideR, e($brand), 'p-foot-sign', $t('signature'));
        }

        if (! $layout['hide_qr']) {
            $boxes[] = [$center - 12, $qrTop, 24, 24, $fill(24, 24, '#ffffff', 'border: 0.3mm solid ' . self::LIGHT . ';')];
            $boxes[] = [$center - 12, $qrTop + 1.2, 24, 22, '<div style="text-align: center;"><barcode code="' . e($verifyUrl) . '" type="QR" class="barcode" size="0.72" error="M" disableborder="1" /></div>'];
            $boxes[] = [$center - 30, $qrTop + 25.5, 60, 5, $text('p-scan', $t('scan'))];
        }

        $ny = $qrTop + ($layout['hide_qr'] ? 12 : 30);
        $boxes[] = [$center - 40, $ny, 80, 4, $text('p-num-label', $t('number'))];
        $boxes[] = [$center - 40, $ny + 3.6, 80, 5, '<div class="p-num" style="text-align: center; font-size: 8.5pt;">' . e($c->certificate_code) . '</div>'];

        return $boxes;
    }
}
