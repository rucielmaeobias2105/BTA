<?php

namespace App\Http\Requests\Customer;

use App\Enums\BookingPreference;
use App\Services\BookingAvailability;
use App\Support\BookingHistory;
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

            /*
             * Step 4's dropdown. Nullable on purpose: an untouched step should
             * leave nothing on the booking, which is a different statement from
             * declaring that the customer has no allergies, and nothing here is
             * worth refusing a booking over. Constrained to the closed set
             * instead, so a crafted payload cannot write free text into the
             * column through a field the panel renders as a `<select>`.
             */
            'allergy_preference' => ['nullable', 'string', Rule::in(array_keys(BookingPreference::storedOptions()))],

            /*
             * Required of a first-time customer, who has no record here for the
             * salon to read the answer out of, and not required of a returning
             * one, whose answer is filled in from their own history — see
             * `BookingHistory`, which is also what this predicate asks, so the
             * form and this rule cannot disagree about who is which.
             *
             * A guest has no history at all, so a guest is always asked.
             */
            'last_services_availed' => [
                Rule::requiredIf(! BookingHistory::isRepeatCustomer()),
                'nullable', 'string', 'max:2000',
            ],
            'last_services_availed_note' => ['nullable', 'string', 'max:2000'],
            // "No preference" submits an empty string, which the `integer` rule
            // turns into null; anything else has to be a technician who is
            // currently being offered, so a retired one cannot be booked.
            //
            // `preferred_stylist_id` is gone from the form — the technician
            // picker replaced it — but the column is still read for the
            // appointments booked before technicians existed.
            'technician_id' => [
                'nullable', 'integer',
                Rule::exists('technicians', 'id')->whereNull('deleted_at')->where('is_active', true),
            ],
            'special_request' => ['nullable', 'string', 'max:2000'],

            // Nothing in the booking form posts this any more, but the column
            // still exists and older bookings carry references in it. Left
            // accepted-and-ignored so a replayed or cached payload cannot fail
            // validation on a field the customer was never shown.
            'down_payment_reference' => ['nullable', 'string', 'max:64'],

            'agree_terms' => ['accepted'],
        ];
    }

    /**
     * Fold the dropdown and the free-text notes into the columns we store.
     */
    protected function prepareForValidation(): void
    {
        $this->merge([
            'customer_name' => trim((string) $this->input('customer_name')),
            'customer_phone' => trim((string) $this->input('customer_phone')),
            'down_payment_reference' => $this->filled('down_payment_reference')
                ? strtoupper(trim((string) $this->input('down_payment_reference')))
                : null,
            'allergies' => $this->healthNote(),
            'last_services_availed' => $this->lastServices(),
        ]);
    }

    /**
     * Step 4 — what the customer told us about their sensitivities.
     *
     * The dropdown's answer is stored as its sentence rather than its backing
     * value, because `appointments.allergies` is read by people: an admin
     * verifying a booking and the customer looking at their own history both see
     * this column, and neither gains anything from `weak_or_brittle_nails`.
     *
     * Two answers are deliberately not stored on their own. `None` is the absence
     * of information, and writing "No known allergies or sensitivities" onto
     * every booking that skipped the step would turn a blank into a claim. `Other`
     * points at the free-text box, so on its own it says nothing — but if that
     * box is empty the customer did pick it, so the label is kept as a hint that
     * there is more to ask about.
     *
     * The legacy `allergies[]` from the old checkbox grid is still folded in.
     * Nothing in the panel sends it now, but a cached page or a replayed request
     * can, and it should land rather than vanish.
     */
    protected function healthNote(): ?string
    {
        $preference = BookingPreference::tryFromLabel($this->input('allergy_preference'));
        $note = trim((string) $this->input('allergies_other'));

        $parts = [];

        if ($preference) {
            $parts[] = $preference === BookingPreference::Other && $note === ''
                ? $preference->label()
                : ($preference->isInformative() ? $preference->label() : '');
        }

        $parts = array_values(array_filter($parts));

        $legacy = collect((array) $this->input('allergies'))
            ->map(fn ($value) => trim((string) $value))
            ->filter()
            ->unique()
            ->all();

        // "None known" beside something else is contradictory — drop it.
        if (count($legacy) > 1) {
            $legacy = array_values(array_filter($legacy, fn ($v) => $v !== 'None known'));
        }

        $parts = array_merge($parts, $legacy);

        if ($note !== '') {
            $parts[] = $note;
        }

        $merged = trim(implode(', ', array_filter($parts)));

        return $merged === '' ? null : $merged;
    }

    /**
     * Step 5 — what this customer last had.
     *
     * A first-timer types it, and the `requiredIf` in `rules()` says so. A
     * returning customer is not asked: their answer is read out of their own
     * completed appointments, which is both more accurate than retyping it and
     * available to the salon on the booking as a typed answer would be.
     *
     * The history is only filled in when the customer sent nothing, so an
     * answer they gave deliberately — correcting what the salon has on file, or
     * adding the treatment they had elsewhere — is never overwritten.
     */
    protected function lastServices(): ?string
    {
        $typed = $this->mergeNotes('last_services_availed', 'last_services_availed_note');

        if ($typed !== null) {
            return $typed;
        }

        return BookingHistory::isRepeatCustomer()
            ? BookingHistory::recentServicesAsText()
            : null;
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
            // Says why it is being asked, because "this field is required" on a
            // customer with no history at all is a confusing thing to be told.
            'last_services_availed.required' => 'Since this is your first visit with us, please tell us what services you had last.',
        ];
    }

    /**
     * Date must be inside operating hours and days, and inside the horizon.
     *
     * The blocked-date check that used to be here went with the Calendar &
     * Blocked Dates feature: a closure is now expressed only through the salon's
     * operating hours, which `BookingAvailability` already reads. That is why
     * `$serviceId` is no longer read out of the basket either — no rule left here
     * depends on which services are in it.
     */
    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            /*
             * Bail only if the date or the time is *itself* already invalid.
             *
             * This used to bail on any error at all, which was free when the only
             * rules were these two and the services. It stopped being free when
             * step 5 gained a required field: a customer who skipped it would be
             * told only about the thing they had skipped, fix it, submit again,
             * and only then be told their date was a closed day — one round trip
             * per problem, and the last problem is the one that actually stops
             * the booking.
             *
             * So this asks about the two fields it is about. Everything else this
             * validator has to say gets said in the same pass.
             */
            if ($validator->errors()->has('preferred_date') || $validator->errors()->has('preferred_time')) {
                return;
            }

            $availability = BookingAvailability::make();
            $date = $this->input('preferred_date');
            $time = $this->input('preferred_time');

            foreach ($availability->dateProblems($date) as $problem) {
                $validator->errors()->add('preferred_date', $problem);
            }

            foreach ($availability->timeProblems($date, $time) as $problem) {
                $validator->errors()->add('preferred_time', $problem);
            }

            // No down payment rule here any more.
            //
            // The booking form used to ask for a GCash reference and made it
            // mandatory whenever the salon's `down_payment_required` was on — which
            // it was, by default, at 50%. Deleting that field from the form while
            // leaving the rule would have failed *every* submission with "A down
            // payment GCash reference number is required to reserve your slot",
            // which reads as a payment fault rather than the bug it was.
            //
            // The rule is gone because the salon takes no deposit for a web
            // booking. `down_payment_required` now defaults to false so
            // `BookingService` stops computing an amount for a booking that never
            // had one, but the check is deliberately not reinstated behind that
            // flag: a booking made with no deposit should not acquire a
            // requirement retroactively because someone re-enabled a setting.
            //
            // Rows that already carry a reference keep it, and the admin's manual
            // verification still reads them — `down_payment_status` is left to
            // `BookingService`, which stores `NotRequired` when there is no
            // reference.
        });
    }

    /**
     * Surface the date rules as a toast as well as inline errors.
     *
     * Amber rather than red: the customer filled the form correctly and the date
     * is simply not one the salon takes bookings for. A red toast for a rule the
     * date picker already greys out would read as a failure on their side.
     *
     * The minimum-notice message is the only one watched by constant. The others
     * are wording about a closed day or the booking window, which read fine
     * inline and would be noise repeated as a toast — and the blocked-date case
     * went entirely with the feature that produced it.
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
