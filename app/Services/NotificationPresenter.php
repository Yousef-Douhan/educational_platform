<?php

namespace App\Services;

use App\Notifications\PlatformNotification;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\Support\Facades\Lang;

/**
 * يحوّل صف الإشعار إلى عنوان ونص بلغة القارئ. كل ما يُرجَع نص عادي؛
 * أما الهروب من HTML فعند الطباعة ({{ }} في Blade وtextContent في JS).
 * الأنواع غير المعروفة (قديمة أو من حزمة أخرى) تُعرض بنص عام ولا تُسقط الصفحة.
 */
final class NotificationPresenter
{
    private const STYLE = [
        'order.paid'               => ['fa-receipt', 'success'],
        'order.refunded'           => ['fa-rotate-left', 'warning'],
        'enrollment.activated'     => ['fa-graduation-cap', 'primary'],
        'certificate.issued'       => ['fa-award', 'success'],
        'certificate.revoked'      => ['fa-ban', 'danger'],
        'live_session.cancelled'   => ['fa-video-slash', 'danger'],
        'live_session.rescheduled' => ['fa-calendar-check', 'info'],
    ];

    /** @return array{kind:string,known:bool,title:string,body:string,icon:string,tone:string} */
    public static function present(DatabaseNotification $notification, ?string $locale = null): array
    {
        $data   = is_array($notification->data) ? $notification->data : [];
        $kind   = is_string($data['kind'] ?? null) ? $data['kind'] : (string) $notification->type;
        $params = is_array($data['params'] ?? null) ? $data['params'] : [];
        $known  = in_array($kind, PlatformNotification::KINDS, true);

        if (! $known) {
            return [
                'kind'  => $kind,
                'known' => false,
                'title' => __('notifications.unknown.title', [], $locale),
                'body'  => __('notifications.unknown.body', [], $locale),
                'icon'  => 'fa-bell',
                'tone'  => 'muted',
            ];
        }

        $values = self::values($params, $locale ?? app()->getLocale());
        $base   = 'notifications.kinds.' . $kind;
        $bodyKey = filled($values[':reason'] ?? null) && Lang::has($base . '.body_with_reason', $locale)
            ? $base . '.body_with_reason'
            : $base . '.body';

        [$icon, $tone] = self::STYLE[$kind];

        return [
            'kind'  => $kind,
            'known' => true,
            // strtr تستبدل في مرور واحد، فلا تُفسَّر قيمة تحتوي ":something" كعنصر نائب
            'title' => strtr(__($base . '.title', [], $locale), $values),
            'body'  => strtr(__($bodyKey, [], $locale), $values),
            'icon'  => $icon,
            'tone'  => $tone,
        ];
    }

    /** x_ar / x_en تصيران :x بحسب اللغة، ويُركَّب :money من المبلغ والعملة */
    private static function values(array $params, string $locale): array
    {
        $out = [];

        foreach ($params as $key => $value) {
            if (is_scalar($value)) {
                $out[':' . $key] = (string) $value;
            }
        }

        foreach (array_keys($params) as $key) {
            if (! str_ends_with((string) $key, '_ar')) {
                continue;
            }
            $base = substr((string) $key, 0, -3);
            $ar   = (string) ($params[$base . '_ar'] ?? '');
            $en   = (string) ($params[$base . '_en'] ?? '');
            $out[':' . $base] = $locale === 'ar' ? ($ar !== '' ? $ar : $en) : ($en !== '' ? $en : $ar);
        }

        if (isset($params['amount'])) {
            $out[':money'] = trim(($params['amount'] ?? '') . ' ' . ($params['currency'] ?? ''));
        }

        return $out;
    }
}
