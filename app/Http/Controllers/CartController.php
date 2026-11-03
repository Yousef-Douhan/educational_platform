<?php

namespace App\Http\Controllers;

use App\Models\Cart;
use App\Services\CartService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\View\View;

/**
 * مراقبة السلات من لوحة الإدارة (قراءة + تفريغ). الطالب يتعامل مع سلته عبر CartService.
 */
class CartController extends Controller implements HasMiddleware
{
    private const SORTABLE = ['id', 'items_count', 'total', 'updated_at', 'created_at'];

    public static function middleware(): array
    {
        return [
            new Middleware('permission:carts.view', only: ['index', 'show']),
            new Middleware('permission:carts.edit', only: ['clear']),
        ];
    }

    public function index(Request $request): View|JsonResponse
    {
        $sort = in_array($request->get('sort'), self::SORTABLE, true) ? $request->get('sort') : 'updated_at';
        $dir  = $request->get('dir') === 'asc' ? 'asc' : 'desc';
        $per  = min(max((int) $request->get('per_page', 10), 5), 100);

        $carts = Cart::query()
            ->with('user')
            ->withSummary()
            ->search($request->get('search'))
            ->inState($request->get('state'))
            ->orderBy($sort, $dir)
            ->orderBy('carts.id', 'desc')
            ->paginate($per)
            ->withQueryString();

        if ($request->expectsJson()) {
            return response()->json([
                'html'  => view('cms.carts.partials.table', compact('carts'))->render(),
                'stats' => $this->stats(),
            ]);
        }

        return view('cms.carts.index', ['carts' => $carts, 'stats' => $this->stats()]);
    }

    public function show(Cart $cart, CartService $service): JsonResponse
    {
        return response()->json(['data' => $this->payload($cart, $service)]);
    }

    public function clear(Cart $cart, CartService $service): JsonResponse
    {
        $removed = $service->clear($cart);

        return response()->json([
            'message' => __('carts.messages.cleared', ['count' => $removed]),
            'stats'   => $this->stats(),
        ]);
    }

    private function stats(): array
    {
        return [
            'total'     => Cart::count(),
            'active'    => Cart::inState('active')->count(),
            'abandoned' => Cart::inState('abandoned')->count(),
            'empty'     => Cart::inState('empty')->count(),
        ];
    }

    private function payload(Cart $cart, CartService $service): array
    {
        $cart->load('user');
        $rows = $service->inspect($cart);

        return [
            'id'         => $cart->id,
            'state'      => $cart->state(),
            'user'       => [
                'name'    => $cart->user->name,
                'email'   => $cart->user->email,
                'deleted' => $cart->user->trashed(),
            ],
            'items'      => $rows->map(fn ($r) => [
                'id'        => $r['item']->id,
                'course_id' => $r['item']->course_id,
                'title_ar'  => $r['course']?->title_ar,
                'title_en'  => $r['course']?->title_en,
                'price'     => $r['price'],
                'issue'     => $r['issue'],
                'added_at'  => $r['item']->created_at?->timezone(config('app.timezone'))->format('Y-m-d H:i'),
            ])->all(),
            'subtotal'   => $service->subtotal($rows),
            'created_at' => $cart->created_at?->copy()->timezone(config('app.timezone'))->format('Y-m-d H:i'),
            'updated_at' => $cart->updated_at?->copy()->timezone(config('app.timezone'))->format('Y-m-d H:i'),
        ];
    }
}
