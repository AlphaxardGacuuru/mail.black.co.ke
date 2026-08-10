<?php

namespace App\Http\Resources;

use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class KopokopoRecipientResource extends JsonResource
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
            "userId" => $this->user_id,
            "destinationReference" => $this->destination_reference,
            "type" => $this->type,
            "firstName" => $this->first_name,
            "lastName" => $this->last_name,
            "email" => $this->email,
            "phoneNumber" => $this->phone_number,
            "accountName" => $this->account_name,
            "accountNumber" => $this->account_number,
            "tillName" => $this->till_name,
            "tillNumber" => $this->till_number,
            "paybillName" => $this->paybill_name,
            "paybillNumber" => $this->paybill_number,
            "paybillAccountNumber" => $this->paybill_account_number,
            "description" => $this->description,
        ];
    }
}
