<?php

namespace App\Concerns;

use App\Models\User;
use Illuminate\Validation\Rule;

trait MailgunCredentialValidationRules
{
    /**
     * Get the validation rules used to validate a user's own Mailgun credentials.
     *
     * @return array<string, array<int, \Illuminate\Contracts\Validation\Rule|array<mixed>|string>>
     */
    protected function mailgunCredentialRules(int|string|null $userId = null): array
    {
        return [
            'mailbox_address' => $this->mailboxAddressRules($userId),
            'mailgun_domain' => $this->mailgunDomainRules(),
            'mailgun_api_key' => $this->mailgunApiKeyRules(),
            'mailgun_endpoint' => $this->mailgunEndpointRules(),
        ];
    }

    /**
     * Get the validation rules used to validate mailbox addresses.
     *
     * @return array<int, \Illuminate\Contracts\Validation\Rule|array<mixed>|string>
     */
    protected function mailboxAddressRules(int|string|null $userId = null): array
    {
        return [
            'nullable',
            'string',
            'email',
            'max:255',
            $userId === null
                ? Rule::unique(User::class)
                : Rule::unique(User::class)->ignore($userId),
        ];
    }

    /**
     * Get the validation rules used to validate a Mailgun sending domain.
     *
     * @return array<int, string>
     */
    protected function mailgunDomainRules(): array
    {
        return ['nullable', 'string', 'max:255'];
    }

    /**
     * Get the validation rules used to validate a Mailgun API key.
     *
     * A blank value means "keep the currently stored key" — it is never used
     * to overwrite an existing key with an empty string.
     *
     * @return array<int, string>
     */
    protected function mailgunApiKeyRules(): array
    {
        return ['nullable', 'string', 'max:255'];
    }

    /**
     * Get the validation rules used to validate a Mailgun regional API endpoint.
     *
     * @return array<int, \Illuminate\Contracts\Validation\Rule|array<mixed>|string>
     */
    protected function mailgunEndpointRules(): array
    {
        return ['nullable', 'string', Rule::in(['api.mailgun.net', 'api.eu.mailgun.net'])];
    }
}
