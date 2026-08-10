<?php

namespace App\Http\Resources;

use Carbon\Carbon;
use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class SupportTicketResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @param  Request  $request
     * @return array|Arrayable|\JsonSerializable
     */
    public function toArray($request)
    {
        $canDelete = Carbon::parse($this->getRawOriginal('created_at'))
            ->gt(now()->subHour());

        return [
            "id" => $this->id,
            "number" => $this->number,
            "userUnitId" => $this->user_unit_id,
            "userId" => $this->userUnit->user->id,
            "tenantName" => $this->userUnit->user->name,
            "tenantAvatar" => $this->userUnit->user->avatar,
            "unitName" => $this->userUnit->unit->name,
            "propertyName" => $this->userUnit->unit->property->name,
            "category" => $this->category,
            "subject" => $this->subject,
            "priority" => $this->priority,
            "description" => $this->description,
            "attachments" => $this->attachments,
            "status" => $this->status,
            "canDelete" => $canDelete,
            "createdAt" => $this->created_at,
        ];
    }
}
