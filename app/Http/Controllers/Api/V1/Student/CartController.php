<?php

namespace App\Http\Controllers\Api\V1\Student;

use App\Exceptions\CartException;
use App\Exceptions\CouponException;
use App\Exceptions\OrderException;
use App\Http\Controllers\Api\V1\Concerns\RespondsWithErrors;
use App\Http\Controllers\Controller;
use App\Http\Resources\V1\OrderResource;
use App\Models\Course;
use App\Services\CartService;
use App\Services\OrderService;
use App\Support\Loc;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CartController extends Controller
{
    use RespondsWithErrors;

    public function show(Request $request, CartService $carts): JsonResponse
    {
        return $this->cart($request, $carts);
    }

    public function add(Request $request, CartService $carts): JsonResponse
    {
        $data   = $request->validate(['course_id' => ['required', 'integer', 'min:1']]);
        $course = Course::findOrFail($data['course_id']);

        try {
            $carts->add($request->user(), $course);
        } catch (CartException $e) {
            return $this->fail($e);
        }

        return $this->cart($request, $carts, 201);
    }

    public function remove(Request $request, CartService $carts, int $course): JsonResponse
    {
        $carts->remove($request->user(), $course);

        return $this->cart($request, $carts);
    }

    /** السعر النهائي والخصم يُثبَّتان في الخادم عند إنشاء الطلب؛ المرسل هو رمز الكوبون فقط */
    public function checkout(Request $request, OrderService $orders): JsonResponse
    {
        $data = $request->validate(['coupon' => ['nullable', 'string', 'max:50']]);

        try {
            $order = $orders->checkout($request->user(), $data['coupon'] ?? null);
        } catch (OrderException|CouponException $e) {
            return $this->fail($e);
        }

        return response()->json(['data' => new OrderResource($order->load('items'))], 201);
    }

    private function cart(Request $request, CartService $carts, int $status = 200): JsonResponse
    {
        $rows = $carts->inspect($carts->forUser($request->user()));

        return response()->json(['data' => [
            'items' => $rows->map(fn ($r) => [
                'course' => $r['course'] ? [
                    'id' => $r['course']->id, 'slug' => $r['course']->slug, 'image' => $r['course']->image_src,
                    'title' => Loc::pick($r['course']->title_ar, $r['course']->title_en),
                ] : null,
                'price' => $r['price'],
                'issue' => $r['issue'],
            ])->values(),
            'subtotal' => $carts->subtotal($rows),
            'currency' => 'USD',
        ]], $status);
    }
}
