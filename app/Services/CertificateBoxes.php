<?php

namespace App\Services;

use App\Models\Certificate;

/**
 * يبني صناديق الشهادة بإحداثيات ثابتة (مم) لـ WriteFixedPosHTML.
 * كل صندوق: [x, y, عرض, ارتفاع, html]، فلا يتأثر بتدفق الصفحة.
 */
class CertificateBoxes
{
    private const NAVY = '#1e1b4b';
    private const GOLD = '#c9a24b';

    /** التصميم العصري الجاهز: شريط جانبي غامق (يمين بالعربية، يسار بالإنجليزية) */
    public function modern(Certificate $c, string $lang, string $verifyUrl): array
    {
        $ar      = $lang === 'ar';
        $dir     = $ar ? 'rtl' : 'ltr';
        $align   = $ar ? 'right' : 'left';
        $spacing = $ar ? '' : 'letter-spacing: 2pt;';
        $p       = $ar ? 233.0 : 0.0;   // بداية الشريط
        $mx      = $ar ? 16.0 : 80.0;   // بداية منطقة النص
        $mw      = 201.0;

        $name   = (string) $c->student_name;
        $course = (string) ($ar ? $c->course_title_ar : $c->course_title_en);

        $nameSize   = mb_strlen($name) > 34 ? 22 : (mb_strlen($name) > 24 ? 27 : 32);
        $courseSize = mb_strlen($course) > 50 ? 15 : (mb_strlen($course) > 34 ? 18 : 22);

        $t      = fn(string $key) => e(__("certificates.pdf.{$key}", [], $lang));
        $text   = fn(string $class, string $body, string $style = '') => "<div class=\"{$class}\" dir=\"{$dir}\" style=\"text-align: {$align}; {$style}\">{$body}</div>";
        $center = fn(string $class, string $body, string $style = '') => "<div class=\"{$class}\" style=\"text-align: center; {$style}\">{$body}</div>";

        $date = fn(float $x) => [
            $x,
            158,
            64,
            22,
            '<div class="m-foot-value" style="text-align: center;">' . $c->issued_at->format('Y-m-d') . '</div>'
                . '<div class="m-foot-line"></div>'
                . "<div class=\"m-foot-label\" dir=\"{$dir}\" style=\"text-align: center;\">" . $t('date') . '</div>',
        ];
        $sign = fn(float $x) => [
            $x,
            158,
            64,
            22,
            '<div class="m-foot-value" style="text-align: center;">&nbsp;</div>'
                . '<div class="m-foot-line"></div>'
                . "<div class=\"m-foot-label\" dir=\"{$dir}\" style=\"text-align: center;\">" . $t('signature') . '</div>',
        ];

        return [
            // الشريط والزخارف
            [$p, 0, 64, 210, '<div style="background-color: ' . self::NAVY . '; width: 64mm; height: 210mm;"></div>'],
            [$ar ? 233.0 : 63.2, 0, 0.8, 210, '<div style="background-color: ' . self::GOLD . '; width: 0.8mm; height: 210mm;"></div>'],
            [$p + 21, 38, 22, 22, '<div style="width: 20mm; height: 20mm; border: 1mm solid ' . self::GOLD . '; border-radius: 11mm;"></div>'],
            [$p + 26, 43, 12, 12, '<div style="background-color: ' . self::GOLD . '; width: 12mm; height: 12mm; border-radius: 6mm;"></div>'],

            // نصوص الشريط
            [$p, 22, 64, 9, $center('m-brand', e((string) config('app.name')), $spacing)],
            [$p, 64, 64, 8, $center('m-seal', $t('seal'), $spacing)],

            // بطاقة الـ QR البيضاء ثم الـ QR فوقها
            [$p + 16.5, 126, 31, 31, '<div style="background-color: #ffffff; width: 31mm; height: 31mm; border-radius: 2mm;"></div>'],
            [$p + 16.5, 128.5, 31, 28, '<div style="text-align: center;"><barcode code="' . e($verifyUrl) . '" type="QR" class="barcode" size="0.95" error="M" disableborder="1" /></div>'],
            [$p, 161, 64, 6, $center('m-code', e($c->certificate_code))],
            [$p, 168, 64, 6, "<div class=\"m-scan\" dir=\"{$dir}\" style=\"text-align: center;\">" . $t('scan') . '</div>'],

            // المحتوى الرئيسي
            [$mx, 26, $mw, 18, $text('m-title', $t('title'), $ar ? 'font-size: 38pt;' : '')],
            [$ar ? $mx + $mw - 26 : $mx, 50, 26, 1, '<div style="background-color: ' . self::GOLD . '; width: 26mm; height: 1mm;"></div>'],
            [$mx, 63, $mw, 8, $text('m-lead', $t('certifies'))],
            [$mx, 72, $mw, 18, $text('m-name', e($name), "font-size: {$nameSize}pt;")],
            [$mx, 93, $mw, 1, '<div style="background-color: #d1d5db; width: ' . $mw . 'mm; height: 0.3mm;"></div>'],
            [$mx, 100, $mw, 8, $text('m-lead', $t('completed'))],
            [$mx, 108, $mw, 16, $text('m-course', e($course), "font-size: {$courseSize}pt;")],

            // التاريخ والتوقيع يتبادلان الجهتين في العربية
            $date($ar ? $mx + $mw - 64 : $mx),
            $sign($ar ? $mx : $mx + $mw - 64),
        ];
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
        $course = (string) ($ar ? $c->course_title_ar : $c->course_title_en);

        $t    = fn(string $key) => e(__("certificates.pdf.{$key}", [], $lang));
        $text = fn(string $class, string $body, string $style = '') => "<div class=\"{$class}\" dir=\"{$dir}\" style=\"text-align: center; {$style}\">{$body}</div>";

        $boxes = [];

        if (! $layout['hide_header']) {
            $boxes[] = [$left, 40, $width, 18, $text('o-title', $t('title'), "color: {$layout['accent_color']};")];
            $boxes[] = [$center - 20, 69, 40, 1, '<div style="background-color: ' . self::GOLD . '; width: 40mm; height: 0.8mm;"></div>'];
        }

        $boxes[] = [$left, $mt, $width, 8, $text('o-lead', $t('certifies'))];
        $boxes[] = [$left, $mt + 8, $width, 14, $text('o-name', e((string) $c->student_name), "color: {$layout['text_color']};")];
        $boxes[] = [$center - 55, $mt + 22, 110, 1, '<div style="background-color: #9ca3af; width: 110mm; height: 0.3mm;"></div>'];
        $boxes[] = [$left, $mt + 26, $width, 8, $text('o-lead', $t('completed'))];
        $boxes[] = [$left, $mt + 34, $width, 12, $text('o-course', e($course), "color: {$layout['accent_color']};")];

        if (! $layout['hide_date']) {
            $boxes[] = [
                $left + 12,
                160,
                64,
                22,
                '<div class="o-foot-value" style="text-align: center;">' . $c->issued_at->format('Y-m-d') . '</div>'
                    . '<div class="o-foot-line"></div>'
                    . "<div class=\"o-foot-label\" dir=\"{$dir}\" style=\"text-align: center;\">" . $t('date') . '</div>',
            ];
        }

        $qr = $layout['hide_qr'] ? ''
            : '<div style="text-align: center;"><barcode code="' . e($verifyUrl) . '" type="QR" class="barcode" size="0.8" error="M" disableborder="1" /></div>';
        $boxes[] = [
            $center - 45,
            (float) $layout['qr_top'],
            90,
            40,
            $qr . '<div class="o-code" style="text-align: center;">' . e($c->certificate_code) . '</div>',
        ];

        if (! $layout['hide_signature']) {
            $boxes[] = [
                $left + $width - 12 - 64,
                160,
                64,
                22,
                '<div class="o-foot-value" style="text-align: center;">&nbsp;</div>'
                    . '<div class="o-foot-line"></div>'
                    . "<div class=\"o-foot-label\" dir=\"{$dir}\" style=\"text-align: center;\">" . $t('signature') . '</div>',
            ];
        }

        return $boxes;
    }
}
