<?php

namespace App\Http\Controllers\Customer;

use App\Enums\TermsCategory;
use App\Http\Controllers\Controller;
use App\Http\Requests\Customer\RescheduleRequest;
use App\Models\Appointment;
use App\Models\TermsAndCondition;
use App\Notifications\AppointmentRescheduledNotification;
use App\Services\BookingAvailability;
use App\Services\BookingService;
use App\Support\SendsNotificationsQuietly;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class RescheduleAppointmentController extends Controller
{
    use SendsNotificationsQuietly;

    public function __construct(protected BookingService $booking) {}

    /**
     * Customer Flow 8 — Reschedule Appointment.
     * Current date/time shown read-only; new date/time re-validated.
     */
    public function edit(Appointment $appointment): View
    {
        $this->authorizeOwnership($appointment);

        $availability = BookingAvailability::make();

        // Still resolved, and still sent to the slots endpoint — which accepts it
        // and no longer reads it. It used to scope the blocked-date map to this
        // appointment's own service; with blocked dates gone, nothing here
        // depends on which service the booking is for.
        $serviceId = $appointment->serviceLines->first()?->service_id;

        $suggested = $this->nextAvailableDate($availability, $appointment);

        return view('customer.appointments.reschedule', [
            'appointment' => $appointment,
            'availability' => $availability,
            'serviceId' => $serviceId,
            'prefilledDate' => $suggested,
            'slots' => $availability->availableSlots($suggested, $appointment->id),
            'policy' => TermsAndCondition::publishedFor(TermsCategory::Rescheduling),
        ]);
    }

    public function update(RescheduleRequest $request, Appointment $appointment): RedirectResponse
    {
        $this->authorizeOwnership($appointment);

        $data = $request->validated();

        $this->booking->reschedule(
            $appointment,
            $data['preferred_date'],
            $data['preferred_time'],
            $data['reason'] ?? null,
        );

        $appointment->load('serviceLines');
        $this->notifyQuietly(
            $appointment->user,
            new AppointmentRescheduledNotification($appointment),
            'appointment rescheduled by customer'
        );

        return redirect()
            ->route('appointments.index', ['view' => $appointment->id])
            ->with('status', 'Your appointment has been rescheduled to '.$appointment->date_time_label.'.');
    }

    /**
     * The first date on or after the current one that still has a free slot.
     *
     * `$serviceId` is no longer a parameter: availability stopped depending on the
     * service when blocked dates went, so passing it would be a value nothing
     * reads.
     */
    protected function nextAvailableDate(BookingAvailability $availability, Appointment $appointment): string
    {
        $cursor = max($availability->firstBookableDate(), $appointment->preferred_date);
        $last = $availability->lastBookableDate();

        while ($cursor->lte($last)) {
            if ($availability->availableSlots($cursor->toDateString(), $appointment->id) !== []) {
                return $cursor->toDateString();
            }

            $cursor->addDay();
        }

        return now()->format('Y-m-d');
    }

    protected function authorizeOwnership(Appointment $appointment): void
    {
        abort_unless(
            auth()->id() !== null && $appointment->user_id === auth()->id(),
            403,
            'This appointment belongs to another account.',
        );
    }
}
