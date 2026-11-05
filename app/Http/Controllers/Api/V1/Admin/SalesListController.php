<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Models\Cart;
use App\Models\Coupon;
use App\Models\Order;
use App\Models\Payment;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * قوائم المبيعات للإدارة (طلبات، مدفوعات، سلات، كوبونات) بترقيم صفحات JSON.
 * الصلاحيات نفسها في الـCMS: orders.view / payments.view / carts.view / coupons.view.
 * العمليات الأخرى (show, markPaid, refund, clear, CRUD الكوبونات...) تُوجَّه إلى متحكمات الـCMS.
 */
class SalesListController extends Controller
{
    public function orders(Request $request): JsonResponse
    {
        $sort = $this->sort($request, ['id', 'total_amount', 'status', 'created_at'], 'created_at');

        $rows = Order::query()
            ->with('user:id,name,email,deleted_at')
            ->withCount('items')
            ->search($this->str($request, 'search'))
            ->withStatus($this->str($request, 'status'))
            ->createdBetween($this->date($request, 'from'), $this->date($request, 'to'))
            ->orderBy("orders.{$sort[0]}", $sort[1])
            ->orderByDesc('orders.id')
            ->paginate($this->per($request))
            ->withQueryString();

        return $this->page($rows, fn (Order $o) => [
            'id'           => $o->id,
            'order_number' => $o->order_number,
            'status'       => $o->status,
            'user'         => $this->user($o->user),
            'items_count'  => $o->items_count,
            'subtotal'     => $o->subtotal,
            'discount_amount' => $o->discount_amount,
            'total_amount' => $o->total_amount,
            'currency'     => $o->currency,
            'gateway'      => $o->gateway,
            'coupon_code'  => $o->coupon_code,
            'paid_at'      => $o->paid_at?->toIso8601String(),
            'created_at'   => $o->created_at?->toIso8601String(),
        ], [
            'stats' => $this->orderStats(),
        ]);
    }

    public function payments(Request $request): JsonResponse
    {
        $sort = $this->sort($request, ['id', 'amount', 'status', 'created_at'], 'created_at');

        $rows = Payment::query()
            ->with('order.user:id,name,email,deleted_at')
            ->search($this->str($request, 'search'))
            ->withStatus($this->str($request, 'status'))
            ->forGateway($this->str($request, 'gateway'))
            ->createdBetween($this->date($request, 'from'), $this->date($request, 'to'))
            ->orderBy("payments.{$sort[0]}", $sort[1])
            ->orderByDesc('payments.id')
            ->paginate($this->per($request))
            ->withQueryString();

        return $this->page($rows, fn (Payment $p) => [
            'id'             => $p->id,
            'status'         => $p->status,
            'gateway'        => $p->gateway,
            'payment_method' => $p->payment_method,
            'amount'         => $p->amount,
            'currency'       => $p->currency,
            'transaction_reference' => $p->transaction_reference,
            'order_id'       => $p->order_id,
            'order_number'   => $p->order?->order_number,
            'user'           => $this->user($p->order?->user),
            'created_at'     => $p->created_at?->toIso8601String(),
        ], [
            'stats' => $this->paymentStats(),
        ]);
    }

    public function carts(Request $request): JsonResponse
    {
        $sort = $this->sort($request, ['id', 'items_count', 'total', 'updated_at', 'created_at'], 'updated_at');
        $col  = in_array($sort[0], ['items_count', 'total'], true) ? $sort[0] : "carts.{$sort[0]}";

        $rows = Cart::query()
            ->with('user:id,name,email,deleted_at')
            ->withSummary()
            ->search($this->str($request, 'search'))
            ->inState($this->str($request, 'state'))
            ->orderBy($col, $sort[1])
            ->orderByDesc('carts.id')
            ->paginate($this->per($request))
            ->withQueryString();

        return $this->page($rows, fn (Cart $c) => [
            'id'          => $c->id,
            'state'       => $c->state(),
            'user'        => $this->user($c->user),
            'items_count' => (int) $c->items_count,
            'total'       => $c->total,
            'updated_at'  => $c->updated_at?->toIso8601String(),
        ], [
            'stats' => [
                'total'     => Cart::count(),
                'active'    => Cart::inState('active')->count(),
                'abandoned' => Cart::inState('abandoned')->count(),
                'empty'     => Cart::inState('empty')->count(),
            ],
        ]);
    }

    public function coupons(Request $request): JsonResponse
    {
        $sort = $this->sort($request, ['id', 'code', 'discount_value', 'times_used', 'expires_at', 'created_at'], 'id');

        $rows = Coupon::query()
            ->search($this->str($request, 'search'))
            ->withStatus($this->str($request, 'status'))
            ->ofType($this->str($request, 'type'))
            ->orderBy($sort[0], $sort[1])
            ->orderByDesc('id')
            ->paginate($this->per($request))
            ->withQueryString();

        return $this->page($rows, fn (Coupon $c) => $this->coupon($c), [
            'stats' => $this->couponStats(),
        ]);
    }

    public function trashedCoupons(Request $request): JsonResponse
    {
        $rows = Coupon::onlyTrashed()
            ->search($this->str($request, 'search'))
            ->latest('deleted_at')
            ->paginate($this->per($request))
            ->withQueryString();

        return $this->page($rows, fn (Coupon $c) => $this->coupon($c) + ['deleted_at' => $c->deleted_at?->toIso8601String()], [
            'stats' => $this->couponStats(),
        ]);
    }

    /* ---------- داخلي ---------- */

    private function coupon(Coupon $c): array
    {
        return [
            'id'               => $c->id,
            'code'             => $c->code,
            'discount_type'    => $c->discount_type,
            'discount_value'   => $c->discount_value,
            'max_discount'     => $c->max_discount,
            'min_order_amount' => $c->min_order_amount,
            'usage_limit'      => $c->usage_limit,
            'per_user_limit'   => $c->per_user_limit,
            'times_used'       => $c->times_used,
            'is_active'        => (bool) $c->is_active,
            'status'           => $c->status(),
            'starts_at'        => $c->starts_at?->toIso8601String(),
            'expires_at'       => $c->expires_at?->toIso8601String(),
        ];
    }

    private function orderStats(): array
    {
        $counts = Order::query()->selectRaw('status, COUNT(*) as aggregate')->groupBy('status')->pluck('aggregate', 'status');

        return [
            'total'    => (int) $counts->sum(),
            'pending'  => (int) ($counts['pending'] ?? 0),
            'paid'     => (int) ($counts['paid'] ?? 0),
            'refunded' => (int) ($counts['refunded'] ?? 0),
            'revenue'  => number_format((float) Order::where('status', 'paid')->sum('total_amount'), 2, '.', ''),
        ];
    }

    private function paymentStats(): array
    {
        $counts = Payment::query()->selectRaw('status, COUNT(*) as aggregate')->groupBy('status')->pluck('aggregate', 'status');

        return [
            'total'     => (int) $counts->sum(),
            'success'   => (int) ($counts['success'] ?? 0),
            'pending'   => (int) ($counts['pending'] ?? 0),
            'failed'    => (int) ($counts['failed'] ?? 0),
            'collected' => number_format((float) Payment::where('status', 'success')->sum('amount'), 2, '.', ''),
        ];
    }

    private function couponStats(): array
    {
        return [
            'total'     => Coupon::count(),
            'active'    => Coupon::withStatus('active')->count(),
            'expired'   => Coupon::withStatus('expired')->count(),
            'exhausted' => Coupon::withStatus('exhausted')->count(),
            'trashed'   => Coupon::onlyTrashed()->count(),
        ];
    }

    private function user($user): ?array
    {
        return $user ? ['id' => $user->id, 'name' => $user->name, 'email' => $user->email, 'deleted' => $user->trashed()] : null;
    }

    private function page($paginator, callable $map, array $extra = []): JsonResponse
    {
        return response()->json([
            'data' => $paginator->getCollection()->map($map)->values()->all(),
            'meta' => [
                'current_page' => $paginator->currentPage(),
                'last_page'    => $paginator->lastPage(),
                'per_page'     => $paginator->perPage(),
                'total'        => $paginator->total(),
            ],
        ] + $extra);
    }

    private function str(Request $request, string $key): ?string
    {
        $v = $request->query($key);

        return is_string($v) && $v !== '' ? $v : null;
    }

    private function date(Request $request, string $key): ?string
    {
        $v = $this->str($request, $key);

        return $v !== null && preg_match('/^\d{4}-\d{2}-\d{2}$/', $v) ? $v : null;
    }

    private function per(Request $request): int
    {
        return min(max((int) $request->query('per_page', 15), 5), 100);
    }

    /** @return array{0:string,1:string} */
    private function sort(Request $request, array $allowed, string $default): array
    {
        $s = $request->query('sort');

        return [in_array($s, $allowed, true) ? $s : $default, $request->query('dir') === 'asc' ? 'asc' : 'desc'];
    }
}
