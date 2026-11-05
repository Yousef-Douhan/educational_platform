<?php

namespace App\Http\Resources\V1;

use App\Support\Loc;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CategoryResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'       => $this->id,
            'slug'     => $this->slug,
            'name'     => Loc::pick($this->name_ar, $this->name_en),
            'name_ar'  => $this->name_ar,
            'name_en'  => $this->name_en,
            'icon'     => $this->icon,
            'parent_id' => $this->parent_id,
            'children' => self::collection($this->whenLoaded('children')),
        ];
    }
}
