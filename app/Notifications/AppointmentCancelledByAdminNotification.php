<?php

namespace App\Notifications;

class AppointmentCancelledByAdminNotification extends BaseAppointmentNotification
{
    public function payload(): array
    {
        return [
            'title' => 'Appointment cancelled by the salon',
            'message' => 'Unfortunately we had to cancel your appointment on '.$this->appointment->preferred_date->format('M j, Y').'.'
                .($this->appointment->cancellation_reason ? ' Reason: '.$this->appointment->cancellation_reason : '')
                .' Please rebook at your convenience.',
            'icon' => 'x',
            'tone' => 'cancelled',
            'url' => route('appointments.create'),
        ];
    }
}
