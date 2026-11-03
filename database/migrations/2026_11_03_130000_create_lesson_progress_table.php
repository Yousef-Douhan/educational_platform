<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** إكمال الدروس لكل طالب. يكتبه ProgressService فقط (يدويًا أو عند نجاح اختبار الدرس) */
    public function up(): void
    {
        Schema::create('lesson_progress', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->restrictOnDelete();
            $table->foreignId('lesson_id')->constrained('lessons')->restrictOnDelete();

            $table->boolean('is_completed')->default(false);
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();

            // صف واحد لكل طالب ودرس
            $table->unique(['user_id', 'lesson_id']);
            $table->index(['lesson_id', 'is_completed']);
            $table->index(['user_id', 'is_completed']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('lesson_progress');
    }
};
