<?php

namespace App\Http\Resources\V1;

use App\Support\Loc;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * بطاقة الدورة في القوائم. لا تحتوي أبدًا على روابط الفيديو أو محتوى الدروس.
 * يُشترط تحميل category وinstructor وlessons_count مسبقًا.
 */
class CourseResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'             => $this->id,
            'slug'           => $this->slug,
            'title'          => Loc::pick($this->title_ar, $this->title_en),
            'title_ar'       => $this->title_ar,
            'title_en'       => $this->title_en,
            'image'          => $this->image_src,
            'level'          => $this->level,
            'price'          => (string) $this->price,
            'discount_price' => $this->discount_price !== null ? (string) $this->discount_price : null,
            'final_price'    => $this->final_price,
            'is_free'        => $this->is_free,
            'currency'       => 'USD',
            'lessons_count'  => (int) ($this->lessons_count ?? 0),
            'category'       => $this->whenLoaded('category', fn () => $this->category ? [
                'id' => $this->category->id, 'slug' => $this->category->slug,
                'name' => Loc::pick($this->category->name_ar, $this->category->name_en),
            ] : null),
            'instructor'     => $this->whenLoaded('instructor', fn () => $this->instructor
                ? ['id' => $this->instructor->id, 'name' => $this->instructor->name] : null),
        ];
    }
}
