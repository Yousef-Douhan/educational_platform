<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('notifications', function (Blueprint $table) {
            $table->uuid('id')->primary();
            // نوع الإشعار (مثل order.paid) وليس اسم صنف PHP، فلا يتعطل السجل إذا غُيّرت الأصناف
            $table->string('type', 191);
            // 191 لا 255: هذا المشروع على MySQL حد مفتاحه 1000 بايت (utf8mb4 = 4 بايت للحرف)
            $table->string('notifiable_type', 191);
            $table->unsignedBigInteger('notifiable_id');
            $table->text('data');
            $table->timestamp('read_at')->nullable();
            $table->timestamps();

            // قائمة المستخدم وعدّاد غير المقروء
            $table->index(['notifiable_type', 'notifiable_id', 'read_at', 'created_at'], 'notifications_recipient_idx');
            // الشاشة الإدارية والتنظيف المجدول
            $table->index(['type', 'created_at'], 'notifications_type_idx');
            $table->index(['read_at', 'created_at'], 'notifications_prune_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('notifications');
    }
};
