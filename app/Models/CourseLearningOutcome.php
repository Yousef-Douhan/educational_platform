<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CourseLearningOutcome extends Model
{
    public $timestamps = false;

    protected $fillable = ['course_id', 'outcome_ar', 'outcome_en'];

    public function course(): BelongsTo
    {
        return $this->belongsTo(Course::class)->withTrashed();
    }
}
