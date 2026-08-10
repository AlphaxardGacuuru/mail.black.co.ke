<?php

namespace App\Http\Resources;

use Carbon\Carbon;
use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class TaskResource extends JsonResource
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
            "number" => $this->number,
            "title" => $this->title,
            "description" => $this->description,
            "createdById" => $this->createdBy->id,
            "createdByName" => $this->createdBy->name,
            "assignedToId" => $this->assignedTo->id,
            "assignedToName" => $this->assignedTo->user->name,
            "startDate" => $this->start_date,
            "endDate" => $this->end_date,
            "startDateRaw" => Carbon::parse($this->start_date)->format("Y-m-d\\TH:i"),
            "endDateRaw" => Carbon::parse($this->end_date)->format("Y-m-d\\TH:i"),
            "priority" => $this->priority,
            "projectId" => $this->project_id,
            "totalComments" => $this->total_comments,
            "position" => $this->position,
            "new" => $this->new,
            "currentStageId" => $this->currentStage()->stage_id,
            "updatedAt" => $this->updated_at,
            "createdAt" => $this->created_at,
        ];
    }
}
