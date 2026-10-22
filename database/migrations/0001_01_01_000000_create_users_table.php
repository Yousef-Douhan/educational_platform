<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('users', function (Blueprint $table) {
            $table->id(); // BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY
            $table->string('name'); // VARCHAR(255) NOT NULL
            $table->string('email')->unique(); // VARCHAR(255) NOT NULL UNIQUE
            $table->timestamp('email_verified_at')->nullable(); // TIMESTAMP NULL
            $table->string('password'); // VARCHAR(255) NOT NULL

            // الحالة مع الخيارات المطلوبة وقيمة افتراضية active
            $table->enum('status', ['active', 'inactive', 'suspended'])->default('active');

            $table->rememberToken(); // VARCHAR(100) NULL
            $table->timestamps(); // created_at و updated_at بنوع TIMESTAMP NULL (افتراضياً في لارفيل)
            $table->softDeletes(); // deleted_at بنوع TIMESTAMP NULL (للحذف الناعم)
        });

        Schema::create('password_reset_tokens', function (Blueprint $table) {
            $table->string('email')->primary();
            $table->string('token');
            $table->timestamp('created_at')->nullable();
        });

        Schema::create('sessions', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->foreignId('user_id')->nullable()->index();
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->longText('payload');
            $table->integer('last_activity')->index();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('users');
        Schema::dropIfExists('password_reset_tokens');
        Schema::dropIfExists('sessions');
    }
};
