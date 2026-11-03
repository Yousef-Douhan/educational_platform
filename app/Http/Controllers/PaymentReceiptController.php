<?php

namespace App\Http\Controllers;

use App\Exceptions\OrderException;
use App\Exceptions\ReceiptException;
use App\Models\PaymentReceipt;
use App\Services\ReceiptService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\Response;

/** مراجعة إشعارات التحويل: العرض بصلاحية receipts.view، والاعتماد/الرفض بصلاحية receipts.review */
class PaymentReceiptController extends Controller implements HasMiddleware
{
    public static function middleware(): array
    {
        return [
            new Middleware('permission:receipts.view', only: ['index', 'show', 'image']),
            new Middleware('permission:receipts.review', only: ['approve', 'reject']),
        ];
    }

    public function index(Request $request): View
    {
        $status = $request->query('status', 'pending');
        $status = is_string($status) ? $status : 'pending';

        $receipts = PaymentReceipt::query()
            ->with(['order', 'user'])
            ->withStatus($status)
            ->search(is_string($request->query('search')) ? $request->query('search') : null)
            ->orderByRaw("CASE WHEN payment_receipts.status = 'pending' THEN 0 ELSE 1 END")
            ->orderByDesc('payment_receipts.id')
            ->paginate(15)->withQueryString();

        $counts = PaymentReceipt::selectRaw('status, count(*) as c')->groupBy('status')->pluck('c', 'status');

        return view('cms.receipts.index', ['receipts' => $receipts, 'status' => $status, 'counts' => $counts]);
    }

    public function show(int $receipt): View
    {
        $row = PaymentReceipt::with(['order.items', 'order.user', 'user', 'reviewer'])->findOrFail($receipt);

        return view('cms.receipts.show', [
            'receipt' => $row,
            'method'  => config("payments.manual.methods.{$row->method}"),
            'others'  => PaymentReceipt::where('order_id', $row->order_id)->where('id', '!=', $row->id)->orderByDesc('id')->get(),
        ]);
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

    public function approve(Request $request, ReceiptService $receipts, int $receipt): RedirectResponse
    {
        $data = $request->validate(['note' => ['nullable', 'string', 'max:250']]);

        try {
            $receipts->approve(PaymentReceipt::findOrFail($receipt), $request->user(), $data['note'] ?? null);
        } catch (ReceiptException|OrderException $e) {
            return back()->withErrors(['receipt' => $e->getMessage()]);
        }

        return redirect()->route('cms.admin.receipts.show', $receipt)->with('status', __('receipts.messages.approved'));
    }

    public function reject(Request $request, ReceiptService $receipts, int $receipt): RedirectResponse
    {
        $data = $request->validate(['reason' => ['required', 'string', 'min:3', 'max:250']]);

        try {
            $receipts->reject(PaymentReceipt::findOrFail($receipt), $request->user(), $data['reason']);
        } catch (ReceiptException $e) {
            return back()->withErrors(['receipt' => $e->getMessage()]);
        }

        return redirect()->route('cms.admin.receipts.show', $receipt)->with('status', __('receipts.messages.rejected'));
    }
}
