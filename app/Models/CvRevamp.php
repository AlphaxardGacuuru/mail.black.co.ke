<?php

namespace App\Models;

use App\Enums\CvRevampStatus;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * The user's CV rewritten by the AI. Each section keeps what the CV said
 * next to the rewrite, and the user picks which one to keep:
 *
 *   document: {name, headline, contact: string[], sections: [{key, heading,
 *              original: entry|null, revamped: entry, why, keep: "revamped"|"original"}]}
 *   entry:    {title, subtitle, period, text, bullets: string[]}
 *   questions: [{key, section, question, answer}] facts the AI asked for
 *              (usually numbers) to make the CV stronger.
 *
 * Once the user chooses to use it, it's drawn as a PDF (sent to employers)
 * and a Word file (for them to edit).
 */
class CvRevamp extends Model
{
    use HasUuids;

    protected $guarded = [];

    protected $casts = [
        'status' => CvRevampStatus::class,
        'document' => 'array',
        'questions' => 'array',
        'used_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::deleting(fn (CvRevamp $revamp) => $revamp->deleteFiles());
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Whether applications send this CV instead of the uploaded one.
     */
    public function inUse(): bool
    {
        return $this->used_at !== null
            && $this->pdf_path !== null
            && Storage::disk('local')->exists($this->pdf_path);
    }

    /**
     * The CV as it's drawn: each section with the version the user kept.
     *
     * @return array{name: string, headline: string, contact: array<int, string>, sections: array<int, array{key: string, heading: string, entry: array<string, mixed>}>}
     */
    public function chosen(): array
    {
        $document = $this->document ?? [];

        return [
            'name' => (string) ($document['name'] ?? ''),
            'headline' => (string) ($document['headline'] ?? ''),
            'contact' => $document['contact'] ?? [],
            'sections' => collect($document['sections'] ?? [])
                ->map(fn (array $section) => [
                    'key' => $section['key'],
                    'heading' => $section['heading'],
                    'entry' => ($section['keep'] ?? 'revamped') === 'original' && $section['original']
                        ? $section['original']
                        : $section['revamped'],
                ])
                ->all(),
        ];
    }

    /**
     * The name employers see: "Wanjiku Muhandi CV.pdf".
     */
    public function fileName(string $extension): string
    {
        return Str::of($this->document['name'] ?? '')
            ->replaceMatches('/[^\p{L}\p{N} .\'-]+/u', '')
            ->squish()
            ->whenEmpty(fn () => Str::of('My'))
            ->append(" CV.{$extension}")
            ->toString();
    }

    public function deleteFiles(): void
    {
        Storage::disk('local')->delete(array_filter([$this->pdf_path, $this->docx_path]));
    }
}
