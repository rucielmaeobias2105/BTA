<?php

namespace App\Services;

use App\Models\User;

/**
 * What happens to a customer account when an admin removes it.
 *
 * Soft-delete plus anonymisation, and the two halves are there for different
 * reasons.
 *
 * The soft delete is so the record survives. A booking history is the salon's
 * evidence that a service was performed and paid for; the revenue reports read
 * those appointments, and the appointment rows carry the customer's name, phone
 * and email. Hard-deleting the account would either cascade into them — rewriting
 * the salon's income history — or leave them pointing at an account that no
 * longer exists, which is worse than either.
 *
 * The anonymisation is so the personal data goes. A soft-deleted row is still a
 * row, and while it sits in the table the name, email, phone number, username and
 * profile photo are all still there. Overwriting them is what makes the deletion
 * mean something for the person it is about: after this, the record says someone
 * was a customer, not who they were.
 *
 * So the booking history keeps its shape — dates, services, amounts, staff — and
 * loses its subject. What is deliberately *not* done is scrambling the appointment
 * rows themselves: those are the salon's records, not the customer's, and
 * rewriting them would break the reports in exchange for nothing.
 */
class UserAnonymizer
{
    /**
     * Anonymise and soft-delete one customer.
     *
     * Ordered the way it is: the identifying fields are overwritten *before* the
     * row is marked deleted, so there is no window in which the account is
     * "gone" from the sign-in list while still carrying a live email address.
     *
     * The anonymised email has to be unique — `users.email` is unique and the
     * original one has to stay recognisable enough to avoid colliding with a real
     * address somebody might register later. `deleted-user-{id}@anonymized.invalid`
     * is both: `.invalid` is reserved by RFC 2606 and can never resolve, and the
     * id makes it unique for good.
     *
     * @return array<string, string|null> The previous values, for the toast.
     */
    public function anonymize(User $user): array
    {
        $previous = [
            'name' => $user->full_name,
            'email' => $user->email,
            'phone' => $user->contact_number,
        ];

        $user->forceFill([
            'first_name' => 'Deleted',
            'last_name' => 'Customer',
            'email' => $this->anonymizedEmail($user),
            // Left free rather than nulled: the column is NOT NULL on some
            // installations and a null there would fail the update.
            'username' => 'deleted-'.$user->id,
            'contact_number' => '—',
            // The photo is a file on disk. Left in place rather than deleted: the
            // storage path is shared with appointments' attachments, and removing
            // a file by path derived from a string is a worse trade than an
            // orphaned image that nothing renders.
            'profile_photo_path' => null,
            // Deactivated as well as deleted, so the row is inert even if it is
            // ever restored: a restored account that logged straight back in would
            // be surprising.
            'is_active' => false,
        ])->save();

        $user->delete();

        return $previous;
    }

    /**
     * A perma-unreachable address that cannot collide with a real registration.
     *
     * `.invalid` rather than `.test`: both are reserved for this, but `.invalid`
     * is reserved specifically for "this name will never resolve", which is the
     * statement being made.
     */
    private function anonymizedEmail(User $user): string
    {
        return 'deleted-user-'.$user->id.'@anonymized.invalid';
    }

    /**
     * Whether an account has already been through this.
     *
     * Not `trashed()` alone — a row can be soft-deleted by something else. This
     * is the actual test of whether it has been anonymised, and it is what stops
     * the action being offered twice on a row that is already gone.
     */
    public static function isAnonymized(User $user): bool
    {
        return str_ends_with((string) $user->email, '@anonymized.invalid');
    }

    /**
     * "Ana Reyes" — the name as it was, for the confirmation dialog's prompt.
     *
     * Falls back to the email for an account with a blank name, so the dialog
     * never reads "Delete 1 user?" with nothing to say which one.
     */
    public static function describe(User $user): string
    {
        return filled($user->full_name) ? $user->full_name : (string) $user->email;
    }

    /**
     * How many bookings this account's history holds, for the same dialog.
     *
     * Counted with `withTrashed()` because the point is to say what will remain on
     * record — and a customer may well have cleared finished visits off their own
     * list, which soft-deleted those rows without touching the salon's copy of
     * the event.
     */
    public static function appointmentCount(User $user): int
    {
        return $user->appointments()->withTrashed()->count();
    }
}