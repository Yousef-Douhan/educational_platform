<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // الجدول الأسبوعي الشخصي: يوم (0=السبت … 6=الجمعة) + وقت بداية ونهاية HH:MM
        Schema::create('student_schedule_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('title', 100);
            $table->string('kind', 10)->default('lecture');
            $table->unsignedTinyInteger('day');
            $table->string('starts_at', 5);
            $table->string('ends_at', 5);
            $table->string('room', 60)->nullable();
            $table->string('color', 7)->default('#0369a1');
            $table->timestamps();

            $table->index(['user_id', 'day']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('student_schedule_items');
    }
};
