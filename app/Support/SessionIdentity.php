<?php

namespace App\Support;

/**
 * Who this browser is signed in as, right now, for the other tabs to find.
 *
 * A session lives in a cookie, and every tab in a browser sends it. That is
 * exactly why two tabs can disagree about who is signed in: Tab A is a customer
 * page rendered while a customer was signed in, Tab B then signs in as an admin,
 * and Tab A is still showing a customer dashboard with working links — until it
 * happens to navigate.
 *
 * The fix is two halves, and this is the server half's payload:
 *
 *   1. each login logs the other guard out, so the server never has two active
 *      roles at once (see the two session controllers); and
 *   2. each rendered page states its own identity here, and `sessionSync` in
 *      `resources/js/app.js` writes it to `localStorage` and watches for another
 *      tab writing a different one.
 *
 * What is deliberately absent is anything secret. This is a label — "an admin is
 * signed in" — not a token, and it is published to `localStorage` where any
 * script on the origin can read it. The `id` is here so a tab can tell "the same
 * account, still me" from "somebody else", which is the difference between a
 * no-op and a redirect.
 */
class SessionIdentity
{
    /**
     * The value for this request, or null when nobody is signed in.
     *
     * Checked in the order a page actually cares about: an admin session wins
     * over a customer one. That order should not matter now that a login clears
     * the other guard, and it is deliberately written so that if it ever does —
     * an old session cookie, a race between two tabs signing in at once — the
     * page renders as the more privileged of the two rather than the less.
     *
     * @return array{guard: string, id: int, name: string}|null
     */
    public static function current(): ?array
    {
        if ($admin = auth('admin')->user()) {
            return [
                'guard' => 'admin',
                'id' => (int) $admin->id,
                'name' => (string) $admin->full_name,
            ];
        }

        if ($user = auth()->user()) {
            return [
                'guard' => 'web',
                'id' => (int) $user->id,
                'name' => (string) $user->full_name,
            ];
        }

        return null;
    }

    /**
     * The same thing as a stable string, for the meta tag and for comparing
     * against what another tab wrote.
     *
     * `null` becomes the literal `guest` rather than an empty value, so an
     * unsigned-out tab's claim is distinguishable from a tab that simply has not
     * written anything yet.
     */
    public static function key(): string
    {
        $identity = static::current();

        return $identity === null
            ? 'guest'
            : $identity['guard'].':'.$identity['id'];
    }
}
