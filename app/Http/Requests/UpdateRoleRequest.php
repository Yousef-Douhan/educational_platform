<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateRoleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $role = $this->route('role');

        return [
            'name'          => [
                'required',
                'string',
                'max:100',
                Rule::unique('roles', 'name')->where('guard_name', $role->guard_name)->ignore($role->id)
            ],
            'permissions'   => ['nullable', 'array'],
            'permissions.*' => ['integer', Rule::exists('permissions', 'id')->where('guard_name', $role->guard_name)],
        ];
    }

    public function messages(): array
    {
        return (new StoreRoleRequest())->messages();
    }

    public function attributes(): array
    {
        return (new StoreRoleRequest())->attributes();
    }
}
