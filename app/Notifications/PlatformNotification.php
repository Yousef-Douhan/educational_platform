<?php

namespace App\Notifications;

use App\Models\Certificate;
use App\Models\Course;
use App\Models\LiveSession;
use App\Models\Order;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Str;

/**
 * إشعار واحد لكل أحداث المنصة. يُخزَّن نوع الحدث ومعاملاته (نصوص عادية) فقط،
 * ويُترجَم عند العرض بلغة من يقرأه. لا يُخزَّن HTML ولا روابط ولا بيانات سرية.
 * قناة قاعدة البيانات فقط: لا يُرسَل بريد ولا يدخل طابور.
 */
class PlatformNotification extends Notification
{
    public const KINDS = [
        'order.paid',
        'order.refunded',
        'enrollment.activated',
        'certificate.issued',
        'certificate.revoked',
        'live_session.cancelled',
        'live_session.rescheduled',
        'receipt.rejected',
    ];

    private const MAX = 255;

    /** @param array<string,scalar|null> $params */
    public function __construct(public readonly string $kind, public readonly array $params = []) {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    /** عمود type يحمل نوع الحدث ليسهل الفلترة */
    public function databaseType(object $notifiable): string
    {
        return $this->kind;
    }

    public function toDatabase(object $notifiable): array
    {
        return ['kind' => $this->kind, 'params' => $this->params];
    }

    /* ---------- أحداث المنصة ---------- */

    public static function orderPaid(Order $order): self
    {
        return self::make('order.paid', [
            'order_number' => $order->order_number,
            'amount'       => $order->total_amount,
            'currency'     => $order->currency,
        ]);
    }

    public static function orderRefunded(Order $order): self
    {
        return self::make('order.refunded', [
            'order_number' => $order->order_number,
            'amount'       => $order->total_amount,
            'currency'     => $order->currency,
        ]);
    }

    public static function receiptRejected(Order $order, string $reason): self
    {
        return self::make('receipt.rejected', [
            'order_number' => $order->order_number,
            'reason'       => $reason,
        ]);
    }

    public static function enrollmentActivated(Course $course): self
    {
        return self::make('enrollment.activated', [
            'course_title_ar' => $course->title_ar,
            'course_title_en' => $course->title_en,
        ]);
    }

    public static function certificateIssued(Certificate $certificate): self
    {
        return self::make('certificate.issued', [
            'course_title_ar' => $certificate->course_title_ar,
            'course_title_en' => $certificate->course_title_en,
            'code'            => $certificate->certificate_code,
        ]);
    }

    public static function certificateRevoked(Certificate $certificate): self
    {
        return self::make('certificate.revoked', [
            'course_title_ar' => $certificate->course_title_ar,
            'course_title_en' => $certificate->course_title_en,
        ]);
    }

    public static function liveSessionCancelled(LiveSession $session): self
    {
        return self::make('live_session.cancelled', [
            'title_ar' => $session->title_ar,
            'title_en' => $session->title_en,
            'when'     => $session->scheduled_at?->copy()->timezone(config('app.timezone'))->format('Y-m-d H:i'),
            'reason'   => $session->cancel_reason,
        ]);
    }

    public static function liveSessionRescheduled(LiveSession $session): self
    {
        return self::make('live_session.rescheduled', [
            'title_ar' => $session->title_ar,
            'title_en' => $session->title_en,
            'when'     => $session->scheduled_at?->copy()->timezone(config('app.timezone'))->format('Y-m-d H:i'),
        ]);
    }

    /** كل قيمة تُقصّ وتُحوَّل إلى نص: لا تتضخم البيانات ولا يدخل فيها غير النصوص */
    private static function make(string $kind, array $params): self
    {
        $clean = [];

        foreach ($params as $key => $value) {
            if ($value === null || ! is_scalar($value)) {
                continue;
            }
            $clean[$key] = Str::limit(trim((string) $value), self::MAX - 1, '…');
        }

        return new self($kind, $clean);
    }
}
