<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class MailgunAccountResource extends JsonResource
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
            'mailboxAddress' => $this->mailbox_address,
            'mailFromName' => $this->mail_from_name,
            'mailgunDomainId' => $this->mailgun_domain_id,
            'mailgunDomain' => $this->mailgunDomain?->domain,
            'mailgunEndpoint' => $this->mailgunDomain?->endpoint,
            'signature' => $this->signature,
            'avatar' => $this->avatar,
            'isActive' => $this->id === $request->user()?->active_mailgun_account_id,
        ];
    }
}
