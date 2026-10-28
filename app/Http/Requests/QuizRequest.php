<?php

namespace App\Http\Requests;

use App\Models\Lesson;
use App\Models\Quiz;
use Closure;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class QuizRequest extends FormRequest
{
    public function authorize(): bool
    {
        $quiz = $this->route('quiz');

        return $quiz
            ? $this->user()->can('update', $quiz)
            : $this->user()->can('create', Quiz::class);
    }

    public function rules(): array
    {
        $isUpdate = (bool) $this->route('quiz');

        return [
            // الدرس لا يتغير بعد الإنشاء
            'lesson_id' => $isUpdate ? ['exclude'] : [
                'required',
                'integer',
                Rule::exists('lessons', 'id')->whereNull('deleted_at')->where('content_type', 'quiz'),
                Rule::unique('quizzes', 'lesson_id'), // يشمل الاختبارات المحذوفة مؤقتًا
                $this->lessonIsEditableByUser(),
            ],
            'title_ar'           => ['required', 'string', 'max:255'],
            'title_en'           => ['required', 'string', 'max:255'],
            'pass_mark'          => ['required', 'integer', 'between:0,100'],
            'time_limit_minutes' => ['required', 'integer', 'between:0,1440'],
            'attempts_allowed'   => ['required', 'integer', 'between:0,100'],
        ];
    }

    private function lessonIsEditableByUser(): Closure
    {
        return function ($attribute, $value, $fail) {
            $course = Lesson::with('section.course')->find($value)?->section?->course;

            if (! $course || $course->trashed() || ! $this->user()->can('update', $course)) {
                $fail(__('quizzes.validation.lesson_forbidden'));
            }
        };
    }

    public function messages(): array
    {
        return [
            'lesson_id.required' => __('quizzes.validation.lesson_required'),
            'lesson_id.exists'   => __('quizzes.validation.lesson_exists'),
            'lesson_id.unique'   => __('quizzes.validation.lesson_unique'),
            'title_ar.required'  => __('quizzes.validation.title_ar_required'),
            'title_en.required'  => __('quizzes.validation.title_en_required'),
            'pass_mark.between'  => __('quizzes.validation.pass_mark_between'),
        ];
    }

    public function attributes(): array
    {
        return [
            'lesson_id'          => __('quizzes.fields.lesson'),
            'title_ar'           => __('quizzes.fields.title_ar'),
            'title_en'           => __('quizzes.fields.title_en'),
            'pass_mark'          => __('quizzes.fields.pass_mark'),
            'time_limit_minutes' => __('quizzes.fields.time_limit'),
            'attempts_allowed'   => __('quizzes.fields.attempts'),
        ];
    }
}
