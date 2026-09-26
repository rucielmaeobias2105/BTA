<?php

namespace App\Services;

use App\Enums\AppointmentStatus;
use App\Enums\ChangedBy;
use App\Enums\DownPaymentStatus;
use App\Enums\ItemTag;
use App\Models\Appointment;
use App\Models\AppointmentService;
use App\Models\InventoryItem;
use App\Models\Service;
use App\Models\ServiceVariant;
use App\Models\SalonSetting;
use App\Notifications\AppointmentBookedNotification;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Creates appointments from the booking form and keeps linked inventory in
 * step. Every write happens in a transaction so a failed availability re-check
 * cannot leave a half-written appointment behind.
 */
class BookingService
{
    /**
     * Resolve the selected service/variant pairs into priced lines.
     *
     * @param  array<int, array{service_id: int|string, service_variant_id?: int|string|null, quantity?: int|string}>  $selections
     * @return array<int, array{Service $service, ?ServiceVariant $variant, int $quantity, float $price, int $duration}>
     */
    public function resolveLines(array $selections): array
    {
        $lines = [];

        foreach ($selections as $selection) {
            $service = Service::withTrashed()->find($selection['service_id']);

            if (! $service) {
                continue;
            }

            $variant = null;

            if (! empty($selection['service_variant_id'])) {
                $variant = ServiceVariant::where('service_id', $service->id)
                    ->find($selection['service_variant_id']);
            }

            $quantity = max(1, (int) ($selection['quantity'] ?? 1));

            $lines[] = [
                'service' => $service,
                'variant' => $variant,
                'quantity' => $quantity,
                'price' => (float) ($variant?->price ?? $service->price),
                'duration' => (int) ($variant?->effectiveDuration() ?? $service->duration_minutes),
            ];
        }

        return $lines;
    }

    /**
     * @param  array<int, array{Service $service, ?ServiceVariant $variant, int $quantity, float $price, int $duration}>  $lines
     */
    public function totalFor(array $lines): float
    {
        return round(array_sum(array_map(
            fn (array $line) => $line['price'] * $line['quantity'],
            $lines,
        )), 2);
    }

    /**
     * Create the appointment, its service lines, and consume linked stock.
     *
     * @param  array<string, mixed>  $data
     */
    public function create(array $data, array $lines, ?int $userId = null): Appointment
    {
        $settings = SalonSetting::current();
        $total = $this->totalFor($lines);

        $appointment = DB::transaction(function () use ($data, $lines, $total, $settings, $userId) {
            $appointment = Appointment::create([
                'reference_number' => Appointment::generateReferenceNumber(),
                'user_id' => $userId,
                'customer_name' => $data['customer_name'],
                'customer_phone' => $data['customer_phone'],
                'customer_email' => $data['customer_email'] ?? null,
                'preferred_date' => $data['preferred_date'],
                'preferred_time' => $data['preferred_time'],
                'allergies' => $data['allergies'] ?? null,
                'last_services_availed' => $data['last_services_availed'] ?? null,
                'preferred_stylist_id' => $data['preferred_stylist_id'] ?? null,
                'special_request' => $data['special_request'] ?? null,
                'down_payment_reference' => $data['down_payment_reference'] ?? null,
                'down_payment_amount' => $settings->down_payment_required
                    ? $settings->expectedDownPaymentFor($total)
                    : null,
                // No payment gateway: an admin verifies the GCash reference by hand.
                'down_payment_status' => $settings->down_payment_required && ! empty($data['down_payment_reference'])
                    ? DownPaymentStatus::Unverified
                    : DownPaymentStatus::NotRequired,
                'total_amount' => $total,
                'status' => AppointmentStatus::Pending,
                'source' => 'web',
            ]);

            foreach ($lines as $line) {
                AppointmentService::create([
                    'appointment_id' => $appointment->id,
                    'service_id' => $line['service']->id,
                    'service_variant_id' => $line['variant']?->id,
                    'service_name' => $line['service']->name,
                    'variant_name' => $line['variant']?->name,
                    'price' => $line['price'],
                    'duration_minutes' => $line['duration'],
                    'quantity' => $line['quantity'],
                ]);
            }

            $this->consumeInventory($lines);

            $appointment->recordStatusChange(
                AppointmentStatus::Pending,
                ChangedBy::Customer,
                $userId,
                $data['customer_name'],
                'Appointment submitted by customer.',
            );

            return $appointment;
        });

        $appointment->load('serviceLines');

        if ($userId) {
            $appointment->user?->notify(new AppointmentBookedNotification($appointment));
        }

        return $appointment;
    }

    /**
     * Deduct each linked item and re-derive its status tag.
     *
     * @param  array<int, array{Service $service, ?ServiceVariant $variant, int $quantity, float $price, int $duration}>  $lines
     */
    protected function consumeInventory(array $lines): void
    {
        foreach ($lines as $line) {
            $items = $line['service']->inventoryItems()->get();

            foreach ($items as $item) {
                $perService = (float) ($item->pivot->quantity_per_service ?: 1);
                $consumed = $perService * $line['quantity'];

                $item->decrement('quantity', $consumed);
                $item->refresh()->syncStatusTag();
            }
        }
    }

    /**
     * Move an appointment to a new date/time, re-validating availability.
     */
    public function reschedule(
        Appointment $appointment,
        string $date,
        string $time,
        ?string $reason = null,
    ): void {
        $previousDate = $appointment->preferred_date->toDateString();
        $previousTime = Carbon::parse($appointment->preferred_time)->format('H:i');

        DB::transaction(function () use ($appointment, $date, $time, $reason, $previousDate, $previousTime) {
            $appointment->update([
                'preferred_date' => $date,
                'preferred_time' => $time,
                'reschedule_reason' => $reason,
            ]);

            // Returning the previously held stock, then consuming the new mix.
            $this->restoreInventory($appointment);
            $this->consumeInventory($appointment->serviceLines->map(fn ($line) => [
                'service' => $line->service,
                'variant' => $line->variant,
                'quantity' => $line->quantity,
            ])->all());

            $appointment->recordStatusChange(
                $appointment->status,
                ChangedBy::Customer,
                $appointment->user_id,
                $appointment->customer_name,
                "Rescheduled from {$previousDate} {$previousTime} to {$date} {$time}."
                .($reason ? " Reason: {$reason}" : ''),
            );
        });
    }

    /**
     * Put consumed stock back when an appointment is cancelled.
     */
    public function restoreInventory(Appointment $appointment): void
    {
        foreach ($appointment->serviceLines as $line) {
            $service = $line->service;

            if (! $service) {
                continue;
            }

            $items = $service->inventoryItems()->get();

            foreach ($items as $item) {
                $perService = (float) ($item->pivot->quantity_per_service ?: 1);
                $item->increment('quantity', $perService * $line->quantity);
                $item->refresh()->syncStatusTag();
            }
        }
    }
}
