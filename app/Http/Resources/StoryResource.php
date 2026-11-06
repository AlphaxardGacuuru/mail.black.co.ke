<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class StoryResource extends JsonResource
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
            // Field news is personal to whoever sees it.
            'name' => $this->career_field ? 'News for you' : $this->user->name,
            'avatar' => $this->user->avatar,
            'verified' => (bool) $this->user->verified,
            'media' => $this->media,
            'text' => $this->text,
            'hasSeen' => $this->hasSeen($request->user()?->id),
            'createdAt' => $this->created_at,
        ];
    }
}
