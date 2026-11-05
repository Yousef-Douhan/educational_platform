<?php

namespace App\Http\Resources\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** المستخدم الحالي فقط (لا يُعرض مستخدم آخر عبر هذا المورد إلا بالاسم في الدورات). */
class UserResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'             => $this->id,
            'name'           => $this->name,
            'email'          => $this->email,
            'email_verified' => $this->email_verified_at !== null,
            'status'         => $this->status,
            'roles'          => $this->getRoleNames()->values(),
            'is_staff'       => $this->isStaff(),
            'created_at'     => $this->created_at?->toIso8601String(),
        ];
    }
}
