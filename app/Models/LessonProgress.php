<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LessonProgress extends Model
{
    use HasFactory;

    protected $table = 'lesson_progress';

    // يُكتب عبر ProgressService فقط، فلا يستطيع طلب أن يضع is_completed
    protected $fillable = [];

    protected $casts = [
        'is_completed' => 'boolean',
        'completed_at' => 'datetime',
        'created_at'   => 'datetime',
        'updated_at'   => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class)->withTrashed();
    }

    public function lesson(): BelongsTo
    {
        return $this->belongsTo(Lesson::class)->withTrashed();
    }
}
