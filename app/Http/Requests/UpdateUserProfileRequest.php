<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateUserProfileRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // اربطها بـ Policy/Gate الموجود في مشروعك
    }

    public function rules(): array
    {
        $profile = $this->route('user_profile');

        return [
            'user_id'      => [
                'required',
                'integer',
                'exists:users,id',
                Rule::unique('user_profiles', 'user_id')->ignore($profile?->id)
            ],
            'bio'          => ['nullable', 'string'],
            'avatar'       => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048'],
            'remove_avatar' => ['nullable', 'boolean'],
            'phone'        => ['nullable', 'string', 'max:50'],
            'website'      => ['nullable', 'url', 'max:255'],
            'social_links' => ['nullable', 'string', 'max:255'],
        ];
    }

    public function messages(): array
    {
        return (new StoreUserProfileRequest())->messages();
    }

    public function attributes(): array
    {
        return (new StoreUserProfileRequest())->attributes();
    }
}
