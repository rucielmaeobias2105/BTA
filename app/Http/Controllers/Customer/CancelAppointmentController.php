<?php

namespace App\Http\Controllers\Customer;

use App\Enums\AppointmentStatus;
use App\Enums\ChangedBy;
use App\Enums\TermsCategory;
use App\Http\Controllers\Controller;
use App\Models\Appointment;
use App\Models\TermsAndCondition;
use App\Notifications\AppointmentCancelledByCustomerNotification;
use App\Services\BookingService;
use App\Support\SendsNotificationsQuietly;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class CancelAppointmentController extends Controller
{
    use SendsNotificationsQuietly;

    /** Quick-pick reasons; the field also accepts free text. */
    public const REASONS = [
        'Schedule conflict',
        'Feeling unwell',
        'Change of plans',
        'Found another salon',
        'Booked by mistake',
        'Other',
    ];

    public function __construct(protected BookingService $booking) {}

    /**
     * Customer Flow 7 — Cancel Appointment.
     * The reference number is auto-filled from the appointment in context.
     */
    public function edit(Appointment $appointment): View
    {
        $this->authorizeOwnership($appointment);

        if (! $appointment->canBeCancelled()) {
            throw ValidationException::withMessages([
                'status' => 'A '.$appointment->status->label().' appointment cannot be cancelled.',
            ]);
        }

        return view('customer.appointments.cancel', [
            'appointment' => $appointment,
            'reasons' => self::REASONS,
            'policy' => TermsAndCondition::publishedFor(TermsCategory::Cancellation),
        ]);
    }

    public function update(Request $request, Appointment $appointment): RedirectResponse
    {
        $this->authorizeOwnership($appointment);

        if (! $appointment->canBeCancelled()) {
            throw ValidationException::withMessages([
                'status' => 'This appointment can no longer be cancelled.',
            ]);
        }

        $data = $request->validate([
            'reason_preset' => ['nullable', 'string', Rule::in(self::REASONS)],
            'reason' => ['nullable', 'string', 'max:500'],
            'agree_cancellation_policy' => ['accepted'],
        ], [
            'agree_cancellation_policy.accepted' => 'You must agree to the Cancellation Policy to proceed.',
        ]);

        // The preset and the free-text note are both optional; combine them.
        $reason = collect([$data['reason_preset'] ?? null, trim((string) ($data['reason'] ?? ''))])
            ->filter()
            ->unique()
            ->join(' — ')
            ?: null;

        $previous = $appointment->status;

        $appointment->update([
            'status' => AppointmentStatus::Cancelled,
            'cancellation_reason' => $reason,
            'cancelled_at' => now(),
        ]);

        $appointment->recordStatusChange(
            AppointmentStatus::Cancelled,
            ChangedBy::Customer,
            auth()->id(),
            $appointment->customer_name,
            'Cancelled by customer.'.($reason ? ' Reason: '.$reason : ''),
            $previous,
        );

        // Stock held for this booking goes back on the shelf.
        $this->booking->restoreInventory($appointment);

        $this->notifyQuietly(
            $appointment->user,
            new AppointmentCancelledByCustomerNotification($appointment),
            'appointment cancelled by customer'
        );

        return redirect()
            ->route('appointments.index', ['view' => $appointment->id])
            ->with('status', "Appointment {$appointment->reference_number} has been cancelled.");
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
