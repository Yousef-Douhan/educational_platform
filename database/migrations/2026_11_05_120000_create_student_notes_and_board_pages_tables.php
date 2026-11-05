<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // ملاحظات الطالب: مجلدها دورة (أو بلا دورة = مجلد "عام")
        Schema::create('student_notes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('course_id')->nullable()->constrained()->nullOnDelete();
            $table->string('title', 150);
            $table->longText('body')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'course_id']);
        });

        // صفحات السبورة: كل صفحة قائمة عناصر رسم (JSON منقّى في الخادم)
        Schema::create('student_board_pages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('name', 60);
            $table->unsignedSmallInteger('position')->default(0);
            $table->longText('objects')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'position']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('student_board_pages');
        Schema::dropIfExists('student_notes');
    }
};
