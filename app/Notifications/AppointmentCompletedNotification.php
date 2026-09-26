<?php

namespace App\Notifications;

class AppointmentCompletedNotification extends BaseAppointmentNotification
{
    public function payload(): array
    {
        return [
            'title' => 'Thank you for visiting',
            'message' => 'We hope you enjoyed your '.$this->appointment->service_names.'. Please rate your experience.',
            'icon' => 'star',
            'tone' => 'completed',
            'url' => route('appointments.rate.create', $this->appointment),
        ];
    }
}
