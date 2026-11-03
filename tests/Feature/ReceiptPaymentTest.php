<?php

namespace Tests\Feature;

use App\Models\Course;
use App\Models\Order;
use App\Models\PaymentReceipt;
use App\Models\User;
use App\Services\CartService;
use App\Services\OrderService;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class ReceiptPaymentTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        app()->setLocale('ar');
        app(PermissionRegistrar::class)->forgetCachedPermissions();
        Storage::fake('local');
        config(['payments.manual.methods.palpay.account' => '0599000111', 'payments.manual.methods.palpay.holder' => 'Test']);
        config(['payments.manual.methods.jawwal_pay.account' => '', 'payments.manual.methods.jawwal_pay.iban' => '']);
    }

    private function order(?User $user = null, float $price = 100): Order
    {
        $user ??= User::factory()->create();
        app(CartService::class)->add($user, Course::factory()->published()->paid($price)->create());

        return app(OrderService::class)->checkout($user);
    }

    private function admin(string ...$permissions): User
    {
        $this->seed(RolesAndPermissionsSeeder::class);
        $u = User::factory()->create();
        $u->givePermissionTo($permissions ?: Permission::all());

        return $u;
    }

    private function submit(Order $order, array $over = [], ?User $as = null)
    {
        return $this->actingAs($as ?? $order->user)->post(route('student.orders.receipt', $order->order_number), array_merge([
            'method' => 'palpay', 'sender_name' => 'Ali Ahmad', 'reference_number' => 'TX-12345',
            'receipt' => UploadedFile::fake()->image('r.jpg', 400, 400),
        ], $over));
    }

    public function test_student_submits_a_receipt_and_it_is_stored_privately(): void
    {
        $order = $this->order();

        $this->submit($order)->assertRedirect(route('student.orders.show', $order->order_number))->assertSessionHasNoErrors();

        $r = PaymentReceipt::firstOrFail();
        $this->assertSame('pending', $r->status);
        $this->assertTrue((bool) $r->is_open);
        Storage::disk('local')->assertExists($r->receipt_path);
        $this->assertSame('pending', $order->fresh()->status);
    }

    public function test_only_one_open_receipt_per_order(): void
    {
        $order = $this->order();
        $this->submit($order);
        $this->submit($order, ['reference_number' => 'TX-99999'])->assertSessionHasErrors('receipt');

        $this->assertSame(1, PaymentReceipt::count());
    }

    public function test_invalid_input_is_rejected(): void
    {
        $order = $this->order();

        $this->submit($order, ['receipt' => UploadedFile::fake()->create('x.pdf', 10, 'application/pdf')])->assertSessionHasErrors('receipt');
        $this->submit($order, ['receipt' => UploadedFile::fake()->image('big.jpg')->size(5000)])->assertSessionHasErrors('receipt');
        $this->submit($order, ['reference_number' => '<script>'])->assertSessionHasErrors('reference_number');
        $this->submit($order, ['sender_name' => 'ab'])->assertSessionHasErrors('sender_name');
        $this->submit($order, ['method' => 'jawwal_pay'])->assertSessionHasErrors('method');
        $this->submit($order, ['method' => 'nonsense'])->assertSessionHasErrors('method');

        $this->assertSame(0, PaymentReceipt::count());
    }

    public function test_cannot_submit_for_someone_elses_order(): void
    {
        $order = $this->order();

        $this->submit($order, [], User::factory()->create())->assertNotFound();
        $this->assertSame(0, PaymentReceipt::count());
    }

    public function test_a_reference_cannot_be_reused(): void
    {
        $this->submit($this->order());
        $this->submit($this->order(), ['reference_number' => 'tx-12345'])->assertSessionHasErrors('receipt');

        $this->assertSame(1, PaymentReceipt::count());
    }

    public function test_approve_marks_the_order_paid_and_enrolls(): void
    {
        $order = $this->order();
        $this->submit($order);
        $r = PaymentReceipt::firstOrFail();

        $this->actingAs($this->admin())->post(route('cms.admin.receipts.approve', $r->id), ['note' => 'ok'])->assertRedirect();

        $this->assertSame('approved', $r->fresh()->status);
        $this->assertSame('paid', $order->fresh()->status);
        $this->assertSame('palpay', $order->fresh()->gateway);
        $this->assertDatabaseHas('enrollments', ['user_id' => $order->user_id]);

        // اعتماد ثانٍ لا يكرر شيئًا
        $this->actingAs($this->admin())->post(route('cms.admin.receipts.approve', $r->id))->assertSessionHasErrors('receipt');
    }

    public function test_reject_requires_a_reason_and_lets_the_student_resubmit(): void
    {
        $order = $this->order();
        $this->submit($order);
        $r = PaymentReceipt::firstOrFail();
        $admin = $this->admin();

        $this->actingAs($admin)->post(route('cms.admin.receipts.reject', $r->id), ['reason' => ''])->assertSessionHasErrors('reason');
        $this->actingAs($admin)->post(route('cms.admin.receipts.reject', $r->id), ['reason' => 'المبلغ غير مطابق'])->assertRedirect();

        $this->assertSame('rejected', $r->fresh()->status);
        $this->assertSame('pending', $order->fresh()->status);

        $this->submit($order)->assertSessionHasNoErrors();
        $this->assertSame(2, PaymentReceipt::count());
    }

    public function test_admin_permissions_are_enforced(): void
    {
        $order = $this->order();
        $this->submit($order);
        $r = PaymentReceipt::firstOrFail();

        auth()->logout();
        $this->get(route('cms.admin.receipts.index'))->assertRedirect();

        $viewer = $this->admin('receipts.view');
        $this->actingAs($viewer)->get(route('cms.admin.receipts.index'))->assertOk();
        $this->actingAs($viewer)->get(route('cms.admin.receipts.show', $r->id))->assertOk();
        $this->actingAs($viewer)->get(route('cms.admin.receipts.image', $r->id))->assertOk();
        $this->actingAs($viewer)->post(route('cms.admin.receipts.approve', $r->id))->assertForbidden();
        $this->actingAs($viewer)->post(route('cms.admin.receipts.reject', $r->id), ['reason' => 'xxx'])->assertForbidden();

        $this->actingAs($order->user)->get(route('cms.admin.receipts.index'))->assertForbidden();
    }

    public function test_student_image_is_visible_to_owner_only(): void
    {
        $order = $this->order();
        $this->submit($order);
        $r = PaymentReceipt::firstOrFail();

        $res = $this->actingAs($order->user)->get(route('student.receipts.image', $r->id))->assertOk();
        $this->assertSame('nosniff', $res->headers->get('X-Content-Type-Options'));

        $this->actingAs(User::factory()->create())->get(route('student.receipts.image', $r->id))->assertNotFound();
        auth()->logout();
        $this->get(route('student.receipts.image', $r->id))->assertRedirect();
    }

    public function test_order_page_shows_only_configured_methods(): void
    {
        $order = $this->order();

        $this->actingAs($order->user)->get(route('student.orders.show', $order->order_number))
            ->assertOk()->assertSee('value="palpay"', false)->assertDontSee('value="jawwal_pay"', false);
    }
}
