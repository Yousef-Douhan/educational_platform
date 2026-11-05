<?php

namespace App\Http\Resources\V1;

use App\Support\Loc;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** طلب الطالب نفسه. يُشترط تحميل items مسبقًا عند الحاجة لها. */
class OrderResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'order_number'    => $this->order_number,
            'status'          => $this->status,
            'subtotal'        => (string) $this->subtotal,
            'discount_amount' => (string) $this->discount_amount,
            'total_amount'    => (string) $this->total_amount,
            'currency'        => $this->currency,
            'coupon_code'     => $this->coupon_code,
            'paid_at'         => $this->paid_at?->toIso8601String(),
            'created_at'      => $this->created_at?->toIso8601String(),
            'items_count'     => $this->when(isset($this->items_count), fn () => (int) $this->items_count),
            'items'           => $this->whenLoaded('items', fn () => $this->items->map(fn ($i) => [
                'course_id' => $i->course_id,
                'title'     => Loc::pick($i->title_ar, $i->title_en),
                'price'     => (string) $i->price,
            ])->values()),
        ];
    }
}
