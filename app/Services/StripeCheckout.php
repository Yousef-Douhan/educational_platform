<?php

namespace App\Services;

use App\Exceptions\GatewayException;
use App\Models\Order;
use App\Models\Payment;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * ينشئ جلسة Stripe Checkout لدفعة معلّقة. المبلغ والعملة من سجلاتنا (Payment) لا من الطالب،
 * ومعرّف دفعتنا يُرسل في metadata فيعيده Stripe في إشعار payment_intent.succeeded
 * (المعالَج في WebhookProcessor، وهو الذي يؤكد الدفع ويفتح الدورات). الرجوع من Stripe لا يؤكد شيئًا.
 */
class StripeCheckout
{
    public function configured(): bool
    {
        return filled(config('payments.stripe.secret'));
    }

    /** @return string رابط صفحة الدفع على Stripe */
    public function createSession(Order $order, Payment $payment, string $successUrl, string $cancelUrl): string
    {
        $secret = config('payments.stripe.secret');

        if (blank($secret)) {
            throw GatewayException::because('not_configured');
        }

        $cents = (int) round(((float) $payment->amount) * 100);

        try {
            $response = Http::withToken($secret)
                ->withHeaders(['Idempotency-Key' => 'payment-' . $payment->id])
                ->asForm()->timeout(15)
                ->post(rtrim((string) config('payments.stripe.api'), '/') . '/checkout/sessions', [
                    'mode'                                                  => 'payment',
                    'client_reference_id'                                   => $order->order_number,
                    'success_url'                                           => $successUrl,
                    'cancel_url'                                            => $cancelUrl,
                    'line_items[0][quantity]'                               => 1,
                    'line_items[0][price_data][currency]'                   => strtolower($payment->currency),
                    'line_items[0][price_data][unit_amount]'                => $cents,
                    'line_items[0][price_data][product_data][name]'         => 'Order ' . $order->order_number,
                    'metadata[payment_id]'                                  => $payment->id,
                    'payment_intent_data[metadata][payment_id]'             => $payment->id,
                    'payment_intent_data[description]'                      => 'Order ' . $order->order_number,
                ]);
        } catch (ConnectionException) {
            throw GatewayException::because('unreachable');
        }

        if ($response->failed()) {
            // نسجّل نوع الخطأ فقط: لا الجسم الكامل ولا أي بيانات
            Log::warning('Stripe checkout session rejected', [
                'status' => $response->status(), 'type' => $response->json('error.type'), 'code' => $response->json('error.code'),
                'payment_id' => $payment->id,
            ]);

            throw GatewayException::because('rejected');
        }

        $url   = $response->json('url');
        $parts = is_string($url) ? parse_url($url) : false;

        // لا نحوّل الطالب إلا إلى صفحة دفع حقيقية على نطاق Stripe
        if (! $parts || ($parts['scheme'] ?? '') !== 'https' || ! in_array(strtolower($parts['host'] ?? ''), ['checkout.stripe.com'], true)) {
            Log::warning('Stripe checkout returned an unexpected url', ['payment_id' => $payment->id]);

            throw GatewayException::because('bad_response');
        }

        return $url;
    }
}
