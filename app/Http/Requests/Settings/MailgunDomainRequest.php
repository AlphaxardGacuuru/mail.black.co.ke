<?php

namespace App\Http\Requests\Settings;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class MailgunDomainRequest extends FormRequest
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
        $domainId = $this->route('domain');

        return [
            'domain' => [
                'required', 'string', 'max:255',
                Rule::unique('mailgun_domains', 'domain')
                    ->where('user_id', $this->user()?->id)
                    ->ignore($domainId),
            ],
            'api_key' => [$this->isMethod('post') ? 'required' : 'nullable', 'string', 'max:255'],
            'endpoint' => ['nullable', 'string', Rule::in(['api.mailgun.net', 'api.eu.mailgun.net'])],
        ];
    }
}
