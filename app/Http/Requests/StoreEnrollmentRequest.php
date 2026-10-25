<?php

namespace App\Http\Requests;

use App\Models\Enrollment;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreEnrollmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('create', Enrollment::class);
    }

    public function rules(): array
    {
        return [
            'user_id' => ['required', 'integer', Rule::exists('users', 'id')->whereNull('deleted_at')],
            'course_id' => [
                'required',
                'integer',
                Rule::exists('courses', 'id')->whereNull('deleted_at')->where('status', 'published'),
                // أي سجل سابق للطالب في هذه الدورة (حتى الملغى) يمنع الإنشاء: يُعدَّل بدل ذلك
                Rule::unique('enrollments', 'course_id')->where('user_id', $this->input('user_id')),
            ],
            'expires_at' => ['nullable', 'date', 'after:now'],
        ];
    }

    public function messages(): array
    {
        return [
            'user_id.required'   => __('enrollments.validation.user_required'),
            'user_id.exists'     => __('enrollments.validation.user_exists'),
            'course_id.required' => __('enrollments.validation.course_required'),
            'course_id.exists'   => __('enrollments.validation.course_exists'),
            'course_id.unique'   => __('enrollments.validation.already_enrolled'),
            'expires_at.after'   => __('enrollments.validation.expires_after_now'),
        ];
    }

    public function attributes(): array
    {
        return [
            'user_id'    => __('enrollments.fields.student'),
            'course_id'  => __('enrollments.fields.course'),
            'expires_at' => __('enrollments.fields.expires_at'),
        ];
    }
}
