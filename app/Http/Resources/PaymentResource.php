<?php

namespace App\Http\Resources;

use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PaymentResource extends JsonResource
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
            "tenantName" => $this->userUnit->user->name,
            "tenantEmail" => $this->userUnit->user->email,
            "tenantPhone" => $this->userUnit->user->phone,
            "unitId" => $this->userUnit->unit->id,
            "unitName" => $this->userUnit->unit->name,
            "userUnitId" => $this->user_unit_id,
            "channel" => $this->channel,
            "transactionReference" => $this->transaction_reference,
            "amount" => number_format($this->amount),
            "month" => $this->month,
            "year" => $this->year,
            "updatedAt" => $this->updatedAt,
            "createdAt" => $this->createdAt,
        ];
    }
}
