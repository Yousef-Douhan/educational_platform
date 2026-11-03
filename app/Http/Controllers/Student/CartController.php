<?php

namespace App\Http\Controllers\Student;

use App\Exceptions\CartException;
use App\Http\Controllers\Controller;
use App\Models\Course;
use App\Services\CartService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class CartController extends Controller
{
    public function show(Request $request, CartService $carts): View
    {
        $cart = $carts->forUser($request->user());
        $rows = $carts->inspect($cart);

        return view('student.cart.show', ['rows' => $rows, 'subtotal' => $carts->subtotal($rows)]);
    }

    public function add(Request $request, CartService $carts): RedirectResponse
    {
        $data   = $request->validate(['course_id' => ['required', 'integer', 'min:1']]);
        $course = Course::findOrFail($data['course_id']);

        try {
            $carts->add($request->user(), $course);
        } catch (CartException $e) {
            return back()->withErrors(['cart' => $e->getMessage()]);
        }

        return redirect()->route('student.cart.show')->with('status', __('student.messages.added'));
    }

    public function remove(Request $request, CartService $carts, int $course): RedirectResponse
    {
        $carts->remove($request->user(), $course);

        return redirect()->route('student.cart.show')->with('status', __('student.messages.removed'));
    }
}
