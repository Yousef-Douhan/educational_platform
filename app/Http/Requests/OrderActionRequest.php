<?php

namespace App\Http\Requests;

use App\Models\Order;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/** تحقق إجراءات الطلب (تأكيد الدفع، الإلغاء، الاسترداد). الصلاحيات في middleware المتحكم */
class OrderActionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return match ($this->route()?->getActionMethod()) {
            'markPaid' => [
                'gateway'               => ['required', 'string', Rule::in(Order::MANUAL_GATEWAYS)],
                'transaction_reference' => ['nullable', 'string', 'max:120'],
                'note'                  => ['nullable', 'string', 'max:500'],
            ],
            'refund' => [
                'note' => ['required', 'string', 'min:3', 'max:500'],
            ],
            default => [
                'note' => ['nullable', 'string', 'max:500'],
            ],
        };
    }

    public function attributes(): array
    {
        return __('orders.fields');
    }

    protected function prepareForValidation(): void
    {
        foreach (['gateway', 'transaction_reference', 'note'] as $key) {
            if (is_string($this->input($key))) {
                $this->merge([$key => trim($this->input($key))]);
            }
        }
    }
}
