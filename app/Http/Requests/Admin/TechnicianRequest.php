<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Validation for the technician form: a name, an optional photo and an Active
 * switch. Nothing else — the technician is who serves the customer, and any
 * further detail would be a field the booking picker has no room to show.
 */
class TechnicianRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user('admin') !== null;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:120'],
            // JPG/PNG/WEBP at 2 MB. A technician photo is a headshot shown small
            // in a 7rem circle on the list and the booking form, so the smaller
            // ceiling stands even though the customer profile photo was raised
            // to 10 MB — that one is a full-size avatar people upload straight
            // from a phone camera. Change them independently.
            'photo' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048'],
            'remove_photo' => ['nullable', 'boolean'],
            'is_active' => ['nullable', 'boolean'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'photo.image' => 'The technician photo must be an image.',
            'photo.mimes' => 'The technician photo must be a JPG, PNG or WEBP file.',
            'photo.max' => 'The technician photo may not be larger than 2 MB.',
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'name' => trim((string) $this->input('name')),
        ]);
    }
}
