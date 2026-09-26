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
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class RescheduleAppointmentController extends Controller
{
    public function __construct(protected BookingService $booking) {}

    /**
     * Customer Flow 8 — Reschedule Appointment.
     * Current date/time shown read-only; new date/time re-validated.
     */
    public function edit(Appointment $appointment): View
    {
        $this->authorizeOwnership($appointment);

        $availability = BookingAvailability::make();
        $serviceId = $appointment->serviceLines->first()?->service_id;

        $suggested = $this->nextAvailableDate($availability, $appointment, $serviceId);

        return view('customer.appointments.reschedule', [
            'appointment' => $appointment,
            'availability' => $availability,
            'serviceId' => $serviceId,
            'prefilledDate' => $suggested,
            'slots' => $availability->availableSlots($suggested, $serviceId, $appointment->id),
            'blockedRanges' => $availability->blockedRanges($serviceId),
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
        $appointment->user?->notify(new AppointmentRescheduledNotification($appointment));

        return redirect()
            ->route('appointments.show', $appointment)
            ->with('status', 'Your appointment has been rescheduled to '.$appointment->date_time_label.'.');
    }

    protected function nextAvailableDate(BookingAvailability $availability, Appointment $appointment, ?int $serviceId): string
    {
        $cursor = max($availability->firstBookableDate(), $appointment->preferred_date);
        $last = $availability->lastBookableDate();

        while ($cursor->lte($last)) {
            if ($availability->availableSlots($cursor->toDateString(), $serviceId, $appointment->id) !== []) {
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
