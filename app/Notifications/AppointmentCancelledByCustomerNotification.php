<?php

namespace App\Notifications;

class AppointmentCancelledByCustomerNotification extends BaseAppointmentNotification
{
    public function payload(): array
    {
        return [
            'title' => 'Appointment cancelled',
            'message' => 'Your appointment for '.$this->appointment->preferred_date->format('M j, Y')
                .' has been cancelled.'
                .($this->appointment->cancellation_reason ? ' Reason: '.$this->appointment->cancellation_reason : ''),
            'icon' => 'x',
            'tone' => 'cancelled',
            'url' => route('appointments.index'),
        ];
    }
}
