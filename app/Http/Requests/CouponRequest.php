<?php

namespace App\Http\Requests;

use App\Models\Coupon;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CouponRequest extends FormRequest
{
    /** الحقول التي تغيّر معنى الاستخدامات السابقة، فتُقفل بعد أول استخدام */
    public const LOCKED_WHEN_USED = ['code', 'discount_type', 'discount_value'];

    public function authorize(): bool
    {
        return true; // الحماية بصلاحيات الـ Controller
    }

    public function coupon(): ?Coupon
    {
        $route = $this->route('coupon');

        return $route instanceof Coupon ? $route : ($route ? Coupon::withTrashed()->find($route) : null);
    }

    protected function prepareForValidation(): void
    {
        $blankToNull = fn ($v) => ($v === '' || $v === null) ? null : $v;
        $type        = $this->input('discount_type');

        $this->merge([
            'code'             => Coupon::normalizeCode($this->input('code')),
            'is_active'        => $this->boolean('is_active'),
            // السقف لا معنى له مع الخصم الثابت
            'max_discount'     => $type === 'percentage' ? $blankToNull($this->input('max_discount')) : null,
            'min_order_amount' => $blankToNull($this->input('min_order_amount')),
            'starts_at'        => $blankToNull($this->input('starts_at')),
            'expires_at'       => $blankToNull($this->input('expires_at')),
            'usage_limit'      => $blankToNull($this->input('usage_limit')),
            'per_user_limit'   => $blankToNull($this->input('per_user_limit')),
        ]);
    }

    public function rules(): array
    {
        $coupon   = $this->coupon();
        $isCreate = $coupon === null;
        $max      = $this->input('discount_type') === 'percentage' ? 'max:100' : 'max:99999999.99';

        return [
            'code' => [
                'required', 'string', 'min:3', 'max:50', 'regex:/^[A-Z0-9][A-Z0-9_-]*$/',
                // المحذوفات مؤقتًا تحجز كودها أيضًا لأن الفهرس الفريد يشملها
                Rule::unique('coupons', 'code')->ignore($coupon?->id),
            ],
            'discount_type'    => ['required', Rule::in(Coupon::TYPES)],
            'discount_value'   => ['required', 'numeric', 'gt:0', 'decimal:0,2', $max],
            'max_discount'     => ['nullable', 'numeric', 'gt:0', 'decimal:0,2', 'max:99999999.99'],
            'min_order_amount' => ['nullable', 'numeric', 'min:0', 'decimal:0,2', 'max:99999999.99'],
            'starts_at'        => ['nullable', 'date'],
            'expires_at'       => array_filter([
                'nullable', 'date',
                $this->filled('starts_at') ? 'after:starts_at' : null,
                $isCreate ? 'after:now' : null,
            ]),
            'usage_limit'    => ['nullable', 'integer', 'min:1', 'max:1000000'],
            'per_user_limit' => ['nullable', 'integer', 'min:1', 'max:1000000'],
            'is_active'      => ['boolean'],
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($v) {
            if ($v->errors()->isNotEmpty()) {
                return;
            }

            $coupon = $this->coupon();
            $limit  = $this->input('usage_limit');
            $per    = $this->input('per_user_limit');

            if ($limit !== null && $per !== null && (int) $per > (int) $limit) {
                $v->errors()->add('per_user_limit', __('coupons.validation.per_user_exceeds'));
            }

            if (! $coupon) {
                return;
            }

            if ($limit !== null && (int) $limit < $coupon->times_used) {
                $v->errors()->add('usage_limit', __('coupons.validation.limit_below_used', ['used' => $coupon->times_used]));
            }

            if ($coupon->hasBeenUsed()) {
                foreach (self::LOCKED_WHEN_USED as $field) {
                    $changed = $field === 'discount_value'
                        ? (float) $this->input($field) !== (float) $coupon->discount_value
                        : (string) $this->input($field) !== (string) $coupon->getRawOriginal($field);

                    if ($changed) {
                        $v->errors()->add($field, __('coupons.validation.locked'));
                    }
                }
            }
        });
    }

    public function messages(): array
    {
        return [
            'code.required'      => __('coupons.validation.code_required'),
            'code.regex'         => __('coupons.validation.code_format'),
            'code.unique'        => __('coupons.validation.code_taken'),
            'code.min'           => __('coupons.validation.code_format'),
            'discount_value.max' => __('coupons.validation.value_max'),
            'discount_value.gt'  => __('coupons.validation.value_positive'),
            'expires_at.after'   => __('coupons.validation.expires_after'),
        ];
    }

    public function attributes(): array
    {
        return [
            'code'             => __('coupons.fields.code'),
            'discount_type'    => __('coupons.fields.discount_type'),
            'discount_value'   => __('coupons.fields.discount_value'),
            'max_discount'     => __('coupons.fields.max_discount'),
            'min_order_amount' => __('coupons.fields.min_order_amount'),
            'starts_at'        => __('coupons.fields.starts_at'),
            'expires_at'       => __('coupons.fields.expires_at'),
            'usage_limit'      => __('coupons.fields.usage_limit'),
            'per_user_limit'   => __('coupons.fields.per_user_limit'),
        ];
    }
}
