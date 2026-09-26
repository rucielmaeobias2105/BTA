<?php

namespace App\Notifications;

class AppointmentInProgressNotification extends BaseAppointmentNotification
{
    public function payload(): array
    {
        return [
            'title' => 'Your treatment is underway',
            'message' => 'Your '.$this->appointment->service_names.' appointment is now in progress. Enjoy!',
            'icon' => 'sparkles',
            'tone' => 'progress',
            'url' => route('appointments.show', $this->appointment),
        ];
    }
}
