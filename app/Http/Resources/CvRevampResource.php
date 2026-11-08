<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\URL;

class CvRevampResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $document = $this->document;

        return [
            'status' => $this->status->value,
            'error' => $this->error,
            'inUse' => $this->inUse(),
            'usedAt' => $this->used_at,
            'updatedAt' => $this->updated_at,
            'name' => $document['name'] ?? null,
            'headline' => $document['headline'] ?? null,
            'contact' => $document['contact'] ?? [],
            'sections' => collect($document['sections'] ?? [])
                ->map(fn (array $section) => [
                    'key' => $section['key'],
                    'heading' => $section['heading'],
                    'original' => $section['original'],
                    'revamped' => $section['revamped'],
                    'why' => $section['why'] ?: null,
                    'keep' => $section['keep'],
                    'edited' => $section['edited'] ?? false,
                ])
                ->all(),
            'questions' => collect($this->questions ?? [])
                ->map(fn (array $question) => collect($question)
                    ->only(['key', 'section', 'question', 'answer', 'used'])
                    ->all())
                ->all(),
            // The CV as it stands, drawn on request. Signed, so it opens in
            // a new tab without the bearer token.
            'downloads' => $document
                ? collect(['pdf', 'docx'])
                    ->mapWithKeys(fn (string $format) => [$format => URL::temporarySignedRoute('cv-revamps.download', now()->addHours(2), [
                        'id' => $this->id,
                        'format' => $format,
                        'v' => $this->updated_at?->timestamp,
                    ])])
                    ->all()
                : null,
        ];
    }
}
