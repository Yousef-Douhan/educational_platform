<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreCategoryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // الحماية عبر middleware الـ Controller
    }

    public function rules(): array
    {
        return [
            'name_ar'   => ['required', 'string', 'max:255'],
            'name_en'   => ['required', 'string', 'max:255'],
            'slug'      => [
                'nullable',
                'string',
                'max:255',
                'regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/',
                Rule::unique('categories', 'slug')
            ],
            'icon'      => ['nullable', 'string', 'max:255', 'regex:/^[A-Za-z0-9\- ]+$/'],
            'parent_id' => [
                'nullable',
                'integer',
                Rule::exists('categories', 'id')->whereNull('deleted_at')
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'name_ar.required'  => __('categories.validation.name_ar_required'),
            'name_en.required'  => __('categories.validation.name_en_required'),
            'slug.regex'        => __('categories.validation.slug_regex'),
            'slug.unique'       => __('categories.validation.slug_unique'),
            'icon.regex'        => __('categories.validation.icon_regex'),
            'parent_id.exists'  => __('categories.validation.parent_exists'),
            'parent_id.not_in'  => __('categories.validation.parent_not_in'),
        ];
    }

    public function attributes(): array
    {
        return [
            'name_ar'   => __('categories.fields.name_ar'),
            'name_en'   => __('categories.fields.name_en'),
            'slug'      => __('categories.fields.slug'),
            'icon'      => __('categories.fields.icon'),
            'parent_id' => __('categories.fields.parent'),
        ];
    }
}
