<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * سجل إشعارات البوابات الموثَّقة التوقيع. يخدم ثلاثة أغراض:
     *  1) منع المعالجة المكررة (unique على البوابة + معرّف الحدث).
     *  2) إثبات ما وصل ومتى (لا يُخزَّن إلا ملخص بلا بيانات بطاقات).
     *  3) إظهار الأحداث التي وصل فيها المال ولم يمكن تطبيقها (status = failed) للمراجعة اليدوية.
     */
    public function up(): void
    {
        Schema::create('webhook_events', function (Blueprint $table) {
            $table->id();
            $table->string('gateway', 50);
            $table->string('event_id', 120);
            $table->string('type', 120);
            $table->enum('status', ['received', 'processed', 'ignored', 'rejected', 'failed'])->default('received');
            $table->string('failure_reason', 120)->nullable();
            $table->foreignId('payment_id')->nullable()->constrained('payments')->nullOnDelete();
            $table->json('payload')->nullable();
            $table->timestamp('processed_at')->nullable();
            $table->timestamps();

            $table->unique(['gateway', 'event_id']);
            $table->index(['status', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('webhook_events');
    }
};
