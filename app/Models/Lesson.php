<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Lesson extends Model
{
    use HasFactory, SoftDeletes;

    public const CONTENT_TYPES   = ['video', 'article', 'document', 'quiz', 'live'];
    public const VIDEO_PROVIDERS = ['s3', 'bunny', 'youtube', 'drive', 'external'];

    protected $fillable = [
        'section_id',
        'title_ar',
        'title_en',
        'content_type',
        'video_provider',
        'video_url',
        'content_ar',
        'content_en',
        'duration_minutes',
        'is_free_preview',
        'is_published',
        'sort_order',
    ];

    protected $casts = [
        'duration_minutes' => 'integer',
        'sort_order'       => 'integer',
        'is_free_preview'  => 'boolean',
        'is_published'     => 'boolean',
        'created_at'       => 'datetime',
        'updated_at'       => 'datetime',
        'deleted_at'       => 'datetime',
    ];

    public function section(): BelongsTo
    {
        return $this->belongsTo(CourseSection::class, 'section_id');
    }

    public function attachments(): HasMany
    {
        return $this->hasMany(LessonAttachment::class)->orderBy('id');
    }

    public function owningCourse(): Course
    {
        return $this->section->course;
    }
}
