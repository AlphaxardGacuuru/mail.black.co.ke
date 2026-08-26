<?php

namespace App\Http\Requests\Settings;

use App\Concerns\MailgunCredentialValidationRules;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class MailgunAccountRequest extends FormRequest
{
    use MailgunCredentialValidationRules;

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
            'mailbox_address' => [
                'required', 'email', 'max:255',
                Rule::unique('mailgun_accounts', 'mailbox_address')->ignore($accountId),
            ],
            'mailgun_domain' => ['required', 'string', 'max:255'],
            'mailgun_api_key' => ['nullable', 'string', 'max:255'],
            'mailgun_endpoint' => $this->mailgunEndpointRules(),
            'signature' => ['nullable', 'string', 'max:10000'],
        ];
    }
}
