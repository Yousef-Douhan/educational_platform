<?php

namespace Tests\Feature;

use App\Models\Course;
use App\Models\Order;
use App\Models\Payment;
use App\Models\User;
use App\Services\CartService;
use App\Services\OrderService;
use App\Services\WebhookSignature;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class StripePaymentTest extends TestCase
{
    use RefreshDatabase;

    private const URL = 'https://checkout.stripe.com/c/pay/cs_test_123';

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        app()->setLocale('ar');
        app(PermissionRegistrar::class)->forgetCachedPermissions();
        config(['payments.stripe.secret' => 'sk_test_secret', 'payments.webhooks.gateways.stripe.secret' => 'whsec_x']);
    }

    private function order(?User $user = null, float $price = 100): Order
    {
        $user ??= User::factory()->create();
        app(CartService::class)->add($user, Course::factory()->published()->paid($price)->create());

        return app(OrderService::class)->checkout($user);
    }

    private function fakeStripe(array $body = ['id' => 'cs_test_123', 'url' => self::URL], int $status = 200): void
    {
        Http::fake(['api.stripe.com/*' => Http::response($body, $status)]);
    }

    public function test_pay_creates_a_pending_payment_and_redirects_to_stripe_checkout(): void
    {
        $this->fakeStripe();
        $order = $this->order();

        $this->actingAs($order->user)->post(route('student.orders.pay', $order->order_number))->assertRedirect(self::URL);

        $payment = Payment::where('order_id', $order->id)->firstOrFail();
        $this->assertSame('pending', $payment->status);
        $this->assertSame('stripe', $payment->gateway);

        Http::assertSent(function (Request $r) use ($order, $payment) {
            parse_str($r->body(), $d);

            return str_ends_with($r->url(), '/checkout/sessions')
                && $r->hasHeader('Authorization', 'Bearer sk_test_secret')
                && $r->hasHeader('Idempotency-Key', 'payment-' . $payment->id)
                && $d['mode'] === 'payment'
                && (int) $d['line_items'][0]['price_data']['unit_amount'] === 10000
                && $d['line_items'][0]['price_data']['currency'] === strtolower($order->currency)
                && (string) $d['metadata']['payment_id'] === (string) $payment->id
                && (string) $d['payment_intent_data']['metadata']['payment_id'] === (string) $payment->id
                && str_contains($d['success_url'], $order->order_number);
        });
    }

    public function test_the_amount_comes_from_the_order_never_from_the_request(): void
    {
        $this->fakeStripe();
        $order = $this->order(null, 250);

        $this->actingAs($order->user)->post(route('student.orders.pay', $order->order_number), ['amount' => 1, 'total_amount' => 1, 'currency' => 'xxx']);

        Http::assertSent(function (Request $r) {
            parse_str($r->body(), $d);

            return (int) $d['line_items'][0]['price_data']['unit_amount'] === 25000;
        });
    }

    public function test_pay_is_refused_when_stripe_is_not_configured(): void
    {
        config(['payments.stripe.secret' => null]);
        Http::fake();
        $order = $this->order();

        $this->actingAs($order->user)->post(route('student.orders.pay', $order->order_number))->assertSessionHasErrors('pay');
        $this->assertSame(0, Payment::count());
        Http::assertNothingSent();

        $this->get(route('student.orders.show', $order->order_number))->assertDontSee(route('student.orders.pay', $order->order_number), false);
    }

    public function test_pay_button_shows_only_when_payable(): void
    {
        $order = $this->order();

        $this->actingAs($order->user)->get(route('student.orders.show', $order->order_number))
            ->assertSee(route('student.orders.pay', $order->order_number), false);
    }

    public function test_orders_of_others_and_guests_cannot_pay(): void
    {
        Http::fake();
        $order = $this->order();

        $this->post(route('student.orders.pay', $order->order_number))->assertRedirect(route('login'));
        $this->actingAs(User::factory()->create())->post(route('student.orders.pay', $order->order_number))->assertNotFound();

        $this->assertSame(0, Payment::count());
        Http::assertNothingSent();
    }

    public function test_a_paid_order_cannot_be_paid_again(): void
    {
        Http::fake();
        $order = $this->order();
        Order::whereKey($order->id)->update(['status' => 'paid']);

        $this->actingAs($order->user)->post(route('student.orders.pay', $order->order_number))->assertSessionHasErrors('pay');
        $this->assertSame(0, Payment::count());
        Http::assertNothingSent();
    }

    public function test_stripe_errors_fail_the_payment_and_show_a_generic_message(): void
    {
        $this->fakeStripe(['error' => ['type' => 'api_error', 'message' => 'SECRET INTERNAL DETAIL']], 500);
        $order = $this->order();

        $response = $this->actingAs($order->user)->post(route('student.orders.pay', $order->order_number));

        $response->assertSessionHasErrors('pay');
        $this->assertStringNotContainsString('SECRET INTERNAL DETAIL', json_encode(session('errors')->getBag('default')->all()));
        $this->assertSame('failed', Payment::firstOrFail()->status);
        $this->assertSame('pending', $order->fresh()->status);
    }

    public function test_an_unreachable_gateway_is_handled(): void
    {
        Http::fake(fn () => throw new ConnectionException('timeout'));
        $order = $this->order();

        $this->actingAs($order->user)->post(route('student.orders.pay', $order->order_number))->assertSessionHasErrors('pay');
        $this->assertSame('failed', Payment::firstOrFail()->status);
    }

    public function test_only_stripe_checkout_urls_are_followed(): void
    {
        foreach (['https://evil.example/pay', 'http://checkout.stripe.com/x', 'https://checkout.stripe.com.evil.example/x', 'javascript:alert(1)'] as $bad) {
            $this->fakeStripe(['id' => 'cs', 'url' => $bad]);
            $order = $this->order();

            $this->actingAs($order->user)->post(route('student.orders.pay', $order->order_number))->assertSessionHasErrors('pay');
            $this->assertSame('failed', Payment::where('order_id', $order->id)->firstOrFail()->status, $bad);
        }
    }

    public function test_retrying_closes_the_previous_pending_attempt(): void
    {
        $this->fakeStripe();
        $order = $this->order();

        $this->actingAs($order->user)->post(route('student.orders.pay', $order->order_number));
        $this->post(route('student.orders.pay', $order->order_number));

        $statuses = Payment::where('order_id', $order->id)->orderBy('id')->pluck('status')->all();
        $this->assertSame(['failed', 'pending'], $statuses);
    }

    public function test_webhook_after_stripe_payment_marks_the_order_paid_and_opens_the_course(): void
    {
        $this->fakeStripe();
        $order = $this->order();
        $this->actingAs($order->user)->post(route('student.orders.pay', $order->order_number));
        $payment = Payment::where('order_id', $order->id)->firstOrFail();

        $body = json_encode(['id' => 'evt_e2e', 'type' => 'payment_intent.succeeded', 'data' => ['object' => [
            'id' => 'pi_e2e', 'amount_received' => 10000, 'currency' => strtolower($payment->currency),
            'metadata' => ['payment_id' => (string) $payment->id],
        ]]]);

        $this->call('POST', '/webhooks/stripe', [], [], [], [
            'CONTENT_TYPE' => 'application/json', 'HTTP_ACCEPT' => 'application/json',
            'HTTP_STRIPE_SIGNATURE' => WebhookSignature::header($body, 'whsec_x'),
        ], $body)->assertOk();

        $this->assertSame('paid', $order->fresh()->status);
        $this->assertDatabaseHas('enrollments', ['user_id' => $order->user_id, 'status' => 'active']);

        $this->get(route('student.orders.show', $order->order_number))->assertSee(__('student.pay.go_learn'));
    }

    public function test_the_stripe_redirect_alone_does_not_grant_access(): void
    {
        $order = $this->order();

        $this->actingAs($order->user)->get(route('student.orders.show', [$order->order_number, 'paid' => 1]))
            ->assertOk()->assertSee(__('student.pay.processing'));

        $this->assertSame('pending', $order->fresh()->status);
        $this->assertDatabaseCount('enrollments', 0);
    }
}
