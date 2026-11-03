<?php

namespace App\Http\Controllers\Student;

use App\Http\Controllers\Controller;
use App\Models\Order;
use Illuminate\Http\Request;
use Illuminate\View\View;

/** طلبات الطالب نفسه فقط. رقم الطلب العشوائي في الرابط، وملكية الطلب شرط في الاستعلام */
class OrderController extends Controller
{
    public function index(Request $request): View
    {
        $orders = Order::where('user_id', $request->user()->id)
            ->withCount('items')->orderByDesc('id')->paginate(10);

        return view('student.orders.index', ['orders' => $orders]);
    }

    public function show(Request $request, string $number): View
    {
        $order = Order::where('user_id', $request->user()->id)
            ->where('order_number', $number)->with('items')->firstOrFail();

        return view('student.orders.show', [
            'order'    => $order,
            'methods'  => app(\App\Services\ReceiptService::class)->methods(),
            'receipts' => \App\Models\PaymentReceipt::where('order_id', $order->id)->orderByDesc('id')->get(),
            'canPay' => app(\App\Services\StripeCheckout::class)->configured() && (float) $order->total_amount > 0,
        ]);
    }
}
