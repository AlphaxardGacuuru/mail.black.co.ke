<?php

namespace App\Http\Resources;

use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class LoanStatementResource extends JsonResource
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
            "period" => data_get($this->resource, "period"),
            "month" => data_get($this->resource, "month"),
            "year" => data_get($this->resource, "year"),
            "expectedDate" => data_get($this->resource, "expectedDate"),
            "expectedAmount" => data_get($this->resource, "expectedAmount"),
            "principal" => data_get($this->resource, "principal"),
            "interest" => data_get($this->resource, "interest"),
            "paidAmount" => data_get($this->resource, "paidAmount"),
            "remainingAmount" => data_get($this->resource, "remainingAmount"),
            "status" => data_get($this->resource, "status"),
            "paidAt" => data_get($this->resource, "paidAt"),
            "balance" => data_get($this->resource, "balance"),
        ];
    }
}
