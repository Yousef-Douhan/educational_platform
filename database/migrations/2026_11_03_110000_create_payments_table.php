<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * سجل المدفوعات: صف لكل محاولة دفع. يُضاف ولا يُحذف (سجل مالي)،
     * والطلب الواحد قد يملك محاولات فاشلة ثم واحدة ناجحة.
     */
    public function up(): void
    {
        Schema::create('payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained('orders')->restrictOnDelete();

            $table->string('gateway', 50);
            $table->string('payment_method', 50);
            $table->decimal('amount', 10, 2);
            $table->string('currency', 3)->default('USD');

            $table->enum('status', ['pending', 'success', 'failed', 'refunded'])->default('pending');
            $table->string('transaction_reference', 120)->nullable();
            $table->json('gateway_response')->nullable();

            // 1 فقط للدفعة الناجحة (NULL لغيرها). القيد الفريد أدناه يجعل
            // قاعدة البيانات نفسها ترفض دفعتين ناجحتين لطلب واحد، حتى لو تسابق طلبان
            $table->boolean('succeeded')->nullable();

            $table->timestamp('paid_at')->nullable();
            $table->timestamp('refunded_at')->nullable();
            $table->timestamps();

            $table->unique(['order_id', 'succeeded']);
            // نفس مرجع البوابة لا يُسجَّل مرتين (يحمي من تكرار الإشعارات webhook)
            $table->unique(['gateway', 'transaction_reference']);
            $table->index(['order_id', 'status']);
            $table->index(['status', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payments');
    }
};
