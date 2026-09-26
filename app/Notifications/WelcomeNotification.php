<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class WelcomeNotification extends Notification
{
    use Queueable;

    public function via(object $notifiable): array
    {
        return ['database', 'mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Welcome to '.config('app.name'))
            ->greeting("Hello {$notifiable->first_name}!")
            ->line('Your account is ready. You can now book appointments and track your treatments.')
            ->action('Book an Appointment', route('appointments.create'))
            ->line('Thank you for choosing us.');
    }

    public function toArray(object $notifiable): array
    {
        return [
            'title' => 'Welcome to '.config('app.name'),
            'message' => 'Your account is ready. You can now book appointments and track your treatments.',
            'icon' => 'sparkles',
            'tone' => 'gold',
            'url' => route('appointments.create'),
        ];
    }
}
