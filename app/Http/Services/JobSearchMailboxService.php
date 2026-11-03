<?php

namespace App\Http\Services;

use App\Models\MailgunAccount;
use App\Models\MailgunDomain;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * The address a user without a mailbox applies for jobs from, on the app's
 * own domain: firstnamelastname@ by default. An address is never given out
 * twice, since its Mailgun credential is never deleted.
 */
class JobSearchMailboxService
{
    public function __construct(protected MailgunMailboxService $mailboxService)
    {
        //
    }

    /**
     * Free addresses for the user to choose from, best first: their name
     * as one word, then other forms of it, then numbered.
     *
     * @return array<int, string>
     */
    public function suggestions(User $user): array
    {
        $domain = MailgunDomain::platform();
        $candidates = $this
            ->localParts($user)
            ->map(fn (string $localPart) => "{$localPart}@{$domain->domain}");

        $taken = MailgunAccount::query()
            ->whereIn('mailbox_address', $candidates)
            ->pluck('mailbox_address')
            ->map(fn (string $address) => Str::lower($address))
            ->merge($this->mailboxService->mailboxAddresses($domain));

        return $candidates
            ->diff($taken)
            ->take(config('job_search.mailbox.suggestions'))
            ->values()
            ->all();
    }

    /**
     * Create the chosen address on Mailgun and as the user's mail account.
     */
    public function create(User $user, string $address): MailgunAccount
    {
        $address = Str::lower(trim($address));

        if ($user->mailgunAccounts()->exists()) {
            throw ValidationException::withMessages(['address' => ['You already have a mail account to apply from.']]);
        }

        if (! $user->jobSeekerProfile?->cv_path) {
            throw ValidationException::withMessages(['address' => ['Upload your CV first.']]);
        }

        if (! in_array($address, $this->suggestions($user), true)) {
            throw ValidationException::withMessages(['address' => ['That address is no longer available. Pick another.']]);
        }

        $domain = MailgunDomain::platform();
        $password = $this->mailboxService->createNewMailbox($domain, $address)
            ?? throw ValidationException::withMessages(['address' => ['That address was just taken. Pick another.']]);

        return DB::transaction(function () use ($user, $domain, $address, $password) {
            $account = $user
                ->mailgunAccounts()
                ->create([
                    'mailgun_domain_id' => $domain->id,
                    'mailbox_address' => $address,
                    'mail_from_name' => $user->name,
                    'mailgun_smtp_password' => $password,
                ]);

            $user->update(['active_mailgun_account_id' => $account->id]);

            return $account;
        });
    }

    /**
     * Local parts made from the user's name (else their login email), in
     * order of preference, within the length limits and never reserved.
     *
     * @return Collection<int, string>
     */
    protected function localParts(User $user): Collection
    {
        $words = $this->words($user->name)
            ->whenEmpty(fn () => $this->words(Str::before((string) $user->email, '@')))
            ->whenEmpty(fn () => collect(['user']));

        $first = $words->first();
        $last = $words->count() > 1 ? $words->last() : null;
        $maxLength = config('job_search.mailbox.max_length');
        $base = Str::substr($first.$last, 0, $maxLength - 2);

        return collect([
            $first.$last,
            $last ? "{$first}.{$last}" : null,
            $last ? Str::substr($first, 0, 1).$last : null,
        ])
            ->filter()
            ->map(fn (string $localPart) => Str::substr($localPart, 0, $maxLength))
            ->merge(collect(range(2, 99))->map(fn (int $number) => $base.$number))
            ->reject(fn (string $localPart) => Str::length($localPart) < config('job_search.mailbox.min_length')
                || in_array($localPart, config('job_search.mailbox.reserved'), true))
            ->unique()
            ->values();
    }

    /**
     * @return Collection<int, string>
     */
    protected function words(string $text): Collection
    {
        return Str::of($text)
            ->ascii()
            ->lower()
            ->replaceMatches('/[^a-z\s]/', '')
            ->squish()
            ->explode(' ')
            ->filter()
            ->values();
    }
}
