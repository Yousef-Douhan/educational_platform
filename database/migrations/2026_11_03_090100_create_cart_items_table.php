<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cart_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('cart_id')->constrained('carts')->cascadeOnDelete();
            $table->foreignId('course_id')->constrained('courses')->cascadeOnDelete();
            $table->timestamp('created_at')->useCurrent();   // وقت الإضافة

            // لا تتكرر الدورة في السلة نفسها. السعر لا يُخزَّن هنا: يُقرأ من الدورة ويتثبّت عند الطلب
            $table->unique(['cart_id', 'course_id']);
            $table->index('course_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cart_items');
    }
};
