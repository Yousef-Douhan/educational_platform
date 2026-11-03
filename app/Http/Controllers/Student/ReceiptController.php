<?php

namespace App\Http\Controllers\Student;

use App\Exceptions\OrderException;
use App\Exceptions\ReceiptException;
use App\Http\Controllers\Controller;
use App\Http\Requests\SubmitReceiptRequest;
use App\Models\Order;
use App\Models\PaymentReceipt;
use App\Services\ReceiptService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\Response;

/** رفع إشعار التحويل لطلب الطالب نفسه، وعرض صورته له وحده */
class ReceiptController extends Controller
{
    public function store(SubmitReceiptRequest $request, ReceiptService $receipts, string $number): RedirectResponse
    {
        $order = Order::where('user_id', $request->user()->id)->where('order_number', $number)->firstOrFail();

        try {
            $receipts->submit(
                $request->user(), $order,
                $request->validated('method'), $request->validated('sender_name'),
                $request->validated('reference_number'), $request->file('receipt'),
            );
        } catch (ReceiptException|OrderException $e) {
            return back()->withErrors(['receipt' => $e->getMessage()])->withInput($request->except('receipt'));
        }

        return redirect()->route('student.orders.show', $order->order_number)->with('status', __('receipts.messages.submitted'));
    }

    public function image(Request $request, int $receipt): Response
    {
        $row = PaymentReceipt::where('user_id', $request->user()->id)->findOrFail($receipt);

        abort_unless(Storage::disk('local')->exists($row->receipt_path), 404);

        return Storage::disk('local')->response($row->receipt_path, null, [
            'X-Content-Type-Options' => 'nosniff',
            'Content-Security-Policy' => "default-src 'none'; img-src 'self'",
            'Cache-Control' => 'private, no-store',
        ]);
    }
}
