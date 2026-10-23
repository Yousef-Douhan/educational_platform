<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreRoleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // اربطها بالصلاحيات لاحقًا
    }

    public function rules(): array
    {
        $guard = $this->input('guard_name') ?: config('auth.defaults.guard');

        return [
            'name'          => [
                'required',
                'string',
                'max:100',
                Rule::unique('roles', 'name')->where('guard_name', $guard)
            ],
            'guard_name'    => ['nullable', 'string', 'max:100', Rule::in(array_keys(config('auth.guards')))],
            'permissions'   => ['nullable', 'array'],
            'permissions.*' => ['integer', Rule::exists('permissions', 'id')->where('guard_name', $guard)],
        ];
    }

    public function messages(): array
    {
        return [
            'name.required'        => __('roles.validation.name_required'),
            'name.unique'          => __('roles.validation.name_unique'),
            'guard_name.in'        => __('roles.validation.guard_in'),
            'permissions.*.exists' => __('roles.validation.permission_exists'),
        ];
    }

    public function attributes(): array
    {
        return [
            'name'        => __('roles.fields.name'),
            'guard_name'  => __('roles.fields.guard_name'),
            'permissions' => __('roles.fields.permissions'),
        ];
    }
}
