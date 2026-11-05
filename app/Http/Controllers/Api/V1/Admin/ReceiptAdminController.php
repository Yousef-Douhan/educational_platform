<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Exceptions\OrderException;
use App\Exceptions\ReceiptException;
use App\Http\Controllers\Api\V1\Concerns\RespondsWithErrors;
use App\Http\Controllers\Controller;
use App\Models\PaymentReceipt;
use App\Services\ReceiptService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\Response;

/** مراجعة إشعارات التحويل عبر API: العرض receipts.view، والاعتماد/الرفض receipts.review (كالـCMS). */
class ReceiptAdminController extends Controller implements HasMiddleware
{
    use RespondsWithErrors;

    public static function middleware(): array
    {
        return [
            new Middleware('permission:receipts.view', only: ['index', 'show', 'image']),
            new Middleware('permission:receipts.review', only: ['approve', 'reject']),
        ];
    }

    public function index(Request $request): JsonResponse
    {
        $status = $request->query('status', 'pending');
        $status = is_string($status) ? $status : 'pending';
        $search = $request->query('search');

        $rows = PaymentReceipt::query()
            ->with(['order:id,order_number,total_amount,currency,status', 'user:id,name,email,deleted_at'])
            ->withStatus($status)
            ->search(is_string($search) ? $search : null)
            ->orderByRaw("CASE WHEN payment_receipts.status = 'pending' THEN 0 ELSE 1 END")
            ->orderByDesc('payment_receipts.id')
            ->paginate(min(max((int) $request->query('per_page', 15), 5), 100))
            ->withQueryString();

        return response()->json([
            'data' => $rows->getCollection()->map(fn (PaymentReceipt $r) => $this->row($r))->values()->all(),
            'meta' => [
                'current_page' => $rows->currentPage(),
                'last_page'    => $rows->lastPage(),
                'per_page'     => $rows->perPage(),
                'total'        => $rows->total(),
            ],
            'counts' => PaymentReceipt::selectRaw('status, count(*) as c')->groupBy('status')->pluck('c', 'status'),
        ]);
    }

    public function show(int $receipt): JsonResponse
    {
        $row = PaymentReceipt::with(['order.items', 'user', 'reviewer'])->findOrFail($receipt);

        return response()->json(['data' => $this->row($row) + [
            'order_items' => $row->order->items->map(fn ($i) => [
                'course_id' => $i->course_id, 'title_ar' => $i->title_ar, 'title_en' => $i->title_en, 'price' => $i->price,
            ])->all(),
            'reviewer'    => $row->reviewer ? ['id' => $row->reviewer->id, 'name' => $row->reviewer->name] : null,
            'reviewed_at' => $row->reviewed_at?->toIso8601String(),
            'review_note' => $row->review_note,
            'others'      => PaymentReceipt::where('order_id', $row->order_id)->where('id', '!=', $row->id)
                ->orderByDesc('id')->get(['id', 'status', 'reference_number', 'created_at'])->all(),
        ]]);
    }

    public function image(int $receipt): Response
    {
        $row = PaymentReceipt::findOrFail($receipt);
        abort_unless(Storage::disk('local')->exists($row->receipt_path), 404);

        return Storage::disk('local')->response($row->receipt_path, null, [
            'X-Content-Type-Options'  => 'nosniff',
            'Content-Security-Policy' => "default-src 'none'; img-src 'self'",
            'Cache-Control'           => 'private, no-store',
        ]);
    }

    public function approve(Request $request, ReceiptService $receipts, int $receipt): JsonResponse
    {
        $data = $request->validate(['note' => ['nullable', 'string', 'max:250']]);

        try {
            $row = $receipts->approve(PaymentReceipt::findOrFail($receipt), $request->user(), $data['note'] ?? null);
        } catch (ReceiptException|OrderException $e) {
            return $this->fail($e, 409);
        }

        return response()->json(['message' => __('receipts.messages.approved'), 'data' => $this->row($row->fresh(['order', 'user']))]);
    }

    public function reject(Request $request, ReceiptService $receipts, int $receipt): JsonResponse
    {
        $data = $request->validate(['reason' => ['required', 'string', 'min:3', 'max:250']]);

        try {
            $row = $receipts->reject(PaymentReceipt::findOrFail($receipt), $request->user(), $data['reason']);
        } catch (ReceiptException $e) {
            return $this->fail($e, 409);
        }

        return response()->json(['message' => __('receipts.messages.rejected'), 'data' => $this->row($row->fresh(['order', 'user']))]);
    }

    private function row(PaymentReceipt $r): array
    {
        return [
            'id'               => $r->id,
            'status'           => $r->status,
            'method'           => $r->method,
            'sender_name'      => $r->sender_name,
            'reference_number' => $r->reference_number,
            'order'            => $r->order ? [
                'id' => $r->order->id, 'order_number' => $r->order->order_number,
                'total_amount' => $r->order->total_amount, 'currency' => $r->order->currency, 'status' => $r->order->status,
            ] : null,
            'user'             => $r->user ? ['id' => $r->user->id, 'name' => $r->user->name, 'email' => $r->user->email] : null,
            'created_at'       => $r->created_at?->toIso8601String(),
        ];
    }
}
