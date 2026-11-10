<?php

namespace App\Http\Services;

use App\Models\MailgunAccount;
use App\Models\MailgunDomain;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
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
     * Removes a mailbox from the app and deletes it on Mailgun. The user's
     * active mailbox moves to another of theirs. A job search address is
     * never removed, so it's never given to anyone else.
     */
    public function removeAccount(MailgunAccount $account): void
    {
        if ($account->isPlatform()) {
            throw ValidationException::withMessages(['account' => ['A job search address can\'t be removed.']]);
        }

        if ($account->mailgunDomain) {
            try {
                $this->deleteMailbox($account->mailgunDomain, $account->mailbox_address);
            } catch (HttpClientException $exception) {
                throw ValidationException::withMessages([
                    'account' => ["Mailgun couldn't delete {$account->mailbox_address}: {$exception->getMessage()}"],
                ]);
            }
        }

        $account->delete();

        $account->user()
            ->where('active_mailgun_account_id', $account->id)
            ->update([
                'active_mailgun_account_id' => MailgunAccount::query()
                    ->where('user_id', $account->user_id)
                    ->value('id'),
            ]);
    }

    /**
     * Deletes the address's mailbox (its SMTP credential) on Mailgun. One
     * that's already gone counts as deleted.
     */
    public function deleteMailbox(MailgunDomain $domain, string $mailboxAddress): void
    {
        try {
            $this->client($domain)
                ->domains()
                ->deleteCredential($domain->domain, Str::lower($mailboxAddress));
        } catch (HttpClientException $exception) {
            if ($exception->getResponseCode() !== 404) {
                throw $exception;
            }
        }
    }

    /**
     * Every address with a mailbox on the domain, lowercased. Job search
     * addresses are never deleted, so this includes ones no longer in use.
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
