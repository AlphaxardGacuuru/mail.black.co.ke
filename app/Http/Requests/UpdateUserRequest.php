<?php

namespace App\Http\Requests;

use App\Concerns\PasswordValidationRules;
use Illuminate\Foundation\Http\FormRequest;

class UpdateUserRequest extends FormRequest
{
    use PasswordValidationRules;

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $id = $this->route('id');
        $rules = [
            'name' => [
                'nullable',
                'string',
                'max:255',
            ],
            'email' => [
                'nullable',
                'string',
                'email',
                'max:255',
                'unique:users,email,'.$id,
            ],
            'phone' => [
                'nullable',
                'unique:users,phone,'.$id,
                'regex:/^\d{10}$/',
            ],
        ];
        if ($this->filled('password')) {
            $rules['password'] = $this->passwordRules();
        }

        return $rules;
    }

    public function messages(): array
    {
        return [
            'phone.regex' => 'Please enter a valid 10-digit phone number',
        ];
    }
}
