<?php

namespace App\Notifications;

class AppointmentRescheduledNotification extends BaseAppointmentNotification
{
    public function payload(): array
    {
        return [
            'title' => 'Appointment rescheduled',
            'message' => 'Your appointment has been moved to '.$this->appointment->date_time_label.'.',
            'icon' => 'calendar',
            'tone' => 'confirmed',
            'url' => route('appointments.show', $this->appointment),
        ];
    }
}
