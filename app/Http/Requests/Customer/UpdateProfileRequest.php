<?php

namespace App\Http\Requests\Customer;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateProfileRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $userId = $this->user()->id;

        return [
            'first_name' => ['required', 'string', 'max:100'],
            'last_name' => ['required', 'string', 'max:100'],
            'email' => [
                'required', 'string', 'email:rfc', 'max:255',
                Rule::unique('users', 'email')->ignore($userId)->whereNull('deleted_at'),
            ],
            'contact_number' => ['required', 'string', 'max:32', 'regex:/^[0-9+\-\s()]{7,32}$/'],

            // Optional password change — only validated when supplied.
            'password' => ['nullable', 'string', 'min:8', 'confirmed'],

            'profile_photo' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048'],
            'remove_photo' => ['nullable', 'boolean'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'contact_number.regex' => 'Please enter a valid contact number.',
            'profile_photo.image' => 'Your profile picture must be an image.',
            'profile_photo.max' => 'Your profile picture may not be larger than 2 MB.',
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
