<?php

namespace App\Http\Resources;

use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class VisitorAdmissionResource extends JsonResource
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
            "tenantId" => $this->user_unit_id,
            "propertyId" => $this->userUnit->unit->property->id,
            "firstName" => $this->first_name,
            "middleName" => $this->middle_name,
            "lastName" => $this->last_name,
            "nationalID" => $this->national_id,
            "email" => $this->email,
            "phone" => $this->phone,
            "propertyName" => $this->userUnit->unit->property->name,
            "unitName" => $this->userUnit->unit->name,
            "tenantName" => $this->userUnit->user->name,
            "status" => $this->status,
            "createdByName" => $this->createdBy->name,
            "createdAt" => $this->created_at,
        ];
    }
}
