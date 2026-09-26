<?php

namespace App\Http\Requests\Auth;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class RegisterRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'first_name' => ['required', 'string', 'max:100'],
            'last_name' => ['required', 'string', 'max:100'],
            'email' => [
                'required', 'string', 'email:rfc', 'max:255',
                Rule::unique('users', 'email')->whereNull('deleted_at'),
            ],
            'contact_number' => ['required', 'string', 'max:32', 'regex:/^[0-9+\-\s()]{7,32}$/'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
            'terms' => ['accepted'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'contact_number.regex' => 'Please enter a valid contact number.',
            'terms.accepted' => 'You must agree to the Terms and Conditions to register.',
            'password.confirmed' => 'The password confirmation does not match.',
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'email' => strtolower(trim((string) $this->input('email'))),
            'contact_number' => trim((string) $this->input('contact_number')),
        ]);
    }
}
