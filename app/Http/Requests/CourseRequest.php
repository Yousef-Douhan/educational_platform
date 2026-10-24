<?php

namespace App\Http\Requests;

use App\Models\Course;
use App\Models\User;
use Closure;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

abstract class CourseRequest extends FormRequest
{
    public function rules(): array
    {
        $user       = $this->user();
        $course     = $this->route('course'); // null عند الإنشاء
        $canPick    = $user->can('courses.manage-all');
        $statuses   = $user->can('courses.publish') ? Course::STATUSES : ['draft', 'pending_review'];

        return [
            // المدير يختار المدرّس، وغيره يُعيَّن تلقائيًا في الـ Controller
            'instructor_id' => $canPick
                ? ['required', 'integer', Rule::exists('users', 'id')->whereNull('deleted_at'), $this->mustBeInstructor()]
                : ['exclude'],

            'category_id' => ['required', 'integer', Rule::exists('categories', 'id')->whereNull('deleted_at')],
            'title_ar'    => ['required', 'string', 'max:255'],
            'title_en'    => ['required', 'string', 'max:255'],
            'slug'        => [
                'nullable',
                'string',
                'max:255',
                'regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/',
                Rule::unique('courses', 'slug')->ignore($course?->id)
            ],
            'description_ar' => ['nullable', 'string', 'max:20000'],
            'description_en' => ['nullable', 'string', 'max:20000'],

            'image'        => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048'],
            'remove_image' => ['nullable', 'boolean'],

            'price'          => ['required', 'numeric', 'min:0', 'max:99999999.99'],
            'discount_price' => ['nullable', 'numeric', 'min:0', 'lt:price', function ($attr, $value, $fail) {
                if ($value !== null && (float) $this->input('price') <= 0) {
                    $fail(__('courses.validation.discount_on_free'));
                }
            }],

            'level'  => ['required', Rule::in(Course::LEVELS)],
            'status' => [$course ? 'sometimes' : 'required', Rule::in($statuses)],

            'co_instructors'   => ['nullable', 'array', 'max:10'],
            'co_instructors.*' => ['integer', 'distinct', Rule::exists('users', 'id')->whereNull('deleted_at'), $this->mustBeInstructor()],

            'requirements'                      => ['nullable', 'array', 'max:20'],
            'requirements.*.requirement_ar'     => ['required', 'string', 'max:1000'],
            'requirements.*.requirement_en'     => ['required', 'string', 'max:1000'],

            'learning_outcomes'                 => ['nullable', 'array', 'max:20'],
            'learning_outcomes.*.outcome_ar'    => ['required', 'string', 'max:1000'],
            'learning_outcomes.*.outcome_en'    => ['required', 'string', 'max:1000'],
        ];
    }

    public function messages(): array
    {
        return [
            'category_id.required'   => __('courses.validation.category_required'),
            'category_id.exists'     => __('courses.validation.category_exists'),
            'instructor_id.required' => __('courses.validation.instructor_required'),
            'instructor_id.exists'   => __('courses.validation.instructor_invalid'),
            'title_ar.required'      => __('courses.validation.title_ar_required'),
            'title_en.required'      => __('courses.validation.title_en_required'),
            'slug.regex'             => __('courses.validation.slug_regex'),
            'slug.unique'            => __('courses.validation.slug_unique'),
            'image.image'            => __('courses.validation.image_image'),
            'image.max'              => __('courses.validation.image_max'),
            'discount_price.lt'      => __('courses.validation.discount_lt'),
            'status.in'              => __('courses.validation.status_in'),
            'requirements.*.requirement_ar.required'  => __('courses.validation.row_required'),
            'requirements.*.requirement_en.required'  => __('courses.validation.row_required'),
            'learning_outcomes.*.outcome_ar.required' => __('courses.validation.row_required'),
            'learning_outcomes.*.outcome_en.required' => __('courses.validation.row_required'),
        ];
    }

    public function attributes(): array
    {
        return [
            'instructor_id'  => __('courses.fields.instructor'),
            'category_id'    => __('courses.fields.category'),
            'title_ar'       => __('courses.fields.title_ar'),
            'title_en'       => __('courses.fields.title_en'),
            'slug'           => __('courses.fields.slug'),
            'image'          => __('courses.fields.image'),
            'price'          => __('courses.fields.price'),
            'discount_price' => __('courses.fields.discount_price'),
            'level'          => __('courses.fields.level'),
            'status'         => __('courses.fields.status'),
        ];
    }

    /** المستخدم المختار يجب أن يحمل دور instructor */
    private function mustBeInstructor(): Closure
    {
        return function ($attribute, $value, $fail) {
            $ok = User::query()
                ->whereKey($value)
                ->whereHas('roles', fn($q) => $q->where('name', 'instructor'))
                ->exists();

            if (! $ok) {
                $fail(__('courses.validation.instructor_invalid'));
            }
        };
    }
}
