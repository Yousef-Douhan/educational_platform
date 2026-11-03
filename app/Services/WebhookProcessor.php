<?php

namespace App\Services;

use App\Exceptions\OrderException;
use App\Models\Payment;
use App\Models\WebhookEvent;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\Log;
use InvalidArgumentException;

/**
 * يترجم إشعار بوابة موثَّق التوقيع إلى استدعاء PaymentService. لا يغيّر طلبًا أو تسجيلًا بنفسه.
 *
 * قواعد الأمان:
 *  - الدفعة تُحدَّد من معرّفنا (metadata.payment_id) وبوابتها يجب أن تطابق البوابة في الرابط.
 *  - المبلغ والعملة يُقارَنان بما سجّلناه نحن عند بدء الدفع، لا بما يدّعيه الإشعار وحده.
 *  - تكرار نفس الحدث لا يُنفَّذ مرتين (unique على البوابة + معرّف الحدث).
 *  - ما يُحفظ من الإشعار حقول معروفة فقط، فلا تُخزَّن بيانات بطاقة.
 */
class WebhookProcessor
{
    public function __construct(private readonly PaymentService $payments) {}

    /**
     * @return array{0:WebhookEvent,1:bool} الحدث، وهل هو تكرار لحدث سبق إنهاؤه
     *
     * @throws InvalidArgumentException إذا كان الإشعار بلا معرّف أو نوع صالح
     */
    public function handle(string $gateway, array $event): array
    {
        $id   = $event['id'] ?? null;
        $type = $event['type'] ?? null;

        foreach ([$id, $type] as $value) {
            if (! is_string($value) || $value === '' || strlen($value) > 120) {
                throw new InvalidArgumentException('Invalid event id or type.');
            }
        }

        $object = is_array($event['data']['object'] ?? null) ? $event['data']['object'] : [];

        try {
            $row = new WebhookEvent;
            $row->forceFill([
                'gateway'  => $gateway,
                'event_id' => $id,
                'type'     => $type,
                'status'   => 'received',
                'payload'  => Payment::sanitize($this->summary($id, $type, $object)),
            ])->save();
        } catch (UniqueConstraintViolationException) {
            $row = WebhookEvent::where(['gateway' => $gateway, 'event_id' => $id])->firstOrFail();

            // انتهى سابقًا: لا نعيد التنفيذ. وإن بقي «received» (انقطاع) نكمل المعالجة
            if ($row->status !== 'received') {
                return [$row, true];
            }
        }

        match ($type) {
            'payment_intent.succeeded'      => $this->succeeded($row, $gateway, $object),
            'payment_intent.payment_failed' => $this->failed($row, $gateway, $object),
            default                         => $this->finish($row, 'ignored'),
        };

        return [$row->refresh(), false];
    }

    /* ---------- الأحداث ---------- */

    private function succeeded(WebhookEvent $row, string $gateway, array $object): void
    {
        $payment = $this->locate($row, $gateway, $object);

        if ($payment === null) {
            return;
        }

        $cents = $object['amount_received'] ?? $object['amount'] ?? null;

        if (! is_numeric($cents)
            || (int) $cents !== (int) round(((float) $payment->amount) * 100)
            || strtoupper((string) ($object['currency'] ?? '')) !== strtoupper($payment->currency)) {
            $this->finish($row, 'failed', 'amount_mismatch');

            return;
        }

        $reference = $object['id'] ?? null;

        if (! is_string($reference) || trim($reference) === '' || strlen($reference) > 120) {
            $this->finish($row, 'rejected', 'reference_missing');

            return;
        }

        try {
            $this->payments->succeed($payment, $reference, $this->summary((string) $row->event_id, (string) $row->type, $object));
        } catch (OrderException $e) {
            // دفعة متأخرة أو طلب أُغلق أو مرجع مكرر: المال قد يكون وصل فيُترك للمراجعة
            $this->finish($row, 'failed', $e->reason);

            return;
        }

        $this->finish($row, 'processed');
    }

    private function failed(WebhookEvent $row, string $gateway, array $object): void
    {
        $payment = $this->locate($row, $gateway, $object);

        if ($payment === null) {
            return;
        }

        try {
            $this->payments->fail($payment, $this->summary((string) $row->event_id, (string) $row->type, $object));
        } catch (OrderException $e) {
            $this->finish($row, 'failed', $e->reason);

            return;
        }

        $this->finish($row, 'processed');
    }

    /* ---------- داخلي ---------- */

    private function locate(WebhookEvent $row, string $gateway, array $object): ?Payment
    {
        $id = $object['metadata']['payment_id'] ?? null;

        $payment = (is_string($id) || is_int($id)) && preg_match('/^[0-9]{1,18}$/', (string) $id)
            ? Payment::find((int) $id)
            : null;

        // دفعة تخص بوابة أخرى لا تُقبل من هذا المسار
        if ($payment === null || $payment->gateway !== $gateway) {
            $this->finish($row, 'rejected', 'payment_not_found');

            return null;
        }

        $row->forceFill(['payment_id' => $payment->id])->save();

        return $payment;
    }

    private function finish(WebhookEvent $row, string $status, ?string $reason = null): void
    {
        $row->forceFill(['status' => $status, 'failure_reason' => $reason, 'processed_at' => now()])->save();

        if (in_array($status, ['failed', 'rejected'], true)) {
            Log::warning('Payment webhook needs attention', [
                'gateway' => $row->gateway, 'event' => $row->event_id, 'status' => $status, 'reason' => $reason,
            ]);
        }
    }

    /** حقول معروفة فقط. أي شيء آخر في الإشعار (بطاقة، عنوان، بريد...) لا يُخزَّن */
    private function summary(string $id, string $type, array $object): array
    {
        $scalar = fn ($v) => is_scalar($v) ? (string) $v : null;

        return array_filter([
            'event'           => $id,
            'type'            => $type,
            'object_id'       => $scalar($object['id'] ?? null),
            'status'          => $scalar($object['status'] ?? null),
            'amount'          => $scalar($object['amount'] ?? null),
            'amount_received' => $scalar($object['amount_received'] ?? null),
            'currency'        => $scalar($object['currency'] ?? null),
            'payment_id'      => $scalar($object['metadata']['payment_id'] ?? null),
        ], fn ($v) => $v !== null);
    }
}
