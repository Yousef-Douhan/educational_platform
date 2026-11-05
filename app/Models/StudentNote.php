<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StudentNote extends Model
{
    public const MAX_PER_USER = 500;
    public const MAX_BODY = 200000;

    protected $fillable = ['user_id', 'course_id', 'title', 'body'];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function course(): BelongsTo
    {
        return $this->belongsTo(Course::class)->withTrashed();
    }

    /** صف الوصف الخفيف للقائمة (بلا النص الكامل) */
    public function toListItem(): array
    {
        return [
            'id'         => $this->id,
            'course_id'  => $this->course_id,
            'title'      => $this->title,
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
