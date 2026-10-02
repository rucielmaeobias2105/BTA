<?php

namespace Tests\Feature;

use App\Enums\AppointmentStatus;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Clearing bookings off My Appointments.
 *
 * Two rules this file exists to pin, both of which are enforced in the
 * controller rather than only by hiding a button:
 *
 *   - a customer may delete a booking only once it is settled (Completed or
 *     Cancelled). A pending, confirmed or in-progress appointment is still a
 *     live commitment the salon has to act on, and deleting it would hide that.
 *   - the ids are resolved through the customer's own relation, so somebody
 *     else's booking is a 404 rather than a silent success.
 *
 * Two shapes of the same action, matching the notifications list: one row, or
 * the ticked set — both through `x-ui.confirm-dialog`, never `confirm()`.
 */
class CustomerAppointmentsDeleteTest extends TestCase
{
    use RefreshDatabase;

    private function unescaped(string $html): string
    {
        return str_replace('\\', '', str_replace('\u0022', '"', $html));
    }

    /* ------------------------------------------------------------------ */
    /* 1. The table                                                        */
    /* ------------------------------------------------------------------ */

    public function test_a_settled_booking_offers_a_checkbox_and_a_delete_icon(): void
    {
        $user = $this->makeUser();
        $appointment = $this->makeAppointment($user, $this->makeService(), ['status' => AppointmentStatus::Completed]);

        $html = $this->actingAs($user)->get(route('appointments.index'))->assertOk()->getContent();

        // Select-all and the bulk button, the notifications-list arrangement.
        $this->assertStringContainsString('appointmentBulk()', $html);
        $this->assertStringContainsString('Select all on this page', $html);
        $this->assertStringContainsString('Delete selected', $html);

        // A box carrying this row's id, and a trash icon pointing at its own URL.
        $this->assertStringContainsString('name="ids[]"', $html);
        $this->assertStringContainsString('value="'.$appointment->id.'"', $html);
        $this->assertStringContainsString(route('appointments.destroy', $appointment), $this->unescaped($html));
        $this->assertStringContainsString('fas fa-trash', $html);
    }

    /**
     * A live booking has no box and no trash icon.
     *
     * Checked across all three live statuses, not just one: the gate is
     * `canBeDeletedByCustomer()`, and a page that happened to hold only
     * completed rows would pass an assertion made against a pending one.
     */
    public function test_a_live_booking_offers_neither_a_checkbox_nor_a_delete_icon(): void
    {
        $user = $this->makeUser();

        $this->makeAppointment($user, $this->makeService(), ['status' => AppointmentStatus::Pending]);
        $this->makeAppointment($user, $this->makeService(), ['status' => AppointmentStatus::Confirmed]);
        $this->makeAppointment($user, $this->makeService(), ['status' => AppointmentStatus::InProgress]);

        $html = $this->actingAs($user)->get(route('appointments.index'))->assertOk()->getContent();

        // No select column at all, so there is no empty checkbox to wonder about.
        $this->assertStringNotContainsString('Select all on this page', $html);
        $this->assertStringNotContainsString('Delete selected', $html);
        $this->assertStringNotContainsString('aria-label="Select appointment', $html);
        $this->assertStringNotContainsString('fas fa-trash', $html);
    }

    public function test_a_cancelled_booking_is_deletable_like_a_completed_one(): void
    {
        $user = $this->makeUser();
        $this->makeAppointment($user, $this->makeService(), ['status' => AppointmentStatus::Cancelled]);

        $html = $this->actingAs($user)->get(route('appointments.index'))->assertOk()->getContent();

        $this->assertStringContainsString('name="ids[]"', $html);
        $this->assertStringContainsString('fas fa-trash', $html);
    }

    /* ------------------------------------------------------------------ */
    /* 2. The confirmation, not confirm()                                  */
    /* ------------------------------------------------------------------ */

    public function test_deletion_goes_through_the_shared_confirmation_dialog(): void
    {
        $user = $this->makeUser();
        $this->makeAppointment($user, $this->makeService(), ['status' => AppointmentStatus::Completed]);

        $html = $this->actingAs($user)->get(route('appointments.index'))->assertOk()->getContent();

        // The component, wired to the event both triggers dispatch.
        $this->assertStringContainsString('confirmDialog(', $html);
        $this->assertStringContainsString('x-on:confirm-appointment-delete.window', $html);
        $this->assertStringContainsString("\$dispatch('confirm-appointment-delete'", $html);
        $this->assertStringContainsString(route('appointments.destroy-many'), $this->unescaped($html));

        // Yes/No, and a real DELETE form behind them.
        $this->assertStringContainsString('value="DELETE"', $html);

        // Never the native prompt.
        $this->assertStringNotContainsString('confirm(', $html);
    }

    /* ------------------------------------------------------------------ */
    /* 3. The routes enforce the same rule the view draws                  */
    /* ------------------------------------------------------------------ */

    public function test_a_settled_booking_can_be_deleted(): void
    {
        $user = $this->makeUser();
        $appointment = $this->makeAppointment($user, $this->makeService(), ['status' => AppointmentStatus::Completed]);

        $this->actingAs($user)
            ->delete(route('appointments.destroy', $appointment))
            ->assertRedirect(route('appointments.index'));

        $this->assertSoftDeleted($appointment);
    }

    public function test_a_live_booking_cannot_be_deleted(): void
    {
        $user = $this->makeUser();

        foreach ([AppointmentStatus::Pending, AppointmentStatus::Confirmed, AppointmentStatus::InProgress] as $status) {
            $appointment = $this->makeAppointment($user, $this->makeService(), ['status' => $status]);

            $this->actingAs($user)
                ->delete(route('appointments.destroy', $appointment))
                ->assertForbidden();

            $this->assertNotSoftDeleted($appointment);
        }
    }

    public function test_someone_elses_booking_is_not_deletable(): void
    {
        $user = $this->makeUser();
        $other = $this->makeUser();

        $appointment = $this->makeAppointment($other, $this->makeService(), ['status' => AppointmentStatus::Completed]);

        $this->actingAs($user)
            ->delete(route('appointments.destroy', $appointment))
            ->assertForbidden();

        $this->assertNotSoftDeleted($appointment);
    }

    /* ------------------------------------------------------------------ */
    /* 4. The ticked set                                                   */
    /* ------------------------------------------------------------------ */

    public function test_the_ticked_set_is_deleted(): void
    {
        $user = $this->makeUser();
        $service = $this->makeService();

        $first = $this->makeAppointment($user, $service, ['status' => AppointmentStatus::Completed]);
        $second = $this->makeAppointment($user, $service, ['status' => AppointmentStatus::Cancelled]);

        $response = $this->actingAs($user)
            ->delete(route('appointments.destroy-many'), ['ids' => [$first->id, $second->id]])
            ->assertRedirect(route('appointments.index'));

        $this->assertSoftDeleted($first);
        $this->assertSoftDeleted($second);
        $this->assertStringContainsString('2 appointments deleted', session('status'));
    }

    /**
     * A payload mixing a settled and a live booking deletes only the settled
     * one, and says so.
     *
     * The status gate is re-checked in the query rather than trusted from the
     * payload — the checkbox is hidden on a live row, but a hidden control is a
     * convenience, not a guarantee.
     */
    public function test_a_live_booking_in_the_ticked_set_is_refused_and_reported(): void
    {
        $user = $this->makeUser();
        $service = $this->makeService();

        $settled = $this->makeAppointment($user, $service, ['status' => AppointmentStatus::Completed]);
        $live = $this->makeAppointment($user, $service, ['status' => AppointmentStatus::Pending]);

        $this->actingAs($user)
            ->delete(route('appointments.destroy-many'), ['ids' => [$settled->id, $live->id]])
            ->assertRedirect(route('appointments.index'));

        $this->assertSoftDeleted($settled);
        $this->assertNotSoftDeleted($live);

        // The count is what was removed, and the refusal is named rather than
        // silently dropped.
        $message = session('status');
        $this->assertStringContainsString('1 appointment deleted', $message);
        $this->assertStringContainsString('1 could not be deleted', $message);
    }

    public function test_someone_elses_id_in_the_ticked_set_is_not_deletable(): void
    {
        $user = $this->makeUser();
        $other = $this->makeUser();

        $mine = $this->makeAppointment($user, $this->makeService(), ['status' => AppointmentStatus::Completed]);
        $theirs = $this->makeAppointment($other, $this->makeService(), ['status' => AppointmentStatus::Completed]);

        $this->actingAs($user)
            ->delete(route('appointments.destroy-many'), ['ids' => [$mine->id, $theirs->id]])
            ->assertRedirect(route('appointments.index'));

        $this->assertSoftDeleted($mine);
        $this->assertNotSoftDeleted($theirs);
    }

    public function test_an_empty_ticked_set_is_refused_by_validation(): void
    {
        $user = $this->makeUser();
        $appointment = $this->makeAppointment($user, $this->makeService(), ['status' => AppointmentStatus::Completed]);

        $this->actingAs($user)
            ->from(route('appointments.index'))
            ->delete(route('appointments.destroy-many'), ['ids' => []])
            ->assertRedirect(route('appointments.index'))
            ->assertSessionHasErrors('ids');

        $this->assertNotSoftDeleted($appointment);
    }

    /** A soft-deleted row is gone from the list, not merely hidden. */
    public function test_a_deleted_booking_leaves_the_list(): void
    {
        $user = $this->makeUser();

        $appointment = $this->makeAppointment($user, $this->makeService(), ['status' => AppointmentStatus::Completed]);

        $this->actingAs($user)->get(route('appointments.index'))->assertOk()
            ->assertSee($appointment->reference_number);

        $this->actingAs($user)->delete(route('appointments.destroy', $appointment));

        // Scoped to the list rather than the whole document: the redirect carries
        // a flash naming the booking it deleted, and the toast legitimately
        // repeats the reference. The empty state is the honest signal.
        $this->actingAs($user)->get(route('appointments.index'))->assertOk()
            ->assertSee('No appointments here');

        $this->assertSame(0, $user->appointments()->count());
    }
}
