<?php

namespace App\Support;

use Illuminate\Support\Facades\Storage;
use Throwable;

/**
 * يحوّل (مزوّد، رابط) المخزّن في الدرس إلى شيء آمن للعرض. لا يثق بما في القاعدة:
 * يعيد التحقق من المضيف والصيغة وقت العرض، ويبني رابط التضمين بنفسه (لا يمرّر الرابط الخام).
 *
 * يعيد ['type' => 'iframe'|'video'|'link', 'src' => string] أو null إن تعذّر العرض.
 */
final class VideoEmbed
{
    public static function resolve(?string $provider, ?string $url): ?array
    {
        $url = trim((string) $url);

        if ($provider === null || $url === '') {
            return null;
        }

        return match ($provider) {
            's3'       => self::s3($url),
            'youtube'  => self::youtube($url),
            'drive'    => self::drive($url),
            'bunny'    => self::bunny($url),
            'external' => self::external($url),
            default    => null,
        };
    }

    private static function parts(string $url): ?array
    {
        $parts = parse_url($url);

        if (! filter_var($url, FILTER_VALIDATE_URL) || ($parts['scheme'] ?? '') !== 'https' || empty($parts['host'])) {
            return null;
        }

        $parts['host'] = strtolower($parts['host']);

        return $parts;
    }

    private static function hostIs(array $parts, array $domains): bool
    {
        foreach ($domains as $d) {
            if ($parts['host'] === $d || str_ends_with($parts['host'], '.' . $d)) {
                return true;
            }
        }

        return false;
    }

    private static function youtube(string $url): ?array
    {
        $p = self::parts($url);

        if (! $p || ! self::hostIs($p, ['youtube.com', 'youtu.be', 'youtube-nocookie.com'])) {
            return null;
        }

        $id = null;

        if (self::hostIs($p, ['youtu.be'])) {
            $id = ltrim($p['path'] ?? '', '/');
        } elseif (preg_match('#^/(?:embed|shorts|live)/([A-Za-z0-9_-]{11})#', $p['path'] ?? '', $m)) {
            $id = $m[1];
        } else {
            parse_str($p['query'] ?? '', $q);
            $id = is_string($q['v'] ?? null) ? $q['v'] : null;
        }

        $id = $id !== null ? substr($id, 0, 11) : null;

        return $id !== null && preg_match('/^[A-Za-z0-9_-]{11}$/', $id)
            ? ['type' => 'iframe', 'src' => "https://www.youtube-nocookie.com/embed/{$id}?rel=0"]
            : null;
    }

    private static function drive(string $url): ?array
    {
        $p = self::parts($url);

        if (! $p || ! self::hostIs($p, ['drive.google.com', 'docs.google.com'])) {
            return null;
        }

        if (preg_match('#/file/d/([A-Za-z0-9_-]{10,100})#', $p['path'] ?? '', $m)) {
            $id = $m[1];
        } else {
            parse_str($p['query'] ?? '', $q);
            $id = is_string($q['id'] ?? null) && preg_match('/^[A-Za-z0-9_-]{10,100}$/', $q['id']) ? $q['id'] : null;
        }

        return $id ? ['type' => 'iframe', 'src' => "https://drive.google.com/file/d/{$id}/preview"] : null;
    }

    private static function bunny(string $url): ?array
    {
        $p = self::parts($url);

        if (! $p || ! self::hostIs($p, ['b-cdn.net', 'mediadelivery.net', 'bunnycdn.com'])) {
            return null;
        }

        $path = $p['path'] ?? '';

        // ملف مباشر (mp4/m3u8) على CDN يُعرض بمشغّل المتصفح؛ غير ذلك صفحة تضمين من bunny
        if (preg_match('#\.(mp4|webm)$#i', $path)) {
            return ['type' => 'video', 'src' => 'https://' . $p['host'] . $path . (isset($p['query']) ? '?' . $p['query'] : '')];
        }

        if (self::hostIs($p, ['mediadelivery.net'])) {
            $path = preg_replace('#^/play/#', '/embed/', $path);

            return ['type' => 'iframe', 'src' => 'https://' . $p['host'] . $path . (isset($p['query']) ? '?' . $p['query'] : '')];
        }

        return null;
    }

    private static function external(string $url): ?array
    {
        $p = self::parts($url);

        if (! $p) {
            return null;
        }

        if (self::hostIs($p, ['vimeo.com']) && preg_match('#/(\d{5,12})(?:/|$)#', $p['path'] ?? '', $m)) {
            return ['type' => 'iframe', 'src' => "https://player.vimeo.com/video/{$m[1]}"];
        }

        // أي نطاق آخر لا يُضمَّن (لا نعرف ما فيه): رابط يفتح في تبويب جديد
        return ['type' => 'link', 'src' => $url];
    }

    private static function s3(string $key): ?array
    {
        if (! preg_match('#^[A-Za-z0-9][A-Za-z0-9._\-/]*$#', $key) || str_contains($key, '..')) {
            return null;
        }

        try {
            return ['type' => 'video', 'src' => Storage::disk('s3')->temporaryUrl($key, now()->addHours(2))];
        } catch (Throwable) {
            return null; // القرص غير مضبوط: لا نكشف سبب الفشل للطالب
        }
    }
}
