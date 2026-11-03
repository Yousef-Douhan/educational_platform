<?php

namespace App\Http\Requests;

use App\Services\ReceiptService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SubmitReceiptRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'method'           => ['required', 'string', Rule::in(array_keys(app(ReceiptService::class)->methods()))],
            'sender_name'      => ['required', 'string', 'min:3', 'max:120'],
            'reference_number' => ['required', 'string', 'min:4', 'max:60', 'regex:/^[\p{L}\p{N}\-_\/. ]+$/u'],
            // صورة فعلية فقط (يُتحقق من المحتوى لا من الامتداد) بحجم وأبعاد معقولة
            'receipt'          => ['required', 'file', 'mimetypes:image/jpeg,image/png,image/webp', 'max:4096', 'dimensions:max_width=8000,max_height=8000'],
        ];
    }

    public function attributes(): array
    {
        return [
            'method' => __('receipts.fields.method'), 'sender_name' => __('receipts.fields.sender_name'),
            'reference_number' => __('receipts.fields.reference'), 'receipt' => __('receipts.fields.image'),
        ];
    }
}
