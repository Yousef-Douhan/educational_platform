<?php

namespace Tests\Feature;

use App\Exceptions\OrderException;
use App\Models\Coupon;
use App\Models\Course;
use App\Models\Enrollment;
use App\Models\Order;
use App\Models\Payment;
use App\Models\User;
use App\Services\CartService;
use App\Services\OrderService;
use App\Services\PaymentService;
use Illuminate\Database\Eloquent\MassAssignmentException;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class PaymentModuleTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        foreach (['payments.view', 'orders.view', 'orders.edit', 'orders.mark-paid', 'orders.refund'] as $name) {
            Permission::findOrCreate($name, 'web');
        }
    }

    /* ---------- أدوات ---------- */

    private function payments(): PaymentService
    {
        return app(PaymentService::class);
    }

    private function orders(): OrderService
    {
        return app(OrderService::class);
    }

    private function admin(string ...$permissions): User
    {
        $u = User::factory()->create();
        $u->givePermissionTo($permissions ?: Permission::all());

        return $u;
    }

    private function pendingOrder(?User $student = null, float $price = 100, ?string $coupon = null): Order
    {
        $student ??= User::factory()->create();
        $course   = Course::factory()->published()->paid($price)->create();
        app(CartService::class)->add($student, $course);

        return $this->orders()->checkout($student, $coupon);
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
     |  فتح محاولة دفع
     * ==================================================================== */

    public function test_start_opens_a_pending_attempt_with_the_order_amount(): void
    {
        $order = $this->pendingOrder(price: 80);

        $payment = $this->payments()->start($order, 'stripe', 'card');

        $this->assertSame('pending', $payment->status);
        $this->assertSame('80.00', $payment->amount);
        $this->assertSame('USD', $payment->currency);
        $this->assertSame('stripe', $payment->gateway);
        $this->assertSame('card', $payment->payment_method);
        $this->assertNull($payment->transaction_reference);
        $this->assertSame('pending', $order->fresh()->status, 'starting a payment does not pay the order');
    }

    public function test_start_refuses_orders_that_cannot_be_paid(): void
    {
        foreach (['paid', 'cancelled', 'failed', 'refunded'] as $status) {
            $order = Order::factory()->status($status)->create();

            $this->assertRejected('order_not_payable', fn () => $this->payments()->start($order, 'stripe', 'card'));
        }

        $free = Order::factory()->create(['subtotal' => 0, 'total_amount' => 0]);
        $this->assertRejected('nothing_to_pay', fn () => $this->payments()->start($free, 'stripe', 'card'));

        $this->assertSame(0, Payment::count());
    }

    /* ====================================================================
     |  نجاح الدفع
     * ==================================================================== */

    public function test_a_successful_payment_pays_the_order_grants_access_and_clears_the_cart(): void
    {
        $student = User::factory()->create();
        $order   = $this->pendingOrder($student);
        $payment = $this->payments()->start($order, 'stripe', 'card');

        $done = $this->payments()->succeed($payment, '  pi_123  ', [
            'id'          => 'pi_123',
            'card_number' => '4242424242424242',
            'nested'      => ['cvc' => '123', 'ok' => true],
        ]);

        $this->assertSame('success', $done->status);
        $this->assertTrue($done->succeeded);
        $this->assertSame('pi_123', $done->transaction_reference);
        $this->assertNotNull($done->paid_at);

        $paid = $order->fresh();
        $this->assertSame('paid', $paid->status);
        $this->assertSame('stripe', $paid->gateway);
        $this->assertSame('pi_123', $paid->transaction_reference);

        $courseId = $order->items->first()->course_id;
        $this->assertTrue(Enrollment::where(['user_id' => $student->id, 'course_id' => $courseId, 'status' => 'active'])->exists());
        $this->assertSame(0, app(CartService::class)->forUser($student)->items()->count());

        // لا بيانات بطاقة في السجل
        $this->assertSame('[redacted]', $done->gateway_response['card_number']);
        $this->assertSame('[redacted]', $done->gateway_response['nested']['cvc']);
        $this->assertTrue($done->gateway_response['nested']['ok']);
        $this->assertSame('pi_123', $done->gateway_response['id']);
    }

    public function test_a_repeated_success_notification_is_harmless(): void
    {
        $order   = $this->pendingOrder();
        $payment = $this->payments()->start($order, 'stripe', 'card');

        $first  = $this->payments()->succeed($payment, 'pi_1');
        $second = $this->payments()->succeed($payment, 'pi_1');

        $this->assertSame($first->id, $second->id);
        $this->assertSame(1, Payment::where('status', 'success')->count());
        $this->assertSame(1, Enrollment::count());
    }

    public function test_a_payment_with_the_wrong_amount_or_currency_is_rejected_and_nothing_changes(): void
    {
        $order = $this->pendingOrder(price: 100);

        $short = $this->payments()->start($order, 'stripe', 'card');
        $short->forceFill(['amount' => 99.99])->save();
        $this->assertRejected('amount_mismatch', fn () => $this->payments()->succeed($short, 'pi_a'));

        $euro = $this->payments()->start($order, 'stripe', 'card');
        $euro->forceFill(['currency' => 'EUR'])->save();
        $this->assertRejected('amount_mismatch', fn () => $this->payments()->succeed($euro, 'pi_b'));

        $this->assertSame('pending', $order->fresh()->status);
        $this->assertSame(0, Enrollment::count());
        $this->assertSame('pending', $short->fresh()->status);
    }

    public function test_a_reference_is_required_to_confirm(): void
    {
        $payment = $this->payments()->start($this->pendingOrder(), 'stripe', 'card');

        $this->assertRejected('reference_required', fn () => $this->payments()->succeed($payment, '   '));
        $this->assertSame('pending', $payment->fresh()->status);
    }

    public function test_success_cannot_be_applied_to_a_closed_order_or_a_closed_attempt(): void
    {
        // طلب أُلغي: محاولاته المفتوحة تُغلق معه
        $order   = $this->pendingOrder();
        $payment = $this->payments()->start($order, 'stripe', 'card');
        $this->orders()->cancel($order);

        $this->assertSame('failed', $payment->fresh()->status);
        $this->assertRejected('payment_not_pending', fn () => $this->payments()->succeed($payment, 'pi_late'));
        $this->assertSame('cancelled', $order->fresh()->status);
        $this->assertSame(0, Enrollment::count());

        // دفعة معلّقة وصلت لطلب ليس معلّقًا (حالة شاذة): تُرفض بحسب حالة الطلب
        $failedOrder = Order::factory()->status('failed')->create();
        $stray       = Payment::factory()->create(['order_id' => $failedOrder->id]);
        $this->assertRejected('order_not_payable', fn () => $this->payments()->succeed($stray, 'pi_x'));

        $paidOrder = Order::factory()->paid('manual', 'M-1')->create();
        $dup       = Payment::factory()->create(['order_id' => $paidOrder->id]);
        $this->assertRejected('order_already_paid', fn () => $this->payments()->succeed($dup, 'pi_y'));
    }

    public function test_the_same_reference_cannot_pay_two_orders(): void
    {
        $a = $this->payments()->start($this->pendingOrder(), 'stripe', 'card');
        $b = $this->payments()->start($orderB = $this->pendingOrder(), 'stripe', 'card');

        $this->payments()->succeed($a, 'pi_same');

        $this->assertRejected('duplicate_reference', fn () => $this->payments()->succeed($b, 'pi_same'));
        $this->assertSame('pending', $orderB->fresh()->status);
        $this->assertSame('pending', $b->fresh()->status);
        $this->assertSame(1, Enrollment::count());
    }

    /* ====================================================================
     |  الفشل وإعادة المحاولة
     * ==================================================================== */

    public function test_a_failed_payment_keeps_the_order_open_for_another_try(): void
    {
        $order = $this->pendingOrder();

        $first = $this->payments()->start($order, 'stripe', 'card');
        $failed = $this->payments()->fail($first, ['error' => 'card_declined', 'client_secret' => 'sek_123']);

        $this->assertSame('failed', $failed->status);
        $this->assertNull($failed->succeeded);
        $this->assertSame('[redacted]', $failed->gateway_response['client_secret']);
        $this->assertSame('pending', $order->fresh()->status);

        $second = $this->payments()->start($order, 'stripe', 'card');
        $this->payments()->succeed($second, 'pi_ok');

        $this->assertSame('paid', $order->fresh()->status);
        $this->assertEquals(['failed', 'success'], Payment::where('order_id', $order->id)->orderBy('id')->pluck('status')->all());
    }

    public function test_fail_twice_is_harmless_but_a_successful_payment_cannot_fail(): void
    {
        $order   = $this->pendingOrder();
        $payment = $this->payments()->start($order, 'stripe', 'card');

        $this->payments()->fail($payment);
        $this->assertSame('failed', $this->payments()->fail($payment)->status);

        $ok = $this->payments()->start($order, 'stripe', 'card');
        $this->payments()->succeed($ok, 'pi_ok');
        $this->assertRejected('payment_not_pending', fn () => $this->payments()->fail($ok));
    }

    public function test_when_one_attempt_succeeds_the_other_open_attempts_are_closed(): void
    {
        $order = $this->pendingOrder();
        $one   = $this->payments()->start($order, 'stripe', 'card');
        $two   = $this->payments()->start($order, 'paypal', 'wallet');

        $this->payments()->succeed($one, 'pi_win');

        $this->assertSame('failed', $two->fresh()->status);
        $this->assertRejected('payment_not_pending', fn () => $this->payments()->succeed($two, 'pp_late'));
        $this->assertSame(1, Payment::where('order_id', $order->id)->where('status', 'success')->count());
    }

    /* ====================================================================
     |  السجل من مسارات الدفع الأخرى
     * ==================================================================== */

    public function test_a_manual_payment_by_an_admin_is_recorded_in_the_ledger(): void
    {
        $order = $this->pendingOrder(price: 60);

        $this->orders()->markPaid($order, 'bank_transfer', 'WIRE-1');

        $payment = Payment::where('order_id', $order->id)->sole();
        $this->assertSame('success', $payment->status);
        $this->assertSame('bank_transfer', $payment->gateway);
        $this->assertSame('bank_transfer', $payment->payment_method);
        $this->assertSame('60.00', $payment->amount);
        $this->assertSame('WIRE-1', $payment->transaction_reference);
        $this->assertTrue($payment->succeeded);
    }

    public function test_a_free_order_records_a_zero_payment(): void
    {
        Coupon::factory()->percentage(100)->create(['code' => 'FREE']);

        $order = $this->pendingOrder(coupon: 'FREE');

        $payment = Payment::where('order_id', $order->id)->sole();
        $this->assertSame('free', $payment->gateway);
        $this->assertSame('0.00', $payment->amount);
        $this->assertSame('success', $payment->status);
    }

    public function test_an_admin_payment_closes_open_gateway_attempts(): void
    {
        $order  = $this->pendingOrder();
        $stripe = $this->payments()->start($order, 'stripe', 'card');

        $this->orders()->markPaid($order, 'cash');

        $this->assertSame('failed', $stripe->fresh()->status);
        $this->assertSame(1, Payment::where('order_id', $order->id)->where('status', 'success')->count());
        $this->assertRejected('payment_not_pending', fn () => $this->payments()->succeed($stripe, 'pi_late'));
    }

    public function test_expiring_an_order_closes_its_open_attempts(): void
    {
        $order   = $this->pendingOrder();
        $payment = $this->payments()->start($order, 'stripe', 'card');
        Order::whereKey($order->id)->update(['created_at' => now()->subHours(Order::PENDING_EXPIRES_AFTER_HOURS + 1)]);

        $this->orders()->expirePending();

        $this->assertSame('failed', $payment->fresh()->status);
    }

    public function test_refunding_an_order_marks_its_successful_payment_refunded(): void
    {
        $order   = $this->pendingOrder();
        $payment = $this->payments()->start($order, 'stripe', 'card');
        $this->payments()->succeed($payment, 'pi_ok');

        $this->orders()->refund($order, 'Requested by the student');

        $fresh = $payment->fresh();
        $this->assertSame('refunded', $fresh->status);
        $this->assertNotNull($fresh->refunded_at);
        $this->assertTrue($fresh->succeeded, 'the one-success-per-order rule still holds after a refund');
    }

    /* ====================================================================
     |  قيود قاعدة البيانات وتنظيف الاستجابة
     * ==================================================================== */

    public function test_database_allows_one_success_per_order_and_unique_gateway_references(): void
    {
        $order = Order::factory()->create();

        Payment::factory()->failed()->count(3)->create(['order_id' => $order->id]);
        Payment::factory()->count(2)->create(['order_id' => $order->id, 'transaction_reference' => null]);
        Payment::factory()->success('ONE')->create(['order_id' => $order->id]);

        try {
            Payment::factory()->success('TWO')->create(['order_id' => $order->id]);
            $this->fail('two successful payments for one order allowed');
        } catch (UniqueConstraintViolationException) {
            $this->assertSame(1, Payment::where('status', 'success')->count());
        }

        // نفس المرجع على بوابة أخرى مسموح، وعلى البوابة نفسها ممنوع
        Payment::factory()->success('ONE')->create(['gateway' => 'paypal']);

        $this->expectException(UniqueConstraintViolationException::class);
        Payment::factory()->success('ONE')->create(['gateway' => 'paypal']);
    }

    public function test_payment_fields_are_not_mass_assignable(): void
    {
        $payment = Payment::factory()->create();

        $this->expectException(MassAssignmentException::class);
        $payment->fill(['status' => 'success', 'amount' => 0]);
    }

    public function test_gateway_responses_are_sanitized(): void
    {
        $clean = Payment::sanitize([
            'id'            => 'pi_1',
            'last4'         => '4242',
            'Card'          => ['number' => '4242', 'brand' => 'visa'],
            'client_secret' => 'sek',
            'Authorization' => 'Bearer abc',
            'api_key'       => 'k',
            'meta'          => ['deep' => ['password' => 'p', 'note' => 'fine']],
            'items'         => [['token' => 't', 'sku' => 'a']],
        ]);

        $this->assertSame('pi_1', $clean['id']);
        $this->assertSame('4242', $clean['last4']);
        $this->assertSame('[redacted]', $clean['Card']);
        $this->assertSame('[redacted]', $clean['client_secret']);
        $this->assertSame('[redacted]', $clean['Authorization']);
        $this->assertSame('[redacted]', $clean['api_key']);
        $this->assertSame('[redacted]', $clean['meta']['deep']['password']);
        $this->assertSame('fine', $clean['meta']['deep']['note']);
        $this->assertSame('[redacted]', $clean['items'][0]['token']);
        $this->assertSame('a', $clean['items'][0]['sku']);

        $this->assertNull(Payment::sanitize(null));
        $this->assertNull(Payment::sanitize([]));
        $this->assertSame(['truncated' => true], Payment::sanitize(['blob' => str_repeat('x', Payment::MAX_RESPONSE_BYTES + 1)]));
    }

    /* ====================================================================
     |  لوحة الإدارة
     * ==================================================================== */

    public function test_guests_and_users_without_permission_are_blocked(): void
    {
        $payment = Payment::factory()->create();

        $this->get(route('cms.admin.payments.index'))->assertRedirect(route('login'));

        $this->actingAs(User::factory()->create());
        $this->getJson(route('cms.admin.payments.index'))->assertForbidden();
        $this->getJson(route('cms.admin.payments.show', $payment))->assertForbidden();

        // صلاحيات الطلبات لا تفتح سجل المدفوعات
        $this->actingAs($this->admin('orders.view', 'orders.refund'));
        $this->getJson(route('cms.admin.payments.index'))->assertForbidden();

        $this->actingAs($this->admin('payments.view'));
        $this->getJson(route('cms.admin.payments.index'))->assertOk();
    }

    public function test_there_is_no_route_to_create_edit_or_delete_payments(): void
    {
        $payment = Payment::factory()->create();
        $this->actingAs($this->admin());

        $this->postJson('/cms/admin/payments')->assertStatus(405);
        $this->putJson(route('cms.admin.payments.show', $payment))->assertStatus(405);
        $this->deleteJson(route('cms.admin.payments.show', $payment))->assertStatus(405);
        $this->assertSame(1, Payment::count());
    }

    public function test_index_page_renders_with_gateway_filter_options(): void
    {
        $this->withoutVite();
        Payment::factory()->create(['gateway' => 'stripe']);
        Payment::factory()->create(['gateway' => 'bank_transfer']);

        $this->actingAs($this->admin())->get(route('cms.admin.payments.index'))
            ->assertOk()->assertSee('stripe')->assertSee('value="bank_transfer"', false);
    }

    public function test_index_stats_count_statuses_and_collected_counts_successful_only(): void
    {
        Payment::factory()->success('A')->create(['amount' => 100]);
        Payment::factory()->success('B')->create(['amount' => 50.50]);
        Payment::factory()->status('refunded')->create(['amount' => 70]);
        Payment::factory()->failed()->create(['amount' => 999]);
        Payment::factory()->create(['amount' => 5]);
        $this->actingAs($this->admin());

        $this->getJson(route('cms.admin.payments.index'))->assertOk()
            ->assertJsonPath('stats', ['total' => 5, 'success' => 2, 'pending' => 1, 'failed' => 1, 'collected' => '150.50']);
    }

    public function test_index_filters_by_status_gateway_search_and_dates(): void
    {
        $orderA = Order::factory()->create(['user_id' => User::factory()->create(['name' => 'Layla Hassan', 'email' => 'layla@example.test'])->id]);
        $orderB = Order::factory()->create(['user_id' => User::factory()->create(['name' => 'Omar Said', 'email' => 'omar@example.test'])->id]);

        $a = Payment::factory()->success('STRIPE-AAA')->create(['order_id' => $orderA->id, 'gateway' => 'stripe']);
        $b = Payment::factory()->failed()->create(['order_id' => $orderB->id, 'gateway' => 'paypal', 'transaction_reference' => 'PP-BBB', 'created_at' => now()->subDays(40)]);
        $this->actingAs($this->admin());

        $this->getJson(route('cms.admin.payments.index', ['status' => 'success']))
            ->assertSee('STRIPE-AAA')->assertDontSee('PP-BBB');

        $this->getJson(route('cms.admin.payments.index', ['gateway' => 'paypal']))
            ->assertSee('PP-BBB')->assertDontSee('STRIPE-AAA');

        $this->getJson(route('cms.admin.payments.index', ['search' => 'pp-bbb']))
            ->assertSee('PP-BBB')->assertDontSee('STRIPE-AAA');

        $this->getJson(route('cms.admin.payments.index', ['search' => $orderA->order_number]))
            ->assertSee('STRIPE-AAA')->assertDontSee('PP-BBB');

        $this->getJson(route('cms.admin.payments.index', ['search' => 'omar@']))
            ->assertSee('PP-BBB')->assertDontSee('STRIPE-AAA');

        $this->getJson(route('cms.admin.payments.index', ['from' => now()->subDay()->toDateString()]))
            ->assertSee('STRIPE-AAA')->assertDontSee('PP-BBB');

        $this->getJson(route('cms.admin.payments.index', ['to' => now()->subDays(30)->toDateString()]))
            ->assertSee('PP-BBB')->assertDontSee('STRIPE-AAA');

        $this->getJson(route('cms.admin.payments.index', ['from' => 'garbage', 'status' => 'nope', 'gateway' => '']))->assertOk();
        $this->assertSame(0, Payment::search('%')->count());
    }

    public function test_index_sort_is_whitelisted_and_amount_sort_works(): void
    {
        Payment::factory()->create(['amount' => 11.11, 'transaction_reference' => 'SMALL-REF']);
        Payment::factory()->create(['amount' => 555.55, 'transaction_reference' => 'BIG-REF']);
        $this->actingAs($this->admin());

        $this->getJson(route('cms.admin.payments.index', ['sort' => 'x; drop table payments', 'dir' => 'zz']))->assertOk();

        $html = $this->getJson(route('cms.admin.payments.index', ['sort' => 'amount', 'dir' => 'desc']))->assertOk()->json('html');
        $this->assertLessThan(strpos($html, '11.11'), strpos($html, '555.55'));
    }

    public function test_references_are_escaped_in_the_table(): void
    {
        Payment::factory()->create(['transaction_reference' => '<img src=x onerror=alert(1)>']);
        $this->actingAs($this->admin());

        $html = $this->getJson(route('cms.admin.payments.index'))->assertOk()->json('html');

        $this->assertStringNotContainsString('<img src=x', $html);
        $this->assertStringContainsString('&lt;img', $html);
    }

    public function test_show_returns_the_stored_response_as_text_and_handles_missing_ones(): void
    {
        $withResponse = Payment::factory()->success('REF-1')->create([
            'gateway_response' => ['id' => 'pi_1', 'note' => '<script>alert(1)</script>'],
        ]);
        $without = Payment::factory()->create();
        $this->actingAs($this->admin());

        $json = $this->getJson(route('cms.admin.payments.show', $withResponse))->assertOk()
            ->assertJsonPath('data.status', 'success')
            ->assertJsonPath('data.order_number', $withResponse->order->order_number)
            ->assertJsonPath('data.transaction_reference', 'REF-1');

        $decoded = json_decode($json->json('data.gateway_response'), true);
        $this->assertSame('pi_1', $decoded['id']);
        $this->assertSame('<script>alert(1)</script>', $decoded['note']);

        $this->getJson(route('cms.admin.payments.show', $without))->assertOk()->assertJsonPath('data.gateway_response', null);
    }

    public function test_show_works_for_deleted_users_and_unknown_ids_404(): void
    {
        $payment = Payment::factory()->create();
        $payment->load('order.user');
        $payment->order->user->delete();
        $this->actingAs($this->admin());

        $this->getJson(route('cms.admin.payments.show', $payment))->assertOk()->assertJsonPath('data.user.deleted', true);
        $this->getJson(route('cms.admin.payments.show', 999999))->assertNotFound();
    }

    public function test_the_order_details_list_its_payment_attempts(): void
    {
        $order = $this->pendingOrder();
        $this->payments()->fail($this->payments()->start($order, 'stripe', 'card'));
        $this->payments()->succeed($this->payments()->start($order, 'stripe', 'card'), 'pi_ok');
        $this->actingAs($this->admin());

        $this->getJson(route('cms.admin.orders.show', $order))->assertOk()
            ->assertJsonCount(2, 'data.payments')
            ->assertJsonPath('data.payments.0.status', 'failed')
            ->assertJsonPath('data.payments.1.status', 'success')
            ->assertJsonPath('data.payments.1.reference', 'pi_ok');
    }

    public function test_messages_exist_in_both_languages(): void
    {
        foreach (['ar', 'en'] as $locale) {
            app()->setLocale($locale);

            foreach (['order_not_payable', 'nothing_to_pay', 'payment_not_pending', 'order_already_paid', 'amount_mismatch', 'reference_required'] as $reason) {
                $this->assertNotSame("orders.errors.{$reason}", OrderException::because($reason)->getMessage(), "{$locale}.{$reason}");
            }

            foreach (Payment::STATUSES as $status) {
                $this->assertNotSame("payments.statuses.{$status}", __("payments.statuses.{$status}"), "{$locale}.{$status}");
            }
        }
    }
}
