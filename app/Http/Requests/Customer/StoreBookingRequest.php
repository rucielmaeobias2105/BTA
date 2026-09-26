<?php

namespace App\Http\Requests\Customer;

use App\Services\BookingAvailability;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreBookingRequest extends FormRequest
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
            'services' => ['required', 'array', 'min:1', 'max:10'],
            'services.*.service_id' => ['required', 'integer', Rule::exists('services', 'id')->whereNull('deleted_at')],
            'services.*.service_variant_id' => ['nullable', 'integer', Rule::exists('service_variants', 'id')],
            'services.*.quantity' => ['required', 'integer', 'min:1', 'max:10'],

            'customer_name' => ['required', 'string', 'max:150'],
            'customer_phone' => ['required', 'string', 'max:32', 'regex:/^[0-9+\-\s()]{7,32}$/'],

            'preferred_date' => ['required', 'date'],
            'preferred_time' => ['required', 'date_format:H:i'],

            'allergies' => ['nullable', 'string', 'max:2000'],
            'allergies_other' => ['nullable', 'string', 'max:2000'],
            'last_services_availed' => ['nullable', 'string', 'max:2000'],
            'last_services_availed_note' => ['nullable', 'string', 'max:2000'],
            'preferred_stylist_id' => ['nullable', 'integer', Rule::exists('admins', 'id')->whereNull('deleted_at')],
            'special_request' => ['nullable', 'string', 'max:2000'],

            'down_payment_reference' => ['nullable', 'string', 'max:64'],

            'agree_terms' => ['accepted'],
        ];
    }

    /**
     * Fold the checkbox lists and free-text notes into the columns we store.
     */
    protected function prepareForValidation(): void
    {
        $this->merge([
            'customer_name' => trim((string) $this->input('customer_name')),
            'customer_phone' => trim((string) $this->input('customer_phone')),
            'down_payment_reference' => $this->filled('down_payment_reference')
                ? strtoupper(trim((string) $this->input('down_payment_reference')))
                : null,
            'allergies' => $this->mergeNotes('allergies', 'allergies_other'),
            'last_services_availed' => $this->mergeNotes('last_services_availed', 'last_services_availed_note'),
        ]);
    }

    /**
     * Combine "Latex, Nickel" style checkbox input with a free-text note.
     */
    protected function mergeNotes(string $field, string $noteField): ?string
    {
        $checked = collect((array) $this->input($field))
            ->map(fn ($value) => trim((string) $value))
            ->filter()
            ->unique();

        $note = trim((string) $this->input($noteField));

        $parts = $checked->all();

        // "None known" alongside something else is contradictory — drop it.
        if (count($parts) > 1) {
            $parts = array_values(array_filter($parts, fn ($v) => $v !== 'None known'));
        }

        if ($note !== '') {
            $parts[] = $note;
        }

        $merged = trim(implode(', ', array_filter($parts)));

        return $merged === '' ? null : $merged;
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'services.required' => 'Please select at least one service to book.',
            'services.min' => 'Please select at least one service to book.',
            'customer_phone.regex' => 'Please enter a valid phone number.',
            'agree_terms.accepted' => 'You must agree to the Terms and Conditions to book.',
        ];
    }

    /**
     * Date must be inside operating hours/days and not blocked by an admin.
     */
    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            if ($validator->errors()->isNotEmpty()) {
                return;
            }

            $availability = BookingAvailability::make();
            $date = $this->input('preferred_date');
            $time = $this->input('preferred_time');
            $serviceId = $this->input('services.0.service_id');

            foreach ($availability->dateProblems($date, $serviceId) as $problem) {
                $validator->errors()->add('preferred_date', $problem);
            }

            foreach ($availability->timeProblems($date, $time, $serviceId) as $problem) {
                $validator->errors()->add('preferred_time', $problem);
            }

            // Down payment reference is mandatory when the salon requires it.
            $settings = $availability->settings();

            if ($settings->down_payment_required && blank($this->input('down_payment_reference'))) {
                $validator->errors()->add(
                    'down_payment_reference',
                    'A down payment GCash reference number is required to reserve your slot.',
                );
            }
        });
    }
}
