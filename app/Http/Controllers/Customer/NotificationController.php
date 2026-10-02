<?php

namespace App\Http\Controllers\Customer;

use App\Http\Controllers\Controller;
use App\Http\Requests\Customer\DeleteNotificationsRequest;
use App\Support\NotificationRow;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\View\View;

class NotificationController extends Controller
{
    /** How many rows the bell's dropdown carries. */
    private const FEED_SIZE = 8;

    /**
     * Customer Flow 9 — Notifications.
     *
     * A flat list: no filters, no tabs, and no read controls. Opening the
     * notifications bell in the navbar is what marks them read (see
     * markAllRead), and a row is a status line and a timestamp — the booking
     * itself is reached from My Appointments.
     *
     * Deletion is the one action the page does carry, because a customer's inbox
     * is theirs to clear. It comes in two shapes — this row, or the ticked set
     * (destroyMany) — and both resolve their ids through
     * `$request->user()->notifications()`, never the table directly. That
     * relation *is* the ownership check: the id column is a UUID, so a customer
     * who posts somebody else's id matches no row and gets a 404 rather than
     * deleting it.
     *
     * Appointment rows say one thing — the status the booking moved to.
     * Everything else the salon sends (the welcome, a promo) keeps its own copy,
     * because it is not a status change.
     */
    /**
     * The live feed behind the navbar bell.
     *
     * Polled rather than pushed. There is no WebSocket server in this project —
     * no Reverb, no Pusher — and adding one to make a badge tick would be a
     * second always-on process for a page that is otherwise plain PHP. Polling a
     * single small JSON endpoint every few seconds is the whole of the real-time
     * behaviour, and it degrades to nothing rather than to a broken socket.
     *
     * Returns only what the bell draws: the unread count and the most recent
     * rows. Sending the customer's whole inbox would grow without bound and
     * would mean shipping rows nobody is looking at.
     *
     * `since` is a cursor, not a filter for the client's benefit: the poller
     * sends the newest timestamp it already has and gets back only what arrived
     * since. That is what keeps the cost of an idle tab at a cheap indexed
     * lookup rather than a re-read of the list on every tick.
     */
    public function feed(Request $request): JsonResponse
    {
        $raw = $request->string('since')->toString();

        /*
         * Parsed rather than compared as a string, because `created_at` is a
         * real timestamp column and handing the query an ISO-8601 string makes
         * the driver do a string comparison against a formatted value — which
         * silently matches nothing.
         *
         * The empty case is handled before parsing rather than with a `rescue`:
         * `Carbon::parse('')` does not fail, it returns *now*, so an absent cursor
         * would become "everything from this second onwards" and the feed would
         * come back empty for a customer who has notifications.
         *
         * A malformed cursor is a different matter, and there it is genuinely an
         * error — so it falls back to no cursor and the caller gets the recent
         * list rather than a 422.
         */
        $since = $raw === ''
            ? null
            : rescue(fn () => Carbon::parse($raw), null, false);

        $rows = $request->user()->notifications()
            // `>=`, not `>`: several notifications can land in the same second,
            // and a strict comparison would silently drop every row but the
            // first of them. The client de-duplicates by id, so re-sending a row
            // it already has costs nothing and losing one costs a status change.
            ->when($since, fn ($query) => $query->where('created_at', '>=', $since))
            ->orderByDesc('created_at')
            ->limit(self::FEED_SIZE)
            ->get()
            ->map(fn ($notification) => NotificationRow::make($notification));

        return response()->json([
            'unread' => $request->user()->unreadNotifications()->count(),

            // The cursor only ever moves forward, so a clock that jumped back
            // mid-session cannot make the poller re-read the whole inbox.
            'since' => max(
                $since?->toIso8601String() ?? '',
                (string) ($rows->max('at') ?? ''),
            ),
            'notifications' => $rows->all(),
        ]);
    }

    /**
     * The list itself.
     *
     * This is where reading happens now. The navbar bell used to be the read
     * receipt — opening it cleared the badge — so the list carried no read
     * controls of its own. The bell is now a plain link to this page, so the
     * actions it used to perform live here instead.
     *
     * A row is otherwise a status line and a timestamp: the booking itself is
     * reached from My Appointments.
     */
    public function index(Request $request): View
    {
        return view('customer.notifications.index', [
            'notifications' => $request->user()->notifications()->paginate(12),
        ]);
    }

    /**
     * Mark every unread notification as read.
     *
     * Two responses, deliberately. A JSON caller — the page's "Mark all as read"
     * button — gets the fresh unread count back so it can correct the bell badge
     * and the tab title without a round trip to render; anyone without
     * scripting gets a redirect back to the list, so the action still completes
     * on its own.
     *
     * Redirects to the list rather than `back()`: the button lives on the list,
     * and honouring a Referer would be a coincidence rather than a decision.
     */
    public function markAllRead(Request $request): RedirectResponse|JsonResponse
    {
        $request->user()->unreadNotifications()->update(['read_at' => now()]);

        if ($request->expectsJson()) {
            return $this->unreadCount($request);
        }

        return redirect()->route('notifications.index');
    }

    /**
     * Mark one notification as read.
     *
     * Back on the list itself, which is where a customer reads something.
     * Resolved through the customer's own relation with `firstOrFail()`, so a
     * foreign or absent id is a 404 rather than a silent success — a silent
     * success would clear the badge for a row the customer cannot even see.
     *
     * Already-read rows are not an error: the same button can be pressed twice,
     * and a double press must not 404.
     */
    public function markRead(Request $request, string $notification): RedirectResponse|JsonResponse
    {
        $row = $request->user()
            ->notifications()
            ->whereKey($notification)
            ->firstOrFail();

        if ($row->read_at === null) {
            $row->markAsRead();
        }

        if ($request->expectsJson()) {
            return $this->unreadCount($request);
        }

        return redirect()->route('notifications.index');
    }

    /**
     * The authoritative unread count, as JSON.
     *
     * Returned after every write so the client can reconcile against the server
     * rather than trusting its own arithmetic — two tabs, a poll landing
     * mid-request, and a double press all make an optimistic count briefly wrong,
     * and the badge must never stay wrong.
     *
     * @see \App\Support\TabTitle for the other half of the same fact
     */
    private function unreadCount(Request $request): JsonResponse
    {
        return response()->json([
            'unread' => $request->user()->unreadNotifications()->count(),
        ]);
    }

    /**
     * One row, deleted from its own trash button.
     *
     * `firstOrFail()` on the customer's own relation, so a foreign id is a 404
     * rather than a silent success — which matters here, because a silent
     * success would tell a customer their notification is gone when it is
     * somebody else's and very much still there.
     */
    public function destroy(Request $request, string $notification): RedirectResponse
    {
        $request->user()
            ->notifications()
            ->whereKey($notification)
            ->firstOrFail()
            ->delete();

        return redirect()
            ->route('notifications.index')
            ->with('status', 'Notification deleted.');
    }

    /**
     * The ticked set, from the "Delete selected" button.
     *
     * `whereKey($ids)` against the same relation, so the delete is capped at
     * what this customer actually owns however the payload was built. The count
     * comes back from the query rather than from `count($ids)`, so the toast
     * says what was removed and not what was asked for.
     *
     * Redirects to the list rather than `back()`: the pager is by offset, so
     * clearing the last row on page 3 with `back()` would land the customer on
     * an empty page 3 instead of the remaining rows.
     */
    public function destroyMany(DeleteNotificationsRequest $request): RedirectResponse
    {
        $removed = $request->user()
            ->notifications()
            ->whereKey($request->validated('ids'))
            ->delete();

        return redirect()
            ->route('notifications.index')
            ->with('status', $this->deletedMessage($removed));
    }

    /**
     * `1 notification deleted.` / `4 notifications deleted.`
     *
     * Counted in words so the two forms cannot collide into one ambiguous
     * string, and so the message is never a bare number.
     */
    private function deletedMessage(int $removed): string
    {
        return $removed.' notification'.($removed === 1 ? '' : 's').' deleted.';
    }
}
