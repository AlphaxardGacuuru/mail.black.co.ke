<?php

namespace App\Http\Requests\Settings;

use App\Models\MailgunDomain;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class MailgunAccountRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $accountId = $this->route('account');

        return [
            // Immutable after creation: the mailbox is provisioned on Mailgun
            // for this exact address, and editing it here wouldn't rename
            // anything on Mailgun's side.
            'mailbox_address' => [
                $this->isMethod('post') ? 'required' : 'sometimes',
                'email', 'max:255',
                Rule::unique('mailgun_accounts', 'mailbox_address')->ignore($accountId),
            ],
            'mailgun_domain_id' => [
                $this->isMethod('post') ? 'required' : 'sometimes',
                Rule::exists('mailgun_domains', 'id')->where('user_id', $this->user()?->id),
            ],
            'signature' => ['nullable', 'string', 'max:10000'],
            'mail_from_name' => ['nullable', 'string', 'max:255'],
        ];
    }

    /**
     * The mailbox address has to actually live on the selected domain —
     * Mailgun would refuse to provision it under a different one anyway.
     */
    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            $domainId = $this->input('mailgun_domain_id');
            $mailboxAddress = $this->input('mailbox_address');

            if (! $domainId || ! $mailboxAddress || ! str_contains($mailboxAddress, '@')) {
                return;
            }

            $domain = MailgunDomain::query()->find($domainId);

            if ($domain && Str::lower(Str::after($mailboxAddress, '@')) !== Str::lower($domain->domain)) {
                $validator->errors()->add(
                    'mailbox_address',
                    "The mail address must belong to the {$domain->domain} domain."
                );
            }
        });
    }
}
