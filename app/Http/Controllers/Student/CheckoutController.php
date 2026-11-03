<?php

namespace App\Http\Controllers\Student;

use App\Exceptions\CouponException;
use App\Exceptions\OrderException;
use App\Http\Controllers\Controller;
use App\Services\OrderService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class CheckoutController extends Controller
{
    public function store(Request $request, OrderService $orders): RedirectResponse
    {
        $data = $request->validate(['coupon' => ['nullable', 'string', 'max:50']]);

        try {
            $order = $orders->checkout($request->user(), $data['coupon'] ?? null);
        } catch (OrderException|CouponException $e) {
            return redirect()->route('student.cart.show')->withErrors(['checkout' => $e->getMessage()])->withInput();
        }

        return redirect()->route('student.orders.show', $order->order_number);
    }
}
