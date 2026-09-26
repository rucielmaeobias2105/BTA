<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class ServiceRequest extends FormRequest
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
        $serviceId = $this->route('service')?->id;

        return [
            'name' => ['required', 'string', 'max:150'],
            'slug' => [
                'nullable', 'string', 'max:180', 'alpha_dash',
                Rule::unique('services', 'slug')->ignore($serviceId)->whereNull('deleted_at'),
            ],
            'category' => ['required', 'string', 'max:80'],
            'price' => ['required', 'numeric', 'min:0', 'max:999999'],
            'duration_minutes' => ['required', 'integer', 'min:5', 'max:1440'],
            'description' => ['nullable', 'string', 'max:3000'],
            'photo' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:3072'],
            'is_active' => ['nullable', 'boolean'],
            'is_featured' => ['nullable', 'boolean'],

            // Variants: short/long hair style pricing.
            'variants' => ['nullable', 'array'],
            'variants.*.id' => ['nullable', 'integer'],
            'variants.*.name' => ['required_with:variants.*', 'string', 'max:100'],
            'variants.*.price' => ['required_with:variants.*', 'numeric', 'min:0', 'max:999999'],
            'variants.*.duration_minutes' => ['nullable', 'integer', 'min:5', 'max:1440'],
            'variants.*.is_default' => ['nullable', 'boolean'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'photo.image' => 'The service photo must be an image.',
            'photo.max' => 'The service photo may not be larger than 3 MB.',
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'slug' => $this->filled('slug')
                ? Str::slug($this->input('slug'))
                : Str::slug((string) $this->input('name')),
        ]);
    }
}
