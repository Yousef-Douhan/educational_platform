<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/** سبب إلغاء المحاولة إلزامي لأن الإلغاء إجراء إداري يُدقَّق لاحقًا. الصلاحية في middleware المتحكم */
class QuizAttemptVoidRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return ['reason' => ['required', 'string', 'min:3', 'max:500']];
    }

    public function attributes(): array
    {
        return ['reason' => __('quiz_attempts.fields.reason')];
    }

    protected function prepareForValidation(): void
    {
        if (is_string($this->input('reason'))) {
            $this->merge(['reason' => trim($this->input('reason'))]);
        }
    }
}
