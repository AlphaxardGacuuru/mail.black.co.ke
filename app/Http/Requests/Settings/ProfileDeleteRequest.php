<?php

namespace App\Http\Requests\Settings;

use App\Concerns\PasswordValidationRules;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ProfileDeleteRequest extends FormRequest
{
    use PasswordValidationRules;

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        // Google sign-ups never saw their password, so they type their
        // email address instead.
        if ($this->user()->google_id) {
            return [
                'password' => ['nullable', 'string', 'current_password'],
                'email' => ['required_without:password', 'nullable', 'string', Rule::in([$this->user()->email])],
            ];
        }

        return [
            'password' => $this->currentPasswordRules(),
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'email.in' => 'Type your email address exactly as shown.',
            'email.required_without' => 'Type your email address to confirm.',
        ];
    }
}
