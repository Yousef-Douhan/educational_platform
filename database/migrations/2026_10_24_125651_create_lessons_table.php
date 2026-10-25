<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('lessons', function (Blueprint $table) {
            $table->id();
            $table->foreignId('section_id')->constrained('course_sections')->cascadeOnDelete();
            $table->string('title_ar', 255);
            $table->string('title_en', 255);
            $table->enum('content_type', ['video', 'article', 'document', 'quiz', 'live']);
            $table->enum('video_provider', ['s3', 'bunny', 'youtube', 'drive', 'external'])->nullable();
            $table->text('video_url')->nullable();
            $table->longText('content_ar')->nullable();
            $table->longText('content_en')->nullable();
            $table->unsignedInteger('duration_minutes')->default(0);
            $table->boolean('is_free_preview')->default(false);
            $table->boolean('is_published')->default(false);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
            $table->softDeletes();

            $table->index(['section_id', 'sort_order']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('lessons');
    }
};
