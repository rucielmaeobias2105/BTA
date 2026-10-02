<?php

namespace Tests\Feature;

use App\Enums\AppointmentStatus;
use App\Enums\ItemTag;
use App\Models\Appointment;
use App\Models\Technician;
use App\Notifications\AppointmentConfirmedNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

/**
 * The admin Appointments table as it stands now: no Reference or Down Payment
 * column, a status dropdown in place of the Approve/Decline buttons, and a
 * read-only View dialog.
 */
class AdminAppointmentTableTest extends TestCase
{
    use RefreshDatabase;

    /* ------------------------------------------------------------------ */
    /* Columns                                                             */
    /* ------------------------------------------------------------------ */

    public function test_the_reference_and_down_payment_columns_are_gone_from_the_table(): void
    {
        $admin = $this->makeAdmin();
        $this->makeAppointment($this->makeUser());

        $html = $this->actingAs($admin, 'admin')
            ->get(route('admin.appointments.index'))
            ->assertOk()
            ->getContent();

        // Header cells, not the words in general: the Manage screen still has a
        // "Reference" label for the GCash reference, and that is meant to stay.
        $this->assertStringNotContainsString('>Reference</th>', $html);
        $this->assertStringNotContainsString('>Down Payment</th>', $html);

        $this->assertStringNotContainsString('reference_number', $html);
        $this->assertStringNotContainsString('down_payment_status', $html);
    }

    public function test_the_remaining_columns_are_still_there(): void
    {
        $admin = $this->makeAdmin();
        $this->makeAppointment($this->makeUser());

        $html = $this->actingAs($admin, 'admin')
            ->get(route('admin.appointments.index'))
            ->assertOk()
            ->getContent();

        foreach (['Customer', 'Service(s)', 'Date &amp; Time', 'Technician', 'Status', 'Total', 'Actions'] as $header) {
            $this->assertStringContainsString('>'.$header.'</th>', $html, "Missing the {$header} column.");
        }
    }

    public function test_the_approve_and_decline_buttons_are_replaced_by_one_dropdown(): void
    {
        $admin = $this->makeAdmin();
        $this->makeAppointment($this->makeUser());

        $html = $this->actingAs($admin, 'admin')
            ->get(route('admin.appointments.index'))
            ->assertOk()
            ->getContent();

        $this->assertStringNotContainsString('>Approve</button>', $html);
        $this->assertStringNotContainsString('>Decline</button>', $html);
        $this->assertStringContainsString('name="status"', $html);
        $this->assertStringContainsString('appointmentStatus(', $html);
    }

    /* ------------------------------------------------------------------ */
    /* Row actions                                                         */
    /* ------------------------------------------------------------------ */

    public function test_the_row_edit_button_is_gone(): void
    {
        $admin = $this->makeAdmin();
        $appointment = $this->makeAppointment($this->makeUser());

        $html = $this->actingAs($admin, 'admin')
            ->get(route('admin.appointments.index'))
            ->assertOk()
            ->getContent();

        // Exactly one icon action per row, and it opens the dialog rather than
        // navigating. `x-ui.icon-action` renders the icon as SVG, so counting
        // the component's own class is what distinguishes one action from two.
        $this->assertSame(1, substr_count($html, 'class="icon-action'));
        $this->assertStringContainsString("\$dispatch('view-appointment', {$appointment->id})", $html);

        // Nothing in the table links straight to the Manage screen any more.
        $this->assertStringNotContainsString('href="'.route('admin.appointments.show', $appointment), $html);
    }

    public function test_the_view_dialog_shows_the_technician(): void
    {
        $admin = $this->makeAdmin();
        $technician = Technician::factory()->create(['name' => 'Maria Santos']);

        $this->makeAppointment($this->makeUser(), null, ['technician_id' => $technician->id]);

        $html = $this->actingAs($admin, 'admin')
            ->get(route('admin.appointments.index'))
            ->assertOk()
            ->getContent();

        // Carried in the dialog's payload, and labelled as its own field.
        $this->assertStringContainsString('Maria Santos', $html);
        $this->assertStringContainsString('Technician', $html);
        $this->assertStringContainsString('x-text="detail?.technician"', $html);
    }

    public function test_the_view_dialog_falls_back_to_no_preference(): void
    {
        $admin = $this->makeAdmin();
        $this->makeAppointment($this->makeUser());

        $html = $this->actingAs($admin, 'admin')
            ->get(route('admin.appointments.index'))
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('No preference', $html);
    }

    public function test_the_view_dialog_has_no_manage_button(): void
    {
        $admin = $this->makeAdmin();
        $appointment = $this->makeAppointment($this->makeUser());

        $html = $this->actingAs($admin, 'admin')
            ->get(route('admin.appointments.index'))
            ->assertOk()
            ->getContent();

        $this->assertStringNotContainsString('Manage booking', $html);
        $this->assertStringNotContainsString('manage_url', $html);

        // Nothing links out to the Manage screen. Matched on `href` rather than
        // the bare path, because the status form's action legitimately shares
        // the `/admin/appointments/{id}` prefix.
        $this->assertStringNotContainsString('href="'.route('admin.appointments.show', $appointment), $html);
    }

    /* ------------------------------------------------------------------ */
    /* Status dropdown                                                     */
    /* ------------------------------------------------------------------ */

    public function test_the_dropdown_offers_only_the_four_statuses_and_shows_the_current_one(): void
    {
        $admin = $this->makeAdmin();
        $this->makeAppointment($this->makeUser());

        $html = $this->actingAs($admin, 'admin')
            ->get(route('admin.appointments.index'))
            ->assertOk()
            ->getContent();

        foreach (['Confirmed', 'Start / In Progress', 'Declined', 'Completed'] as $label) {
            $this->assertStringContainsString('>'.$label.'</option>', $html);
        }

        // Declining is how the salon stops a booking, so there is no separate
        // "Cancelled" action to pick.
        $this->assertStringNotContainsString('>Cancelled</option>', $html);

        // The current status is the selected value, and is not selectable, so
        // the row cannot post the state it is already in.
        $this->assertMatchesRegularExpression(
            '/<option\s+value="pending"\s+selected\s+disabled[^>]*>Pending<\/option>/',
            $html,
        );
    }

    public function test_no_menu_offers_a_status_outside_the_four(): void
    {
        $allowed = ['confirmed', 'in_progress', 'declined', 'completed'];

        foreach (AppointmentStatus::cases() as $status) {
            foreach (array_keys($status->menu()) as $key) {
                $this->assertContains($key, $allowed, "{$status->value} offers an unexpected action: {$key}");
            }
        }

        $this->assertSame($allowed, AppointmentStatus::actionKeys());
    }

    public function test_a_pending_row_can_be_confirmed_through_the_dropdown(): void
    {
        Notification::fake();

        $admin = $this->makeAdmin();
        $user = $this->makeUser();
        $appointment = $this->makeAppointment($user);

        $this->actingAs($admin, 'admin')
            ->patchJson(route('admin.appointments.status', $appointment), ['status' => 'confirmed'])
            ->assertOk()
            ->assertJsonPath('status', 'confirmed');

        $appointment->refresh();

        $this->assertSame(AppointmentStatus::Confirmed, $appointment->status);
        $this->assertNotNull($appointment->confirmed_at);

        // The side effects the old Approve button fired are not lost.
        Notification::assertSentTo($user, AppointmentConfirmedNotification::class);
        $this->assertSame(1, $appointment->statusHistory()->count());
    }

    public function test_the_declined_action_saves_the_cancelled_state(): void
    {
        Notification::fake();

        $admin = $this->makeAdmin();
        $appointment = $this->makeAppointment($this->makeUser());

        $this->actingAs($admin, 'admin')
            ->patchJson(route('admin.appointments.status', $appointment), ['status' => 'declined'])
            ->assertOk();

        $appointment->refresh();

        $this->assertSame(AppointmentStatus::Cancelled, $appointment->status);
        $this->assertNotNull($appointment->cancelled_at);
    }

    public function test_declining_frees_the_slot_and_returns_the_stock(): void
    {
        Notification::fake();

        $service = $this->makeService(['price' => 500]);
        $item = $this->makeItem(['quantity' => 8, 'reorder_threshold' => 5, 'status_tag' => ItemTag::Available]);
        $this->linkItemToService($service, $item, 2);

        $admin = $this->makeAdmin();
        $appointment = $this->makeAppointment($this->makeUser(), $service);

        $this->actingAs($admin, 'admin')
            ->patchJson(route('admin.appointments.status', $appointment), ['status' => 'declined'])
            ->assertOk();

        $this->assertEquals(10, (float) $item->refresh()->quantity);

        // A cancelled booking no longer holds its time, so the slot is offered
        // to the next customer.
        $availability = \App\Services\BookingAvailability::make();
        $date = $appointment->preferred_date->toDateString();

        $this->assertNotContains('10:00', $availability->takenTimes($date, $appointment->id));
        $this->assertContains('10:00', $availability->availableSlots($date, null, $appointment->id));
    }

    /**
     * A dropdown is a menu, not a rule. `in_progress` cannot go back to
     * `confirmed`, so the server refuses the jump even though a hand-written
     * POST can name it.
     */
    public function test_an_illegal_transition_is_refused_even_when_posted_directly(): void
    {
        Notification::fake();

        $admin = $this->makeAdmin();
        $appointment = $this->makeAppointment($this->makeUser(), null, [
            'status' => AppointmentStatus::InProgress,
        ]);

        $this->actingAs($admin, 'admin')
            ->patchJson(route('admin.appointments.status', $appointment), ['status' => 'confirmed'])
            ->assertStatus(422)
            ->assertJsonPath('message', 'An appointment that is In Progress cannot be marked as Confirmed.');

        $this->assertSame(AppointmentStatus::InProgress, $appointment->fresh()->status);
        Notification::assertNothingSent();
    }

    public function test_an_illegal_transition_from_the_plain_form_redirects_with_an_error(): void
    {
        $admin = $this->makeAdmin();
        $appointment = $this->makeAppointment($this->makeUser(), null, [
            'status' => AppointmentStatus::Completed,
        ]);

        // A finished visit can only be reopened — going straight back to a fresh
        // confirmation is not a transition.
        $this->actingAs($admin, 'admin')
            ->from(route('admin.appointments.index'))
            ->patch(route('admin.appointments.status', $appointment), ['status' => 'confirmed'])
            ->assertSessionHasErrors('status');

        $this->assertSame(AppointmentStatus::Completed, $appointment->fresh()->status);
    }

    public function test_cancelled_is_not_an_action_key(): void
    {
        $admin = $this->makeAdmin();
        $appointment = $this->makeAppointment($this->makeUser());

        // The stored state is still `cancelled`, but the only way an admin
        // reaches it is the `declined` action.
        $this->actingAs($admin, 'admin')
            ->patchJson(route('admin.appointments.status', $appointment), ['status' => 'cancelled'])
            ->assertStatus(422);

        $this->assertSame(AppointmentStatus::Pending, $appointment->fresh()->status);
    }

    public function test_pending_is_not_an_action_anymore(): void
    {
        $admin = $this->makeAdmin();
        $appointment = $this->makeAppointment($this->makeUser());

        // `pending` is a state the salon waits in, not something an admin
        // moves a booking back to from the list.
        $this->actingAs($admin, 'admin')
            ->patchJson(route('admin.appointments.status', $appointment), ['status' => 'pending'])
            ->assertStatus(422);
    }

    /**
     * A completed row is locked: greyed out, non-interactive, and refused at the
     * route even by a hand-built request.
     *
     * This is the opposite of what the test here used to assert. It pinned
     * "no row should ever render a disabled status control" on the grounds that
     * a dead dropdown reads as a broken control, and offered 'Reopen / In
     * Progress' so a mis-filed booking had an escape hatch. Completed is now
     * terminal on purpose — a completed service is a record of work that
     * happened — so the control is disabled and the recovery path is gone.
     *
     * The route check is the part that matters. A greyed-out `<select>` is a
     * suggestion; `canTransitionTo()` returning false is what actually stops it,
     * and without that assertion this could be reverted to a cosmetic-only
     * change that a crafted PATCH walks straight through.
     */
    public function test_a_completed_row_is_locked(): void
    {
        $admin = $this->makeAdmin();
        $appointment = $this->makeAppointment($this->makeUser(), null, [
            'status' => AppointmentStatus::Completed,
        ]);

        $html = $this->actingAs($admin, 'admin')
            ->get(route('admin.appointments.index'))
            ->assertOk()
            ->getContent();

        $this->assertMatchesRegularExpression(
            '/<select[^>]*name="status"[^>]*\sdisabled/',
            $html,
            'The status control must be disabled on a completed row.',
        );

        // And it must say why, so a dead dropdown is not read as a bug.
        $this->assertStringContainsString('A completed service is final', $html);

        // Every other status keeps a live control.
        $this->assertSame(1, preg_match_all('/<select[^>]*name="status"/', $html));
        $this->assertSame(1, preg_match_all('/<select[^>]*name="status"[^>]*\sdisabled/', $html));
    }

    /** @return array<string, array{0: AppointmentStatus}> */
    public static function editableStatuses(): array
    {
        return [
            'pending' => [AppointmentStatus::Pending],
            'confirmed' => [AppointmentStatus::Confirmed],
            'in_progress' => [AppointmentStatus::InProgress],
            'cancelled' => [AppointmentStatus::Cancelled],
        ];
    }

    /**
     * Only Completed is finished. Everything else must keep a working dropdown.
     *
     * @dataProvider editableStatuses
     */
    public function test_every_unfinished_row_offers_a_way_out_of_its_status(AppointmentStatus $status): void
    {
        $this->assertFalse($status->isLocked(), "A {$status->value} appointment should stay editable.");
        $this->assertNotEmpty(
            $status->menu(),
            "A {$status->value} appointment has no legal transition, so its dropdown would be dead.",
        );
    }

    public function test_completed_is_the_only_locked_status(): void
    {
        $locked = array_values(array_map(
            fn (AppointmentStatus $status): string => $status->value,
            array_filter(AppointmentStatus::cases(), fn (AppointmentStatus $status): bool => $status->isLocked()),
        ));

        $this->assertSame(
            ['completed'],
            $locked,
            'If a new status is meant to be final it has to be a deliberate change, not a side effect.',
        );
    }

    /** A crafted request must not be able to move a completed booking. */
    public function test_a_completed_booking_cannot_be_moved_by_a_hand_built_request(): void
    {
        $admin = $this->makeAdmin();
        $appointment = $this->makeAppointment($this->makeUser(), null, [
            'status' => AppointmentStatus::Completed,
            'completed_at' => now(),
        ]);

        // `in_progress` is a valid action key in its own right, so this gets past
        // validation and is turned away by the transition check — which is the
        // point. It is a `message`, not a validation error on `status`, because
        // the value was legal; the *transition* was not.
        $this->actingAs($admin, 'admin')
            ->patchJson(route('admin.appointments.status', $appointment), ['status' => 'in_progress'])
            ->assertStatus(422)
            ->assertJsonPath('message', 'An appointment that is Completed cannot be marked as In Progress.');

        $this->assertSame(
            AppointmentStatus::Completed,
            $appointment->fresh()->status,
            'The disabled dropdown is only half the rule; the route has to refuse too.',
        );
    }

    /** The completion stamp must survive a refused attempt to re-open. */
    public function test_a_refused_reopen_leaves_the_completion_stamp_alone(): void
    {
        $admin = $this->makeAdmin();
        $completedAt = now()->subHour();

        $appointment = $this->makeAppointment($this->makeUser(), null, [
            'status' => AppointmentStatus::Completed,
            'completed_at' => $completedAt,
        ]);

        $this->actingAs($admin, 'admin')
            ->patchJson(route('admin.appointments.status', $appointment), ['status' => 'in_progress'])
            ->assertStatus(422);

        $this->assertNotNull($appointment->fresh()->completed_at);
    }

    public function test_a_cancelled_booking_can_be_accepted_again(): void
    {
        Notification::fake();

        $service = $this->makeService(['price' => 500]);
        $item = $this->makeItem(['quantity' => 10, 'reorder_threshold' => 5, 'status_tag' => ItemTag::Available]);
        $this->linkItemToService($service, $item, 2);

        $admin = $this->makeAdmin();
        $appointment = $this->makeAppointment($this->makeUser(), $service, [
            'status' => AppointmentStatus::Cancelled,
        ]);

        // Declining gave the 2 reserved units back; re-accepting takes them again.
        $item->update(['quantity' => 12]);
        $item->syncStatusTag();

        $this->actingAs($admin, 'admin')
            ->patchJson(route('admin.appointments.status', $appointment), ['status' => 'confirmed'])
            ->assertOk();

        $this->assertSame(AppointmentStatus::Confirmed, $appointment->fresh()->status);
        $this->assertEquals(10, (float) $item->refresh()->quantity);
    }

    public function test_the_dropdown_still_posts_without_javascript(): void
    {
        $admin = $this->makeAdmin();
        $appointment = $this->makeAppointment($this->makeUser());

        $this->actingAs($admin, 'admin')
            ->from(route('admin.appointments.index'))
            ->patch(route('admin.appointments.status', $appointment), ['status' => 'in_progress'])
            ->assertRedirect(route('admin.appointments.index'))
            ->assertSessionHas('toast');

        $appointment->refresh();

        $this->assertSame(AppointmentStatus::InProgress, $appointment->status);
        $this->assertNotNull($appointment->started_at);
    }

    /* ------------------------------------------------------------------ */
    /* View dialog                                                         */
    /* ------------------------------------------------------------------ */

    public function test_the_view_dialog_carries_the_services_date_time_and_request(): void
    {
        $admin = $this->makeAdmin();
        $service = $this->makeService(['name' => 'Gelish Manicure', 'price' => 750, 'duration_minutes' => 90]);

        $appointment = $this->makeAppointment($this->makeUser(), $service, [
            'preferred_date' => today()->addDays(3),
            'preferred_time' => '14:30',
            'allergies' => 'Shellac allergy',
            'special_request' => 'Prefers a quiet room',
            'last_services_availed' => 'Gelish last May',
        ]);

        $html = $this->actingAs($admin, 'admin')
            ->get(route('admin.appointments.index'))
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('view-appointment', $html);
        $this->assertStringContainsString('appointmentViewer', $html);

        // The payload is embedded in the page, so the dialog opens with no
        // request of its own.
        foreach (['Gelish Manicure', '90 min', 'Shellac allergy', 'Prefers a quiet room', 'Gelish last May', '2:30 PM'] as $expected) {
            $this->assertStringContainsString($expected, $html);
        }

        $this->assertStringContainsString(today()->addDays(3)->format('l, M j, Y'), $html);
    }

    public function test_the_view_dialog_is_read_only(): void
    {
        $admin = $this->makeAdmin();
        $this->makeAppointment($this->makeUser());

        $html = $this->actingAs($admin, 'admin')
            ->get(route('admin.appointments.index'))
            ->assertOk()
            ->getContent();

        // One form per row, and it is the status dropdown's. The View dialog
        // adds none, and carries no editable control either.
        $this->assertSame(1, substr_count($html, '/status"'));
        $this->assertStringNotContainsString('<textarea', $html);
        $this->assertStringNotContainsString('<input type="text"', $html);
    }
}
