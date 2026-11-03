<?php

namespace App\Http\Controllers;

use App\Http\Requests\CouponRequest;
use App\Models\Coupon;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\View\View;

class CouponController extends Controller implements HasMiddleware
{
    private const SORTABLE = ['id', 'code', 'discount_value', 'times_used', 'expires_at', 'created_at'];

    public static function middleware(): array
    {
        return [
            new Middleware('permission:coupons.view',   only: ['index', 'show', 'trashed']),
            new Middleware('permission:coupons.create', only: ['create', 'store']),
            new Middleware('permission:coupons.edit',   only: ['edit', 'update', 'restore', 'toggle']),
            new Middleware('permission:coupons.delete', only: ['destroy', 'forceDelete']),
        ];
    }

    public function index(Request $request): View|JsonResponse
    {
        $sort = in_array($request->get('sort'), self::SORTABLE, true) ? $request->get('sort') : 'id';
        $dir  = $request->get('dir') === 'asc' ? 'asc' : 'desc';
        $per  = min(max((int) $request->get('per_page', 10), 5), 100);

        $coupons = Coupon::query()
            ->search($request->get('search'))
            ->withStatus($request->get('status'))
            ->ofType($request->get('type'))
            ->orderBy($sort, $dir)
            ->orderBy('id', 'desc')
            ->paginate($per)
            ->withQueryString();

        if ($request->expectsJson()) {
            return response()->json([
                'html'  => view('cms.coupons.partials.table', compact('coupons'))->render(),
                'stats' => $this->stats(),
            ]);
        }

        return view('cms.coupons.index', ['coupons' => $coupons, 'stats' => $this->stats()]);
    }

    public function create(): JsonResponse
    {
        return response()->json(['options' => new \stdClass()]);
    }

    public function store(CouponRequest $request): JsonResponse
    {
        try {
            $coupon = Coupon::create($request->validated());
        } catch (UniqueConstraintViolationException) {
            // سباق نادر: كودان متطابقان في اللحظة نفسها
            return $this->duplicate();
        }

        return response()->json([
            'message' => __('coupons.messages.created'),
            'data'    => $this->payload($coupon),
            'stats'   => $this->stats(),
        ], 201);
    }

    public function show(Coupon $coupon): JsonResponse
    {
        return response()->json(['data' => $this->payload($coupon)]);
    }

    public function edit(Coupon $coupon): JsonResponse
    {
        return response()->json(['data' => $this->payload($coupon)]);
    }

    public function update(CouponRequest $request, Coupon $coupon): JsonResponse
    {
        $data = $request->validated();

        // بعد أول استخدام تبقى هذه القيم كما هي (يضمنها الـ Request أيضًا)
        if ($coupon->hasBeenUsed()) {
            $data = array_diff_key($data, array_flip(CouponRequest::LOCKED_WHEN_USED));
        }

        try {
            $coupon->update($data);
        } catch (UniqueConstraintViolationException) {
            return $this->duplicate();
        }

        return response()->json([
            'message' => __('coupons.messages.updated'),
            'data'    => $this->payload($coupon->fresh()),
            'stats'   => $this->stats(),
        ]);
    }

    /** تفعيل/تعطيل سريع دون حذف */
    public function toggle(Coupon $coupon): JsonResponse
    {
        $coupon->update(['is_active' => ! $coupon->is_active]);

        return response()->json([
            'message' => __($coupon->is_active ? 'coupons.messages.enabled' : 'coupons.messages.disabled'),
            'data'    => $this->payload($coupon),
            'stats'   => $this->stats(),
        ]);
    }

    /** حذف مؤقت: يختفي الكوبون من الاستخدام فورًا، ويمكن استرجاعه */
    public function destroy(Coupon $coupon): JsonResponse
    {
        $coupon->delete();

        return response()->json(['message' => __('coupons.messages.deleted'), 'stats' => $this->stats()]);
    }

    public function trashed(Request $request): JsonResponse
    {
        $coupons = Coupon::onlyTrashed()
            ->search($request->get('search'))
            ->latest('deleted_at')
            ->paginate(min(max((int) $request->get('per_page', 10), 5), 100))
            ->withQueryString();

        return response()->json([
            'html'  => view('cms.coupons.partials.trash-table', compact('coupons'))->render(),
            'stats' => $this->stats(),
        ]);
    }

    public function restore(int $id): JsonResponse
    {
        Coupon::onlyTrashed()->findOrFail($id)->restore();

        return response()->json(['message' => __('coupons.messages.restored'), 'stats' => $this->stats()]);
    }

    public function forceDelete(int $id): JsonResponse
    {
        $coupon = Coupon::onlyTrashed()->findOrFail($id);

        // الكوبون المستخدم جزء من سجل الطلبات، فلا يُحذف نهائيًا
        if ($coupon->hasBeenUsed()) {
            return response()->json(['message' => __('coupons.messages.in_use')], 409);
        }

        $coupon->forceDelete();

        return response()->json(['message' => __('coupons.messages.force_deleted'), 'stats' => $this->stats()]);
    }

    private function duplicate(): JsonResponse
    {
        return response()->json([
            'message' => __('coupons.validation.code_taken'),
            'errors'  => ['code' => [__('coupons.validation.code_taken')]],
        ], 422);
    }

    private function stats(): array
    {
        return [
            'total'     => Coupon::count(),
            'active'    => Coupon::withStatus('active')->count(),
            'expired'   => Coupon::withStatus('expired')->count(),
            'exhausted' => Coupon::withStatus('exhausted')->count(),
            'trashed'   => Coupon::onlyTrashed()->count(),
        ];
    }

    /** مواعيد منسَّقة بتوقيت التطبيق، حتى لا يتزحزح الوقت في حقول datetime-local */
    private function payload(Coupon $c): array
    {
        $fmt = fn ($d, string $f) => $d?->copy()->timezone(config('app.timezone'))->format($f);

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
            'is_active'        => $c->is_active,
            'status'           => $c->status(),
            'has_been_used'    => $c->hasBeenUsed(),
            'starts_at_input'  => $fmt($c->starts_at, 'Y-m-d\TH:i'),
            'expires_at_input' => $fmt($c->expires_at, 'Y-m-d\TH:i'),
            'starts_at_label'  => $fmt($c->starts_at, 'Y-m-d H:i'),
            'expires_at_label' => $fmt($c->expires_at, 'Y-m-d H:i'),
            'created_at_label' => $fmt($c->created_at, 'Y-m-d H:i'),
            'updated_at_label' => $fmt($c->updated_at, 'Y-m-d H:i'),
        ];
    }
}
