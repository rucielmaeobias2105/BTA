<?php

namespace App\Support;

use Illuminate\Notifications\DatabaseNotification;

/**
 * Turns a notification row into the shape the bell's dropdown renders.
 *
 * One definition, two callers: the navbar partial seeds the dropdown with what
 * the page was rendered with, and `NotificationController::feed` re-shapes the
 * rows a poll brings back. They must agree exactly — a notification that reads
 * "Appointment status updated: Confirmed" on arrival and "Appointment confirmed!"
 * once the page reloads would read as two different events.
 *
 * The wording is derived from the payload's tone rather than read from the
 * payload's title, which is what makes it uniform across every status, every
 * producer, and rows written before this existed.
 */
class NotificationRow
{
    /**
     * Payload tone → the status a customer is told about.
     *
     * Each notification's payload already carries the tone of the status it
     * reports, and the five tones map one-to-one onto the five statuses. Reusing
     * it means there is no second field to keep in step, and no migration to
     * backfill — a row from last week reads as correctly as one from a minute
     * ago.
     */
    public const STATUS_BY_TONE = [
        'pending' => 'Pending',
        'confirmed' => 'Confirmed',
        'progress' => 'In Progress',
        'completed' => 'Completed',
        'cancelled' => 'Cancelled',
    ];

    /**
     * @return array<string, mixed>
     */
    public static function make(DatabaseNotification $notification): array
    {
        $data = $notification->data;

        $isBooking = ! empty($data['appointment_id']);

        return [
            /*
             * `id` is a UUID, and a v4 UUID is random — so its first characters
             * are not a clock and cannot be compared to ask "is this newer than
             * what I have?". The feed cursor is therefore `created_at`, and this
             * row carries it so the client can advance its cursor without a
             * second field of its own.
             */
            'id' => $notification->id,
            'at' => $notification->created_at?->toIso8601String(),
            'title' => $isBooking
                ? 'Appointment status updated'.(($status = self::statusFor($data)) ? ': '.$status : '')
                : ($data['title'] ?? 'Notification'),

            // A booking row is the status line and nothing more. Everything else
            // the salon sends keeps its own body, because it is not a status
            // change.
            'message' => $isBooking ? '' : ($data['message'] ?? ''),
            'ago' => $notification->created_at->diffForHumans(),
            'unread' => $notification->read_at === null,
            'tone' => $data['tone'] ?? 'gold',
        ];
    }

    /**
     * The status a payload reports, or null for the salon's own messages.
     *
     * @param  array<string, mixed>  $data
     */
    public static function statusFor(array $data): ?string
    {
        return self::STATUS_BY_TONE[$data['tone'] ?? ''] ?? null;
    }
}
