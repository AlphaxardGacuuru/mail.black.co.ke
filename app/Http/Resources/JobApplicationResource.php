<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class JobApplicationResource extends JsonResource
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
            'status' => $this->status->value,
            'matchScore' => $this->match_score,
            'matchReason' => $this->match_reason,
            'subject' => $this->subject,
            'coverLetter' => $this->cover_letter,
            'mailThreadId' => $this->mail_thread_id,
            'appliedAt' => $this->applied_at,
            'repliedAt' => $this->replied_at,
            'createdAt' => $this->created_at,
            'posting' => [
                'title' => $this->posting?->title,
                'company' => $this->posting?->company,
                'location' => $this->posting?->location,
                'url' => $this->posting?->url,
                'applyEmail' => $this->posting?->apply_email,
                'source' => $this->posting?->source,
                'sourceName' => $this->posting?->sourceName(),
                'postedAt' => $this->posting?->posted_at,
            ],
        ];
    }
}
