<?php

namespace App\Http\Requests\Customer;

use App\Models\Appointment;
use App\Services\BookingAvailability;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\ValidationException;

class RescheduleRequest extends FormRequest
{
    public function authorize(): bool
    {
        $appointment = $this->route('appointment');

        return $appointment instanceof Appointment
            && auth()->id() !== null
            && $appointment->user_id === auth()->id();
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'preferred_date' => ['required', 'date'],
            'preferred_time' => ['required', 'date_format:H:i'],
            'reason' => ['nullable', 'string', 'max:500'],
        ];
    }

    /**
     * Re-validate the new slot against blocked dates and availability.
     */
    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            if ($validator->errors()->isNotEmpty()) {
                return;
            }

            /** @var Appointment $appointment */
            $appointment = $this->route('appointment');

            if (! $appointment->canBeRescheduled()) {
                throw ValidationException::withMessages([
                    'preferred_date' => 'This appointment can no longer be rescheduled.',
                ]);
            }

            $availability = BookingAvailability::make();
            $serviceId = $appointment->serviceLines->first()?->service_id;

            $problems = $availability->timeProblems(
                $this->input('preferred_date'),
                $this->input('preferred_time'),
                $serviceId,
                $appointment->id,
            );

            foreach ($problems as $problem) {
                $validator->errors()->add('preferred_date', $problem);
            }
        });
    }
}
