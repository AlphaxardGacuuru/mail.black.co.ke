<?php

namespace App\Http\Resources;

use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class TaskCommentResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @param  Request  $request
     * @return array|Arrayable|\JsonSerializable
     */
    public function toArray($request)
    {
        // Check if user is logged in
        $userId = auth('sanctum')->user()
            ? auth('sanctum')->user()->id
            : 0;

        return [
            "id" => $this->id,
            "taskId" => $this->task_id,
            "text" => $this->text,
            "userId" => $this->createdBy->id,
            "userAvatar" => $this->createdBy->avatar,
            "userName" => $this->createdBy->name,
            "likes" => $this->total_likes,
            // "hasLiked" => $this->hasLiked($userId),
            "updatedAt" => $this->updated_at,
            "createdAt" => $this->created_at,
        ];
    }
}
