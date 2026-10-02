<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\UserAnonymizer;
use App\Support\DataTable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Admin Flow 9 — Registered Users Management.
 *
 * A register with one destructive action on it. Customers register themselves, so
 * there is no create here; but an admin can remove an account, which is
 * `destroy()` below and goes through `App\Services\UserAnonymizer` rather than
 * deleting a row.
 *
 * `is_active` is still only ever read here. Deleting an account deactivates it as
 * a side effect of anonymisation, so a deleted row is inert even if restored —
 * but nothing on this screen switches an account off while it is still live.
 */
class UserController extends Controller
{
    /** Columns the list lets an admin sort by. */
    private const SORTABLE = ['last_name', 'first_name', 'email', 'contact_number', 'is_active', 'created_at'];

    public function __construct(protected UserAnonymizer $anonymizer) {}

    public function index(Request $request): View
    {
        [$sort, $direction] = DataTable::sort($request, self::SORTABLE, 'last_name');

        // The whole row set is rendered and the browser filters and pages it
        // live, so a search is not a round trip per keystroke. `search` is only
        // read back out to seed the search box.
        //
        // Surname first, as a phone book reads — which is why the Name header
        // sorts on `last_name` rather than `first_name`.
        $users = DataTable::applySort(
            User::query(),
            $sort,
            $direction,
            self::SORTABLE,
            'last_name',
        )
            ->orderBy('last_name')
            ->orderBy('first_name')
            ->get();

        return view('admin.users.index', [
            'users' => $users,
            'search' => $request->input('search'),
            'sort' => $sort,
            'direction' => $direction,
        ]);
    }

    /**
     * One customer's record: who they are, what they have booked and what they
     * have said.
     *
     * Read-only by design. There are no input fields here and nothing that can
     * be written, so this is two queries of display data.
     */
    public function show(User $user): View
    {
        $user->load(['appointments' => fn ($q) => $q->with('serviceLines')->latest()]);

        return view('admin.users.show', [
            'user' => $user,
        ]);
    }

    /**
     * Remove a customer account: anonymise, then soft-delete.
     *
     * What is *not* here is a cascade. The appointments stay, with their dates,
     * services, amounts and staff intact, because they are the salon's records and
     * the revenue reports read them. What goes is the person: name, email, phone,
     * username and photo are overwritten before the row is marked deleted, so a
     * deleted account leaves behind the fact that someone was a customer and
     * nothing about who they were. See `UserAnonymizer` for why both halves.
     *
     * Deletes nothing permanently. There is a `forceDelete()` on the model that
     * nothing in this panel calls, so even an admin acting deliberately here is
     * left with a recoverable row — which is the point of pairing a confirmation
     * dialog with a soft delete rather than choosing one or the other.
     *
     * Refuses an already-anonymised account rather than reporting a second
     * deletion. The row stays invisible either way, so a repeat would be a lie
     * about what just changed; and re-running the anonymiser would overwrite the
     * original values with the already-anonymised ones and lose nothing useful.
     */
    public function destroy(User $user): RedirectResponse
    {
        abort_if(UserAnonymizer::isAnonymized($user), 404);

        $name = UserAnonymizer::describe($user);
        $bookings = UserAnonymizer::appointmentCount($user);

        $this->anonymizer->anonymize($user);

        return redirect()
            ->route('admin.users.index')
            ->with('toast', [
                'type' => 'success',
                'message' => $bookings > 0
                    ? sprintf('%s deleted. %d booking%s kept on record.', $name, $bookings, $bookings === 1 ? '' : 's')
                    : sprintf('%s deleted.', $name),
            ]);
    }

    /**
     * The confirmation payload for one row, read by the shared dialog.
     *
     * A separate endpoint rather than inline JSON in the markup so the button
     * carries only the id and the server decides what the prompt says — which
     * means the wording cannot drift from the action, and there is one source for
     * it rather than one per row.
     */
    public function destroyConfirmation(Request $request, User $user): JsonResponse
    {
        abort_if(UserAnonymizer::isAnonymized($user), 404);

        return response()->json([
            'name' => UserAnonymizer::describe($user),
            'email' => $user->email,
            'appointments' => UserAnonymizer::appointmentCount($user),
            'action' => route('admin.users.destroy', $user),
        ]);
    }
}
