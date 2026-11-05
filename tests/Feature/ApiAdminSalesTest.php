<?php

namespace Tests\Feature;

use App\Models\Cart;
use App\Models\CartItem;
use App\Models\Coupon;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Payment;
use App\Models\PaymentReceipt;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Laravel\Passport\Passport;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

/** API الإدارة — الدفعة 3: الطلبات والمدفوعات والإيصالات والكوبونات والسلات */
class ApiAdminSalesTest extends TestCase
{
    use RefreshDatabase;

    private const BASE = '/api/v1/admin';

    protected function setUp(): void
    {
        parent::setUp();
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    private function superAdmin(): User
    {
        $this->seed(RolesAndPermissionsSeeder::class);
        $u = User::factory()->create();
        $u->assignRole(Role::findByName('super-admin', 'web'));
        Passport::actingAs($u, ['student', 'admin'], 'api');

        return $u;
    }

    private function staffWith(string ...$perms): User
    {
        $u = User::factory()->create();
        foreach ($perms as $p) {
            $u->givePermissionTo(Permission::findOrCreate($p, 'web'));
        }
        Passport::actingAs($u, ['student', 'admin'], 'api');

        return $u;
    }

    private function receipt(Order $order, string $ref = 'TX-1'): PaymentReceipt
    {
        $id = DB::table('payment_receipts')->insertGetId([
            'order_id' => $order->id, 'user_id' => $order->user_id, 'method' => 'bank_transfer',
            'sender_name' => 'Sender', 'reference_number' => $ref, 'receipt_path' => 'receipts/none.jpg',
            'status' => 'pending', 'is_open' => 1, 'ref_key' => 'bank_transfer:'.strtolower($ref),
            'created_at' => now(), 'updated_at' => now(),
        ]);

        return PaymentReceipt::findOrFail($id);
    }

    public function test_student_token_and_plain_users_cannot_reach_sales_endpoints(): void
    {
        Passport::actingAs(User::factory()->create(), ['student'], 'api');
        $this->getJson(self::BASE.'/orders')->assertForbidden();

        $this->staffWith('categories.view');
        $this->getJson(self::BASE.'/orders')->assertForbidden();
        $this->getJson(self::BASE.'/payments')->assertForbidden();
        $this->getJson(self::BASE.'/receipts')->assertForbidden();
        $this->getJson(self::BASE.'/coupons')->assertForbidden();
        $this->getJson(self::BASE.'/carts')->assertForbidden();
    }

    public function test_orders_list_filters_and_stats(): void
    {
        $this->superAdmin();
        Order::factory()->count(2)->create();
        Order::factory()->paid()->create(['total_amount' => 50]);

        $this->getJson(self::BASE.'/orders')->assertOk()
            ->assertJsonPath('meta.total', 3)->assertJsonPath('stats.paid', 1)->assertJsonPath('stats.pending', 2)
            ->assertJsonPath('stats.revenue', '50.00');

        $this->getJson(self::BASE.'/orders?status=paid')->assertOk()->assertJsonPath('meta.total', 1);
        $this->getJson(self::BASE.'/orders?sort=evil;drop&dir=zzz&from=not-a-date')->assertOk();
    }

    public function test_order_actions_respect_permissions_and_state(): void
    {
        $this->staffWith('orders.view');
        $order = Order::factory()->create();
        OrderItem::factory()->create(['order_id' => $order->id]);

        $this->getJson(self::BASE."/orders/{$order->id}")->assertOk()->assertJsonPath('data.status', 'pending');
        $this->postJson(self::BASE."/orders/{$order->id}/mark-paid", ['gateway' => 'manual'])->assertForbidden();
        $this->postJson(self::BASE."/orders/{$order->id}/cancel")->assertForbidden();
        $this->postJson(self::BASE."/orders/{$order->id}/refund", ['note' => 'because'])->assertForbidden();

        $this->superAdmin();
        $this->postJson(self::BASE."/orders/{$order->id}/mark-paid", ['gateway' => 'evil-gw'])->assertUnprocessable();
        $this->postJson(self::BASE."/orders/{$order->id}/mark-paid", ['gateway' => 'manual', 'transaction_reference' => 'R1'])->assertOk();
        $this->assertSame('paid', $order->fresh()->status);

        // لا يُلغى طلب مدفوع، ولا يُسترد إلا بسبب
        $this->postJson(self::BASE."/orders/{$order->id}/cancel")->assertStatus(409);
        $this->postJson(self::BASE."/orders/{$order->id}/refund", [])->assertUnprocessable();
        $this->postJson(self::BASE."/orders/{$order->id}/refund", ['note' => 'customer request'])->assertOk();
        $this->assertSame('refunded', $order->fresh()->status);
        $this->postJson(self::BASE."/orders/{$order->id}/refund", ['note' => 'again please'])->assertStatus(409);
    }

    public function test_payments_list_and_show(): void
    {
        $this->superAdmin();
        $order = Order::factory()->paid()->create();
        $payment = Payment::factory()->success('PAY-1')->create(['order_id' => $order->id]);

        $this->getJson(self::BASE.'/payments')->assertOk()->assertJsonPath('meta.total', 1)
            ->assertJsonPath('data.0.order_number', $order->order_number)->assertJsonPath('stats.success', 1);
        $this->getJson(self::BASE.'/payments?status=failed')->assertOk()->assertJsonPath('meta.total', 0);
        $this->getJson(self::BASE."/payments/{$payment->id}")->assertOk()->assertJsonPath('data.id', $payment->id);
        $this->getJson(self::BASE.'/payments/999999')->assertNotFound()->assertJsonPath('code', 'not_found');
    }

    public function test_receipt_review_flow(): void
    {
        $order = Order::factory()->create();
        OrderItem::factory()->create(['order_id' => $order->id]);
        $receipt = $this->receipt($order);

        // عرض فقط: لا اعتماد ولا رفض
        $this->staffWith('receipts.view');
        $this->getJson(self::BASE.'/receipts')->assertOk()->assertJsonPath('meta.total', 1)->assertJsonPath('counts.pending', 1);
        $this->getJson(self::BASE."/receipts/{$receipt->id}")->assertOk()->assertJsonPath('data.reference_number', 'TX-1');
        $this->postJson(self::BASE."/receipts/{$receipt->id}/approve")->assertForbidden();
        $this->postJson(self::BASE."/receipts/{$receipt->id}/reject", ['reason' => 'blurry image'])->assertForbidden();

        $this->superAdmin();
        $this->postJson(self::BASE."/receipts/{$receipt->id}/reject", [])->assertUnprocessable();
        $this->postJson(self::BASE."/receipts/{$receipt->id}/approve")->assertOk()->assertJsonPath('data.status', 'approved');
        $this->assertSame('paid', $order->fresh()->status);

        // لا مراجعة ثانية
        $this->postJson(self::BASE."/receipts/{$receipt->id}/approve")->assertStatus(409);
        $this->postJson(self::BASE."/receipts/{$receipt->id}/reject", ['reason' => 'too late now'])->assertStatus(409);
    }

    public function test_receipt_reject_keeps_order_pending(): void
    {
        $this->superAdmin();
        $order = Order::factory()->create();
        $receipt = $this->receipt($order, 'TX-9');

        $this->postJson(self::BASE."/receipts/{$receipt->id}/reject", ['reason' => 'amount mismatch'])
            ->assertOk()->assertJsonPath('data.status', 'rejected');
        $this->assertSame('pending', $order->fresh()->status);
        $this->getJson(self::BASE.'/receipts?status=rejected')->assertOk()->assertJsonPath('meta.total', 1);
    }

    public function test_receipt_image_missing_file_is_404(): void
    {
        $this->superAdmin();
        $receipt = $this->receipt(Order::factory()->create());

        $this->getJson(self::BASE."/receipts/{$receipt->id}/image")->assertNotFound();
    }

    public function test_coupon_crud_and_locking(): void
    {
        $this->staffWith('coupons.view');
        $this->postJson(self::BASE.'/coupons', ['code' => 'NEW10', 'discount_type' => 'percentage', 'discount_value' => 10])->assertForbidden();

        $this->superAdmin();
        $id = $this->postJson(self::BASE.'/coupons', [
            'code' => 'new10', 'discount_type' => 'percentage', 'discount_value' => 10, 'is_active' => true,
        ])->assertCreated()->json('data.id');

        $this->postJson(self::BASE.'/coupons', ['code' => 'NEW10', 'discount_type' => 'fixed', 'discount_value' => 5])
            ->assertUnprocessable();

        $this->getJson(self::BASE.'/coupons')->assertOk()->assertJsonPath('meta.total', 1)->assertJsonPath('data.0.code', 'NEW10');
        $this->getJson(self::BASE."/coupons/{$id}")->assertOk();
        $this->patchJson(self::BASE."/coupons/{$id}/toggle")->assertOk();
        $this->assertFalse(Coupon::find($id)->is_active);

        $this->deleteJson(self::BASE."/coupons/{$id}")->assertOk();
        $this->getJson(self::BASE.'/coupons/trashed')->assertOk()->assertJsonPath('meta.total', 1);
        $this->patchJson(self::BASE."/coupons/{$id}/restore")->assertOk();
        $this->deleteJson(self::BASE."/coupons/{$id}")->assertOk();
        $this->deleteJson(self::BASE."/coupons/{$id}/force-delete")->assertOk();
        $this->assertNull(Coupon::withTrashed()->find($id));
    }

    public function test_used_coupon_cannot_be_force_deleted_or_have_its_value_changed(): void
    {
        $this->superAdmin();
        $coupon = Coupon::factory()->create(['code' => 'USED5', 'discount_value' => 10, 'times_used' => 3]);

        $this->putJson(self::BASE."/coupons/{$coupon->id}", [
            'code' => 'USED5', 'discount_type' => 'percentage', 'discount_value' => 90, 'is_active' => true,
        ]);
        $this->assertSame('10.00', number_format((float) $coupon->fresh()->discount_value, 2, '.', ''));

        $this->deleteJson(self::BASE."/coupons/{$coupon->id}")->assertOk();
        $this->deleteJson(self::BASE."/coupons/{$coupon->id}/force-delete")->assertStatus(409);
    }

    public function test_carts_list_show_and_clear(): void
    {
        $this->staffWith('carts.view');
        $cart = Cart::factory()->create();
        CartItem::factory()->create(['cart_id' => $cart->id]);
        Cart::factory()->create();

        $this->getJson(self::BASE.'/carts')->assertOk()->assertJsonPath('meta.total', 2)->assertJsonPath('stats.empty', 1);
        $this->getJson(self::BASE.'/carts?state=empty')->assertOk()->assertJsonPath('meta.total', 1);
        $this->getJson(self::BASE."/carts/{$cart->id}")->assertOk()->assertJsonCount(1, 'data.items');
        $this->deleteJson(self::BASE."/carts/{$cart->id}/items")->assertForbidden();

        $this->superAdmin();
        $this->deleteJson(self::BASE."/carts/{$cart->id}/items")->assertOk();
        $this->assertSame(0, CartItem::where('cart_id', $cart->id)->count());
    }
}
