<?php

namespace Tests\Feature;

use App\Enums\AppointmentStatus;
use App\Models\Appointment;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * The Archived Appointments screen as a place to *work*, not just to look.
 *
 * Before this the archive was a read-only list with one row action on it, which
 * meant putting a booking back was one click per booking — so an admin cleaning
 * up after the nightly sweep had twenty of them to click twenty times. These
 * tests cover the three things that had to be true for a bulk restore to be
 * trustworthy, and they are separate because they fail separately:
 *
 *   1. the screen offers the selection it claims to (markup, so a control that
 *      silently stopped rendering is caught);
 *   2. a selected set comes back to the working list, clearing both archive
 *      columns; and
 *   3. the endpoint refuses anything that is not actually archived, because the
 *      ids come from checkboxes and a crafted POST can name any row in the
 *      table.
 *
 * (3) is the one that matters most. The other two are convenience; a bulk
 * restore that accepted a live booking id would be a way to un-archive work the
 * archiver had filed away.
 *
 * Note on fixtures: archiving here writes the two archive columns directly
 * rather than posting to the archive route. `actingAs($admin, 'admin')` makes
 * `admin` the default guard and leaves the test authenticated, so a fixture that
 * used the route would silently turn a later "guest" request into a signed-in
 * one and that test would stop asserting anything. `AppointmentArchiveTest`
 * covers the archive route itself.
 */
class ArchivedAppointmentsScreenTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->makeSalonSettings();
    }

    /* ------------------------------------------------------------------ */
    /* 1. The screen offers selection                                      */
    /* ------------------------------------------------------------------ */

    public function test_the_archived_screen_offers_select_all_and_a_bulk_restore(): void
    {
        $this->archived($this->settled());

        $html = $this->actingAs($this->makeAdmin(), 'admin')
            ->get(route('admin.appointments.archived'))
            ->assertOk()
            ->getContent();

        // A per-row checkbox, named the way `appointmentBulk` reads them.
        $this->assertStringContainsString('name="ids[]"', $html);
        $this->assertStringContainsString('x-data="appointmentBulk()"', $html);

        // The header toggle, labelled "on this page" because the list is
        // paginated and only drawn rows can be ticked.
        $this->assertStringContainsString('id="archived-select-all"', $html);
        $this->assertStringContainsString(
            'Select every archived booking on this page',
            $html,
            'Select-all must not claim to cover rows that are not on the screen.',
        );

        $this->assertStringContainsString('Restore selected', $html);
        $this->assertStringContainsString('confirm-archive-restore', $html);
    }

    public function test_the_bulk_restore_posts_ids_and_is_not_a_delete(): void
    {
        $this->archived($this->settled());

        $html = $this->actingAs($this->makeAdmin(), 'admin')
            ->get(route('admin.appointments.archived'))
            ->assertOk()
            ->getContent();

        /*
         * The dialog's action travels into Alpine through `@js()`, which
         * JSON-encodes it and then wraps it in `JSON.parse('…')` — so the URL
         * reaches the page with its slashes escaped *twice* over. Dropping every
         * backslash from both sides is blunt but exact here, and beats writing a
         * pattern that has to know how many layers of escaping were applied.
         */
        $plain = str_replace('\\', '', $html);

        $this->assertStringContainsString(
            str_replace('\\', '', route('admin.appointments.restore-many')),
            $plain,
            'The confirmation must post to the bulk restore route.',
        );

        // The shared dialog spoofs DELETE by default because until now it was
        // only ever used for destructive actions. A restore is a POST, and this
        // screen also carries a real Delete dialog — so getting it wrong would
        // either 405 or point the "Yes" button at a different handler than the
        // prompt describes.
        $this->assertStringContainsString('name="_method" value="POST"', $html);
        $this->assertStringContainsString('name="_method" value="DELETE"', $html);
    }

    public function test_a_booking_can_still_be_archived_and_then_restored_one_at_a_time(): void
    {
        $admin = $this->makeAdmin();
        $appointment = $this->settled();

        $this->actingAs($admin, 'admin')
            ->post(route('admin.appointments.archive', $appointment))
            ->assertRedirect();

        $this->assertTrue($appointment->fresh()->isArchived());

        $this->actingAs($admin, 'admin')
            ->post(route('admin.appointments.restore', $appointment))
            ->assertRedirect();

        $this->assertFalse($appointment->fresh()->isArchived());
    }

    public function test_the_archive_has_its_own_sidebar_row(): void
    {
        $html = $this->actingAs($this->makeAdmin(), 'admin')
            ->get(route('admin.dashboard'))
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString(
            'href="'.route('admin.appointments.archived').'"',
            $html,
            'The archive must be reachable from the sidebar, not only from the toolbar pill.',
        );
    }

    public function test_the_archive_row_lights_up_on_the_archive_and_not_on_appointments(): void
    {
        $html = $this->actingAs($this->makeAdmin(), 'admin')
            ->get(route('admin.appointments.archived'))
            ->assertOk()
            ->getContent();

        // Scoped to the nav, so a link to the working list elsewhere on the page
        // (the "Back to Appointments" button, a row's restore form) cannot
        // satisfy the assertion or trip the other one.
        $nav = Str::between($html, '<nav class="flex-1', '</nav>');

        $this->assertNotSame('', $nav, 'The sidebar nav must render.');

        $current = fn (string $path): string => '/href="'.preg_quote($path, '/').
            '"[^>]*aria-current="page"|aria-current="page"[^>]*href="'.preg_quote($path, '/').'"/';

        $this->assertMatchesRegularExpression(
            $current(route('admin.appointments.archived')),
            $nav,
            'The Archived row must be the highlighted one while on the archive.',
        );

        $this->assertDoesNotMatchRegularExpression(
            $current(route('admin.appointments.index')),
            $nav,
            'And the Appointments row must not also claim to be current.',
        );
    }

    /* ------------------------------------------------------------------ */
    /* 2. A selected set comes back                                        */
    /* ------------------------------------------------------------------ */

    public function test_a_selected_set_is_restored_in_one_request(): void
    {
        $admin = $this->makeAdmin();

        $first = $this->archived($this->settled());
        $second = $this->archived($this->settled());
        $untouched = $this->archived($this->settled());

        $this->actingAs($admin, 'admin')
            ->post(route('admin.appointments.restore-many'), [
                'ids' => [$first->id, $second->id],
            ])
            ->assertRedirect(route('admin.appointments.archived'))
            ->assertSessionHas('toast');

        $this->assertFalse($first->fresh()->isArchived());
        $this->assertFalse($second->fresh()->isArchived());

        // The row that was not ticked stays archived — a bulk action that
        // restored everything would make the checkboxes pointless.
        $this->assertTrue($untouched->fresh()->isArchived());
    }

    public function test_a_bulk_restore_clears_the_archiving_admin_too(): void
    {
        $admin = $this->makeAdmin();
        $appointment = $this->settled();

        $this->actingAs($admin, 'admin')->post(route('admin.appointments.archive', $appointment));

        $this->actingAs($admin, 'admin')
            ->post(route('admin.appointments.restore-many'), ['ids' => [$appointment->id]]);

        $this->assertNull($appointment->fresh()->archived_at);
        $this->assertNull(
            $appointment->fresh()->archived_by,
            'A restored row must not still name the admin who archived it.',
        );
    }

    public function test_a_bulk_restore_returns_to_the_archive_rather_than_the_list_the_rows_left(): void
    {
        $appointment = $this->archived($this->settled());

        $this->actingAs($this->makeAdmin(), 'admin')
            ->post(route('admin.appointments.restore-many'), ['ids' => [$appointment->id]])
            ->assertRedirect(route('admin.appointments.archived'));
    }

    public function test_the_restored_booking_is_back_in_the_working_list(): void
    {
        $admin = $this->makeAdmin();
        $appointment = $this->archived($this->settled());

        $this->actingAs($admin, 'admin')
            ->post(route('admin.appointments.restore-many'), ['ids' => [$appointment->id]]);

        // By customer name, not reference number: the working list dropped its
        // Reference column (see AdminAppointmentTableTest), so the reference is
        // on record but not on screen there.
        $this->actingAs($admin, 'admin')
            ->get(route('admin.appointments.index'))
            ->assertOk()
            ->assertSee($appointment->customer_name);
    }

    /* ------------------------------------------------------------------ */
    /* 3. Only archived rows, only from an admin                           */
    /* ------------------------------------------------------------------ */

    public function test_a_bulk_restore_cannot_reach_a_booking_that_is_not_archived(): void
    {
        $live = $this->settled(AppointmentStatus::Pending);

        $this->assertFalse($live->isArchived());

        $this->actingAs($this->makeAdmin(), 'admin')
            ->from(route('admin.appointments.archived'))
            ->post(route('admin.appointments.restore-many'), ['ids' => [$live->id]])
            ->assertSessionHasErrors('ids.0');

        $this->assertNull(
            $live->fresh()->archived_at,
            'A live booking must be untouched by a crafted restore.',
        );
    }

    public function test_a_bulk_restore_with_no_selection_is_refused(): void
    {
        $this->actingAs($this->makeAdmin(), 'admin')
            ->from(route('admin.appointments.archived'))
            ->post(route('admin.appointments.restore-many'), ['ids' => []])
            ->assertSessionHasErrors('ids');
    }

    public function test_a_bulk_restore_repeats_nothing(): void
    {
        $admin = $this->makeAdmin();
        $appointment = $this->archived($this->settled());

        $this->actingAs($admin, 'admin')
            ->post(route('admin.appointments.restore-many'), ['ids' => [$appointment->id]]);

        // The second attempt now names a row that is no longer archived, and
        // must therefore be refused rather than silently accepted.
        $this->actingAs($admin, 'admin')
            ->from(route('admin.appointments.archived'))
            ->post(route('admin.appointments.restore-many'), ['ids' => [$appointment->id]])
            ->assertSessionHasErrors('ids.0');
    }

    public function test_a_guest_and_a_customer_cannot_bulk_restore(): void
    {
        $appointment = $this->archived($this->settled());

        // No admin has been authenticated anywhere in this test, so both of
        // these really are unauthenticated requests.
        $this->post(route('admin.appointments.restore-many'), ['ids' => [$appointment->id]])
            ->assertRedirect();

        $this->actingAs($this->makeUser(), 'web')
            ->post(route('admin.appointments.restore-many'), ['ids' => [$appointment->id]])
            ->assertRedirect();

        $this->assertTrue(
            $appointment->fresh()->isArchived(),
            'Neither request may have restored anything.',
        );
    }

    /* ------------------------------------------------------------------ */
    /* Fixtures                                                            */
    /* ------------------------------------------------------------------ */

    /** A settled booking, so it is eligible for the archive. */
    private function settled(AppointmentStatus $status = AppointmentStatus::Completed): Appointment
    {
        return $this->makeAppointment($this->makeUser(), null, [
            'status' => $status,
            'preferred_date' => today()->addDays(3),
            'completed_at' => $status === AppointmentStatus::Completed ? now()->subDays(2) : null,
        ]);
    }

    /**
     * Put a booking in the archive without touching the session.
     *
     * The same two columns the archiver writes, which is all anything on this
     * screen reads.
     */
    private function archived(Appointment $appointment): Appointment
    {
        $appointment->forceFill([
            'archived_at' => now()->subDay(),
            'archived_by' => null,
        ])->save();

        return $appointment->fresh();
    }
}
