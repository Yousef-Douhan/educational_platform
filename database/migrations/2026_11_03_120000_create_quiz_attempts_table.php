<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * محاولات الطلاب في الاختبارات. لا تُحذف أبدًا: الإلغاء يتم بحالة voided مع السبب،
     * وهكذا يبقى أثر كل محاولة (للتدقيق ولحساب عدد المحاولات).
     */
    public function up(): void
    {
        Schema::create('quiz_attempts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->restrictOnDelete();
            $table->foreignId('quiz_id')->constrained('quizzes')->restrictOnDelete();

            // يزداد لكل طالب واختبار ولا يتكرر حتى بعد إلغاء محاولة
            $table->unsignedInteger('attempt_number');
            $table->enum('status', ['in_progress', 'submitted', 'expired', 'voided'])->default('in_progress');

            // 1 فقط أثناء المحاولة الجارية (NULL بعدها). القيد الفريد أدناه يمنع محاولتين
            // جاريتين لنفس الطالب والاختبار، حتى لو تسابق طلبان
            $table->boolean('is_open')->nullable();

            $table->decimal('score', 5, 2)->default(0);          // نسبة مئوية 0-100
            $table->unsignedInteger('points_earned')->default(0);
            $table->unsignedInteger('points_total')->default(0);
            $table->unsignedInteger('pass_mark')->default(0);    // لقطة وقت البدء: تعديل الاختبار لا يغيّر نتيجة قديمة
            $table->boolean('is_passed')->default(false);

            $table->timestamp('started_at')->useCurrent();
            $table->timestamp('expires_at')->nullable();         // null = بلا حد زمني
            $table->timestamp('submitted_at')->nullable();

            $table->timestamp('voided_at')->nullable();
            $table->foreignId('voided_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('void_reason', 500)->nullable();

            $table->timestamps();

            $table->unique(['user_id', 'quiz_id', 'attempt_number']);
            $table->unique(['user_id', 'quiz_id', 'is_open']);
            $table->index(['quiz_id', 'status']);
            $table->index(['user_id', 'status']);
            $table->index(['status', 'expires_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('quiz_attempts');
    }
};
