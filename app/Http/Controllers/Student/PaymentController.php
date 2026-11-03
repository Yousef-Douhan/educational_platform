<?php

namespace App\Http\Controllers\Student;

use App\Exceptions\GatewayException;
use App\Exceptions\OrderException;
use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\Payment;
use App\Services\PaymentService;
use App\Services\StripeCheckout;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/** بدء دفع طلب الطالب نفسه عبر Stripe Checkout. المبلغ دائمًا من الطلب المخزّن */
class PaymentController extends Controller
{
    public function store(Request $request, PaymentService $payments, StripeCheckout $stripe, string $number): RedirectResponse
    {
        $order = Order::where('user_id', $request->user()->id)->where('order_number', $number)->firstOrFail();

        if (! $stripe->configured()) {
            return back()->withErrors(['pay' => __('student.pay.errors.not_configured')]);
        }

        try {
            $payment = DB::transaction(function () use ($order, $payments) {
                // محاولات Stripe المعلّقة السابقة لنفس الطلب تُغلق: محاولة واحدة نشطة في كل مرة
                Payment::where(['order_id' => $order->id, 'gateway' => 'stripe', 'status' => 'pending'])->get()
                    ->each(fn (Payment $old) => $payments->fail($old, ['reason' => 'superseded']));

                return $payments->start($order, 'stripe', 'card');
            });
        } catch (OrderException $e) {
            return back()->withErrors(['pay' => $e->getMessage()]);
        }

        try {
            $url = $stripe->createSession(
                $order, $payment,
                route('student.orders.show', [$order->order_number, 'paid' => 1]),
                route('student.orders.show', [$order->order_number, 'canceled' => 1]),
            );
        } catch (GatewayException $e) {
            $payments->fail($payment, ['reason' => $e->reason]);

            return back()->withErrors(['pay' => $e->getMessage()]);
        }

        return redirect()->away($url);
    }
}
