<?php

namespace App\Http\Services;

use App\Models\MailgunDomain;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Mailgun\Exception\HttpClientException;
use Mailgun\Mailgun;
use Mailgun\Model\Domain\CredentialResponseItem;

class MailgunMailboxService
{
    /**
     * Provision a mailbox under the given Mailgun domain, generating a
     * random password for it. A Mailgun mailbox is an SMTP credential, so
     * this goes through the credentials API (the old mailboxes endpoint now
     * 404s). If the credential already exists on Mailgun's side, this is
     * treated as success rather than an error, since the caller just wants
     * the local record to exist either way.
     *
     * @return string the generated password, to store alongside the account
     */
    public function createMailbox(MailgunDomain $domain, string $mailboxAddress): string
    {
        return $this->createNewMailbox($domain, $mailboxAddress) ?? Str::random(24);
    }

    /**
     * Like createMailbox, but an address that already exists on Mailgun is
     * someone else's: returns null instead of a password.
     */
    public function createNewMailbox(MailgunDomain $domain, string $mailboxAddress): ?string
    {
        $password = Str::random(24);

        try {
            $this->client($domain)
                ->domains()
                ->createCredential($domain->domain, Str::lower($mailboxAddress), $password);
        } catch (HttpClientException $exception) {
            if ($this->mailboxAlreadyExists($exception)) {
                return null;
            }

            throw $exception;
        }

        return $password;
    }

    /**
     * Every address with a mailbox on the domain, lowercased. Credentials
     * are never deleted, so this includes addresses no longer in use.
     *
     * @return Collection<int, string>
     */
    public function mailboxAddresses(MailgunDomain $domain): Collection
    {
        $addresses = collect();
        $skip = 0;

        do {
            $page = collect($this->client($domain)
                ->domains()
                ->credentials($domain->domain, 100, $skip)
                ->getCredentials())
                ->map(fn (CredentialResponseItem $credential) => Str::lower((string) $credential->getLogin()));

            $addresses = $addresses->merge($page);
            $skip += 100;
        } while ($page->count() === 100);

        return $addresses;
    }

    protected function client(MailgunDomain $domain): Mailgun
    {
        return Mailgun::create($domain->api_key,"https://{$domain->endpoint}");
    }

    private function mailboxAlreadyExists(HttpClientException $exception): bool
    {
        if (! in_array($exception->getResponseCode(), [400, 409], true)) {
            return false;
        }

        $message = $exception->getResponseBody()['message'] ?? '';

        return str_contains(strtolower($message), 'already exist');
    }
}
