<?php

namespace App\Http\Resources;

use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CardTransactionResource extends JsonResource
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
            "currency" => $this->currency,
            "amount" => $this->amount,
            "chargedAmount" => $this->charged_amount,
            "chargeResponseCode" => $this->charge_response_code,
            "chargeResponseMessage" => $this->charge_response_message,
            "flwRef" => $this->flw_ref,
            "txRef" => $this->tx_ref,
            "status" => $this->status,
            "transactionId" => $this->transaction_id,
            "transactionCreatedAt" => $this->transaction_created_at,
        ];
    }
}
