<?php

namespace App\Http\Resources;

use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class MPESATransactionResource extends JsonResource
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
            "userName" => $this->user->name,
            "senderPhoneNumber" => $this->sender_phone_number,
            "amount" => number_format($this->amount),
            "currency" => $this->currency,
            "kopokopoId" => $this->kopokopo_id,
            "type" => $this->type,
            "initiationTime" => $this->initiation_time,
            "status" => $this->status,
            "eventType" => $this->event_type,
            "resourceId" => $this->resource_id,
            "reference" => $this->reference,
            "originationTime" => $this->origination_time,
            "tillNumber" => $this->till_number,
            "system" => $this->system,
            "resourceStatus" => $this->resource_status,
            "updatedAt" => $this->updated_at,
            "createdAt" => $this->created_at,
        ];
    }
}
