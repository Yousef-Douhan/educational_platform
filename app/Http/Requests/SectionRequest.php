<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class SectionRequest extends FormRequest
{
    public function authorize(): bool
    {
        $course = $this->route('course') ?? $this->route('section')?->course;

        return $course && ! $course->trashed() && $this->user()->can('update', $course);
    }

    public function rules(): array
    {
        return [
            'title_ar' => ['required', 'string', 'max:255'],
            'title_en' => ['required', 'string', 'max:255'],
        ];
    }

    public function messages(): array
    {
        return [
            'title_ar.required' => __('curriculum.validation.title_ar_required'),
            'title_en.required' => __('curriculum.validation.title_en_required'),
        ];
    }

    public function attributes(): array
    {
        return [
            'title_ar' => __('curriculum.fields.title_ar'),
            'title_en' => __('curriculum.fields.title_en'),
        ];
    }
}
