<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('course_learning_outcomes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('course_id')->constrained('courses')->cascadeOnDelete();
            $table->text('outcome_ar');
            $table->text('outcome_en');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('course_learning_outcomes');
    }
};
