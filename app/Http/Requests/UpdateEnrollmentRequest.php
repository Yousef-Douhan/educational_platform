<?php

namespace App\Http\Requests;

use App\Models\Enrollment;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateEnrollmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('update', $this->route('enrollment'));
    }

    /** الطالب والدورة لا يتغيران: لا تظهر قواعدهما فلا تدخلان في validated() */
    public function rules(): array
    {
        return [
            'status'     => ['required', Rule::in(Enrollment::STATUSES)],
            'expires_at' => ['nullable', 'date', function ($attribute, $value, $fail) {
                // لا يجوز تفعيل تسجيل بتاريخ انتهاء منقضٍ
                if ($value && $this->input('status') === 'active' && strtotime($value) <= time()) {
                    $fail(__('enrollments.validation.expires_in_past'));
                }
            }],
        ];
    }

    public function messages(): array
    {
        return [
            'status.required' => __('enrollments.validation.status_required'),
            'status.in'       => __('enrollments.validation.status_in'),
        ];
    }

    public function attributes(): array
    {
        return [
            'status'     => __('enrollments.fields.status'),
            'expires_at' => __('enrollments.fields.expires_at'),
        ];
    }
}
