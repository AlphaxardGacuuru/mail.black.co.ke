<?php

namespace App\Http\Resources;

use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class UnitStatementResource extends JsonResource
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
            "userUnitId" => $this->user_unit_id,
            "tenantId" => $this->userUnit ?
            $this->userUnit->user_id :
            $this->invoice->userUnit->user_id,
            "tenantName" => $this->userUnit ?
            $this->userUnit->user->name :
            $this->invoice->userUnit->user->name,
            "tenantPhone" => $this->userUnit ?
            $this->userUnit->user->phone :
            $this->invoice->userUnit->user->phone,
            "tenantEmail" => $this->userUnit ?
            $this->userUnit->user->email :
            $this->invoice->userUnit->user->email,
            "unitId" => $this->userUnit ?
            $this->userUnit->unit_id :
            $this->invoice->userUnit->unit_id,
            "unitName" => $this->userUnit ?
            $this->userUnit->unit->name :
            $this->invoice->userUnit->unit->name,
            "type" => $this->type,
            "credit" => number_format($this->credit ?? 0),
            "debit" => number_format($this->debit ?? 0),
            "balance" => number_format($this->balance ?? 0),
            "month" => $this->month ? $this->month : $this->invoice->month,
            "year" => $this->year ? $this->year : $this->invoice->year,
            "updatedAt" => $this->updatedAt,
            "createdAt" => $this->createdAt,
        ];
    }
}
