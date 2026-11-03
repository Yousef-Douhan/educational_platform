<?php

namespace App\Services;

use App\Exceptions\CartException;
use App\Models\Cart;
use App\Models\CartItem;
use App\Models\Course;
use App\Models\Enrollment;
use App\Models\User;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * منطق السلة. واجهة الطالب والطلبات تعتمد عليه ولا تلمس الجداول مباشرة.
 * السعر لا يُخزَّن في السلة: يُقرأ من الدورة دائمًا ويتثبّت عند إنشاء الطلب.
 */
class CartService
{
    public function forUser(User $user): Cart
    {
        try {
            return Cart::firstOrCreate(['user_id' => $user->id]);
        } catch (UniqueConstraintViolationException) {
            // طلبان متزامنان أنشآ السلة معًا: القيد الفريد يحمينا ونقرأ الفائزة
            return Cart::where('user_id', $user->id)->firstOrFail();
        }
    }

    public function add(User $user, Course $course): CartItem
    {
        $this->assertCanAdd($user, $course);

        return DB::transaction(function () use ($user, $course) {
            $cart = $this->forUser($user);

            // يُسلسل الإضافات المتزامنة لنفس السلة حتى لا يُتجاوز الحد الأقصى
            Cart::whereKey($cart->id)->lockForUpdate()->first();

            if ($cart->items()->where('course_id', $course->id)->exists()) {
                throw CartException::because('already_in_cart');
            }

            if ($cart->items()->count() >= Cart::MAX_ITEMS) {
                throw CartException::because('cart_full');
            }

            try {
                $item = $cart->items()->create(['course_id' => $course->id]);
            } catch (UniqueConstraintViolationException) {
                throw CartException::because('already_in_cart');
            }

            $cart->touch();

            return $item;
        });
    }

    /** يعيد true إن كانت الدورة في السلة وأُزيلت */
    public function remove(User $user, Course|int $course): bool
    {
        $courseId = $course instanceof Course ? $course->id : $course;
        $cart     = Cart::where('user_id', $user->id)->first();

        if (! $cart) {
            return false;
        }

        $removed = $cart->items()->where('course_id', $courseId)->delete() > 0;

        if ($removed) {
            $cart->touch();
        }

        return $removed;
    }

    /** يفرّغ السلة ويعيد عدد العناصر المحذوفة */
    public function clear(Cart $cart): int
    {
        $count = $cart->items()->delete();

        if ($count > 0) {
            $cart->touch();
        }

        return $count;
    }

    /**
     * يفحص كل عنصر الآن (قد تتغير حالة الدورة بعد إضافتها).
     * كل صف: item, course, price (السعر الحالي), issue (null إن كان صالحًا).
     */
    public function inspect(Cart $cart): Collection
    {
        $cart->load('items.course');

        $ids = $cart->items->pluck('course_id');

        $enrolled = Enrollment::grantingAccess()
            ->where('user_id', $cart->user_id)->whereIn('course_id', $ids)->pluck('course_id')->all();

        $coTeaching = DB::table('course_instructors')
            ->where('instructor_id', $cart->user_id)->whereIn('course_id', $ids)->pluck('course_id')->all();

        return $cart->items->map(function (CartItem $item) use ($cart, $enrolled, $coTeaching) {
            $course = $item->course;

            $issue = match (true) {
                $course === null || $course->trashed() || $course->status !== 'published' => 'course_unavailable',
                $course->instructor_id === $cart->user_id || in_array($course->id, $coTeaching, true) => 'own_course',
                in_array($course->id, $enrolled, true)                                    => 'already_enrolled',
                $course->is_free                                                          => 'became_free',
                default                                                                   => null,
            };

            return ['item' => $item, 'course' => $course, 'price' => $course?->final_price, 'issue' => $issue];
        })->values();
    }

    /** مجموع العناصر الصالحة فقط، كنص بمنزلتين (بالسنتات لتفادي الفاصلة العائمة) */
    public function subtotal(Collection $rows): string
    {
        $cents = $rows->filter(fn ($r) => $r['issue'] === null)
            ->sum(fn ($r) => (int) round(((float) $r['price']) * 100));

        return number_format($cents / 100, 2, '.', '');
    }

    /**
     * يُستدعى قبل الدفع: يحذف العناصر غير الصالحة ويعيد الصالحة والمحذوفة والمجموع.
     * لا يرمي خطأ عند السلة الفارغة، فالطلبات تتحقق من items بنفسها.
     */
    public function prepareForCheckout(User $user): array
    {
        return DB::transaction(function () use ($user) {
            $cart = $this->forUser($user);
            Cart::whereKey($cart->id)->lockForUpdate()->first();

            $rows    = $this->inspect($cart);
            $invalid = $rows->filter(fn ($r) => $r['issue'] !== null)->values();

            if ($invalid->isNotEmpty()) {
                CartItem::whereIn('id', $invalid->map(fn ($r) => $r['item']->id))->delete();
                $cart->touch();
            }

            $valid = $rows->filter(fn ($r) => $r['issue'] === null)->values();

            return [
                'cart'     => $cart,
                'items'    => $valid,
                'removed'  => $invalid,
                'subtotal' => $this->subtotal($valid),
            ];
        });
    }

    /** قواعد الإضافة، بترتيب يعطي أوضح سبب للرفض */
    private function assertCanAdd(User $user, Course $course): void
    {
        if (($user->status ?? 'active') !== 'active') {
            throw CartException::because('account_inactive');
        }

        if ($course->trashed() || $course->status !== 'published') {
            throw CartException::because('course_unavailable');
        }

        $teaches = $course->instructor_id === $user->id
            || $course->coInstructors()->where('users.id', $user->id)->exists();

        if ($teaches) {
            throw CartException::because('own_course');
        }

        if ($course->is_free) {
            throw CartException::because('free_course');
        }

        $hasAccess = Enrollment::grantingAccess()
            ->where(['user_id' => $user->id, 'course_id' => $course->id])->exists();

        if ($hasAccess) {
            throw CartException::because('already_enrolled');
        }
    }
}
