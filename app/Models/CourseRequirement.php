<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CourseRequirement extends Model
{
    public $timestamps = false;

    protected $fillable = ['course_id', 'requirement_ar', 'requirement_en'];

    public function course(): BelongsTo
    {
        return $this->belongsTo(Course::class)->withTrashed();
    }
}
