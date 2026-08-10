<?php

namespace App\Http\Resources;

use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class DeductionResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @param  Request  $request
     * @return array|Arrayable|\JsonSerializable
     */
    public function toArray($request)
    {
        return [
            "id" => $this->id,
            "number" => $this->number,
            "tenantId" => $this->userUnit->user_id,
            "tenantName" => $this->userUnit->user->name,
            "tenantPhone" => $this->userUnit->user->phone,
            "tenantEmail" => $this->userUnit->user->email,
            "unitId" => $this->userUnit->unit->id,
            "unitName" => $this->userUnit->unit->name,
            "userUnitId" => $this->user_unit_id,
            "description" => $this->description,
            "amount" => number_format($this->amount),
            "month" => $this->month,
            "year" => $this->year,
            "createdBy" => $this->createdBy,
            "updatedAt" => $this->updated_at,
            "createdAt" => $this->created_at,
        ];
    }
}
