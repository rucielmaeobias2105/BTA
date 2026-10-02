<?php

namespace App\Notifications;

class AppointmentConfirmedNotification extends BaseAppointmentNotification
{
    public function payload(): array
    {
        return [
            'title' => 'Your appointment is confirmed',
            'message' => 'We look forward to welcoming you for '.$this->appointment->service_names.'.',
            'icon' => 'check',
            'tone' => 'confirmed',
            'url' => $this->detailsUrl(),
        ];
    }
}
