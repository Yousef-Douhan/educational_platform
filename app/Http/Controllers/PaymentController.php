<?php

namespace App\Http\Controllers;

use App\Models\Payment;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\View\View;

/**
 * سجل المدفوعات للقراءة فقط. الدفعات تُكتب من OrderService (يدوي/مجاني) ومن
 * PaymentService (البوابات)، ولا يوجد إنشاء ولا تعديل ولا حذف لأنه سجل مالي.
 */
class PaymentController extends Controller implements HasMiddleware
{
    private const SORTABLE = ['id', 'amount', 'status', 'created_at'];

    public static function middleware(): array
    {
        return [new Middleware('permission:payments.view')];
    }

    public function index(Request $request): View|JsonResponse
    {
        $sort = in_array($request->get('sort'), self::SORTABLE, true) ? $request->get('sort') : 'created_at';
        $dir  = $request->get('dir') === 'asc' ? 'asc' : 'desc';
        $per  = min(max((int) $request->get('per_page', 10), 5), 100);

        $payments = Payment::query()
            ->with('order.user')
            ->search($request->get('search'))
            ->withStatus($request->get('status'))
            ->forGateway($request->get('gateway'))
            ->createdBetween($this->date($request->get('from')), $this->date($request->get('to')))
            ->orderBy("payments.{$sort}", $dir)
            ->orderBy('payments.id', 'desc')
            ->paginate($per)
            ->withQueryString();

        if ($request->expectsJson()) {
            return response()->json([
                'html'  => view('cms.payments.partials.table', compact('payments'))->render(),
                'stats' => $this->stats(),
            ]);
        }

        return view('cms.payments.index', [
            'payments' => $payments,
            'stats'    => $this->stats(),
            'gateways' => Payment::query()->distinct()->orderBy('gateway')->pluck('gateway'),
        ]);
    }

    public function show(Payment $payment): JsonResponse
    {
        $payment->load('order.user');
        $tz  = config('app.timezone');
        $fmt = fn ($d) => $d?->copy()->timezone($tz)->format('Y-m-d H:i');

        return response()->json(['data' => [
            'id'                    => $payment->id,
            'status'                => $payment->status,
            'gateway'               => $payment->gateway,
            'payment_method'        => $payment->payment_method,
            'amount'                => $payment->amount,
            'currency'              => $payment->currency,
            'transaction_reference' => $payment->transaction_reference,
            'order_number'          => $payment->order->order_number,
            'order_id'              => $payment->order_id,
            'user'                  => [
                'name'    => $payment->order->user->name,
                'email'   => $payment->order->user->email,
                'deleted' => $payment->order->user->trashed(),
            ],
            // نص منسّق: يُعرض بـ textContent فقط، فلا يُنفَّذ أي شيء داخله
            'gateway_response'      => $payment->gateway_response === null
                ? null
                : json_encode($payment->gateway_response, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            'created_at'            => $fmt($payment->created_at),
            'paid_at'               => $fmt($payment->paid_at),
            'refunded_at'           => $fmt($payment->refunded_at),
        ]]);
    }

    private function date(?string $value): ?string
    {
        return is_string($value) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $value) ? $value : null;
    }

    private function stats(): array
    {
        $counts = Payment::query()->selectRaw('status, COUNT(*) as aggregate')->groupBy('status')->pluck('aggregate', 'status');

        return [
            'total'     => (int) $counts->sum(),
            'success'   => (int) ($counts['success'] ?? 0),
            'pending'   => (int) ($counts['pending'] ?? 0),
            'failed'    => (int) ($counts['failed'] ?? 0),
            'collected' => number_format((float) Payment::where('status', 'success')->sum('amount'), 2),
        ];
    }
}
