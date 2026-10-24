<?php

namespace App\Http\Requests;

class UpdateCourseRequest extends CourseRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('update', $this->route('course'));
    }
}
