<?php

namespace App\Http\Controllers\Admin;

use App\Enums\AppointmentStatus;
use App\Enums\ChangedBy;
use App\Enums\DownPaymentStatus;
use App\Http\Controllers\Controller;
use App\Models\Appointment;
use App\Notifications\AppointmentCancelledByAdminNotification;
use App\Notifications\AppointmentCompletedNotification;
use App\Notifications\AppointmentConfirmedNotification;
use App\Notifications\AppointmentInProgressNotification;
use App\Notifications\AppointmentRescheduledNotification;
use App\Services\BookingAvailability;
use App\Services\BookingService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class AppointmentController extends Controller
{
    public function __construct(protected BookingService $booking) {}

    /**
     * Admin Flow 3 — Appointment Management (list + filters).
     */
    public function index(Request $request): View
    {
        $filters = $request->only(['search', 'status', 'from', 'to']);

        $appointments = Appointment::query()
            ->with(['serviceLines', 'user', 'preferredStylist'])
            ->status($filters['status'] ?? null)
            ->betweenDates($filters['from'] ?? null, $filters['to'] ?? null)
            ->when($filters['search'] ?? null, function ($q, $term) {
                $like = '%'.str_replace('%', '\%', $term).'%';

                $q->where(fn ($inner) => $inner
                    ->where('reference_number', 'like', $like)
                    ->orWhere('customer_name', 'like', $like)
                    ->orWhere('customer_phone', 'like', $like)
                    ->orWhere('customer_email', 'like', $like));
            })
            ->orderByDesc('preferred_date')
            ->orderByDesc('preferred_time')
            ->paginate(15)
            ->withQueryString();

        return view('admin.appointments.index', [
            'appointments' => $appointments,
            'filters' => $filters,
            'statusOptions' => AppointmentStatus::options(),
            'counts' => Appointment::query()
                ->selectRaw('status, COUNT(*) as total')
                ->groupBy('status')
                ->pluck('total', 'status')
                ->map(fn ($v) => (int) $v)
                ->all(),
        ]);
    }

    public function show(Appointment $appointment): View
    {
        $appointment->load(['serviceLines', 'user', 'preferredStylist', 'statusHistory', 'review']);

        return view('admin.appointments.show', [
            'appointment' => $appointment,
            'statusOptions' => AppointmentStatus::options(),
            'downPaymentOptions' => DownPaymentStatus::options(),
        ]);
    }

    public function edit(Appointment $appointment): View
    {
        $appointment->load(['serviceLines', 'user', 'preferredStylist']);

        return view('admin.appointments.edit', [
            'appointment' => $appointment,
            'statusOptions' => AppointmentStatus::options(),
        ]);
    }

    /**
     * Approve / Decline / Update Status.
     * Records an appointment_status_history row and notifies the customer.
     */
    public function updateStatus(Request $request, Appointment $appointment): RedirectResponse
    {
        $data = $request->validate([
            'status' => ['required', Rule::in(AppointmentStatus::values())],
            'admin_notes' => ['nullable', 'string', 'max:2000'],
        ]);

        $target = AppointmentStatus::from($data['status']);
        $previous = $appointment->status;
        $admin = $request->user('admin');

        $appointment->update([
            'status' => $target,
            'admin_notes' => $data['admin_notes'] ?? $appointment->admin_notes,
            'confirmed_at' => $target === AppointmentStatus::Confirmed ? ($appointment->confirmed_at ?? now()) : $appointment->confirmed_at,
            'started_at' => $target === AppointmentStatus::InProgress ? ($appointment->started_at ?? now()) : $appointment->started_at,
            'completed_at' => $target === AppointmentStatus::Completed ? now() : $appointment->completed_at,
            'cancelled_at' => $target === AppointmentStatus::Cancelled ? now() : null,
        ]);

        $appointment->recordStatusChange(
            $target,
            ChangedBy::Admin,
            $admin->id,
            $admin->full_name,
            $data['admin_notes'] ?? null,
        );

        $this->dispatchCustomerNotification($appointment, $target, $previous);
        $this->handleInventorySideEffects($appointment, $previous, $target);

        return back()->with('status', "Appointment {$appointment->reference_number} marked as {$target->label()}.");
    }

    /**
     * Admin Notes are internal only.
     */
    public function updateNotes(Request $request, Appointment $appointment): RedirectResponse
    {
        $data = $request->validate([
            'admin_notes' => ['nullable', 'string', 'max:2000'],
        ]);

        $appointment->update(['admin_notes' => $data['admin_notes']]);

        return back()->with('status', 'Internal notes saved.');
    }

    /**
     * Manual GCash down-payment verification (no payment gateway).
     */
    public function updateDownPayment(Request $request, Appointment $appointment): RedirectResponse
    {
        $data = $request->validate([
            'down_payment_status' => ['required', Rule::in(array_keys(DownPaymentStatus::options()))],
        ]);

        $appointment->update([
            'down_payment_status' => DownPaymentStatus::from($data['down_payment_status']),
        ]);

        return back()->with('status', 'Down payment status updated.');
    }

    /**
     * Admin-initiated date/time move.
     */
    public function update(Request $request, Appointment $appointment): RedirectResponse
    {
        $data = $request->validate([
            'preferred_date' => ['required', 'date'],
            'preferred_time' => ['required', 'date_format:H:i'],
            'status' => ['required', Rule::in(AppointmentStatus::values())],
            'admin_notes' => ['nullable', 'string', 'max:2000'],
        ]);

        $availability = BookingAvailability::make();
        $serviceId = $appointment->serviceLines->first()?->service_id;

        $problems = $availability->timeProblems(
            $data['preferred_date'],
            $data['preferred_time'],
            $serviceId,
            $appointment->id,
        );

        if ($problems !== []) {
            return back()
                ->withInput()
                ->withErrors(['preferred_date' => $problems[0]]);
        }

        $target = AppointmentStatus::from($data['status']);
        $previous = $appointment->status;
        $dateChanged = $appointment->preferred_date->toDateString() !== $data['preferred_date'];

        if ($dateChanged) {
            $this->booking->reschedule(
                $appointment,
                $data['preferred_date'],
                $data['preferred_time'],
                'Moved by the salon.',
            );
        } else {
            $appointment->update(['preferred_time' => $data['preferred_time']]);
        }

        $appointment->update([
            'status' => $target,
            'admin_notes' => $data['admin_notes'] ?? $appointment->admin_notes,
            'confirmed_at' => $target === AppointmentStatus::Confirmed ? ($appointment->confirmed_at ?? now()) : $appointment->confirmed_at,
            'completed_at' => $target === AppointmentStatus::Completed ? now() : $appointment->completed_at,
            'cancelled_at' => $target === AppointmentStatus::Cancelled ? now() : null,
        ]);

        $appointment->recordStatusChange(
            $target,
            ChangedBy::Admin,
            $request->user('admin')->id,
            $request->user('admin')->full_name,
            $data['admin_notes'] ?? null,
        );

        if ($dateChanged) {
            $appointment->user?->notify(new AppointmentRescheduledNotification($appointment));
        }

        $this->dispatchCustomerNotification($appointment, $target, $previous);
        $this->handleInventorySideEffects($appointment, $previous, $target);

        return back()->with('status', "Appointment {$appointment->reference_number} updated.");
    }

    protected function dispatchCustomerNotification(Appointment $appointment, AppointmentStatus $target, AppointmentStatus $previous): void
    {
        if ($target === $previous || ! $appointment->user) {
            return;
        }

        $map = [
            AppointmentStatus::Confirmed->value => AppointmentConfirmedNotification::class,
            AppointmentStatus::InProgress->value => AppointmentInProgressNotification::class,
            AppointmentStatus::Completed->value => AppointmentCompletedNotification::class,
            AppointmentStatus::Cancelled->value => AppointmentCancelledByAdminNotification::class,
        ];

        if (isset($map[$target])) {
            $appointment->user->notify(new $map[$target]($appointment));
        }
    }

    /**
     * Cancelling frees the reserved stock; un-cancelling re-reserves it.
     */
    protected function handleInventorySideEffects(
        Appointment $appointment,
        AppointmentStatus $previous,
        AppointmentStatus $target,
    ): void {
        $appointment->loadMissing('serviceLines.service');

        if ($target === AppointmentStatus::Cancelled && $previous !== AppointmentStatus::Cancelled) {
            $this->booking->restoreInventory($appointment);
        }

        if ($previous === AppointmentStatus::Cancelled && $target !== AppointmentStatus::Cancelled) {
            foreach ($appointment->serviceLines as $line) {
                if (! $line->service) {
                    continue;
                }

                foreach ($line->service->inventoryItems as $item) {
                    $perService = (float) ($item->pivot->quantity_per_service ?: 1);
                    $item->decrement('quantity', $perService * $line->quantity);
                    $item->refresh()->syncStatusTag();
                }
            }
        }
    }
}
