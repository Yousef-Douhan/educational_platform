<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('orders', function (Blueprint $table) {
            $table->id();
            $table->string('order_number', 20)->unique();

            // الطلبات سجلات مالية: لا حذف ناعم ولا حذف بالتتالي. المستخدم يُحذف ناعمًا فقط
            $table->foreignId('user_id')->constrained('users')->restrictOnDelete();

            // لقطة وقت الشراء: الكوبون قد يُحذف لاحقًا لكن الكود والخصم يبقيان للتاريخ
            $table->foreignId('coupon_id')->nullable()->constrained('coupons')->nullOnDelete();
            $table->string('coupon_code', 50)->nullable();

            $table->decimal('subtotal', 10, 2);
            $table->decimal('discount_amount', 10, 2)->default(0);
            $table->decimal('total_amount', 10, 2);
            $table->string('currency', 3)->default('USD');

            $table->enum('status', ['pending', 'paid', 'failed', 'cancelled', 'refunded'])->default('pending');
            $table->string('gateway', 50)->nullable();
            // 120 لا 255: الفهرس الفريد (gateway + reference) يجب أن يبقى تحت حد MySQL (1000 بايت مع utf8mb4)
            $table->string('transaction_reference', 120)->nullable();

            $table->timestamp('paid_at')->nullable();
            $table->timestamp('refunded_at')->nullable();
            $table->string('note', 500)->nullable();
            $table->timestamps();

            // مرجع العملية فريد لكل بوابة (يمنع تسجيل نفس الدفعة على طلبين)
            $table->unique(['gateway', 'transaction_reference']);
            $table->index(['user_id', 'status']);
            $table->index(['status', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('orders');
    }
};
