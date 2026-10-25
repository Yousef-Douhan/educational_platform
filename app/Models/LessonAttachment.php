<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LessonAttachment extends Model
{
    public const UPDATED_AT = null; // الجدول فيه created_at فقط

    protected $fillable = ['lesson_id', 'title', 'file_path', 'file_size'];

    protected $casts = [
        'file_size'  => 'integer',
        'created_at' => 'datetime',
    ];

    public function lesson(): BelongsTo
    {
        return $this->belongsTo(Lesson::class)->withTrashed();
    }
}
