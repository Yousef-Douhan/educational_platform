<?php

namespace App\Http\Requests;

use App\Models\Certificate;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class IssueCertificateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('create', Certificate::class);
    }

    public function rules(): array
    {
        return [
            'user_id'     => ['required', 'integer', Rule::exists('users', 'id')->whereNull('deleted_at')],
            'course_id'   => ['required', 'integer', Rule::exists('courses', 'id')->whereNull('deleted_at')],
            'template_id' => ['nullable', 'integer', Rule::exists('certificate_templates', 'id')->whereNull('deleted_at')],
        ];
    }

    public function messages(): array
    {
        return [
            'user_id.required'   => __('certificates.validation.user_required'),
            'user_id.exists'     => __('certificates.validation.user_exists'),
            'course_id.required' => __('certificates.validation.course_required'),
            'course_id.exists'   => __('certificates.validation.course_exists'),
            'template_id.exists' => __('certificates.validation.template_exists'),
        ];
    }

    public function attributes(): array
    {
        return [
            'user_id'     => __('certificates.fields.student'),
            'course_id'   => __('certificates.fields.course'),
            'template_id' => __('certificates.fields.template'),
        ];
    }
}
