<?php

namespace App\Http\Requests;

use App\Models\Course;
use App\Models\Lesson;
use Closure;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class LessonRequest extends FormRequest
{
    private const HOSTS = [
        'youtube' => ['youtube.com', 'youtu.be', 'youtube-nocookie.com'],
        'drive'   => ['drive.google.com', 'docs.google.com'],
        'bunny'   => ['b-cdn.net', 'mediadelivery.net', 'bunnycdn.com'],
    ];

    public function authorize(): bool
    {
        $course = $this->course();

        return $course && ! $course->trashed() && $this->user()->can('update', $course);
    }

    /** الدورة صاحبة الدرس: من القسم (إنشاء) أو من الدرس نفسه (تعديل) */
    private function course(): ?Course
    {
        $section = $this->route('section') ?? $this->route('lesson')?->section;

        return $section?->course;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'is_free_preview' => $this->boolean('is_free_preview'),
            'is_published'    => $this->boolean('is_published'),
        ]);
    }

    public function rules(): array
    {
        $isVideo   = $this->input('content_type') === 'video';
        $isArticle = $this->input('content_type') === 'article';
        $isUpdate  = (bool) $this->route('lesson');

        return [
            // النقل بين الأقسام عند التعديل فقط، وإلى قسم من نفس الدورة
            'section_id' => $isUpdate
                ? ['sometimes', 'integer', Rule::exists('course_sections', 'id')->where('course_id', $this->course()?->id)]
                : ['exclude'],

            'title_ar'     => ['required', 'string', 'max:255'],
            'title_en'     => ['required', 'string', 'max:255'],
            'content_type' => ['required', Rule::in(Lesson::CONTENT_TYPES)],

            'video_provider' => [Rule::requiredIf($isVideo), 'nullable', Rule::in(Lesson::VIDEO_PROVIDERS)],
            'video_url'      => [Rule::requiredIf($isVideo), 'nullable', 'string', 'max:2000', $this->videoUrlRule()],

            'content_ar' => [Rule::requiredIf($isArticle), 'nullable', 'string', 'max:200000'],
            'content_en' => ['nullable', 'string', 'max:200000'],

            'duration_minutes' => ['nullable', 'integer', 'min:0', 'max:6000'],
            'is_free_preview'  => ['boolean'],
            'is_published'     => ['boolean'],
        ];
    }

    /** يمنع الروابط غير الآمنة ويقيّد الرابط بنطاقات المزوّد المختار */
    private function videoUrlRule(): Closure
    {
        return function ($attribute, $value, $fail) {
            if ($this->input('content_type') !== 'video' || blank($value)) {
                return;
            }

            $provider = $this->input('video_provider');

            if ($provider === 's3') {
                if (! preg_match('#^[A-Za-z0-9][A-Za-z0-9._\-/]*$#', $value) || str_contains($value, '..')) {
                    $fail(__('curriculum.validation.video_key_invalid'));
                }

                return;
            }

            $parts = parse_url($value);

            if (! filter_var($value, FILTER_VALIDATE_URL) || ($parts['scheme'] ?? '') !== 'https' || empty($parts['host'])) {
                $fail(__('curriculum.validation.video_https'));

                return;
            }

            $allowed = self::HOSTS[$provider] ?? null; // external: أي نطاق https
            $host    = strtolower($parts['host']);

            if ($allowed && ! collect($allowed)->contains(fn($d) => $host === $d || str_ends_with($host, '.' . $d))) {
                $fail(__('curriculum.validation.video_host'));
            }
        };
    }

    public function messages(): array
    {
        return [
            'title_ar.required'       => __('curriculum.validation.title_ar_required'),
            'title_en.required'       => __('curriculum.validation.title_en_required'),
            'content_type.in'         => __('curriculum.validation.content_type_in'),
            'video_provider.required' => __('curriculum.validation.video_provider_required'),
            'video_url.required'      => __('curriculum.validation.video_url_required'),
            'content_ar.required'     => __('curriculum.validation.content_ar_required'),
            'section_id.exists'       => __('curriculum.validation.section_invalid'),
        ];
    }

    public function attributes(): array
    {
        return [
            'title_ar'         => __('curriculum.fields.title_ar'),
            'title_en'         => __('curriculum.fields.title_en'),
            'content_type'     => __('curriculum.fields.content_type'),
            'video_provider'   => __('curriculum.fields.video_provider'),
            'video_url'        => __('curriculum.fields.video_url'),
            'content_ar'       => __('curriculum.fields.content_ar'),
            'content_en'       => __('curriculum.fields.content_en'),
            'duration_minutes' => __('curriculum.fields.duration_minutes'),
        ];
    }
}
