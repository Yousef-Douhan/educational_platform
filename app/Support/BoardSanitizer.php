<?php

namespace App\Support;

/**
 * تنقية عناصر السبورة قبل التخزين. لا نثق بما يرسله المتصفح: نقبل أنواعًا معروفة فقط، بأرقام محصورة،
 * وألوان #rrggbb، وصور data: من png/jpeg/webp بحجم محدود. أي عنصر مخالف يُسقَط بصمت.
 *
 * الأنواع: p قلم، e ممحاة، l خط، r مستطيل، o بيضاوي/دائرة، i صورة.
 *
 * حقول اختيارية (تُهمَل بصمت إن لم تكن صالحة، ولا تُخزَّن قيمها الافتراضية):
 *  a شفافية 0.05–1 (كل الأنواع إلا الممحاة)، d نمط الخط 1 متقطع / 2 منقّط (قلم/خط/مستطيل/بيضاوي)،
 *  sm تنعيم المنحنى (قلم)، k نوع القلم h تظليل (قلم)، rot تدوير بالدرجات وfx/fy قلب (صورة).
 */
final class BoardSanitizer
{
    public const MAX_OBJECTS = 3000;
    public const MAX_POINTS = 4000;
    public const MAX_IMAGE_CHARS = 900000;
    public const MAX_TOTAL_CHARS = 3000000;
    public const COORD = 6000;

    /** @return array<int,array<string,mixed>> */
    public static function clean(mixed $objects): array
    {
        if (! is_array($objects)) {
            return [];
        }

        $out = [];
        $total = 0;

        foreach (array_slice(array_values($objects), 0, self::MAX_OBJECTS) as $o) {
            $c = is_array($o) ? self::one($o) : null;
            if ($c === null) {
                continue;
            }
            $total += strlen(json_encode($c));
            if ($total > self::MAX_TOTAL_CHARS) {
                break;
            }
            $out[] = $c;
        }

        return $out;
    }

    private static function num(mixed $v): ?float
    {
        if (! is_int($v) && ! is_float($v)) {
            return null;
        }
        $v = (float) $v;

        return is_finite($v) && abs($v) <= self::COORD ? round($v, 1) : null;
    }

    private static function color(mixed $v): ?string
    {
        return is_string($v) && preg_match('/^#[0-9a-fA-F]{6}$/', $v) ? strtolower($v) : null;
    }

    /** شفافية 0.05–1، وأي قيمة أخرى أو 1 تعني الافتراضي (null) */
    private static function alpha(mixed $v): ?float
    {
        if ((! is_int($v) && ! is_float($v)) || ! is_finite((float) $v)) {
            return null;
        }
        $v = round((float) $v, 2);

        return $v >= 0.05 && $v < 1 ? $v : null;
    }

    /**
     * يضيف الحقول الاختيارية المسموحة لنوع العنصر إلى عنصر نظيف.
     *
     * @param  array<string,mixed>  $in
     * @param  array<string,mixed>  $clean
     * @return array<string,mixed>
     */
    private static function extras(array $in, array $clean, string $t): array
    {
        if ($t !== 'e' && ($a = self::alpha($in['a'] ?? null)) !== null) {
            $clean['a'] = $a;
        }
        if (in_array($t, ['p', 'l', 'r', 'o'], true) && in_array($in['d'] ?? null, [1, 2], true)) {
            $clean['d'] = $in['d'];
        }
        if ($t === 'p' && ($in['sm'] ?? null) === 1) {
            $clean['sm'] = 1;
        }
        if ($t === 'p' && ($in['k'] ?? null) === 'h') {
            $clean['k'] = 'h';
        }
        if ($t === 'i') {
            $rot = self::num($in['rot'] ?? null);
            if ($rot !== null && abs($rot) <= 360 && $rot != 0.0) {
                $clean['rot'] = $rot;
            }
            foreach (['fx', 'fy'] as $f) {
                if (($in[$f] ?? null) === 1) {
                    $clean[$f] = 1;
                }
            }
        }

        return $clean;
    }

    private static function width(mixed $v): ?float
    {
        $n = self::num($v);

        return $n !== null && $n >= 1 && $n <= 120 ? $n : null;
    }

    /** @return array<string,mixed>|null */
    private static function one(array $o): ?array
    {
        $t = $o['t'] ?? null;

        if ($t === 'p' || $t === 'e') {
            $w = self::width($o['w'] ?? null);
            $c = $t === 'e' ? '#000000' : self::color($o['c'] ?? null);
            if ($w === null || $c === null || ! is_array($o['p'] ?? null)) {
                return null;
            }
            $pts = [];
            foreach (array_slice($o['p'], 0, self::MAX_POINTS) as $pt) {
                if (is_array($pt) && count($pt) === 2 && ($x = self::num($pt[0] ?? null)) !== null && ($y = self::num($pt[1] ?? null)) !== null) {
                    $pts[] = [$x, $y];
                }
            }

            return $pts === [] ? null : self::extras($o, ['t' => $t, 'c' => $c, 'w' => $w, 'p' => $pts], $t);
        }

        if ($t === 'l') {
            $c = self::color($o['c'] ?? null);
            $w = self::width($o['w'] ?? null);
            $v = array_map(fn ($k) => self::num($o[$k] ?? null), ['x1', 'y1', 'x2', 'y2']);

            return $c && $w !== null && ! in_array(null, $v, true)
                ? self::extras($o, ['t' => 'l', 'c' => $c, 'w' => $w, 'x1' => $v[0], 'y1' => $v[1], 'x2' => $v[2], 'y2' => $v[3]], 'l') : null;
        }

        if ($t === 'r' || $t === 'o') {
            $c = self::color($o['c'] ?? null);
            $w = self::width($o['w'] ?? null);
            $v = array_map(fn ($k) => self::num($o[$k] ?? null), ['x', 'y', 'bw', 'bh']);

            return $c && $w !== null && ! in_array(null, $v, true)
                ? self::extras($o, ['t' => $t, 'c' => $c, 'w' => $w, 'x' => $v[0], 'y' => $v[1], 'bw' => $v[2], 'bh' => $v[3]], $t) : null;
        }

        if ($t === 'i') {
            $src = $o['src'] ?? null;
            $v = array_map(fn ($k) => self::num($o[$k] ?? null), ['x', 'y', 'bw', 'bh']);
            if (! is_string($src) || strlen($src) > self::MAX_IMAGE_CHARS || in_array(null, $v, true)
                || ! preg_match('#^data:image/(png|jpeg|webp);base64,[A-Za-z0-9+/]+={0,2}$#', $src)) {
                return null;
            }

            return self::extras($o, ['t' => 'i', 'src' => $src, 'x' => $v[0], 'y' => $v[1], 'bw' => $v[2], 'bh' => $v[3]], 'i');
        }

        return null;
    }
}
