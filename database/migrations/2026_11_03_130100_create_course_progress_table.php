<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** ملخص تقدم الطالب في الدورة؛ يُحسب دائمًا من lesson_progress ولا يُكتب يدويًا */
    public function up(): void
    {
        Schema::create('course_progress', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->restrictOnDelete();
            $table->foreignId('course_id')->constrained('courses')->restrictOnDelete();

            $table->unsignedInteger('completed_lessons_count')->default(0);
            $table->unsignedInteger('total_lessons_count')->default(0);   // وقت آخر حساب
            $table->decimal('progress_percentage', 5, 2)->default(0);
            $table->boolean('is_finished')->default(false);
            $table->timestamp('finished_at')->nullable();
            $table->timestamps();

            // صف واحد لكل طالب ودورة
            $table->unique(['user_id', 'course_id']);
            $table->index(['course_id', 'is_finished']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('course_progress');
    }
};
