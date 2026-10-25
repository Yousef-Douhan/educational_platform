<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class LessonAttachmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        $course = $this->route('lesson')?->section?->course;

        return $course && ! $course->trashed() && $this->user()->can('update', $course);
    }

    public function rules(): array
    {
        return [
            'file'  => [
                'required',
                'file',
                'max:20480',
                'mimes:pdf,doc,docx,ppt,pptx,xls,xlsx,txt,zip,png,jpg,jpeg,webp,mp3'
            ],
            'title' => ['nullable', 'string', 'max:255'],
        ];
    }

    public function messages(): array
    {
        return [
            'file.required' => __('curriculum.validation.file_required'),
            'file.mimes'    => __('curriculum.validation.file_mimes'),
            'file.max'      => __('curriculum.validation.file_max'),
            'file.uploaded' => __('curriculum.validation.file_max'),
        ];
    }

    public function attributes(): array
    {
        return [
            'file'  => __('curriculum.fields.file'),
            'title' => __('curriculum.fields.attachment_title'),
        ];
    }
}
