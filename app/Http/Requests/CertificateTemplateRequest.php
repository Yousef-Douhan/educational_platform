<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class CertificateTemplateRequest extends FormRequest
{
    private const FLAGS = ['hide_header', 'hide_date', 'hide_signature', 'hide_qr'];

    public function authorize(): bool
    {
        return true; // الحماية بصلاحيات الـ Controller
    }

    protected function prepareForValidation(): void
    {
        $layout = (array) $this->input('layout', []);

        // الخانات غير المؤشَّرة لا تُرسل، فنعتبرها false
        foreach (self::FLAGS as $flag) {
            $layout[$flag] = filter_var($layout[$flag] ?? false, FILTER_VALIDATE_BOOLEAN);
        }

        $this->merge(['is_default' => $this->boolean('is_default'), 'layout' => $layout]);
    }

    public function rules(): array
    {
        $isCreate = ! $this->route('certificate_template');

        return [
            'name'       => ['required', 'string', 'max:255'],
            'is_default' => ['boolean'],
            'design'     => [
                $isCreate ? 'required' : 'nullable',
                'image',
                'mimes:jpg,jpeg,png,webp',
                'max:5120',
                'dimensions:min_width=1000,min_height=700',
                function ($attribute, $value, $fail) {
                    $size = @getimagesize($value->getRealPath());

                    if (! $size || $size[0] <= $size[1]) {
                        $fail(__('certificates.validation.design_landscape'));
                    }
                },
            ],

            'layout'                  => ['nullable', 'array'],
            'layout.area_left'        => ['nullable', 'numeric', 'between:0,200'],
            'layout.area_width'       => ['nullable', 'numeric', 'between:100,297'],
            'layout.middle_top'       => ['nullable', 'numeric', 'between:30,130'],
            'layout.qr_top'           => ['nullable', 'numeric', 'between:100,170'],
            'layout.hide_header'      => ['boolean'],
            'layout.hide_date'        => ['boolean'],
            'layout.hide_signature'   => ['boolean'],
            'layout.hide_qr'          => ['boolean'],
            'layout.text_color'       => ['nullable', 'regex:/^#[0-9a-fA-F]{6}$/'],
            'layout.accent_color'     => ['nullable', 'regex:/^#[0-9a-fA-F]{6}$/'],
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($v) {
            if ($v->errors()->isNotEmpty()) {
                return;
            }

            $left  = (float) ($this->input('layout.area_left') ?? 0);
            $width = (float) ($this->input('layout.area_width') ?? 297);

            if ($left + $width > 297.01) {
                $v->errors()->add('layout.area_width', __('certificate-templates.validation.layout_area'));
            }
        });
    }

    public function messages(): array
    {
        return [
            'name.required'     => __('certificates.validation.name_required'),
            'design.required'   => __('certificates.validation.design_required'),
            'design.image'      => __('certificates.validation.design_image'),
            'design.mimes'      => __('certificates.validation.design_image'),
            'design.max'        => __('certificates.validation.design_max'),
            'design.dimensions' => __('certificates.validation.design_dimensions'),
            'layout.*.between'  => __('certificate-templates.validation.layout_range'),
            'layout.*.numeric'  => __('certificate-templates.validation.layout_range'),
            'layout.*.regex'    => __('certificate-templates.validation.layout_color'),
        ];
    }

    public function attributes(): array
    {
        return [
            'name'   => __('certificates.fields.template_name'),
            'design' => __('certificates.fields.design'),
        ];
    }
}
