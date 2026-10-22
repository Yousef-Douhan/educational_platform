<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreUserProfileRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // اربطها بـ Policy/Gate الموجود في مشروعك
    }

    public function rules(): array
    {
        return [
            'user_id'      => [
                'required',
                'integer',
                'exists:users,id',
                Rule::unique('user_profiles', 'user_id')
            ],
            'bio'          => ['nullable', 'string'],
            'avatar'       => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048'],
            'phone'        => ['nullable', 'string', 'max:50'],
            'website'      => ['nullable', 'url', 'max:255'],
            'social_links' => ['nullable', 'string', 'max:255'],
        ];
    }

    public function messages(): array
    {
        return [
            'user_id.required' => __('user-profiles.validation.user_id_required'),
            'user_id.exists'   => __('user-profiles.validation.user_id_exists'),
            'user_id.unique'   => __('user-profiles.validation.user_id_unique'),
            'avatar.image'     => __('user-profiles.validation.avatar_image'),
            'avatar.max'       => __('user-profiles.validation.avatar_max'),
            'website.url'      => __('user-profiles.validation.website_url'),
        ];
    }

    public function attributes(): array
    {
        return [
            'user_id'      => __('user-profiles.fields.user'),
            'bio'          => __('user-profiles.fields.bio'),
            'avatar'       => __('user-profiles.fields.avatar'),
            'phone'        => __('user-profiles.fields.phone'),
            'website'      => __('user-profiles.fields.website'),
            'social_links' => __('user-profiles.fields.social_links'),
        ];
    }
}
