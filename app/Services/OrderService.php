<?php

namespace App\Services;

use App\Exceptions\OrderException;
use App\Models\Cart;
use App\Models\CartItem;
use App\Models\Certificate;
use App\Models\Coupon;
use App\Models\CouponRedemption;
use App\Models\Course;
use App\Models\Enrollment;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Payment;
use App\Models\User;
use App\Notifications\PlatformNotification;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * دورة حياة الطلب كاملة. واجهة الطالب وبوابات الدفع والإدارة تمرّ كلها من هنا،
 * ولا أحد يغيّر حالة الطلب أو المبالغ أو التسجيلات مباشرة.
 *
 *   pending ──► paid ──► refunded
 *      ├──────► failed
 *      └──────► cancelled
 *
 * كل عملية داخل معاملة وتقفل صف الطلب، فتكرار نفس الإشعار (webhook) أو ضغطتين
 * متزامنتين لا تنتج تسجيلًا مكررًا ولا خصمًا مضاعفًا.
 */
class OrderService
{
    public function __construct(
        private readonly CartService $carts,
        private readonly CouponService $coupons,
    ) {}

    /* ====================================================================
     |  إنشاء الطلب من السلة
     * ==================================================================== */

    /**
     * يحوّل سلة الطالب إلى طلب معلّق بأسعار مثبّتة. السلة تبقى كما هي حتى يتم الدفع.
     * إن كان المبلغ النهائي صفرًا (كوبون 100%) يُعتمد الطلب مباشرة.
     *
     * @throws OrderException|\App\Exceptions\CouponException
     */
    public function checkout(User $user, ?string $couponCode = null): Order
    {
        if (($user->status ?? 'active') !== 'active') {
            throw OrderException::because('account_inactive');
        }

        $code = filled($couponCode) ? Coupon::normalizeCode($couponCode) : null;

        return DB::transaction(function () use ($user, $code) {
            // prepareForCheckout يقفل صف السلة حتى نهاية المعاملة، فلا يتزامن
            // طلبان لنفس الطالب (وهذا يحمي per_user_limit أيضًا)
            $rows = $this->carts->prepareForCheckout($user)['items'];

            if ($rows->isEmpty()) {
                throw OrderException::because('empty_cart');
            }

            // السلة هي المصدر الوحيد: أي طلب معلّق قديم يُستبدل بالجديد
            Order::where('user_id', $user->id)->where('status', 'pending')
                ->lockForUpdate()->get()
                ->each(fn (Order $old) => $this->close($old, 'cancelled', __('orders.system_notes.superseded')));

            $subtotalCents = (int) $rows->sum(fn ($r) => $this->cents($r['price']));

            $coupon        = null;
            $discountCents = 0;

            if ($code !== null) {
                $coupon = $this->coupons->check($code, $this->money($subtotalCents));
                $this->assertPerUserLimit($coupon, $user);
                $discountCents = $this->cents($coupon->discountFor($this->money($subtotalCents)));
                $this->coupons->consume($coupon); // تحديث ذرّي، يرمي إن نفد الحد للتو
            }

            $totalCents = max(0, $subtotalCents - $discountCents);

            $order = $this->insertOrder([
                'user_id'         => $user->id,
                'coupon_id'       => $coupon?->id,
                'coupon_code'     => $coupon?->code,
                'subtotal'        => $this->money($subtotalCents),
                'discount_amount' => $this->money($discountCents),
                'total_amount'    => $this->money($totalCents),
                'currency'        => config('app.currency', 'USD'),
                'status'          => 'pending',
            ]);

            foreach ($rows as $row) {
                (new OrderItem)->forceFill([
                    'order_id'  => $order->id,
                    'course_id' => $row['course']->id,
                    'price'     => $row['price'], // السعر وقت الشراء
                    'title_ar'  => $row['course']->title_ar ?? '',
                    'title_en'  => $row['course']->title_en ?? '',
                ])->save();
            }

            if ($coupon) {
                (new CouponRedemption)->forceFill([
                    'coupon_id' => $coupon->id,
                    'user_id'   => $user->id,
                    'order_id'  => $order->id,
                ])->save();
            }

            if ($totalCents === 0) {
                $this->settle($order, 'free', null, null);
            }

            return $order->fresh('items');
        });
    }

    /* ====================================================================
     |  تغيير الحالة
     * ==================================================================== */

    /** تأكيد الدفع: يفعّل التسجيلات. تكراره على طلب مدفوع لا يفعل شيئًا (idempotent) */
    public function markPaid(Order $order, string $gateway, ?string $reference = null, ?string $note = null, ?Payment $payment = null): Order
    {
        return DB::transaction(function () use ($order, $gateway, $reference, $note, $payment) {
            $locked = $this->lock($order);

            if ($locked->status === 'paid') {
                return $locked;
            }

            $this->assertCan($locked, 'paid');
            $this->settle($locked, $gateway, filled($reference) ? trim($reference) : null, $note, $payment);

            return $locked->refresh();
        });
    }

    public function fail(Order $order, ?string $note = null): Order
    {
        return $this->closePending($order, 'failed', $note);
    }

    public function cancel(Order $order, ?string $note = null): Order
    {
        return $this->closePending($order, 'cancelled', $note);
    }

    /**
     * استرداد طلب مدفوع: يلغي التسجيلات ويسحب الشهادات التي أُصدرت عنها.
     * الكوبون يبقى مستهلكًا (الاستخدام حدث فعلًا)، ولا يُسترد وصول دورة اشتراها
     * الطالب بطلب مدفوع آخر.
     */
    public function refund(Order $order, string $reason): Order
    {
        return DB::transaction(function () use ($order, $reason) {
            $locked = $this->lock($order);
            $this->assertCan($locked, 'refunded');

            $locked->forceFill([
                'status'      => 'refunded',
                'refunded_at' => now(),
                'note'        => $this->note($reason),
            ])->save();

            // الدفعة الناجحة تصير مستردة في السجل (الاسترداد الفعلي للمال عند مزوّد الدفع)
            Payment::where('order_id', $locked->id)->where('status', 'success')
                ->update(['status' => 'refunded', 'refunded_at' => now()]);

            $courseIds = $locked->items()->pluck('course_id');

            $keep = OrderItem::query()
                ->whereIn('course_id', $courseIds)
                ->whereHas('order', fn ($q) => $q->where('user_id', $locked->user_id)
                    ->where('status', 'paid')->where('id', '!=', $locked->id))
                ->pluck('course_id');

            $revoke = $courseIds->diff($keep)->values();

            Enrollment::where('user_id', $locked->user_id)
                ->whereIn('course_id', $revoke)
                ->whereIn('status', Enrollment::ACCESS_STATUSES)
                ->update(['status' => 'cancelled']);

            $certificates = app(CertificateService::class);

            Certificate::where('user_id', $locked->user_id)
                ->whereIn('course_id', $revoke)->whereNull('revoked_at')->get()
                ->each(fn (Certificate $c) => $certificates->revoke($c, __('orders.system_notes.refund_revoked')));

            app(Notifier::class)->toUser($locked->user_id, PlatformNotification::orderRefunded($locked));

            return $locked;
        });
    }

    /** يُغلق الطلبات المعلّقة المتروكة ويعيد حصة الكوبون. يعيد عددها */
    public function expirePending(?Carbon $now = null): int
    {
        $cutoff = ($now ?? now())->copy()->subHours(Order::PENDING_EXPIRES_AFTER_HOURS);
        $count  = 0;

        Order::where('status', 'pending')->where('created_at', '<=', $cutoff)
            ->orderBy('id')->pluck('id')
            ->each(function (int $id) use (&$count) {
                try {
                    $this->fail(Order::findOrFail($id), __('orders.system_notes.expired'));
                    $count++;
                } catch (OrderException) {
                    // دُفع أو أُلغي للتو بعملية متزامنة: لا يُمس
                }
            });

        return $count;
    }

    /* ====================================================================
     |  داخلي
     * ==================================================================== */

    private function closePending(Order $order, string $status, ?string $note): Order
    {
        return DB::transaction(function () use ($order, $status, $note) {
            $locked = $this->lock($order);
            $this->assertCan($locked, $status);

            return $this->close($locked, $status, $note);
        });
    }

    private function close(Order $order, string $status, ?string $note): Order
    {
        $order->forceFill(['status' => $status, 'note' => $note ? $this->note($note) : $order->note])->save();
        $this->releaseCoupon($order);

        // محاولات الدفع المفتوحة لطلب أُغلق لم تعد صالحة
        Payment::where('order_id', $order->id)->where('status', 'pending')->update(['status' => 'failed']);

        return $order;
    }

    /** يعيد الاستخدام للكوبون وللطالب مرة واحدة فقط مهما تكرر الاستدعاء */
    private function releaseCoupon(Order $order): void
    {
        if ($order->coupon_id === null) {
            return;
        }

        $deleted = CouponRedemption::where('order_id', $order->id)->delete();

        if ($deleted > 0 && ($coupon = Coupon::withTrashed()->find($order->coupon_id))) {
            $this->coupons->release($coupon);
        }
    }

    /** يثبّت الدفع ويفعّل الوصول وينظّف السلة. يجب استدعاؤه داخل معاملة */
    private function settle(Order $order, string $gateway, ?string $reference, ?string $note, ?Payment $payment = null): void
    {
        try {
            $order->forceFill([
                'status'                => 'paid',
                'gateway'               => $gateway,
                'transaction_reference' => $reference,
                'paid_at'               => now(),
                'note'                  => $note ? $this->note($note) : $order->note,
            ])->save();

            // سجل المدفوعات: الدفع اليدوي والمجاني يُسجَّلان كدفعة ناجحة مثل الإلكتروني
            $payment ??= (new Payment)->forceFill([
                'order_id'       => $order->id,
                'gateway'        => $gateway,
                'payment_method' => $gateway,
                'amount'         => $order->total_amount,
                'currency'       => $order->currency,
            ]);

            $payment->forceFill([
                'status'                => 'success',
                'succeeded'             => true,
                'transaction_reference' => $reference,
                'paid_at'               => now(),
            ])->save();

            // أي محاولة دفع أخرى ما زالت مفتوحة لهذا الطلب تُغلق
            Payment::where('order_id', $order->id)->where('status', 'pending')
                ->where('id', '!=', $payment->id)->update(['status' => 'failed']);
        } catch (UniqueConstraintViolationException) {
            // نفس مرجع العملية مسجّل على طلب آخر لنفس البوابة
            throw OrderException::because('duplicate_reference');
        }

        $items = $order->items()->get();

        $this->grantAccess($order, $items);
        $this->removeFromCart($order, $items);

        app(Notifier::class)->toUser($order->user_id, PlatformNotification::orderPaid($order));
    }

    /** يفعّل التسجيل، أو يعيد تفعيل المنتهي/الملغى (unique(user_id, course_id)) ولا يمس الفعّال */
    private function grantAccess(Order $order, Collection $items): void
    {
        foreach ($items as $item) {
            $keys       = ['user_id' => $order->user_id, 'course_id' => $item->course_id];
            $enrollment = Enrollment::where($keys)->lockForUpdate()->first();

            if ($enrollment === null) {
                try {
                    Enrollment::create($keys + ['status' => 'active', 'enrolled_at' => now(), 'expires_at' => null]);
                    $this->notifyEnrolled($order, $item->course_id);

                    continue;
                } catch (UniqueConstraintViolationException) {
                    // سجّله المدير يدويًا في اللحظة نفسها
                    $enrollment = Enrollment::where($keys)->first();
                }
            }

            if ($enrollment && ! $enrollment->grantsAccess()) {
                $enrollment->update(['status' => 'active', 'enrolled_at' => now(), 'expires_at' => null]);
                $this->notifyEnrolled($order, $item->course_id);
            }
        }
    }

    private function notifyEnrolled(Order $order, int $courseId): void
    {
        if ($course = Course::withTrashed()->find($courseId)) {
            app(Notifier::class)->toUser($order->user_id, PlatformNotification::enrollmentActivated($course));
        }
    }

    private function removeFromCart(Order $order, Collection $items): void
    {
        $cartIds = Cart::where('user_id', $order->user_id)->pluck('id');

        if ($cartIds->isEmpty()) {
            return;
        }

        CartItem::whereIn('cart_id', $cartIds)->whereIn('course_id', $items->pluck('course_id'))->delete();
        Cart::whereIn('id', $cartIds)->update(['updated_at' => now()]);
    }

    private function assertPerUserLimit(Coupon $coupon, User $user): void
    {
        if ($coupon->per_user_limit === null) {
            return;
        }

        $used = CouponRedemption::where(['coupon_id' => $coupon->id, 'user_id' => $user->id])->count();

        if ($used >= $coupon->per_user_limit) {
            throw OrderException::because('coupon_per_user_limit');
        }
    }

    private function assertCan(Order $order, string $to): void
    {
        if (! $order->canTransitionTo($to)) {
            throw OrderException::because('invalid_transition');
        }
    }

    private function lock(Order $order): Order
    {
        return Order::whereKey($order->getKey())->lockForUpdate()->firstOrFail();
    }

    private function insertOrder(array $attributes): Order
    {
        // رقم الطلب عشوائي؛ التصادم نادر جدًا لكن القيد الفريد يكشفه فنعيد المحاولة
        for ($attempt = 0; $attempt < 5; $attempt++) {
            try {
                $order = new Order;
                $order->forceFill($attributes + ['order_number' => Order::generateNumber()])->save();

                return $order;
            } catch (UniqueConstraintViolationException) {
                continue;
            }
        }

        throw new RuntimeException('Could not generate a unique order number.');
    }

    private function note(string $text): string
    {
        return Str::limit(trim($text), 500, '');
    }

    private function cents(string|int|float $amount): int
    {
        return (int) round(((float) $amount) * 100);
    }

    private function money(int $cents): string
    {
        return number_format($cents / 100, 2, '.', '');
    }
}
