<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('carts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained('users')->cascadeOnDelete(); // سلة واحدة لكل طالب
            $table->timestamps();                                                              // updated_at لاكتشاف السلات المهجورة

            $table->index('updated_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('carts');
    }
};
