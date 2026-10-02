<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Appointment;
use App\Models\ContactMessage;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Marking the admin bell's work queue as read, live.
 *
 * The bell's "unread" is two different things and they do not behave alike, so
 * only one of them can be read here:
 *
 *   - an enquiry is genuinely read once a human opens it, so clicking one clears
 *     it, and `mark all` clears the lot; and
 *   - a booking is seen when somebody opens the Appointments screen, which is not
 *     something a click in a dropdown can express. That marking happens
 *     server-side at the top of the index action — see
 *     `Admin\AppointmentController::index()` — so it survives a reload and is not
 *     dependent on this endpoint being reached.
 *
 * Every response carries the whole new picture (`pending` and `messages`) rather
 * than just the number that moved. The client has one badge, one tab title and a
 * sidebar count to keep in agreement, and handing back all three from the single
 * query that decided the answer is what stops them drifting apart.
 */
class NotificationReadController extends Controller
{
    /**
     * Read one enquiry.
     *
     * PATCH rather than POST because nothing here creates anything, and because
     * it is idempotent: re-reading an already-read enquiry is a no-op that
     * returns the same numbers, so a double click cannot corrupt the count.
     */
    public function read(Request $request, ContactMessage $message): JsonResponse
    {
        /*
         * `where('is_read', false)` in the update rather than a plain save, so a
         * second click cannot rewrite `updated_at` and push the row to the top of
         * an inbox the admin is looking at.
         */
        $message->newQuery()->whereKey($message->getKey())->where('is_read', false)->update([
            'is_read' => true,
            'updated_at' => now(),
        ]);

        return $this->state();
    }

    /**
     * Read every enquiry at once — the "Mark all as read" in the bell's dropdown.
     *
     * `forceFill` is not used here; a single bulk `update` is one statement
     * rather than one per row, which matters on a busy inbox and keeps the
     * count the response reports consistent with what was written.
     */
    public function readAll(): JsonResponse
    {
        ContactMessage::query()->unread()->update([
            'is_read' => true,
            'updated_at' => now(),
        ]);

        return $this->state();
    }

    /**
     * The whole badge, recomputed from the database.
     *
     * `Appointment::unseenForAdminCount()` for the same reason the feed uses it:
     * this response is what the client's badge, tab title and sidebar count are
     * all set from, so it has to be asking the same question as the server-rendered
     * halves or the two will disagree within fifteen seconds of each other.
     */
    private function state(): JsonResponse
    {
        return response()->json([
            'pending' => Appointment::unseenForAdminCount(),
            'messages' => ContactMessage::query()->unread()->count(),
        ]);
    }
}
