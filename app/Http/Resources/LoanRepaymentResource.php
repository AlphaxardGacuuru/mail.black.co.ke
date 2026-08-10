<?php

namespace App\Http\Resources;

use Cknow\Money\Money;
use Illuminate\Http\Resources\Json\JsonResource;

class LoanRepaymentResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     */
    public function toArray($request): array
    {
        return [
            'id' => $this->id,
            'loanId' => $this->loan_id,
            'loanStatus' => $this->loan?->status,
            'applicantName' => $this->loan?->user?->name,
            'amount' => Money::KES((int) $this->amount),
            'source' => $this->source,
            'sourceId' => $this->source_id,
            'sourceType' => $this->source_type,
            'transactionReference' => $this->transaction_reference,
            'updated_at' => $this->updated_at,
            'created_at' => $this->created_at,
        ];
    }
}
