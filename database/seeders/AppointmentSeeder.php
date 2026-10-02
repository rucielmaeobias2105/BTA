<?php

namespace Database\Seeders;

use App\Enums\AdminRole;
use App\Enums\AppointmentStatus;
use App\Enums\ChangedBy;
use App\Enums\DownPaymentStatus;
use App\Models\Admin;
use App\Models\Appointment;
use App\Models\AppointmentService;
use App\Models\SalonSetting;
use App\Models\Service;
use App\Models\User;
use Illuminate\Database\Seeder;

/**
 * Demo appointments spread across every status so the admin dashboard,
 * appointment table and reports all have content.
 */
class AppointmentSeeder extends Seeder
{
    public function run(): void
    {
        $users = User::where('is_active', true)->get();
        $services = Service::with('variants')->get()->keyBy('name');
        $staff = Admin::where('is_active', true)->get();
        $settings = SalonSetting::current();

        if ($users->isEmpty() || $services->isEmpty()) {
            return;
        }

        // name => [offset days, time, status, service keys, optional extra]
        $plan = [
            ['juan@example.test', 0, '10:00', AppointmentStatus::Confirmed, ['Gelish Manicure']],
            ['maria@example.test', 0, '13:30', AppointmentStatus::InProgress, ['Signature Blowout & Styling']],
            ['angeline@example.test', 0, '16:00', AppointmentStatus::Pending, ['Brightening Facial']],
            ['paolo@example.test', 1, '11:00', AppointmentStatus::Confirmed, ['Volume Lash Extensions']],
            ['kristine@example.test', 2, '14:00', AppointmentStatus::Pending, ['Spa Pedicure', 'Glow Manicure']],
            ['diego@example.test', 3, '09:30', AppointmentStatus::Pending, ['Aromatherapy Massage (90 min)']],
            ['juan@example.test', -3, '15:00', AppointmentStatus::Completed, ['Classic Eyelash Extensions']],
            ['maria@example.test', -6, '10:30', AppointmentStatus::Completed, ['Gelish Manicure']],
            ['angeline@example.test', -8, '13:00', AppointmentStatus::Completed, ['Hydrating Facial']],
            ['paolo@example.test', -10, '16:30', AppointmentStatus::Completed, ['Relaxing Massage (60 min)']],
            ['kristine@example.test', -12, '11:00', AppointmentStatus::Cancelled, ['Color & Highlights'], 'Change of plans'],
            ['diego@example.test', -14, '14:00', AppointmentStatus::Completed, ['Classic Pedicure']],
            ['juan@example.test', -20, '10:00', AppointmentStatus::Completed, ['Spa Pedicure']],
            ['maria@example.test', -25, '15:00', AppointmentStatus::Completed, ['Brazilian Waxing']],
            ['angeline@example.test', -4, '09:00', AppointmentStatus::Cancelled, ['Bridal Glow Package'], 'Rescheduled to a later date'],
        ];

        foreach ($plan as $index => $row) {
            [$email, $offset, $time, $status, $serviceNames] = $row;
            $cancelReason = $row[5] ?? null;

            $user = $users->firstWhere('email', $email);

            if (! $user) {
                continue;
            }

            $date = today()->addDays($offset);

            // Land on an open day so the demo data is self-consistent.
            while (! $settings->isOpenOn($date)) {
                $date->addDay();
            }

            $lines = [];
            $total = 0;

            foreach ($serviceNames as $name) {
                $service = $services->get($name);

                if (! $service) {
                    continue;
                }

                $variant = $service->variants->firstWhere('is_default', true) ?? $service->variants->first();
                $price = (float) ($variant?->price ?? $service->price);
                $total += $price;

                $lines[] = [
                    'service_id' => $service->id,
                    'service_variant_id' => $variant?->id,
                    'service_name' => $service->name,
                    'variant_name' => $variant?->name,
                    'price' => $price,
                    'duration_minutes' => (int) ($variant?->effectiveDuration() ?? $service->duration_minutes),
                    'quantity' => 1,
                ];
            }

            if ($lines === []) {
                continue;
            }

            $appointment = Appointment::create([
                'reference_number' => Appointment::generateReferenceNumber(),
                'user_id' => $user->id,
                'customer_name' => $user->full_name,
                'customer_phone' => $user->contact_number,
                'customer_email' => $user->email,
                'preferred_date' => $date,
                'preferred_time' => $time,
                'allergies' => $index % 4 === 0 ? 'Mild sensitivity to latex.' : null,
                'last_services_availed' => $index > 6 ? 'Glow Manicure' : null,
                'preferred_stylist_id' => $staff->random()?->id,
                'special_request' => $index % 3 === 0 ? 'Please prepare a quiet corner if possible.' : null,
                'down_payment_reference' => 'GCASH'.str_pad((string) (100000 + $index), 6, '0', STR_PAD_LEFT),
                'down_payment_amount' => $settings->expectedDownPaymentFor($total),
                'down_payment_status' => $index % 3 === 0
                    ? DownPaymentStatus::Verified
                    : ($index % 3 === 1 ? DownPaymentStatus::Unverified : DownPaymentStatus::Rejected),
                'total_amount' => $total,
                'status' => $status,
                'admin_notes' => $index % 5 === 0 ? 'Prefers late afternoon slots.' : null,
                'cancellation_reason' => $status === AppointmentStatus::Cancelled ? $cancelReason : null,
                'cancelled_at' => $status === AppointmentStatus::Cancelled ? now()->subDays(abs($offset)) : null,
                'confirmed_at' => in_array($status, [AppointmentStatus::Confirmed, AppointmentStatus::InProgress, AppointmentStatus::Completed], true) ? now()->subDays(abs($offset) + 2) : null,
                'completed_at' => $status === AppointmentStatus::Completed ? now()->subDays(abs($offset)) : null,
                'started_at' => $status === AppointmentStatus::InProgress ? now() : null,
            ]);

            foreach ($lines as $line) {
                $line['appointment_id'] = $appointment->id;
                AppointmentService::create($line);
            }

            $this->recordHistory($appointment, $status, $cancelReason);
        }
    }

    protected function recordHistory(Appointment $appointment, AppointmentStatus $status, ?string $cancelReason): void
    {
        $admin = Admin::where('role', AdminRole::Admin)->first();

        $appointment->recordStatusChange(
            AppointmentStatus::Pending,
            ChangedBy::Customer,
            $appointment->user_id,
            $appointment->customer_name,
            'Appointment submitted by customer.',
        );

        $path = match ($status) {
            AppointmentStatus::Pending => [],
            AppointmentStatus::Confirmed => [AppointmentStatus::Confirmed],
            AppointmentStatus::InProgress => [AppointmentStatus::Confirmed, AppointmentStatus::InProgress],
            AppointmentStatus::Completed => [AppointmentStatus::Confirmed, AppointmentStatus::InProgress, AppointmentStatus::Completed],
            AppointmentStatus::Cancelled => [AppointmentStatus::Cancelled],
        };

        foreach ($path as $step) {
            $appointment->recordStatusChange(
                $step,
                $step === AppointmentStatus::Cancelled ? ChangedBy::Customer : ChangedBy::Admin,
                $step === AppointmentStatus::Cancelled ? $appointment->user_id : $admin?->id,
                $step === AppointmentStatus::Cancelled ? $appointment->customer_name : $admin?->full_name,
                $step === AppointmentStatus::Cancelled ? ('Cancelled by customer. Reason: '.($cancelReason ?? 'n/a')) : null,
            );
        }
    }
}
