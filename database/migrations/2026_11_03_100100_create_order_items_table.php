<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('order_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained('orders')->cascadeOnDelete();
            // الدورة المباعة لا تُحذف نهائيًا (CourseController::hasSales يمنع ذلك أيضًا)
            $table->foreignId('course_id')->constrained('courses')->restrictOnDelete();

            // السعر والعنوان يتثبّتان وقت الشراء ولا يتأثران بتعديل الدورة لاحقًا
            $table->decimal('price', 10, 2);
            $table->string('title_ar', 255);
            $table->string('title_en', 255);

            $table->unique(['order_id', 'course_id']);
            $table->index('course_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('order_items');
    }
};
