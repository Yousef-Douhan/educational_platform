<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdatePermissionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $permission = $this->route('permission');

        return [
            'name' => [
                'required',
                'string',
                'max:100',
                Rule::unique('permissions', 'name')->where('guard_name', $permission->guard_name)->ignore($permission->id)
            ],
        ];
    }

    public function messages(): array
    {
        return (new StorePermissionRequest())->messages();
    }

    public function attributes(): array
    {
        return (new StorePermissionRequest())->attributes();
    }
}
