<?php

namespace App\Http\Controllers\Customer;

use App\Enums\AppointmentStatus;
use App\Http\Controllers\Controller;
use App\Models\Appointment;
use App\Models\Review;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class RateServiceController extends Controller
{
    /**
     * Customer Flow 10 — Rate Service.
     * Only reachable for completed appointments, one review per appointment.
     */
    public function create(Appointment $appointment): View
    {
        $this->authorizeOwnership($appointment);
        $this->ensureRateable($appointment);

        $appointment->load('serviceLines');

        return view('customer.appointments.rate', [
            'appointment' => $appointment,
        ]);
    }

    public function store(Request $request, Appointment $appointment): RedirectResponse
    {
        $this->authorizeOwnership($appointment);
        $this->ensureRateable($appointment);

        $data = $request->validate([
            'rating' => ['required', 'integer', 'min:1', 'max:5'],
            'message' => ['required', 'string', 'min:5', 'max:2000'],
        ]);

        Review::create([
            'appointment_id' => $appointment->id,
            'user_id' => $appointment->user_id,
            'service_id' => $appointment->serviceLines->first()?->service_id,
            'rating' => $data['rating'],
            'message' => $data['message'],
            'customer_name' => $appointment->customer_name,
        ]);

        return redirect()
            ->route('appointments.show', $appointment)
            ->with('status', 'Thank you! Your review has been submitted.');
    }

    protected function ensureRateable(Appointment $appointment): void
    {
        if ($appointment->status !== AppointmentStatus::Completed) {
            throw ValidationException::withMessages([
                'rating' => 'Only completed appointments can be rated.',
            ]);
        }

        if ($appointment->review()->exists()) {
            throw ValidationException::withMessages([
                'rating' => 'You have already reviewed this appointment.',
            ]);
        }
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
