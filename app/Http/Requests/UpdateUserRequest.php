<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateUserRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', Rule::unique('users')->ignore($this->user)],
            'password' => ['nullable', 'string', 'min:8'],
            'status' => ['required', 'in:active,inactive,suspended'],
        ];
    }

    public function attributes(): array
    {
        return [
            'name' => __('users.name'),
            'email' => __('users.email'),
            'password' => __('users.password'),
            'status' => __('users.status'),
        ];
    }
}
