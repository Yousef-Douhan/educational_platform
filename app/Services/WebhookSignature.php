<?php

namespace App\Services;

/**
 * التحقق من توقيع الإشعار بصيغة Stripe: الترويسة  t=<وقت>,v1=<HMAC-SHA256>[,v1=...]
 * والنص الموقَّع هو  "<وقت>.<الجسم الخام>". المقارنة ثابتة الزمن، والوقت محدود بفترة سماح.
 */
final class WebhookSignature
{
    public static function verify(string $payload, ?string $header, ?string $secret, int $tolerance, ?int $now = null): bool
    {
        if ($secret === null || $secret === '' || $header === null || $header === '') {
            return false;
        }

        $timestamp  = null;
        $signatures = [];

        foreach (explode(',', $header) as $part) {
            [$key, $value] = array_pad(explode('=', trim($part), 2), 2, '');

            if ($key === 't' && ctype_digit($value)) {
                $timestamp = (int) $value;
            } elseif ($key === 'v1' && $value !== '') {
                $signatures[] = $value;
            }
        }

        if ($timestamp === null || $signatures === []) {
            return false;
        }

        if (abs(($now ?? time()) - $timestamp) > $tolerance) {
            return false;
        }

        $expected = hash_hmac('sha256', $timestamp . '.' . $payload, $secret);

        foreach ($signatures as $candidate) {
            if (hash_equals($expected, $candidate)) {
                return true;
            }
        }

        return false;
    }

    /** يبني ترويسة صحيحة (للاختبارات ولأدوات التجربة المحلية) */
    public static function header(string $payload, string $secret, ?int $timestamp = null): string
    {
        $timestamp ??= time();

        return 't=' . $timestamp . ',v1=' . hash_hmac('sha256', $timestamp . '.' . $payload, $secret);
    }
}
