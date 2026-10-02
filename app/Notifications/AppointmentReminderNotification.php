<?php

namespace App\Notifications;

class AppointmentReminderNotification extends BaseAppointmentNotification
{
    public function payload(): array
    {
        return [
            'title' => 'Reminder: appointment tomorrow',
            'message' => 'A friendly reminder for your '.$this->appointment->service_names.' appointment on '
                .$this->appointment->preferred_date->format('M j, Y').' at '.$this->appointment->time_label.'.',
            'icon' => 'bell',
            'tone' => 'gold',
            'url' => $this->detailsUrl(),
        ];
    }
}
