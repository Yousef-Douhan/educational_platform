<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * إشعارات التحويل (بال باي / جوال بي / بنك فلسطين): الطالب يرفع صورة، والإدارة تعتمد أو ترفض.
     * الاعتماد وحده يؤكد الدفع (OrderService::markPaid). الصورة على قرص خاص لا يُخدم مباشرة.
     */
    public function up(): void
    {
        Schema::create('payment_receipts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained('orders')->restrictOnDelete();
            $table->foreignId('user_id')->constrained('users')->restrictOnDelete();

            $table->string('method', 30);
            $table->string('sender_name', 120);
            $table->string('reference_number', 60);
            $table->string('receipt_path', 255);

            $table->enum('status', ['pending', 'approved', 'rejected'])->default('pending');

            // 1 للإشعار قيد المراجعة (NULL لغيره): قيد فريد يمنع إشعارين مفتوحين لطلب واحد حتى مع التسابق
            $table->boolean('is_open')->nullable();
            // method:reference لغير المرفوض: نفس رقم العملية لا يُستخدم لطلبين (190 حرفًا كحد أقصى لمفاتيح MySQL)
            $table->string('ref_key', 100)->nullable();

            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->string('review_note', 255)->nullable();
            $table->timestamps();

            $table->unique(['order_id', 'is_open']);
            $table->unique('ref_key');
            $table->index(['status', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payment_receipts');
    }
};
