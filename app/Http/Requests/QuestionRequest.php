<?php

namespace App\Http\Requests;

use App\Models\Question;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class QuestionRequest extends FormRequest
{
    public function authorize(): bool
    {
        $quiz = $this->route('quiz') ?? $this->route('question')?->quiz;

        return $quiz && ! $quiz->trashed() && $this->user()->can('update', $quiz);
    }

    public function rules(): array
    {
        $type = $this->input('question_type');

        return [
            'question_text' => ['required', 'string', 'max:5000'],
            'question_type' => ['required', Rule::in(Question::TYPES)],
            'points'        => ['required', 'integer', 'between:1,100'],

            'tf_answer' => [Rule::requiredIf($type === 'true_false'), 'nullable', Rule::in(['true', 'false'])],

            'options'                => [Rule::requiredIf(in_array($type, ['multiple_choice', 'text'], true)), 'nullable', 'array', 'max:10'],
            'options.*.id'           => ['nullable', 'integer'],
            'options.*.option_text'  => ['required', 'string', 'max:1000'],
            'options.*.is_correct'   => ['nullable', 'boolean'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $v) {
            if ($v->errors()->isNotEmpty()) {
                return;
            }

            $type    = $this->input('question_type');
            $options = collect($this->input('options', []));
            $texts   = $options->map(fn($o) => mb_strtolower(trim($o['option_text'])));

            if ($type === 'multiple_choice') {
                $correct = $options->filter(fn($o) => filter_var($o['is_correct'] ?? false, FILTER_VALIDATE_BOOLEAN))->count();

                if ($options->count() < 2) {
                    $v->errors()->add('options', __('quizzes.validation.mcq_min'));
                } elseif ($correct !== 1) {
                    $v->errors()->add('options', __('quizzes.validation.mcq_one_correct'));
                } elseif ($texts->unique()->count() !== $texts->count()) {
                    $v->errors()->add('options', __('quizzes.validation.options_duplicate'));
                }
            }

            if ($type === 'text') {
                if ($options->isEmpty()) {
                    $v->errors()->add('options', __('quizzes.validation.text_min'));
                } elseif ($texts->unique()->count() !== $texts->count()) {
                    $v->errors()->add('options', __('quizzes.validation.options_duplicate'));
                }
            }
        });
    }

    public function messages(): array
    {
        return [
            'question_text.required'        => __('quizzes.validation.question_required'),
            'question_type.in'              => __('quizzes.validation.type_in'),
            'points.between'                => __('quizzes.validation.points_between'),
            'tf_answer.required'            => __('quizzes.validation.tf_required'),
            'options.required'              => __('quizzes.validation.options_required'),
            'options.*.option_text.required' => __('quizzes.validation.option_text_required'),
        ];
    }

    public function attributes(): array
    {
        return [
            'question_text' => __('quizzes.fields.question_text'),
            'question_type' => __('quizzes.fields.question_type'),
            'points'        => __('quizzes.fields.points'),
            'options'       => __('quizzes.fields.options'),
        ];
    }
}
