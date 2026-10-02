<?php

namespace App\Notifications;

use App\Models\Admin;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * The "reset your password" email for the staff portal.
 *
 * Laravel ships `Illuminate\Auth\Notifications\ResetPassword`, and it is close
 * to right — but its `resetUrl()` builds a link to `route('password.reset')`,
 * which is the *customer's* four-step wizard. An admin following that link would
 * land on a customer screen asking for a six-digit code that was never issued.
 *
 * That is not a cosmetic mismatch. The link is the whole mechanism, so getting it
 * wrong means the flow reports success and delivers an admin to a page that
 * cannot help them.
 *
 * So this subclasses the framework notification and overrides only `resetUrl()`.
 * Everything else — the subject, the wording, the expiry line, the "if you did not
 * request this" line — is inherited, so this cannot drift from the framework's
 * wording the way a from-scratch notification would.
 *
 * One second thing is inherited deliberately and worth naming: the expiry in the
 * body reads `config('auth.passwords.users.expire')`, because the framework
 * notification asks for the *default* broker's window, not this model's. For
 * admins that would print the customers' 60 minutes while the `admins` broker
 * actually rejects the token at 30 — telling someone they have an hour when they
 * have half of one. `buildMailMessage()` is overridden for that line alone.
 */
class AdminPasswordResetNotification extends \Illuminate\Auth\Notifications\ResetPassword
{
    public function toMail($notifiable)
    {
        if (static::$toMailCallback) {
            return call_user_func(static::$toMailCallback, $notifiable, $this->token);
        }

        return $this->buildMailMessage($this->resetUrl($notifiable));
    }

    /**
     * The admin reset URL.
     *
     * `absolute: false` and a manual `url()`, exactly as the parent does, so the
     * link is built from `APP_URL` rather than from whatever host happened to
     * serve the request — which matters here because the link is read in an inbox
     * on a different machine entirely.
     */
    protected function resetUrl($notifiable): string
    {
        if (static::$createUrlCallback) {
            return call_user_func(static::$createUrlCallback, $notifiable, $this->token);
        }

        return url(route('admin.password.reset', [
            'token' => $this->token,
            'email' => $notifiable->getEmailForPasswordReset(),
        ], false));
    }

    /**
     * The same message, with the *admin* broker's expiry rather than the default
     * broker's.
     *
     * Read from `config('auth.passwords.'.Admin::PASSWORD_BROKER.'.expire')` so
     * the number in the email is the number the broker will enforce. Everything
     * else in the message is the framework's own text, unchanged.
     */
    protected function buildMailMessage($url): MailMessage
    {
        $minutes = (int) config('auth.passwords.'.Admin::PASSWORD_BROKER.'.expire');

        return (new MailMessage)
            ->subject(__('Reset your staff password'))
            ->line(__('You are receiving this email because a password reset was requested for your staff account.'))
            ->action(__('Reset Password'), $url)
            ->line(__('This password reset link will expire in :count minutes.', ['count' => $minutes]))
            ->line(__('If you did not request a password reset, no further action is required — the link simply stops working.'));
    }
}