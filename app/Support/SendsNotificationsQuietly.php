<?php

namespace App\Support;

use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Sends a notification, and never lets the send be the thing that fails.
 *
 * A notification is a side effect of something else that has already succeeded.
 * When the booking is written and the confirmation email will not go out because
 * the SMTP password is wrong, the customer has a real booking in the database —
 * and a 500 page telling them the booking failed. They will submit again, and
 * book twice. The error the operator sees blames the booking form for a mail
 * credential they mistyped.
 *
 * So this treats the send as what it is: best-effort, logged, and unable to undo
 * or mask the work that preceded it.
 *
 * Only the send is swallowed. Everything the caller has already done stays done,
 * and the failure is logged at `error` with enough to identify which notification,
 * to whom, and what the transport said — which is the difference between a bug
 * report and a fix.
 *
 * `report()`d as well as logged, so it also reaches Sentry or Bugsnag if the
 * project is wired to one, rather than living only in the log file.
 */
trait SendsNotificationsQuietly
{
    /**
     * Send a notification, swallowing and logging any transport failure.
     *
     * @param  object  $notifiable  Anything with `notify()` — usually a model.
     * @param  object  $notification  A `Notification`, or a class name for one.
     * @param  string  $context  A short phrase naming the action, e.g. "booking confirmed".
     * @return bool  Whether the send completed without throwing.
     */
    protected function notifyQuietly(?object $notifiable, object $notification, string $context): bool
    {
        if ($notifiable === null) {
            return false;
        }

        try {
            $notifiable->notify($notification);

            return true;
        } catch (Throwable $e) {
            $this->logNotificationFailure($notifiable, $notification, $context, $e);

            return false;
        }
    }

    protected function logNotificationFailure(
        object $notifiable,
        object $notification,
        string $context,
        Throwable $e,
    ): void {
        $payload = [
            'context' => $context,
            'notification' => $notification::class,
            'notifiable' => $notifiable::class,
            'notifiable_id' => $notifiable->getKey() ?? null,
            'mailer' => config('mail.default'),
            'mail_host' => config('mail.mailers.smtp.host'),
            'exception' => $e,
        ];

        Log::error('Notification could not be sent: '.$context, $payload);

        report($e);
    }
}
