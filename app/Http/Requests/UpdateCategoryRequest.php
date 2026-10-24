<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateCategoryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $category = $this->route('category');

        return [
            'name_ar'   => ['required', 'string', 'max:255'],
            'name_en'   => ['required', 'string', 'max:255'],
            'slug'      => [
                'nullable',
                'string',
                'max:255',
                'regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/',
                Rule::unique('categories', 'slug')->ignore($category->id)
            ],
            'icon'      => ['nullable', 'string', 'max:255', 'regex:/^[A-Za-z0-9\- ]+$/'],
            'parent_id' => [
                'nullable',
                'integer',
                Rule::exists('categories', 'id')->whereNull('deleted_at'),
                Rule::notIn(array_merge([$category->id], $category->descendantIds()))
            ],
        ];
    }

    public function messages(): array
    {
        return (new StoreCategoryRequest())->messages();
    }

    public function attributes(): array
    {
        return (new StoreCategoryRequest())->attributes();
    }
}
