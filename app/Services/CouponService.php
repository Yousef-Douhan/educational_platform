<?php

namespace App\Services;

use App\Exceptions\CouponException;
use App\Models\Coupon;
use Illuminate\Support\Facades\DB;

/**
 * منطق استخدام الكوبون، وهو ما ستستدعيه السلة والطلبات لاحقًا.
 * ملاحظة: حد الاستخدام لكل مستخدم (per_user_limit) يُفحص مع جدول
 * coupon_redemptions عند بناء الطلبات، لأن الجدول يعتمد على orders.
 */
class CouponService
{
    /** يتحقق من صلاحية الكود لمبلغ معيّن، ويعيد الكوبون. لا يغيّر أي بيانات */
    public function check(string $code, string|int|float $subtotal): Coupon
    {
        $coupon = Coupon::findByCode($code) ?? throw CouponException::because('not_found');

        $this->assertUsable($coupon, $subtotal);

        return $coupon;
    }

    public function assertUsable(Coupon $coupon, string|int|float $subtotal): void
    {
        match ($coupon->status()) {
            'disabled'  => throw CouponException::because('disabled'),
            'expired'   => throw CouponException::because('expired'),
            'exhausted' => throw CouponException::because('exhausted'),
            'scheduled' => throw CouponException::because('not_started'),
            default     => null,
        };

        if ($coupon->min_order_amount !== null && (float) $subtotal < (float) $coupon->min_order_amount) {
            throw CouponException::because('min_order');
        }
    }

    /**
     * يحجز استخدامًا واحدًا بتحديث ذرّي واحد، فلا يتجاوز العدد الحد حتى لو
     * جاء طلبان في اللحظة نفسها. يجب استدعاؤه داخل معاملة الطلب.
     */
    public function consume(Coupon $coupon): void
    {
        $now = now();

        $affected = Coupon::query()
            ->whereKey($coupon->getKey())
            ->where('is_active', true)
            ->where(fn ($q) => $q->whereNull('expires_at')->orWhere('expires_at', '>', $now))
            ->where(fn ($q) => $q->whereNull('starts_at')->orWhere('starts_at', '<=', $now))
            ->where(fn ($q) => $q->whereNull('usage_limit')->orWhereColumn('times_used', '<', 'usage_limit'))
            ->update(['times_used' => DB::raw('times_used + 1')]);

        if ($affected !== 1) {
            throw CouponException::because('unavailable');
        }

        $coupon->refresh();
    }

    /** يعيد الاستخدام عند فشل الطلب أو استرداده، ولا ينزل تحت الصفر */
    public function release(Coupon $coupon): void
    {
        // withTrashed: كوبون حُذف ناعمًا بعد الشراء يجب أن يستعيد استخدامه أيضًا
        Coupon::withTrashed()->whereKey($coupon->getKey())->where('times_used', '>', 0)
            ->update(['times_used' => DB::raw('times_used - 1')]);

        $coupon->refresh();
    }
}
