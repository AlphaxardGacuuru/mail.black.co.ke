<?php

namespace App\Http\Requests\Settings;

use App\Concerns\MailgunCredentialValidationRules;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class MailgunCredentialsUpdateRequest extends FormRequest
{
    use MailgunCredentialValidationRules;

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return $this->mailgunCredentialRules($this->user()->id);
    }
}
