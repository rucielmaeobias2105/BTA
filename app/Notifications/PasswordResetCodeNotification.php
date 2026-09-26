<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Step 2 of the password reset flow — emails the six-digit code.
 * The plaintext code exists only here; the database stores its hash.
 */
class PasswordResetCodeNotification extends Notification
{
    use Queueable;

    public function __construct(
        public readonly string $code,
        public readonly int $expiryMinutes = 15,
    ) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Your '.config('app.name').' verification code')
            ->greeting("Hello {$notifiable->first_name},")
            ->line('Use the following verification code to reset your password:')
            ->line("**{$this->code}**")
            ->line("This code expires in {$this->expiryMinutes} minutes and can only be used once.")
            ->line('If you did not request a password reset, no action is needed.');
    }

    public function toArray(object $notifiable): array
    {
        return [
            'title' => 'Password reset requested',
            'message' => 'A verification code was sent to your email address.',
            'icon' => 'key',
            'tone' => 'pending',
        ];
    }
}
