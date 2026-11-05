<?php

namespace App\Http\Controllers\Api\V1\Student;

use App\Exceptions\GatewayException;
use App\Exceptions\OrderException;
use App\Exceptions\ReceiptException;
use App\Http\Controllers\Api\V1\Concerns\RespondsWithErrors;
use App\Http\Controllers\Controller;
use App\Http\Requests\SubmitReceiptRequest;
use App\Http\Resources\V1\OrderResource;
use App\Models\Order;
use App\Models\Payment;
use App\Models\PaymentReceipt;
use App\Services\PaymentService;
use App\Services\ReceiptService;
use App\Services\StripeCheckout;
use App\Support\Loc;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\Response;

/** طلبات الطالب نفسه فقط: الملكية شرط في كل استعلام، والغريب 404 لا 403. */
class OrderController extends Controller
{
    use RespondsWithErrors;

    public function index(Request $request): JsonResponse
    {
        $orders = Order::where('user_id', $request->user()->id)
            ->withCount('items')->orderByDesc('id')->paginate(10);

        return OrderResource::collection($orders)->response();
    }

    public function show(Request $request, ReceiptService $receipts, StripeCheckout $stripe, string $number): JsonResponse
    {
        $order = $this->own($request, $number)->load('items');

        return response()->json(['data' => (new OrderResource($order))->resolve($request) + [
            'can_pay_online'  => $stripe->configured() && (float) $order->total_amount > 0 && $order->status === 'pending',
            'manual_methods'  => $this->methods($receipts),
            'receipts'        => PaymentReceipt::where('order_id', $order->id)->orderByDesc('id')->get()
                ->map(fn (PaymentReceipt $r) => $this->receipt($r))->values(),
        ]]);
    }

    /** يبدأ دفع Stripe المستضاف ويعيد الرابط لفتحه في المتصفح. المبلغ دائمًا من الطلب المخزّن */
    public function pay(Request $request, PaymentService $payments, StripeCheckout $stripe, string $number): JsonResponse
    {
        $order = $this->own($request, $number);

        if (! $stripe->configured()) {
            return $this->failMessage(__('student.pay.errors.not_configured'), 'not_configured', 503);
        }

        try {
            $payment = DB::transaction(function () use ($order, $payments) {
                Payment::where(['order_id' => $order->id, 'gateway' => 'stripe', 'status' => 'pending'])->get()
                    ->each(fn (Payment $old) => $payments->fail($old, ['reason' => 'superseded']));

                return $payments->start($order, 'stripe', 'card');
            });
        } catch (OrderException $e) {
            return $this->fail($e);
        }

        try {
            $url = $stripe->createSession(
                $order, $payment,
                route('student.orders.show', [$order->order_number, 'paid' => 1]),
                route('student.orders.show', [$order->order_number, 'canceled' => 1]),
            );
        } catch (GatewayException $e) {
            $payments->fail($payment, ['reason' => $e->reason]);

            return $this->fail($e, 502);
        }

        return response()->json(['data' => ['checkout_url' => $url]]);
    }

    /** طرق التحويل المفعّلة (ما له رقم حساب فقط) */
    public function methodsList(ReceiptService $receipts): JsonResponse
    {
        return response()->json(['data' => $this->methods($receipts)]);
    }

    /** رفع إشعار التحويل (multipart): صورة فعلية فقط، يتحقق منها SubmitReceiptRequest والخدمة */
    public function submitReceipt(SubmitReceiptRequest $request, ReceiptService $receipts, string $number): JsonResponse
    {
        $order = $this->own($request, $number);

        try {
            $receipt = $receipts->submit(
                $request->user(), $order,
                $request->validated('method'), $request->validated('sender_name'),
                $request->validated('reference_number'), $request->file('receipt'),
            );
        } catch (ReceiptException|OrderException $e) {
            return $this->fail($e);
        }

        return response()->json(['message' => __('receipts.messages.submitted'), 'data' => $this->receipt($receipt)], 201);
    }

    public function receiptImage(Request $request, int $receipt): Response
    {
        $row = PaymentReceipt::where('user_id', $request->user()->id)->findOrFail($receipt);
        abort_unless(Storage::disk('local')->exists($row->receipt_path), 404);

        return Storage::disk('local')->response($row->receipt_path, null, [
            'X-Content-Type-Options'  => 'nosniff',
            'Content-Security-Policy' => "default-src 'none'; img-src 'self'",
            'Cache-Control'           => 'private, no-store',
        ]);
    }

    /* ---------- داخلي ---------- */

    private function own(Request $request, string $number): Order
    {
        return Order::where('user_id', $request->user()->id)->where('order_number', $number)->firstOrFail();
    }

    private function methods(ReceiptService $receipts): array
    {
        $out = [];
        foreach ($receipts->methods() as $key => $m) {
            $out[] = [
                'key' => $key, 'label' => Loc::pick($m['label_ar'] ?? null, $m['label_en'] ?? null),
                'account' => $m['account'] ?? null, 'iban' => $m['iban'] ?? null, 'holder' => $m['holder'] ?? null,
            ];
        }

        return $out;
    }

    private function receipt(PaymentReceipt $r): array
    {
        return [
            'id' => $r->id, 'method' => $r->method, 'sender_name' => $r->sender_name,
            'reference_number' => $r->reference_number, 'status' => $r->status,
            'review_note' => $r->status === 'rejected' ? $r->review_note : null,
            'image_url' => route('api.receipts.image', $r->id),
            'created_at' => $r->created_at?->toIso8601String(),
        ];
    }
}
