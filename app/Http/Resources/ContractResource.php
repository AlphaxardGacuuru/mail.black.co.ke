<?php

namespace App\Http\Resources;

use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ContractResource extends JsonResource
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
            "propertyName" => $this->userUnit->unit->property->name,
            "userUnitId" => $this->user_unit_id,
            "type" => $this->type,
            "startDate" => $this->start_date,
            "endDate" => $this->end_date,
            "rentAmount" => number_format($this->rent_amount),
            "rentAmountRaw" => $this->getRawOriginal('rent_amount'),
            "depositAmount" => number_format($this->deposit_amount),
            "depositAmountRaw" => $this->getRawOriginal('deposit_amount'),
            "paymentFrequency" => $this->payment_frequency,
            "terms" => $this->terms,
            "document" => $this->document,
            "status" => $this->status,
            "signedAt" => $this->signed_at,
            "terminatedAt" => $this->terminated_at,
            "terminationReason" => $this->termination_reason,
            "autoRenew" => $this->auto_renew,
            "noticePeriodDays" => $this->notice_period_days,
            "createdBy" => $this->createdBy,
            "updatedAt" => $this->updated_at,
            "createdAt" => $this->created_at,
        ];
    }
}
