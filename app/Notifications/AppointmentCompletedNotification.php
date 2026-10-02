<?php

namespace App\Notifications;

class AppointmentCompletedNotification extends BaseAppointmentNotification
{
    /**
     * The "rate your visit" ask is gone with the rest of the review feature, so
     * this is a plain thank-you and the link goes to the booking rather than to
     * a rating form that no longer exists.
     */
    public function payload(): array
    {
        return [
            'title' => 'Thank you for visiting',
            'message' => 'We hope you enjoyed your '.$this->appointment->service_names.'. We would love to see you again.',
            'icon' => 'sparkles',
            'tone' => 'completed',
            'url' => $this->detailsUrl(),
        ];
    }
}
