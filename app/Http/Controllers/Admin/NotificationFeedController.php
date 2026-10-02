<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Appointment;
use App\Models\ContactMessage;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * The admin panel's live work-queue feed.
 *
 * The same polling arrangement as the customer bell, and the same reason for it:
 * there is no WebSocket server in this project, and this is a badge and a short
 * queue rather than a chat.
 *
 * "Notifications" for the salon means work waiting to be done, not messages sent
 * to it. There are exactly two such things, and these are them:
 *
 *   - bookings the salon has not looked at yet, which is what
 *     `Appointment::unseenForAdminCount()` answers — new since the last time
 *     somebody opened the Appointments screen; and
 *   - contact enquiries that have not been opened.
 *
 * The two counts are what the sidebar badges and the tab title's "(n)" are made
 * of; see `adminLiveNotifications` in resources/js/app.js, which polls this and
 * broadcasts each half to the row that displays it.
 *
 * Nothing here is marked read. An enquiry is read when it is marked read on the
 * Messages screen; a booking is seen when the Appointments screen is opened, and
 * that happens server-side in the index action rather than from here, so the
 * first render after a visit already reports the new number. The writes live in
 * NotificationReadController and in that action.
 *
 * The `items` list is what the two counts are a summary of. It has no consumer in
 * the panel at the moment: it fed the topbar bell's dropdown, which has since been
 * removed in favour of putting each count on the sidebar row that owns it. It is
 * still computed and still covered by tests because this is a JSON endpoint whose
 * shape is asserted deliberately — but if nothing needs the rows, dropping them is
 * the obvious next cleanup, and it would take two queries off every poll.
 */
class NotificationFeedController extends Controller
{
    /** How many rows the feed carries, per kind. */
    private const ROWS = 4;

    public function __invoke(Request $request): JsonResponse
    {
        /*
         * Unseen bookings, which is not the same set as Pending ones.
         *
         * This query used to be `where('status', Pending)` to match the sidebar
         * badge and the tab title. It counted a status, so no act of *looking*
         * could change it: the badge stayed on the Appointments page itself, for
         * as long as a booking sat undecided, telling an admin reading the list
         * that the list was unread.
         *
         * `Appointment::unseenForAdminCount()` is what the badge and the title ask
         * for, and this is the third copy of the same question — so it asks the
         * model rather than repeating the query. When the sidebar and the title
         * were changed to count unseen bookings, this would have been the third
         * badge to keep saying otherwise.
         *
         * `active()` is applied inside that helper, so an archived booking cannot
         * badger a screen it is no longer on.
         *
         * The `items` list is the same set. It has no consumer in the panel
         * currently — it fed the topbar bell's dropdown, which has since been
         * removed in favour of the sidebar badges — but it is a summary of the
         * same count, so leaving it on the old query would have it describing a
         * different queue from the one the badge counts.
         */
        $unseen = Appointment::query()
            ->active()
            ->unseenForAdmin()
            ->orderBy('preferred_date')
            ->orderBy('preferred_time')
            ->limit(self::ROWS)
            ->get();

        $unread = ContactMessage::query()
            ->unread()
            ->latest()
            ->limit(self::ROWS)
            ->get();

        return response()->json([
            // The key is still `pending` because the sidebar row that displays it
            // is the Appointments row and the event that carries it is
            // `admin-pending`; renaming both would be a rename with no behaviour
            // in it. What the value *means* has changed, and the names on both
            // sides now say so.
            'pending' => Appointment::unseenForAdminCount(),
            'messages' => ContactMessage::query()->unread()->count(),

            'items' => [
                ...$unseen->map(fn (Appointment $appointment) => [
                    'kind' => 'appointment',
                    'id' => $appointment->id,
                    'title' => 'Booking awaiting a look',
                    'detail' => $appointment->customer_name.' — '.$appointment->service_names,
                    'when' => $appointment->preferred_date->format('M j').' at '.$appointment->getTimeLabelAttribute(),
                    'url' => route('admin.appointments.index'),
                ]),
                ...$unread->map(fn (ContactMessage $message) => [
                    'kind' => 'message',
                    'id' => $message->id,
                    'title' => 'New enquiry',
                    'detail' => $message->topic->label(),
                    'when' => $message->created_at->diffForHumans(),
                    'url' => route('admin.messages.index'),
                ]),
            ],
        ]);
    }
}
