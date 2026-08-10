<?php

namespace App\Http\Resources;

use Cknow\Money\Money;
use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class LoanResource extends JsonResource
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
            'id' => $this->id,
            'userId' => $this->user_id,
            'applicantName' => $this->user->name,
            "number" => $this->number,
            'interest' => $this->interest,
            'duration' => $this->duration,
            'amount' => Money::KES($this->amount),
            'balance' => Money::KES($this->balance),
            'due_date' => $this->due_date,
            'disbursed_at' => $this->disbursed_at,
            'status' => $this->status,
            'updated_at' => $this->updated_at,
            'created_at' => $this->created_at,
        ];
    }
}
