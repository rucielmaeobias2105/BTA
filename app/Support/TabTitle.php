<?php

namespace App\Support;

/**
 * The unread count in the browser tab title, the way Messenger and Facebook do
 * it: "(3) NOTIFICATIONS | Balai ti Arjud".
 *
 * Two halves have to agree, and this class is only the server one:
 *
 *   - the rendered `<title>`, so the count is right before a single byte of
 *     JavaScript has run. Without this the tab says "NOTIFICATIONS" for as long
 *     as it takes to load the bundle, which is the window where a user reads the
 *     title to decide whether to switch to the tab; and
 *   - `resources/js/app.js`, which keeps it live from the same poll that drives
 *     the bell badge, so a count that arrives while the tab sits in the
 *     background is reflected without a reload.
 *
 * Both derive the count from the same source as the badge — the bell's own feed
 * payload — because a title that disagreed with the badge next to it would be
 * worse than either being absent.
 */
class TabTitle
{
    /** How many unread items before the count stops being a count. */
    public const MAX = 99;

    /**
     * Prefix a page title with its unread count.
     *
     * Zero returns the title untouched: "(0) Home" is noise, and the requirement
     * is that the prefix is there when there is something unread and gone when
     * there is not.
     *
     * `ltrim` rather than a trim: the templates in this project are written with
     * an upper-cased section and a space before the pipe, so the count is
     * inserted after that leading space is dropped and put back.
     */
    public static function withCount(string $title, int $count): string
    {
        if ($count <= 0) {
            return $title;
        }

        return '('.min($count, self::MAX).') '.ltrim($title);
    }

    /**
     * Strip a count prefix back off, if there is one.
     *
     * Needed by the client-side updater so that a title arriving from somewhere
     * else (a hash-navigation retitle, say) does not end up carrying two
     * prefixes, and so the updater can keep the unprefixed title as its base
     * rather than growing a new prefix on every tick.
     */
    public static function strip(string $title): string
    {
        return (string) preg_replace('/^\(\d+\)\s*/', '', $title);
    }

    /**
     * The count the customer side shows, which is unread notifications.
     */
    public static function customerUnread(): int
    {
        return auth()->check()
            ? (int) auth()->user()->unreadNotifications()->count()
            : 0;
    }

    /**
     * The count the admin side shows.
     *
     * The same two numbers the two sidebar badges show — unseen bookings and
     * unopened enquiries — so the title cannot disagree with the badges beside
     * it.
     *
     * The bookings half is `Appointment::unseenForAdminCount()` rather than a
     * Pending count, and that is the whole fix. It used to be a Pending count,
     * which is a status rather than an acknowledgement: nothing about opening a
     * page changed it, so the prefix stayed on the tab for as long as a booking
     * sat undecided — on the Appointments page itself included, where the admin
     * had the list open in front of them and was being told there were two
     * unread things about the screen they were reading. Unseen bookings are
     * marked seen when that page is opened, so the prefix now means "something
     * has arrived that you have not looked at", and leaves when they look.
     *
     * Both halves come from the model and the same scopes the sidebar uses, so
     * this cannot drift from the badges next to it.
     */
    public static function adminUnread(): int
    {
        if (! auth('admin')->check()) {
            return 0;
        }

        return \App\Models\Appointment::unseenForAdminCount()
            + (int) \App\Models\ContactMessage::query()->unread()->count();
    }
}