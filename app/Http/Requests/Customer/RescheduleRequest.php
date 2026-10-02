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
     * Re-validate the new slot against availability.
     *
     * The blocked-date check that used to be here went with the Calendar &
     * Blocked Dates feature. Nothing else about rescheduling depends on which
     * service the booking is for, so `$serviceId` is no longer resolved from the
     * appointment's service lines either.
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

            $problems = $availability->timeProblems(
                $this->input('preferred_date'),
                $this->input('preferred_time'),
                $appointment->id,
            );

            foreach ($problems as $problem) {
                $validator->errors()->add('preferred_date', $problem);
            }
        });
    }

    /**
     * The minimum-notice rule raises an amber toast, same as the booking form.
     *
     * Reschedule reaches it through `timeProblems()` rather than
     * `dateProblems()`, so the message arrives on a different path but is the same
     * constant.
     */
    protected function failedValidation(Validator $validator): void
    {
        $messages = $validator->errors()->all();

        foreach ([BookingAvailability::MINIMUM_NOTICE_MESSAGE] as $watched) {
            if (in_array($watched, $messages, true)) {
                session()->flash('toast', [
                    'type' => 'warning',
                    'message' => $watched,
                ]);

                break;
            }
        }

        parent::failedValidation($validator);
    }
}
