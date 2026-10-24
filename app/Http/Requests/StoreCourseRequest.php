<?php

namespace App\Http\Requests;

use App\Models\Course;

class StoreCourseRequest extends CourseRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('create', Course::class);
    }
}
