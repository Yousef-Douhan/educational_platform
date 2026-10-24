<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('course_instructors', function (Blueprint $table) {
            $table->foreignId('course_id')->constrained('courses')->cascadeOnDelete();
            $table->foreignId('instructor_id')->constrained('users')->cascadeOnDelete();
            $table->primary(['course_id', 'instructor_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('course_instructors');
    }
};
