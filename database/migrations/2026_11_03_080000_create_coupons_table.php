<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('coupons', function (Blueprint $table) {
            $table->id();
            $table->string('code', 50)->unique();
            $table->enum('discount_type', ['percentage', 'fixed']);
            $table->decimal('discount_value', 10, 2);
            $table->decimal('max_discount', 10, 2)->nullable();      // سقف الخصم للنسبة المئوية
            $table->decimal('min_order_amount', 10, 2)->nullable();  // أقل مبلغ طلب
            $table->timestamp('starts_at')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->unsignedInteger('usage_limit')->nullable();      // null = بلا حد
            $table->unsignedInteger('per_user_limit')->nullable();   // null = بلا حد
            $table->unsignedInteger('times_used')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();

            $table->index(['is_active', 'expires_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('coupons');
    }
};
