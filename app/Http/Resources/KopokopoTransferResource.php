<?php

namespace App\Http\Resources;

use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class KopokopoTransferResource extends JsonResource
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
            "user" => $this->user->name,
            "kopokopoId" => $this->kopokopo_id,
            "kopokopoCreatedAt" => $this->kopokopo_created_at,
            "amount" => number_format($this->amount),
            "currency" => $this->currency,
            "transferBatches" => $this->transfer_batches,
            "metadata" => $this->metadata,
            "updateAt" => $this->update_at,
            "createdAt" => $this->created_at,
        ];
    }
}
