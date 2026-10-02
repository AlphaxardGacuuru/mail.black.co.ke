<?php

namespace App\Http\Services;

use App\Models\MailgunDomain;
use Illuminate\Support\Str;
use Mailgun\Exception\HttpClientException;
use Mailgun\Mailgun;

class MailgunMailboxService
{
    /**
     * Provision a mailbox under the given Mailgun domain, generating a
     * random password for it. If the mailbox already exists on Mailgun's
     * side, this is treated as success rather than an error — the caller
     * just wants the local record to exist either way.
     *
     * @return string the generated password, to store alongside the account
     */
    public function createMailbox(MailgunDomain $domain, string $mailboxAddress): string
    {
        $localPart = Str::before($mailboxAddress, '@');
        $password = Str::random(24);

        $client = Mailgun::create($domain->api_key, "https://{$domain->endpoint}");

        try {
            $client->mailboxes()->create($domain->domain, [
                'mailbox' => $localPart,
                'password' => $password,
            ]);
        } catch (HttpClientException $exception) {
            if (! $this->mailboxAlreadyExists($exception)) {
                throw $exception;
            }
        }

        return $password;
    }

    private function mailboxAlreadyExists(HttpClientException $exception): bool
    {
        if ($exception->getResponseCode() !== 400) {
            return false;
        }

        $message = $exception->getResponseBody()['message'] ?? '';

        return str_contains(strtolower($message), 'already exists');
    }
}
