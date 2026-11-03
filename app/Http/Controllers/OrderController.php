<?php

namespace App\Http\Controllers;

use App\Exceptions\OrderException;
use App\Http\Requests\OrderActionRequest;
use App\Models\Order;
use App\Services\OrderService;
use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\View\View;

/**
 * إدارة الطلبات: عرض + إجراءات (تأكيد دفع يدوي، إلغاء، استرداد).
 * الطلبات تُنشأ من واجهة الطالب عبر OrderService::checkout، وليس من هنا،
 * ولا يوجد تعديل ولا حذف لأنها سجلات مالية.
 */
class OrderController extends Controller implements HasMiddleware
{
    private const SORTABLE = ['id', 'total_amount', 'status', 'created_at'];

    public static function middleware(): array
    {
        return [
            new Middleware('permission:orders.view', only: ['index', 'show']),
            new Middleware('permission:orders.edit', only: ['cancel']),
            new Middleware('permission:orders.mark-paid', only: ['markPaid']),
            new Middleware('permission:orders.refund', only: ['refund']),
        ];
    }

    public function index(Request $request): View|JsonResponse
    {
        $sort = in_array($request->get('sort'), self::SORTABLE, true) ? $request->get('sort') : 'created_at';
        $dir  = $request->get('dir') === 'asc' ? 'asc' : 'desc';
        $per  = min(max((int) $request->get('per_page', 10), 5), 100);

        $orders = Order::query()
            ->with('user')
            ->withCount('items')
            ->search($request->get('search'))
            ->withStatus($request->get('status'))
            ->createdBetween($this->date($request->get('from')), $this->date($request->get('to')))
            ->orderBy("orders.{$sort}", $dir)
            ->orderBy('orders.id', 'desc')
            ->paginate($per)
            ->withQueryString();

        if ($request->expectsJson()) {
            return response()->json([
                'html'  => view('cms.orders.partials.table', compact('orders'))->render(),
                'stats' => $this->stats(),
            ]);
        }

        return view('cms.orders.index', ['orders' => $orders, 'stats' => $this->stats()]);
    }

    public function show(Request $request, Order $order): JsonResponse
    {
        return response()->json(['data' => $this->payload($order, $request)]);
    }

    public function markPaid(OrderActionRequest $request, Order $order, OrderService $service): JsonResponse
    {
        $data = $request->validated();

        return $this->run('marked_paid', fn () => $service->markPaid(
            $order, $data['gateway'], $data['transaction_reference'] ?? null, $data['note'] ?? null,
        ));
    }

    public function cancel(OrderActionRequest $request, Order $order, OrderService $service): JsonResponse
    {
        return $this->run('cancelled', fn () => $service->cancel($order, $request->validated('note')));
    }

    public function refund(OrderActionRequest $request, Order $order, OrderService $service): JsonResponse
    {
        return $this->run('refunded', fn () => $service->refund($order, $request->validated('note')));
    }

    /* ---------- داخلي ---------- */

    private function run(string $messageKey, Closure $action): JsonResponse
    {
        try {
            $order = $action();
        } catch (OrderException $e) {
            return response()->json(['message' => $e->getMessage()], 409);
        }

        return response()->json([
            'message' => __("orders.messages.{$messageKey}", ['number' => $order->order_number]),
            'stats'   => $this->stats(),
        ]);
    }

    private function date(?string $value): ?string
    {
        return is_string($value) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $value) ? $value : null;
    }

    private function stats(): array
    {
        $counts = Order::query()->selectRaw('status, COUNT(*) as aggregate')->groupBy('status')->pluck('aggregate', 'status');

        return [
            'total'    => (int) $counts->sum(),
            'pending'  => (int) ($counts['pending'] ?? 0),
            'paid'     => (int) ($counts['paid'] ?? 0),
            'refunded' => (int) ($counts['refunded'] ?? 0),
            'revenue'  => number_format((float) Order::where('status', 'paid')->sum('total_amount'), 2),
        ];
    }

    private function payload(Order $order, Request $request): array
    {
        $order->load(['user', 'items.course', 'coupon']);
        $tz  = config('app.timezone');
        $fmt = fn ($d) => $d?->copy()->timezone($tz)->format('Y-m-d H:i');
        $me  = $request->user();

        return [
            'id'                    => $order->id,
            'order_number'          => $order->order_number,
            'status'                => $order->status,
            'user'                  => [
                'name'    => $order->user->name,
                'email'   => $order->user->email,
                'deleted' => $order->user->trashed(),
            ],
            'items'                 => $order->items->map(fn ($i) => [
                'id'             => $i->id,
                'course_id'      => $i->course_id,
                'title_ar'       => $i->title_ar,
                'title_en'       => $i->title_en,
                'price'          => $i->price,
                'course_deleted' => $i->course?->trashed() ?? true,
            ])->all(),
            'subtotal'              => $order->subtotal,
            'discount_amount'       => $order->discount_amount,
            'total_amount'          => $order->total_amount,
            'currency'              => $order->currency,
            'coupon_code'           => $order->coupon_code,
            'gateway'               => $order->gateway,
            'transaction_reference' => $order->transaction_reference,
            'note'                  => $order->note,
            'paid_at'               => $fmt($order->paid_at),
            'refunded_at'           => $fmt($order->refunded_at),
            'created_at'            => $fmt($order->created_at),
            'can'                   => [
                'mark_paid' => $order->canTransitionTo('paid') && $me->can('orders.mark-paid'),
                'cancel'    => $order->canTransitionTo('cancelled') && $me->can('orders.edit'),
                'refund'    => $order->canTransitionTo('refunded') && $me->can('orders.refund'),
            ],
        ];
    }
}
