<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StorePermissionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $guard = $this->input('guard_name') ?: config('auth.defaults.guard');

        return [
            'name'       => [
                'required',
                'string',
                'max:100',
                Rule::unique('permissions', 'name')->where('guard_name', $guard)
            ],
            'guard_name' => ['nullable', 'string', 'max:100', Rule::in(array_keys(config('auth.guards')))],
        ];
    }

    public function messages(): array
    {
        return [
            'name.required' => __('permissions.validation.name_required'),
            'name.unique'   => __('permissions.validation.name_unique'),
            'guard_name.in' => __('permissions.validation.guard_in'),
        ];
    }

    public function attributes(): array
    {
        return [
            'name'       => __('permissions.fields.name'),
            'guard_name' => __('permissions.fields.guard_name'),
        ];
    }
}
