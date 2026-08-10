<?php

namespace App\Http\Resources;

use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class WaterReadingResource extends JsonResource
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
            "tenantName" => $this->userUnit->user->name,
            "unitId" => $this->userUnit->unit->id,
            "unitName" => $this->userUnit->unit->name,
            "type" => $this->type,
            "reading" => $this->reading,
            "usage" => $this->usage,
            "bill" => number_format($this->bill),
            "month" => $this->month,
            "year" => $this->year,
            "updated_at" => $this->updated_at,
            "created_at" => $this->created_at,
        ];
    }
}
