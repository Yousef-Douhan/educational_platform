<?php

namespace App\Http\Controllers;

use App\Services\WebhookProcessor;
use App\Services\WebhookSignature;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use InvalidArgumentException;

/**
 * نقطة استقبال إشعارات البوابات. بلا جلسة ولا CSRF (تُسجَّل خارج مجموعة web)،
 * وأمانها كله في التوقيع. الرفض لا يكشف سببه للمرسل.
 */
class PaymentWebhookController extends Controller
{
    public function __invoke(Request $request, string $gateway, WebhookProcessor $processor): JsonResponse
    {
        $config = config("payments.webhooks.gateways.{$gateway}");

        if (! is_array($config)) {
            abort(404);
        }

        // بلا سرّ مضبوط نرفض بدل أن نقبل بلا تحقق
        if (blank($config['secret'] ?? null)) {
            report(new \RuntimeException("Webhook secret for [{$gateway}] is not configured."));

            return response()->json(['message' => 'unavailable'], 503);
        }

        $payload = $request->getContent();

        if (strlen($payload) > (int) config('payments.webhooks.max_bytes')) {
            return response()->json(['message' => 'too_large'], 413);
        }

        $valid = WebhookSignature::verify(
            $payload,
            $request->header($config['header'] ?? 'Signature'),
            $config['secret'],
            (int) config('payments.webhooks.tolerance'),
        );

        if (! $valid) {
            return response()->json(['message' => 'invalid_signature'], 400);
        }

        $event = json_decode($payload, true);

        if (! is_array($event)) {
            return response()->json(['message' => 'invalid_payload'], 400);
        }

        try {
            [$row, $duplicate] = $processor->handle($gateway, $event);
        } catch (InvalidArgumentException) {
            return response()->json(['message' => 'invalid_payload'], 400);
        }

        // 200 حتى لو تعذّر التطبيق: إعادة الإرسال لن تغيّر النتيجة، والمراجعة من السجل.
        // أما خطأ غير متوقع (قاعدة بيانات...) فيرتفع 500 فتعيد البوابة المحاولة.
        return response()->json(['status' => $row->status, 'duplicate' => $duplicate]);
    }
}
