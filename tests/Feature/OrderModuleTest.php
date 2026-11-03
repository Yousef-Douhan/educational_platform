<?php

namespace Tests\Feature;

use App\Exceptions\CouponException;
use App\Exceptions\OrderException;
use App\Models\Certificate;
use App\Models\Coupon;
use App\Models\CouponRedemption;
use App\Models\Course;
use App\Models\Enrollment;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\User;
use App\Services\CartService;
use App\Services\OrderService;
use Illuminate\Database\Eloquent\MassAssignmentException;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class OrderModuleTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        foreach (['view', 'edit', 'mark-paid', 'refund'] as $a) {
            Permission::findOrCreate("orders.{$a}", 'web');
        }
    }

    /* ---------- أدوات ---------- */

    private function orders(): OrderService
    {
        return app(OrderService::class);
    }

    private function admin(): User
    {
        $u = User::factory()->create();
        $u->givePermissionTo(Permission::all());

        return $u;
    }

    private function adminWith(string ...$permissions): User
    {
        $u = User::factory()->create();
        $u->givePermissionTo($permissions);

        return $u;
    }

    private function course(array $o = []): Course
    {
        return Course::factory()->published()->paid()->create($o);
    }

    /** يضيف الدورات لسلة الطالب عبر الخدمة (فتُطبَّق كل قواعد الإضافة) ثم يُنشئ الطلب */
    private function checkoutFor(User $user, array $courses, ?string $coupon = null): Order
    {
        foreach ($courses as $course) {
            app(CartService::class)->add($user, $course);
        }

        return $this->orders()->checkout($user, $coupon);
    }

    private function assertRejected(string $reason, callable $fn): void
    {
        try {
            $fn();
        } catch (OrderException $e) {
            $this->assertSame($reason, $e->reason);

            return;
        }

        $this->fail("Expected OrderException: {$reason}");
    }

    /* ====================================================================
     |  إنشاء الطلب
     * ==================================================================== */

    public function test_checkout_creates_a_pending_order_with_frozen_prices_and_keeps_the_cart(): void
    {
        $student = User::factory()->create();
        $a = $this->course(['price' => 100, 'discount_price' => 80, 'title_ar' => 'دورة أ', 'title_en' => 'Course A']);
        $b = $this->course(['price' => 50]);

        $order = $this->checkoutFor($student, [$a, $b]);

        $this->assertSame('pending', $order->status);
        $this->assertSame('130.00', $order->subtotal);
        $this->assertSame('0.00', $order->discount_amount);
        $this->assertSame('130.00', $order->total_amount);
        $this->assertMatchesRegularExpression('/^ORD-[A-Z2-9]{8}$/', $order->order_number);

        $this->assertSame(['80.00', '50.00'], $order->items->pluck('price')->all());
        $this->assertSame('دورة أ', $order->items[0]->title_ar);
        $this->assertSame('Course A', $order->items[0]->title_en);

        // لا وصول قبل الدفع، والسلة كما هي
        $this->assertSame(0, Enrollment::count());
        $this->assertSame(2, app(CartService::class)->forUser($student)->items()->count());
    }

    public function test_prices_stay_frozen_when_the_course_changes_later(): void
    {
        $a     = $this->course(['price' => 100, 'discount_price' => 80]);
        $order = $this->checkoutFor(User::factory()->create(), [$a]);

        $a->update(['price' => 999, 'discount_price' => null, 'title_en' => 'Renamed']);

        $item = OrderItem::where('order_id', $order->id)->first();
        $this->assertSame('80.00', $item->price);
        $this->assertNotSame('Renamed', $item->title_en);
        $this->assertSame('80.00', $order->fresh()->total_amount);
    }

    public function test_money_is_summed_in_cents_without_float_errors(): void
    {
        $order = $this->checkoutFor(User::factory()->create(), [
            $this->course(['price' => 10.10]),
            $this->course(['price' => 20.20]),
            $this->course(['price' => 0.10]),
        ]);

        $this->assertSame('30.40', $order->subtotal);
        $this->assertSame('30.40', $order->total_amount);
    }

    public function test_checkout_with_an_empty_cart_is_rejected(): void
    {
        $this->assertRejected('empty_cart', fn () => $this->orders()->checkout(User::factory()->create()));
        $this->assertSame(0, Order::count());
    }

    public function test_inactive_accounts_cannot_check_out(): void
    {
        $student = User::factory()->create();
        app(CartService::class)->add($student, $this->course());
        $student->update(['status' => 'inactive']);

        $this->assertRejected('account_inactive', fn () => $this->orders()->checkout($student));
        $this->assertSame(0, Order::count());
    }

    public function test_items_that_became_invalid_are_dropped_and_never_charged(): void
    {
        $student = User::factory()->create();
        $good    = $this->course(['price' => 40]);
        $gone    = $this->course(['price' => 60]);
        app(CartService::class)->add($student, $good);
        app(CartService::class)->add($student, $gone);
        $gone->update(['status' => 'archived']);

        $order = $this->orders()->checkout($student);

        $this->assertSame('40.00', $order->total_amount);
        $this->assertEquals([$good->id], $order->items->pluck('course_id')->all());
    }

    public function test_a_cart_with_only_invalid_items_cannot_check_out(): void
    {
        $student = User::factory()->create();
        $c       = $this->course();
        app(CartService::class)->add($student, $c);
        $c->delete();

        $this->assertRejected('empty_cart', fn () => $this->orders()->checkout($student));
    }

    public function test_a_new_checkout_replaces_the_previous_pending_order(): void
    {
        $student = User::factory()->create();
        $first   = $this->checkoutFor($student, [$this->course()]);
        $second  = $this->checkoutFor($student, [$this->course()]);

        $this->assertSame('cancelled', $first->fresh()->status);
        $this->assertNotNull($first->fresh()->note);
        $this->assertSame('pending', $second->status);
        $this->assertSame(1, Order::where('user_id', $student->id)->where('status', 'pending')->count());
    }

    public function test_other_students_pending_orders_are_not_touched(): void
    {
        $other = $this->checkoutFor(User::factory()->create(), [$this->course()]);
        $this->checkoutFor(User::factory()->create(), [$this->course()]);

        $this->assertSame('pending', $other->fresh()->status);
    }

    /* ====================================================================
     |  الكوبونات
     * ==================================================================== */

    public function test_percentage_coupon_is_applied_consumed_and_recorded(): void
    {
        $student = User::factory()->create();
        $coupon  = Coupon::factory()->percentage(10)->create(['code' => 'SAVE10']);

        $order = $this->checkoutFor($student, [$this->course(['price' => 200])], '  save10 ');

        $this->assertSame('200.00', $order->subtotal);
        $this->assertSame('20.00', $order->discount_amount);
        $this->assertSame('180.00', $order->total_amount);
        $this->assertSame('SAVE10', $order->coupon_code);
        $this->assertSame($coupon->id, $order->coupon_id);
        $this->assertSame(1, $coupon->fresh()->times_used);
        $this->assertDatabaseHas('coupon_redemptions', ['coupon_id' => $coupon->id, 'user_id' => $student->id, 'order_id' => $order->id]);
    }

    public function test_a_coupon_failure_rolls_everything_back(): void
    {
        $student = User::factory()->create();
        $first   = $this->checkoutFor($student, [$this->course(['price' => 50])]);
        $coupon  = Coupon::factory()->create(['min_order_amount' => 1000]);

        app(CartService::class)->add($student, $this->course(['price' => 20]));

        try {
            $this->orders()->checkout($student, $coupon->code);
            $this->fail('coupon should have been rejected');
        } catch (CouponException $e) {
            $this->assertSame('min_order', $e->reason);
        }

        $this->assertSame('pending', $first->fresh()->status, 'previous order must not be cancelled');
        $this->assertSame(1, Order::count());
        $this->assertSame(0, $coupon->fresh()->times_used);
        $this->assertSame(2, app(CartService::class)->forUser($student)->items()->count());
    }

    public function test_unknown_expired_and_exhausted_coupons_are_rejected(): void
    {
        $cases = [
            'not_found' => 'NOPE-123',
            'expired'   => Coupon::factory()->expired()->create()->code,
            'exhausted' => Coupon::factory()->limit(1, 1)->create()->code,
            'disabled'  => Coupon::factory()->disabled()->create()->code,
        ];

        foreach ($cases as $reason => $code) {
            $student = User::factory()->create();

            try {
                $this->checkoutFor($student, [$this->course()], $code);
                $this->fail("{$reason} coupon accepted");
            } catch (CouponException $e) {
                $this->assertSame($reason, $e->reason);
            }
        }

        $this->assertSame(0, Order::count());
    }

    public function test_per_user_limit_is_enforced_across_paid_orders(): void
    {
        $student = User::factory()->create();
        $coupon  = Coupon::factory()->percentage(10)->create(['per_user_limit' => 1]);

        $first = $this->checkoutFor($student, [$this->course()], $coupon->code);
        $this->orders()->markPaid($first, 'manual');

        $this->assertRejected('coupon_per_user_limit', fn () => $this->checkoutFor($student, [$this->course()], $coupon->code));

        $this->assertSame(1, $coupon->fresh()->times_used);

        // طالب آخر يستطيع استخدامه
        $this->checkoutFor(User::factory()->create(), [$this->course()], $coupon->code);
        $this->assertSame(2, $coupon->fresh()->times_used);
    }

    public function test_replacing_a_pending_order_gives_its_coupon_use_back_first(): void
    {
        $student = User::factory()->create();
        $coupon  = Coupon::factory()->percentage(10)->create(['per_user_limit' => 1, 'usage_limit' => 1]);

        $this->checkoutFor($student, [$this->course()], $coupon->code);
        $second = $this->checkoutFor($student, [$this->course()], $coupon->code);

        $this->assertSame(1, $coupon->fresh()->times_used);
        $this->assertSame(1, CouponRedemption::count());
        $this->assertSame($second->id, CouponRedemption::first()->order_id);
    }

    public function test_a_full_discount_coupon_pays_the_order_and_grants_access_at_once(): void
    {
        $student = User::factory()->create();
        $c       = $this->course(['price' => 80]);
        Coupon::factory()->percentage(100)->create(['code' => 'FREE100']);

        $order = $this->checkoutFor($student, [$c], 'FREE100');

        $this->assertSame('paid', $order->status);
        $this->assertSame('0.00', $order->total_amount);
        $this->assertSame('free', $order->gateway);
        $this->assertTrue(Enrollment::where(['user_id' => $student->id, 'course_id' => $c->id, 'status' => 'active'])->exists());
        $this->assertSame(0, app(CartService::class)->forUser($student)->items()->count());
    }

    public function test_a_fixed_coupon_never_exceeds_the_subtotal(): void
    {
        Coupon::factory()->fixed(500)->create(['code' => 'BIG']);

        $order = $this->checkoutFor(User::factory()->create(), [$this->course(['price' => 30])], 'BIG');

        $this->assertSame('30.00', $order->discount_amount);
        $this->assertSame('0.00', $order->total_amount);
        $this->assertSame('paid', $order->status);
    }

    /* ====================================================================
     |  الدفع
     * ==================================================================== */

    public function test_mark_paid_grants_access_records_payment_and_clears_only_bought_courses(): void
    {
        $student = User::factory()->create();
        $bought  = $this->course();
        $other   = $this->course();
        $order   = $this->checkoutFor($student, [$bought]);
        app(CartService::class)->add($student, $other); // أُضيفت بعد الطلب

        $paid = $this->orders()->markPaid($order, 'bank_transfer', ' TRX-1 ', 'Verified');

        $this->assertSame('paid', $paid->status);
        $this->assertSame('bank_transfer', $paid->gateway);
        $this->assertSame('TRX-1', $paid->transaction_reference);
        $this->assertNotNull($paid->paid_at);
        $this->assertSame('Verified', $paid->note);

        $this->assertTrue(Enrollment::where(['user_id' => $student->id, 'course_id' => $bought->id, 'status' => 'active'])->exists());
        $this->assertEquals([$other->id], app(CartService::class)->forUser($student)->items()->pluck('course_id')->all());
    }

    public function test_mark_paid_twice_is_harmless(): void
    {
        $student = User::factory()->create();
        $order   = $this->checkoutFor($student, [$this->course()]);

        $first  = $this->orders()->markPaid($order, 'manual', 'R-1');
        $second = $this->orders()->markPaid($order, 'manual', 'R-1');

        $this->assertEquals($first->paid_at, $second->paid_at);
        $this->assertSame(1, Enrollment::count());
    }

    public function test_payment_reactivates_a_cancelled_or_expired_enrollment_without_duplicating_it(): void
    {
        foreach (['cancelled', 'expired'] as $status) {
            $student = User::factory()->create();
            $c       = $this->course();
            Enrollment::factory()->status($status)->expiresAt(now()->subDay())->create(['user_id' => $student->id, 'course_id' => $c->id]);

            $this->orders()->markPaid($this->checkoutFor($student, [$c]), 'manual');

            $rows = Enrollment::where(['user_id' => $student->id, 'course_id' => $c->id])->get();
            $this->assertCount(1, $rows, $status);
            $this->assertSame('active', $rows[0]->status);
            $this->assertNull($rows[0]->expires_at);
        }
    }

    public function test_payment_does_not_touch_an_enrollment_that_already_grants_access(): void
    {
        $student = User::factory()->create();
        $c       = $this->course();
        $order   = $this->checkoutFor($student, [$c]);

        // سجّله المدير يدويًا بعد إنشاء الطلب
        $expires = now()->addDays(30)->startOfSecond();
        Enrollment::factory()->expiresAt($expires)->create(['user_id' => $student->id, 'course_id' => $c->id]);

        $this->orders()->markPaid($order, 'manual');

        $e = Enrollment::where(['user_id' => $student->id, 'course_id' => $c->id])->sole();
        $this->assertTrue($e->expires_at->equalTo($expires));
        $this->assertSame(1, Enrollment::count());
    }

    public function test_a_payment_reference_can_only_be_used_once_per_gateway(): void
    {
        $one = $this->checkoutFor(User::factory()->create(), [$this->course()]);
        $two = $this->checkoutFor(User::factory()->create(), [$this->course()]);

        $this->orders()->markPaid($one, 'bank_transfer', 'SAME-REF');

        $this->assertRejected('duplicate_reference', fn () => $this->orders()->markPaid($two, 'bank_transfer', 'SAME-REF'));

        $this->assertSame('pending', $two->fresh()->status);
        $this->assertSame(1, Enrollment::count(), 'rejected payment must not grant access');

        // بوابة مختلفة بنفس الرقم مسموح
        $this->assertSame('paid', $this->orders()->markPaid($two, 'cash', 'SAME-REF')->status);
    }

    public function test_orders_without_a_reference_do_not_collide(): void
    {
        $this->orders()->markPaid($this->checkoutFor(User::factory()->create(), [$this->course()]), 'manual');
        $this->orders()->markPaid($this->checkoutFor(User::factory()->create(), [$this->course()]), 'manual');

        $this->assertSame(2, Order::where('status', 'paid')->count());
    }

    public function test_only_allowed_status_transitions_work(): void
    {
        $student = User::factory()->create();

        $cancelled = $this->checkoutFor($student, [$this->course()]);
        $this->orders()->cancel($cancelled);
        $this->assertRejected('invalid_transition', fn () => $this->orders()->markPaid($cancelled, 'manual'));
        $this->assertRejected('invalid_transition', fn () => $this->orders()->refund($cancelled, 'why'));

        $paid = $this->checkoutFor($student, [$this->course()]);
        $this->orders()->markPaid($paid, 'manual');
        $this->assertRejected('invalid_transition', fn () => $this->orders()->cancel($paid));
        $this->assertRejected('invalid_transition', fn () => $this->orders()->fail($paid));

        $pending = $this->checkoutFor($student, [$this->course()]);
        $this->assertRejected('invalid_transition', fn () => $this->orders()->refund($pending, 'why'));

        $refunded = $this->checkoutFor($student, [$this->course()]);
        $this->orders()->markPaid($refunded, 'manual');
        $this->orders()->refund($refunded, 'Requested');
        $this->assertRejected('invalid_transition', fn () => $this->orders()->markPaid($refunded, 'manual'));
        $this->assertRejected('invalid_transition', fn () => $this->orders()->refund($refunded, 'again'));
    }

    /* ====================================================================
     |  إلغاء وفشل واسترداد
     * ==================================================================== */

    public function test_cancel_and_fail_give_the_coupon_use_back_exactly_once(): void
    {
        foreach (['cancel', 'fail'] as $method) {
            $coupon = Coupon::factory()->percentage(10)->create();
            $order  = $this->checkoutFor(User::factory()->create(), [$this->course()], $coupon->code);
            $this->assertSame(1, $coupon->fresh()->times_used);

            $this->orders()->{$method}($order, 'reason');

            $this->assertSame(0, $coupon->fresh()->times_used, $method);
            $this->assertSame(0, CouponRedemption::where('coupon_id', $coupon->id)->count());

            $this->assertRejected('invalid_transition', fn () => $this->orders()->{$method}($order));
            $this->assertSame(0, $coupon->fresh()->times_used, 'must never go negative');
        }
    }

    public function test_a_soft_deleted_coupon_still_gets_its_use_back(): void
    {
        $coupon = Coupon::factory()->percentage(10)->create();
        $order  = $this->checkoutFor(User::factory()->create(), [$this->course()], $coupon->code);
        $coupon->delete();

        $this->orders()->cancel($order);

        $this->assertSame(0, Coupon::withTrashed()->find($coupon->id)->times_used);
    }

    public function test_refund_removes_access_revokes_certificates_and_keeps_the_coupon_consumed(): void
    {
        $student = User::factory()->create();
        $c       = $this->course();
        $coupon  = Coupon::factory()->percentage(10)->create();
        $order   = $this->checkoutFor($student, [$c], $coupon->code);
        $this->orders()->markPaid($order, 'manual');

        $cert = Certificate::factory()->create(['user_id' => $student->id, 'course_id' => $c->id]);

        $refunded = $this->orders()->refund($order, 'Student requested');

        $this->assertSame('refunded', $refunded->status);
        $this->assertNotNull($refunded->refunded_at);
        $this->assertSame('Student requested', $refunded->note);
        $this->assertSame('cancelled', Enrollment::where(['user_id' => $student->id, 'course_id' => $c->id])->value('status'));
        $this->assertNotNull($cert->fresh()->revoked_at);

        $this->assertSame(1, $coupon->fresh()->times_used, 'a refunded order still used the coupon');
        $this->assertSame(1, CouponRedemption::count());
    }

    public function test_refund_keeps_access_the_student_bought_in_another_paid_order(): void
    {
        $student = User::factory()->create();
        $c       = $this->course();
        $first   = $this->checkoutFor($student, [$c]);
        $this->orders()->markPaid($first, 'manual');

        // طلب مدفوع آخر لنفس الدورة (مثلًا بعد انتهاء وصوله الأول)
        $second = Order::factory()->paid('manual', 'OTHER')->create(['user_id' => $student->id]);
        OrderItem::factory()->create(['order_id' => $second->id, 'course_id' => $c->id]);

        $this->orders()->refund($first, 'Duplicate');

        $this->assertSame('active', Enrollment::where(['user_id' => $student->id, 'course_id' => $c->id])->value('status'));
    }

    /* ====================================================================
     |  الانتهاء التلقائي
     * ==================================================================== */

    public function test_expire_pending_fails_only_old_pending_orders_and_returns_coupons(): void
    {
        $coupon = Coupon::factory()->percentage(10)->create();

        $old = $this->checkoutFor(User::factory()->create(), [$this->course()], $coupon->code);
        Order::whereKey($old->id)->update(['created_at' => now()->subHours(Order::PENDING_EXPIRES_AFTER_HOURS + 1)]);

        $fresh = $this->checkoutFor(User::factory()->create(), [$this->course()]);
        Order::whereKey($fresh->id)->update(['created_at' => now()->subHours(Order::PENDING_EXPIRES_AFTER_HOURS - 1)]);

        $oldPaid = Order::factory()->paid('manual', 'P-1')->createdAgo(100)->create();

        $this->assertSame(1, $this->orders()->expirePending());

        $this->assertSame('failed', $old->fresh()->status);
        $this->assertSame(0, $coupon->fresh()->times_used);
        $this->assertSame('pending', $fresh->fresh()->status);
        $this->assertSame('paid', $oldPaid->fresh()->status);
    }

    public function test_expire_command_runs_and_reports_the_count(): void
    {
        Order::factory()->createdAgo(48)->create();

        $this->assertSame(0, Artisan::call('orders:expire-pending'));
        $this->assertStringContainsString('Expired 1', Artisan::output());
        $this->assertSame('failed', Order::first()->status);
    }

    /* ====================================================================
     |  قيود قاعدة البيانات
     * ==================================================================== */

    public function test_database_enforces_unique_order_numbers_items_and_redemptions(): void
    {
        $order = Order::factory()->create();

        try {
            Order::factory()->create(['order_number' => $order->order_number]);
            $this->fail('duplicate order number allowed');
        } catch (UniqueConstraintViolationException) {
            $this->assertSame(1, Order::count());
        }

        $c = $this->course();
        OrderItem::factory()->create(['order_id' => $order->id, 'course_id' => $c->id]);

        try {
            OrderItem::factory()->create(['order_id' => $order->id, 'course_id' => $c->id]);
            $this->fail('duplicate course in one order allowed');
        } catch (UniqueConstraintViolationException) {
            $this->assertSame(1, OrderItem::count());
        }

        $coupon = Coupon::factory()->create();
        (new CouponRedemption)->forceFill(['coupon_id' => $coupon->id, 'user_id' => $order->user_id, 'order_id' => $order->id])->save();

        $this->expectException(UniqueConstraintViolationException::class);
        (new CouponRedemption)->forceFill(['coupon_id' => $coupon->id, 'user_id' => $order->user_id, 'order_id' => $order->id])->save();
    }

    public function test_order_fields_are_not_mass_assignable(): void
    {
        $order = Order::factory()->create(['total_amount' => 100]);

        // النموذج مقفل بالكامل: أي محاولة تعبئة جماعية ترمي استثناءً بدل أن تمرّ بصمت
        try {
            $order->fill(['total_amount' => 0, 'status' => 'paid', 'user_id' => 999]);
            $this->fail('mass assignment allowed');
        } catch (MassAssignmentException) {
            // المتوقع
        }

        $this->assertSame('100.00', $order->fresh()->total_amount);
        $this->assertSame('pending', $order->fresh()->status);
    }

    public function test_the_coupon_snapshot_survives_deleting_the_coupon_for_good(): void
    {
        $coupon = Coupon::factory()->percentage(10)->create(['code' => 'GONE10']);
        $order  = $this->checkoutFor(User::factory()->create(), [$this->course()], $coupon->code);
        $this->orders()->cancel($order); // يحرّر الاستخدام فيسمح الحذف النهائي

        $coupon->forceDelete();

        $fresh = $order->fresh();
        $this->assertNull($fresh->coupon_id);
        $this->assertSame('GONE10', $fresh->coupon_code);
    }

    /* ====================================================================
     |  لوحة الإدارة
     * ==================================================================== */

    public function test_guests_and_users_without_permission_are_blocked(): void
    {
        $order = Order::factory()->create();

        $this->get(route('cms.admin.orders.index'))->assertRedirect(route('login'));

        $this->actingAs(User::factory()->create());
        $this->getJson(route('cms.admin.orders.index'))->assertForbidden();
        $this->getJson(route('cms.admin.orders.show', $order))->assertForbidden();
        $this->postJson(route('cms.admin.orders.mark-paid', $order), ['gateway' => 'manual'])->assertForbidden();
        $this->postJson(route('cms.admin.orders.cancel', $order))->assertForbidden();
        $this->postJson(route('cms.admin.orders.refund', $order), ['note' => 'abc'])->assertForbidden();
    }

    public function test_each_action_needs_its_own_permission(): void
    {
        $order = Order::factory()->create();
        OrderItem::factory()->create(['order_id' => $order->id]);

        $this->actingAs($this->adminWith('orders.view'));
        $this->getJson(route('cms.admin.orders.index'))->assertOk();
        $this->postJson(route('cms.admin.orders.cancel', $order))->assertForbidden();
        $this->postJson(route('cms.admin.orders.mark-paid', $order), ['gateway' => 'manual'])->assertForbidden();

        $this->actingAs($this->adminWith('orders.view', 'orders.edit'));
        $this->postJson(route('cms.admin.orders.mark-paid', $order), ['gateway' => 'manual'])->assertForbidden();
        $this->postJson(route('cms.admin.orders.refund', $order), ['note' => 'abc'])->assertForbidden();

        $this->assertSame('pending', $order->fresh()->status);
    }

    public function test_there_is_no_route_to_create_edit_or_delete_orders(): void
    {
        $order = Order::factory()->create();
        $this->actingAs($this->admin());

        $this->postJson('/cms/admin/orders')->assertStatus(405);
        $this->putJson(route('cms.admin.orders.show', $order))->assertStatus(405);
        $this->deleteJson(route('cms.admin.orders.show', $order))->assertStatus(405);
        $this->assertSame(1, Order::count());
    }

    public function test_index_page_renders_including_deleted_users(): void
    {
        $this->withoutVite();
        $order = Order::factory()->create();
        $gone  = Order::factory()->create();
        $gone->user->delete();

        $this->actingAs($this->admin())->get(route('cms.admin.orders.index'))
            ->assertOk()->assertSee($order->order_number)->assertSee($gone->order_number);
    }

    public function test_index_stats_count_every_status_and_revenue_counts_paid_only(): void
    {
        Order::factory()->paid('manual', 'A')->create(['total_amount' => 100]);
        Order::factory()->paid('manual', 'B')->create(['total_amount' => 50.50]);
        Order::factory()->create(['total_amount' => 999]);
        Order::factory()->status('refunded')->create(['total_amount' => 70]);
        Order::factory()->status('failed')->create();
        $this->actingAs($this->admin());

        $this->getJson(route('cms.admin.orders.index'))->assertOk()
            ->assertJsonPath('stats', ['total' => 5, 'pending' => 1, 'paid' => 2, 'refunded' => 1, 'revenue' => '150.50']);
    }

    public function test_index_filters_by_status_search_reference_and_dates(): void
    {
        $paid    = Order::factory()->paid('bank_transfer', 'WIRE-777')->create(['user_id' => User::factory()->create(['name' => 'Layla Hassan', 'email' => 'layla@example.test'])->id]);
        $pending = Order::factory()->createdAgo(24 * 40)->create(['user_id' => User::factory()->create(['name' => 'Omar Said', 'email' => 'omar@example.test'])->id]);
        $this->actingAs($this->admin());

        $this->getJson(route('cms.admin.orders.index', ['status' => 'paid']))
            ->assertSee($paid->order_number)->assertDontSee($pending->order_number);

        $this->getJson(route('cms.admin.orders.index', ['search' => 'omar@']))
            ->assertSee($pending->order_number)->assertDontSee($paid->order_number);

        $this->getJson(route('cms.admin.orders.index', ['search' => 'WIRE-777']))
            ->assertSee($paid->order_number)->assertDontSee($pending->order_number);

        $this->getJson(route('cms.admin.orders.index', ['search' => strtolower($paid->order_number)]))
            ->assertSee($paid->order_number);

        $this->getJson(route('cms.admin.orders.index', ['from' => now()->subDay()->toDateString()]))
            ->assertSee($paid->order_number)->assertDontSee($pending->order_number);

        $this->getJson(route('cms.admin.orders.index', ['to' => now()->subDays(30)->toDateString()]))
            ->assertSee($pending->order_number)->assertDontSee($paid->order_number);

        // قيم غير صالحة تُتجاهل بدل أن تكسر الاستعلام
        $this->getJson(route('cms.admin.orders.index', ['from' => 'garbage', 'status' => 'nope']))->assertOk();
        $this->assertSame(0, Order::search('%')->count());
    }

    public function test_index_sort_is_whitelisted_and_total_sort_works(): void
    {
        $small = Order::factory()->create(['total_amount' => 11.11]);
        $big   = Order::factory()->create(['total_amount' => 555.55]);
        $this->actingAs($this->admin());

        $this->getJson(route('cms.admin.orders.index', ['sort' => 'x; drop table orders', 'dir' => 'zz']))->assertOk();

        $html = $this->getJson(route('cms.admin.orders.index', ['sort' => 'total_amount', 'dir' => 'desc']))->assertOk()->json('html');
        $this->assertLessThan(strpos($html, $small->order_number), strpos($html, $big->order_number));
    }

    public function test_show_returns_the_frozen_snapshot_and_what_the_viewer_may_do(): void
    {
        $student = User::factory()->create();
        $coupon  = Coupon::factory()->percentage(10)->create(['code' => 'TEN']);
        $order   = $this->checkoutFor($student, [$this->course(['price' => 100, 'title_en' => 'Frozen title'])], 'TEN');

        $this->actingAs($this->adminWith('orders.view', 'orders.edit'));

        $this->getJson(route('cms.admin.orders.show', $order))->assertOk()
            ->assertJsonPath('data.order_number', $order->order_number)
            ->assertJsonPath('data.user.email', $student->email)
            ->assertJsonPath('data.subtotal', '100.00')
            ->assertJsonPath('data.discount_amount', '10.00')
            ->assertJsonPath('data.total_amount', '90.00')
            ->assertJsonPath('data.coupon_code', 'TEN')
            ->assertJsonPath('data.items.0.title_en', 'Frozen title')
            ->assertJsonPath('data.can', ['mark_paid' => false, 'cancel' => true, 'refund' => false]);

        $this->actingAs($this->admin());
        $this->getJson(route('cms.admin.orders.show', $order))
            ->assertJsonPath('data.can', ['mark_paid' => true, 'cancel' => true, 'refund' => false]);

        $this->orders()->markPaid($order, 'manual');
        $this->getJson(route('cms.admin.orders.show', $order))
            ->assertJsonPath('data.can', ['mark_paid' => false, 'cancel' => false, 'refund' => true]);
    }

    public function test_show_works_for_deleted_users_and_courses(): void
    {
        $student = User::factory()->create();
        $c       = $this->course();
        $order   = $this->checkoutFor($student, [$c]);
        $c->delete();
        $student->delete();

        $this->actingAs($this->admin())->getJson(route('cms.admin.orders.show', $order))->assertOk()
            ->assertJsonPath('data.user.deleted', true)
            ->assertJsonPath('data.items.0.course_deleted', true);
    }

    public function test_mark_paid_over_http_validates_and_grants_access(): void
    {
        $student = User::factory()->create();
        $c       = $this->course();
        $order   = $this->checkoutFor($student, [$c]);
        $this->actingAs($this->admin());

        $url = route('cms.admin.orders.mark-paid', $order);

        $this->postJson($url, [])->assertStatus(422)->assertJsonValidationErrors('gateway');
        // البوابات الإلكترونية لا تُسجَّل يدويًا
        $this->postJson($url, ['gateway' => 'stripe'])->assertStatus(422)->assertJsonValidationErrors('gateway');
        $this->postJson($url, ['gateway' => 'manual', 'note' => str_repeat('x', 501)])->assertStatus(422)->assertJsonValidationErrors('note');
        $this->assertSame('pending', $order->fresh()->status);

        $this->postJson($url, ['gateway' => 'cash', 'transaction_reference' => '  R-9  ', 'note' => 'Paid at the desk'])
            ->assertOk()->assertJsonPath('stats.paid', 1);

        $fresh = $order->fresh();
        $this->assertSame('paid', $fresh->status);
        $this->assertSame('R-9', $fresh->transaction_reference);
        $this->assertTrue(Enrollment::where(['user_id' => $student->id, 'course_id' => $c->id, 'status' => 'active'])->exists());

        // إعادة الإرسال لا تكرر شيئًا
        $this->postJson($url, ['gateway' => 'cash', 'transaction_reference' => 'R-9'])->assertOk();
        $this->assertSame(1, Enrollment::count());
    }

    public function test_duplicate_reference_over_http_is_a_409_with_a_readable_message(): void
    {
        Order::factory()->paid('bank_transfer', 'USED')->create();
        $order = $this->checkoutFor(User::factory()->create(), [$this->course()]);
        $this->actingAs($this->admin());

        $this->postJson(route('cms.admin.orders.mark-paid', $order), ['gateway' => 'bank_transfer', 'transaction_reference' => 'USED'])
            ->assertStatus(409)->assertJsonPath('message', __('orders.errors.duplicate_reference'));

        $this->assertSame('pending', $order->fresh()->status);
    }

    public function test_cancel_over_http_and_it_refuses_paid_orders(): void
    {
        $pending = $this->checkoutFor(User::factory()->create(), [$this->course()]);
        $paid    = $this->checkoutFor(User::factory()->create(), [$this->course()]);
        $this->orders()->markPaid($paid, 'manual');
        $this->actingAs($this->admin());

        $this->postJson(route('cms.admin.orders.cancel', $pending), ['note' => 'Customer asked'])->assertOk();
        $this->assertSame('cancelled', $pending->fresh()->status);
        $this->assertSame('Customer asked', $pending->fresh()->note);

        $this->postJson(route('cms.admin.orders.cancel', $paid))->assertStatus(409);
        $this->assertSame('paid', $paid->fresh()->status);
    }

    public function test_refund_over_http_requires_a_reason_and_a_paid_order(): void
    {
        $student = User::factory()->create();
        $c       = $this->course();
        $paid    = $this->checkoutFor($student, [$c]);
        $this->orders()->markPaid($paid, 'manual');
        $pending = $this->checkoutFor(User::factory()->create(), [$this->course()]);
        $this->actingAs($this->admin());

        $this->postJson(route('cms.admin.orders.refund', $paid), [])->assertStatus(422)->assertJsonValidationErrors('note');
        $this->postJson(route('cms.admin.orders.refund', $paid), ['note' => 'ab'])->assertStatus(422);
        $this->postJson(route('cms.admin.orders.refund', $pending), ['note' => 'Because'])->assertStatus(409);

        $this->postJson(route('cms.admin.orders.refund', $paid), ['note' => 'Duplicate purchase'])
            ->assertOk()->assertJsonPath('stats.refunded', 1);

        $this->assertSame('refunded', $paid->fresh()->status);
        $this->assertSame('cancelled', Enrollment::where(['user_id' => $student->id, 'course_id' => $c->id])->value('status'));
    }

    public function test_unknown_order_ids_return_404(): void
    {
        $this->actingAs($this->admin());

        $this->getJson(route('cms.admin.orders.show', 999999))->assertNotFound();
        $this->postJson(route('cms.admin.orders.cancel', 999999))->assertNotFound();
    }

    public function test_messages_exist_in_both_languages(): void
    {
        foreach (['ar', 'en'] as $locale) {
            app()->setLocale($locale);

            foreach (['account_inactive', 'empty_cart', 'coupon_per_user_limit', 'invalid_transition', 'duplicate_reference'] as $reason) {
                $this->assertNotSame("orders.errors.{$reason}", OrderException::because($reason)->getMessage(), "{$locale}.{$reason}");
            }

            foreach (['superseded', 'expired', 'refund_revoked'] as $key) {
                $this->assertNotSame("orders.system_notes.{$key}", __("orders.system_notes.{$key}"), "{$locale}.{$key}");
            }

            foreach (Order::STATUSES as $status) {
                $this->assertNotSame("orders.statuses.{$status}", __("orders.statuses.{$status}"), "{$locale}.{$status}");
            }

            foreach (['marked_paid', 'cancelled', 'refunded'] as $key) {
                $this->assertNotSame("orders.messages.{$key}", __("orders.messages.{$key}"), "{$locale}.{$key}");
            }
        }
    }
}
