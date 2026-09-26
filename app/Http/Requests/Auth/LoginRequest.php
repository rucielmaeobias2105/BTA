<?php

namespace App\Http\Requests\Auth;

use Illuminate\Foundation\Http\FormRequest;

class LoginRequest extends FormRequest
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
            'login' => ['required', 'string', 'max:255'],
            'password' => ['required', 'string'],
            'remember' => ['nullable', 'boolean'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'login.required' => 'Please enter your username or email address.',
        ];
    }

    protected function prepareForValidation(): void
    {
        if ($this->filled('login')) {
            $this->merge(['login' => strtolower(trim((string) $this->input('login')))]);
        }
    }
}
