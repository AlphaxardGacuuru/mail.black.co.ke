<?php

namespace App\Http\Resources;

use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class TenantResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @param  Request  $request
     * @return array|Arrayable|\JsonSerializable
     */
    public function toArray($request)
    {
        if ($request->filled("idAndName")) {
            return [
                "id" => $this->user->id,
                "userUnitId" => $this->id,
                "unitId" => $this->unit_id,
                "unitName" => $this->unit->name,
                "propertyId" => $this->unit->property->id,
                "name" => $this->user->name,
                "rent" => $this->unit->rent,
                "deposit" => $this->unit->deposit,
                "contractTerms" => $this->unit->property->contract_terms,
            ];
        }

        return [
            "id" => $this->user->id,
            "userUnitId" => $this->id,
            "unitId" => $this->unit_id,
            "unitName" => $this->unit->name,
            "propertyId" => $this->unit->property->id,
            "name" => $this->user->name,
            "email" => $this->user->email,
            "phone" => $this->user->phone,
            "gender" => $this->user->gender,
            "avatar" => $this->user->avatar,
            "occupiedAt" => $this->occupied_at,
            "vacatedAt" => $this->vacated_at ?? "occupied",
            "createdAt" => $this->created_at,
        ];
    }
}
