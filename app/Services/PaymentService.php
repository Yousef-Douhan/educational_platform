<?php

namespace App\Services;

use App\Exceptions\OrderException;
use App\Models\Order;
use App\Models\Payment;
use Illuminate\Support\Facades\DB;

/**
 * واجهة بوابات الدفع (Stripe وغيرها) مع الطلبات. معالجات الإشعارات webhook
 * تستدعي هذه الدوال فقط، ولا تغيّر الطلب أو التسجيل مباشرة.
 *
 *   start()   ──►  pending ──► succeed()  ──► الطلب paid + تسجيل الطالب
 *                          └─► fail()     ──► failed (الطلب يبقى pending ليعيد الطالب المحاولة)
 *
 * الدفع اليدوي من الإدارة لا يمر من هنا: OrderService::markPaid يكتب صف الدفعة بنفسه.
 */
class PaymentService
{
    public function __construct(private readonly OrderService $orders) {}

    /** يفتح محاولة دفع لطلب معلّق بمبلغ الطلب نفسه (لا يأتي المبلغ من الطالب) */
    public function start(Order $order, string $gateway, string $method): Payment
    {
        return DB::transaction(function () use ($order, $gateway, $method) {
            $locked = Order::whereKey($order->getKey())->lockForUpdate()->firstOrFail();

            if ($locked->status !== 'pending') {
                throw OrderException::because('order_not_payable');
            }

            if ((float) $locked->total_amount <= 0) {
                // الطلب المجاني يُعتمد تلقائيًا عند إنشائه ولا يحتاج دفعة
                throw OrderException::because('nothing_to_pay');
            }

            return $this->insert([
                'order_id'       => $locked->id,
                'gateway'        => $gateway,
                'payment_method' => $method,
                'amount'         => $locked->total_amount,
                'currency'       => $locked->currency,
                'status'         => 'pending',
            ]);
        });
    }

    /**
     * تأكيد البوابة للدفع. تكرار الإشعار لنفس الدفعة يعيدها كما هي (idempotent).
     * يُرفض إن اختلف المبلغ أو العملة، أو لم يعد الطلب معلّقًا (يبقى الإشعار للمراجعة).
     */
    public function succeed(Payment $payment, string $reference, array $response = []): Payment
    {
        $reference = trim($reference);

        return DB::transaction(function () use ($payment, $reference, $response) {
            $locked = Payment::whereKey($payment->getKey())->lockForUpdate()->firstOrFail();

            if ($locked->status === 'success') {
                return $locked;
            }

            if ($locked->status !== 'pending') {
                throw OrderException::because('payment_not_pending');
            }

            $order = Order::whereKey($locked->order_id)->lockForUpdate()->firstOrFail();

            if ($order->status === 'paid') {
                throw OrderException::because('order_already_paid');
            }

            if ($order->status !== 'pending') {
                throw OrderException::because('order_not_payable');
            }

            if (! $this->sameMoney($locked, $order)) {
                throw OrderException::because('amount_mismatch');
            }

            if ($reference === '') {
                throw OrderException::because('reference_required');
            }

            $locked->forceFill(['gateway_response' => Payment::sanitize($response)])->save();

            // markPaid يثبّت الدفعة ويفعّل التسجيل وينظّف السلة في المعاملة نفسها
            $this->orders->markPaid($order, $locked->gateway, $reference, null, $locked);

            return $locked->refresh();
        });
    }

    /** فشل الدفع: الطلب يبقى معلّقًا فيستطيع الطالب إعادة المحاولة قبل انتهاء المهلة */
    public function fail(Payment $payment, array $response = []): Payment
    {
        return DB::transaction(function () use ($payment, $response) {
            $locked = Payment::whereKey($payment->getKey())->lockForUpdate()->firstOrFail();

            if ($locked->status === 'failed') {
                return $locked;
            }

            if ($locked->status !== 'pending') {
                throw OrderException::because('payment_not_pending');
            }

            $locked->forceFill(['status' => 'failed', 'gateway_response' => Payment::sanitize($response)])->save();

            return $locked;
        });
    }

    private function sameMoney(Payment $payment, Order $order): bool
    {
        return (int) round(((float) $payment->amount) * 100) === (int) round(((float) $order->total_amount) * 100)
            && strtoupper($payment->currency) === strtoupper($order->currency);
    }

    private function insert(array $attributes): Payment
    {
        $payment = new Payment;
        $payment->forceFill($attributes)->save();

        return $payment;
    }
}
