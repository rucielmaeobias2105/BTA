<?php

namespace App\Http\Requests\Admin;

use App\Models\Appointment;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Validating a bulk restore out of the archive.
 *
 * The ids arrive from checkboxes, so they are attacker-controlled in exactly the
 * way a path parameter is not: a crafted POST can name any appointment id in the
 * table, including a live booking or one that was never archived. Both are
 * refused here rather than in the controller, so the rule and the reason sit
 * together and a second bulk endpoint cannot forget the check.
 *
 * Archived-only is the rule that matters. Without it the endpoint would double
 * as a way to "restore" a pending booking, which is not a no-op — it would
 * clear the `archived_at` stamp off a row the archiver had set and quietly move
 * it back into the working list.
 */
class RestoreAppointmentsRequest extends FormRequest
{
    public function authorize(): bool
    {
        // The route's `admin.role:admin.appointments.manage` middleware is the
        // gate; this exists so a direct call cannot slip past it.
        return $this->user('admin') !== null;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'ids' => ['required', 'array', 'min:1'],
            'ids.*' => [
                'required',
                'integer',
                'distinct',
                Rule::exists('appointments', 'id')->whereNotNull('archived_at'),
            ],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'ids.required' => 'Select at least one archived booking to restore.',
            'ids.min' => 'Select at least one archived booking to restore.',
            'ids.*.exists' => 'One of the selected bookings is no longer archived, so nothing was restored.',
        ];
    }

    /**
     * The ids, as integers, ready to write.
     *
     * Read back off the validated payload rather than off the raw request so
     * this cannot become a way to pass something the rules did not check.
     *
     * @return list<int>
     */
    public function appointmentIds(): array
    {
        return array_map('intval', $this->validated('ids'));
    }
}
