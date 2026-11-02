<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class JobSeekerProfileResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'autoApply' => $this->auto_apply,
            'mode' => $this->mode,
            'consentedAt' => $this->consented_at,
            'mailgunAccountId' => $this->mailgun_account_id,
            'cvName' => $this->cv_original_name,
            'cvStatus' => match (true) {
                ! $this->cv_path => 'missing',
                $this->parsed_at === null => 'reading',
                default => 'ready',
            },
            'profile' => $this->profile,
            'coverLetter' => $this->cover_letter,
            'coverLetterMode' => $this->cover_letter_mode ?? 'tailor',
            'preferences' => [
                'keywords' => $this->preference('keywords'),
                'locations' => $this->preference('locations'),
                'remoteOk' => (bool) ($this->preferences['remote_ok'] ?? false),
                'excludedCompanies' => $this->preference('excluded_companies'),
            ],
            'dailyLimit' => $this->daily_limit,
            'minMatchScore' => $this->min_match_score,
            'pausedReason' => $this->paused_reason,
            'lastDiscoveryAt' => $this->last_discovery_at,
            'lastAlertAt' => $this->last_alert_at,
            'lastAlertSource' => $this->last_alert_source,
        ];
    }
}
