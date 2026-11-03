<?php

namespace Tests\Feature;

use App\Models\Course;
use App\Models\Enrollment;
use App\Models\Order;
use App\Models\Payment;
use App\Models\User;
use App\Models\UserNotification;
use App\Models\WebhookEvent;
use App\Services\CartService;
use App\Services\OrderService;
use App\Services\PaymentService;
use App\Services\WebhookSignature;
use Illuminate\Database\Eloquent\MassAssignmentException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class PaymentWebhookTest extends TestCase
{
    use RefreshDatabase;

    private const SECRET = 'whsec_test_secret';

    protected function setUp(): void
    {
        parent::setUp();
        app(PermissionRegistrar::class)->forgetCachedPermissions();
        config(['payments.webhooks.gateways.stripe.secret' => self::SECRET]);
    }

    /* ---------- أدوات ---------- */

    private function pendingPayment(float $price = 100): Payment
    {
        $student = User::factory()->create();
        $course  = Course::factory()->published()->paid($price)->create();
        app(CartService::class)->add($student, $course);
        $order = app(OrderService::class)->checkout($student);

        return app(PaymentService::class)->start($order, 'stripe', 'card');
    }

    private function event(Payment $payment, array $over = [], string $type = 'payment_intent.succeeded', ?string $id = null): array
    {
        $object = array_merge([
            'id'              => 'pi_' . $payment->id,
            'status'          => 'succeeded',
            'amount'          => (int) round(((float) $payment->amount) * 100),
            'amount_received' => (int) round(((float) $payment->amount) * 100),
            'currency'        => strtolower($payment->currency),
            'metadata'        => ['payment_id' => (string) $payment->id],
        ], $over);

        return ['id' => $id ?? 'evt_' . uniqid(), 'type' => $type, 'data' => ['object' => $object]];
    }

    /** يرسل إشعارًا موقَّعًا بالجسم الخام كما تفعل البوابة */
    private function deliver(array|string $event, ?string $header = null, string $gateway = 'stripe', ?string $secret = null)
    {
        $body = is_string($event) ? $event : json_encode($event);

        return $this->call('POST', "/webhooks/{$gateway}", [], [], [], [
            'CONTENT_TYPE'          => 'application/json',
            'HTTP_ACCEPT'           => 'application/json',
            'HTTP_STRIPE_SIGNATURE' => $header ?? WebhookSignature::header($body, $secret ?? self::SECRET),
        ], $body);
    }

    /* ====================================================================
     |  التوقيع
     * ==================================================================== */

    public function test_signature_accepts_a_valid_header_and_rejects_everything_else(): void
    {
        $body = '{"a":1}';
        $now  = 1_700_000_000;
        $ok   = WebhookSignature::header($body, 's3cret', $now);

        $this->assertTrue(WebhookSignature::verify($body, $ok, 's3cret', 300, $now));
        $this->assertTrue(WebhookSignature::verify($body, $ok, 's3cret', 300, $now + 299));
        $this->assertFalse(WebhookSignature::verify($body, $ok, 's3cret', 300, $now + 301), 'قديم');
        $this->assertFalse(WebhookSignature::verify($body, $ok, 's3cret', 300, $now - 301), 'من المستقبل');
        $this->assertFalse(WebhookSignature::verify($body . ' ', $ok, 's3cret', 300, $now), 'جسم معدّل');
        $this->assertFalse(WebhookSignature::verify($body, $ok, 'other', 300, $now), 'سرّ آخر');
        $this->assertFalse(WebhookSignature::verify($body, null, 's3cret', 300, $now));
        $this->assertFalse(WebhookSignature::verify($body, '', 's3cret', 300, $now));
        $this->assertFalse(WebhookSignature::verify($body, 'garbage', 's3cret', 300, $now));
        $this->assertFalse(WebhookSignature::verify($body, 't=abc,v1=00', 's3cret', 300, $now));
        $this->assertFalse(WebhookSignature::verify($body, $ok, '', 300, $now), 'بلا سرّ لا قبول');
        $this->assertFalse(WebhookSignature::verify($body, $ok, null, 300, $now));
    }

    public function test_signature_accepts_when_any_of_several_v1_values_matches(): void
    {
        $body   = '{"a":1}';
        $header = WebhookSignature::header($body, 's3cret', 1_700_000_000);

        $this->assertTrue(WebhookSignature::verify($body, 't=1700000000,v1=deadbeef,' . substr($header, strpos($header, 'v1=')), 's3cret', 300, 1_700_000_000));
    }

    public function test_signature_is_bound_to_the_timestamp(): void
    {
        $body = '{"a":1}';
        $sig  = substr(WebhookSignature::header($body, 's3cret', 1_700_000_000), strlen('t=1700000000,'));

        // نفس التوقيع بوقت آخر لا ينجح (لا يمكن تجديد إشعار قديم بتغيير الوقت)
        $this->assertFalse(WebhookSignature::verify($body, 't=1700000100,' . $sig, 's3cret', 300, 1_700_000_100));
    }

    public function test_the_endpoint_rejects_bad_signatures_without_touching_anything(): void
    {
        $payment = $this->pendingPayment();
        $event   = $this->event($payment);

        $this->deliver($event, 't=' . time() . ',v1=' . str_repeat('0', 64))->assertStatus(400)->assertJson(['message' => 'invalid_signature']);
        $this->deliver($event, '')->assertStatus(400);
        $this->deliver($event, null, 'stripe', 'wrong-secret')->assertStatus(400);

        $old = WebhookSignature::header(json_encode($event), self::SECRET, time() - 3600);
        $this->deliver($event, $old)->assertStatus(400);

        $this->assertSame(0, WebhookEvent::count());
        $this->assertSame('pending', $payment->fresh()->status);
    }

    public function test_a_tampered_body_is_rejected(): void
    {
        $payment = $this->pendingPayment(100);
        $body    = json_encode($this->event($payment));
        $header  = WebhookSignature::header($body, self::SECRET);

        $tampered = str_replace('10000', '1', $body);

        $this->deliver($tampered, $header)->assertStatus(400);
        $this->assertSame('pending', $payment->fresh()->status);
    }

    public function test_it_refuses_to_run_without_a_configured_secret(): void
    {
        config(['payments.webhooks.gateways.stripe.secret' => null]);
        $payment = $this->pendingPayment();

        // حتى لو وُقّع بسرّ فارغ لا يُقبل
        $body = json_encode($this->event($payment));
        $this->deliver($body, WebhookSignature::header($body, ''))->assertStatus(503);
        $this->assertSame('pending', $payment->fresh()->status);
    }

    public function test_unknown_gateways_are_404(): void
    {
        $this->deliver(['id' => 'e', 'type' => 't'], null, 'paypal')->assertNotFound();
    }

    public function test_oversized_bodies_are_refused(): void
    {
        config(['payments.webhooks.max_bytes' => 100]);

        $this->deliver(str_repeat('x', 500))->assertStatus(413);
    }

    public function test_signed_but_malformed_payloads_are_400(): void
    {
        $this->deliver('not json')->assertStatus(400);
        $this->deliver('"just a string"')->assertStatus(400);
        $this->deliver(['type' => 'payment_intent.succeeded'])->assertStatus(400);
        $this->deliver(['id' => 'evt_1'])->assertStatus(400);
        $this->deliver(['id' => str_repeat('a', 121), 'type' => 'x'])->assertStatus(400);
        $this->deliver(['id' => ['array'], 'type' => 'x'])->assertStatus(400);

        $this->assertSame(0, WebhookEvent::count());
    }

    public function test_the_route_has_no_session_or_csrf_middleware_but_is_throttled(): void
    {
        $route = Route::getRoutes()->getByName('webhooks.payment');
        $mw    = $route->gatherMiddleware();

        $this->assertContains('throttle:payment-webhooks', $mw);
        $this->assertNotContains('web', $mw);
        foreach ($mw as $m) {
            $this->assertStringNotContainsString('VerifyCsrfToken', (string) $m);
            $this->assertStringNotContainsString('PreventRequestForgery', (string) $m);
            $this->assertStringNotContainsString('StartSession', (string) $m);
        }
        $this->assertNotNull(\Illuminate\Support\Facades\RateLimiter::limiter('payment-webhooks'));
    }

    public function test_only_post_is_allowed(): void
    {
        $this->get('/webhooks/stripe')->assertStatus(405);
    }

    /* ====================================================================
     |  نجاح الدفع
     * ==================================================================== */

    public function test_a_succeeded_event_pays_the_order_and_enrolls_the_student(): void
    {
        $payment = $this->pendingPayment(100);

        $this->deliver($this->event($payment))
            ->assertOk()->assertJson(['status' => 'processed', 'duplicate' => false]);

        $payment->refresh();
        $order = $payment->order;

        $this->assertSame('success', $payment->status);
        $this->assertSame('pi_' . $payment->id, $payment->transaction_reference);
        $this->assertSame('paid', $order->status);
        $this->assertSame(1, Enrollment::where('user_id', $order->user_id)->where('status', 'active')->count());
        $this->assertSame(1, UserNotification::where('type', 'order.paid')->count());

        $log = WebhookEvent::sole();
        $this->assertSame('processed', $log->status);
        $this->assertSame($payment->id, $log->payment_id);
        $this->assertNotNull($log->processed_at);
    }

    public function test_the_same_event_delivered_twice_is_applied_once(): void
    {
        $payment = $this->pendingPayment();
        $event   = $this->event($payment, [], 'payment_intent.succeeded', 'evt_same');

        $this->deliver($event)->assertOk()->assertJson(['duplicate' => false]);
        $this->deliver($event)->assertOk()->assertJson(['status' => 'processed', 'duplicate' => true]);

        $this->assertSame(1, WebhookEvent::count());
        $this->assertSame(1, Payment::where('status', 'success')->count());
        $this->assertSame(1, UserNotification::where('type', 'order.paid')->count());
        $this->assertSame(1, Enrollment::count());
    }

    public function test_a_second_event_for_an_already_paid_payment_changes_nothing(): void
    {
        $payment = $this->pendingPayment();

        $this->deliver($this->event($payment))->assertOk();
        $this->deliver($this->event($payment))->assertOk()->assertJson(['status' => 'processed']);

        $this->assertSame(1, Payment::where('status', 'success')->count());
        $this->assertSame(1, UserNotification::where('type', 'order.paid')->count());
    }

    public function test_an_event_left_in_received_is_completed_on_redelivery(): void
    {
        $payment = $this->pendingPayment();
        $event   = $this->event($payment, [], 'payment_intent.succeeded', 'evt_retry');

        // انقطعت المعالجة بعد حفظ الحدث
        (new WebhookEvent)->forceFill([
            'gateway' => 'stripe', 'event_id' => 'evt_retry', 'type' => 'payment_intent.succeeded', 'status' => 'received',
        ])->save();

        $this->deliver($event)->assertOk()->assertJson(['status' => 'processed', 'duplicate' => false]);

        $this->assertSame('paid', $payment->fresh()->order->status);
        $this->assertSame(1, WebhookEvent::count());
    }

    public function test_a_wrong_amount_is_never_applied_and_is_flagged(): void
    {
        $payment = $this->pendingPayment(100);

        $this->deliver($this->event($payment, ['amount' => 100, 'amount_received' => 100]))
            ->assertOk()->assertJson(['status' => 'failed']);

        $this->assertSame('pending', $payment->fresh()->status);
        $this->assertSame('pending', $payment->order->fresh()->status);
        $this->assertSame('amount_mismatch', WebhookEvent::sole()->failure_reason);
    }

    public function test_a_wrong_currency_is_never_applied(): void
    {
        $payment = $this->pendingPayment(100);

        $this->deliver($this->event($payment, ['currency' => 'eur']))->assertOk()->assertJson(['status' => 'failed']);

        $this->assertSame('pending', $payment->order->fresh()->status);
    }

    public function test_the_amount_comes_from_our_record_not_from_the_event_alone(): void
    {
        $payment = $this->pendingPayment(100);

        // يدّعي دفع 1.00 فقط (إشعار مزوّر بتوقيع صحيح لا ينقص السعر)
        $this->deliver($this->event($payment, ['amount' => 100, 'amount_received' => 100]))->assertOk();

        $this->assertSame(0, Enrollment::count());
    }

    public function test_missing_or_invalid_amount_fields_are_not_trusted(): void
    {
        $payment = $this->pendingPayment(100);

        foreach ([['amount' => null, 'amount_received' => null], ['amount' => 'abc', 'amount_received' => 'abc']] as $i => $over) {
            $this->deliver($this->event($payment, $over, 'payment_intent.succeeded', "evt_bad_{$i}"))
                ->assertOk()->assertJson(['status' => 'failed']);
        }

        $this->assertSame('pending', $payment->order->fresh()->status);
    }

    public function test_events_for_unknown_or_foreign_payments_are_recorded_and_rejected(): void
    {
        $payment = $this->pendingPayment();

        $cases = [
            'evt_unknown' => ['metadata' => ['payment_id' => '999999']],
            'evt_nometa'  => ['metadata' => []],
            'evt_text'    => ['metadata' => ['payment_id' => '1 OR 1=1']],
            'evt_array'   => ['metadata' => ['payment_id' => ['1']]],
        ];

        foreach ($cases as $id => $over) {
            $this->deliver($this->event($payment, $over, 'payment_intent.succeeded', $id))
                ->assertOk()->assertJson(['status' => 'rejected']);
        }

        $this->assertSame('pending', $payment->fresh()->status);
        $this->assertSame(4, WebhookEvent::where('status', 'rejected')->count());
    }

    public function test_a_payment_that_belongs_to_another_gateway_is_rejected(): void
    {
        $payment = $this->pendingPayment();
        $payment->forceFill(['gateway' => 'paypal'])->save();

        $this->deliver($this->event($payment))->assertOk()->assertJson(['status' => 'rejected']);

        $this->assertSame('pending', $payment->fresh()->status);
    }

    public function test_a_late_success_after_the_order_was_cancelled_is_flagged_not_applied(): void
    {
        $payment = $this->pendingPayment();
        app(OrderService::class)->cancel($payment->order);

        $this->deliver($this->event($payment))->assertOk()->assertJson(['status' => 'failed']);

        $this->assertSame('cancelled', $payment->order->fresh()->status);
        $this->assertSame(0, Enrollment::count());
        $this->assertSame('payment_not_pending', WebhookEvent::sole()->failure_reason);

        $this->artisan('webhooks:needs-review')->expectsOutputToContain('payment_not_pending')->assertSuccessful();
    }

    public function test_a_success_for_an_order_paid_another_way_is_flagged(): void
    {
        $payment = $this->pendingPayment();
        // دفع المدير الطلب يدويًا فأُغلقت محاولة البوابة
        app(OrderService::class)->markPaid($payment->order, 'manual', 'CASH-1');

        $this->deliver($this->event($payment))->assertOk()->assertJson(['status' => 'failed']);

        $this->assertSame(1, Enrollment::count());
        $this->assertSame(1, Payment::where('status', 'success')->count());
    }

    public function test_the_needs_review_command_is_quiet_when_all_is_well(): void
    {
        $this->artisan('webhooks:needs-review')->expectsOutputToContain('No webhook events need review.')->assertSuccessful();
    }

    /* ====================================================================
     |  فشل الدفع وأحداث أخرى
     * ==================================================================== */

    public function test_a_failed_event_keeps_the_order_open_for_a_retry(): void
    {
        $payment = $this->pendingPayment();

        $this->deliver($this->event($payment, ['status' => 'requires_payment_method'], 'payment_intent.payment_failed'))
            ->assertOk()->assertJson(['status' => 'processed']);

        $this->assertSame('failed', $payment->fresh()->status);
        $this->assertSame('pending', $payment->order->fresh()->status);
        $this->assertSame(0, Enrollment::count());
    }

    public function test_a_failure_event_cannot_undo_a_successful_payment(): void
    {
        $payment = $this->pendingPayment();
        $this->deliver($this->event($payment))->assertOk();

        $this->deliver($this->event($payment, [], 'payment_intent.payment_failed'))
            ->assertOk()->assertJson(['status' => 'failed']);

        $this->assertSame('success', $payment->fresh()->status);
        $this->assertSame('paid', $payment->order->fresh()->status);
    }

    public function test_unrelated_event_types_are_acknowledged_and_ignored(): void
    {
        $payment = $this->pendingPayment();

        $this->deliver($this->event($payment, [], 'charge.dispute.created'))
            ->assertOk()->assertJson(['status' => 'ignored']);

        $this->assertSame('pending', $payment->fresh()->status);
    }

    /* ====================================================================
     |  ما يُخزَّن
     * ==================================================================== */

    public function test_only_a_known_summary_is_stored_never_card_data(): void
    {
        $payment = $this->pendingPayment();

        $this->deliver($this->event($payment, [
            'payment_method_details' => ['card' => ['last4' => '4242', 'brand' => 'visa']],
            'billing_details'        => ['email' => 'secret@example.com', 'name' => 'Real Name'],
            'client_secret'          => 'pi_x_secret_y',
        ]))->assertOk();

        $stored = json_encode(WebhookEvent::sole()->payload) . json_encode($payment->fresh()->gateway_response);

        foreach (['4242', 'visa', 'secret@example.com', 'Real Name', 'pi_x_secret_y'] as $leak) {
            $this->assertStringNotContainsString($leak, $stored);
        }
        $this->assertStringContainsString('pi_' . $payment->id, $stored);
    }

    public function test_the_event_model_cannot_be_mass_assigned(): void
    {
        $this->expectException(MassAssignmentException::class);

        WebhookEvent::create(['gateway' => 'stripe', 'event_id' => 'x', 'type' => 'y', 'status' => 'processed']);
    }

    public function test_the_same_event_id_is_independent_per_gateway(): void
    {
        (new WebhookEvent)->forceFill(['gateway' => 'paypal', 'event_id' => 'evt_shared', 'type' => 't', 'status' => 'processed'])->save();
        $payment = $this->pendingPayment();

        $this->deliver($this->event($payment, [], 'payment_intent.succeeded', 'evt_shared'))
            ->assertOk()->assertJson(['status' => 'processed', 'duplicate' => false]);
    }
}
