<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreUserRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', 'unique:users'],
            'password' => ['required', 'string', 'min:8'],
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
