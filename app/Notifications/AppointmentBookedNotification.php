<?php

namespace App\Notifications;

class AppointmentBookedNotification extends BaseAppointmentNotification
{
    public function payload(): array
    {
        return [
            'title' => 'Appointment request received',
            'message' => 'We have received your booking request and will confirm it shortly. Total: ₱'.number_format((float) $this->appointment->total_amount, 2).'.',
            'icon' => 'calendar',
            'tone' => 'pending',
            'url' => $this->detailsUrl(),
        ];
    }
}
