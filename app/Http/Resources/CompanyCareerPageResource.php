<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CompanyCareerPageResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'url' => $this->url,
            'source' => $this->source,
            'companyName' => $this->company_name,
            'lastFetchedAt' => $this->last_fetched_at,
            'lastError' => $this->last_error,
        ];
    }
}
