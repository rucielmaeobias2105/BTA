<?php

namespace App\Notifications;

use App\Models\Appointment;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Base class for the system-generated customer notifications. Subclasses only
 * supply copy + a route; the database payload shape stays identical so the
 * bell icon and notification list can render any of them uniformly.
 */
abstract class BaseAppointmentNotification extends Notification
{
    use Queueable;

    public function __construct(public readonly Appointment $appointment) {}

    public function via(object $notifiable): array
    {
        return ['database', 'mail'];
    }

    /** @return array{title: string, message: string, icon: string, tone: string, url: string} */
    abstract public function payload(): array;

    public function toArray(object $notifiable): array
    {
        return $this->payload() + [
            'appointment_id' => $this->appointment->id,
            'reference_number' => $this->appointment->reference_number,
        ];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $data = $this->payload();

        return (new MailMessage)
            ->subject($data['title'].' — '.config('app.name'))
            ->greeting("Hello {$notifiable->first_name},")
            ->line($data['message'])
            ->line('Reference: '.$this->appointment->reference_number)
            ->line('Appointment: '.$this->appointment->date_time_label)
            ->action('View Appointment', $data['url']);
    }
}
