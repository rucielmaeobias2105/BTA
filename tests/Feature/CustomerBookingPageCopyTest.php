<?php

namespace Tests\Feature;

use App\Enums\AppointmentStatus;
use App\Models\SalonSetting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

/**
 * The "Book Appointment" page after its copy cleanup, and after the down
 * payment step lost its card.
 *
 * Three things are pinned here:
 *
 *   - the helper copy and the closure-dates list are gone;
 *   - the date rules that are left are still enforced, because the list that used
 *     to spell them out was never the thing enforcing them. The blocked-date rule
 *     went with the Calendar & Blocked Dates feature — nothing can create a block
 *     any more — so what replaced it is the operating-hours rule: a day the salon
 *     is closed is refused, and the picker says why. If dropping the closure prose
 *     had quietly opened the door to a closed-day booking, this file is what would
 *     notice; and
 *   - the down payment step is flat while every other step keeps its card, so
 *     neither can drift the other way unnoticed.
 */
class CustomerBookingPageCopyTest extends TestCase
{
    use RefreshDatabase;

    private function html(): string
    {
        return $this->actingAs($this->makeUser())
            ->get(route('appointments.create'))
            ->assertOk()
            ->getContent();
    }

    /* ------------------------------------------------------------------ */
    /* 1. The copy that came out                                         */
    /* ------------------------------------------------------------------ */

    public function test_the_header_is_the_title_and_nothing_else(): void
    {
        $html = $this->html();

        $this->assertMatchesRegularExpression('/>\s*Book Appointment\s*<\/h1>/', $html);

        $this->assertStringNotContainsString('Reservations', $html);
        $this->assertStringNotContainsString('Choose your treatments, pick a date and time', $html);
    }

    public function test_the_card_helper_lines_are_gone(): void
    {
        $html = $this->html();

        $this->assertStringNotContainsString('Tap a service to add it to your booking.', $html);
        $this->assertStringNotContainsString('Only open days and available slots can be selected.', $html);
        $this->assertStringNotContainsString('Helps your therapist prepare ahead of time.', $html);

        // The cards themselves are untouched — only their subtitles went. The
        // sequence runs 1…7: there is no down payment step, so Terms moved up.
        foreach ([
            '1. Selected Service',
            '2. Your Details',
            '3. Preferred Date &amp; Time',
            '4. Preferences &amp; Health Notes',
            '5. Last Service(s) Availed',
            '6. Choose Technician &amp; Special Request',
            '7. Terms &amp; Conditions',
        ] as $title) {
            $this->assertStringContainsString($title, $html);
        }

        // No step 8 left behind by the renumber.
        $this->assertStringNotContainsString('8. Terms', $html);
    }

    /* ------------------------------------------------------------------ */
    /* 2. The closure list is gone, and nothing replaces it                */
    /* ------------------------------------------------------------------ */

    /**
     * No closure copy anywhere on the page.
     *
     * The list was removed first, then the feature behind it, so there is now
     * nothing that could put it back: no admin can block a date, and no screen
     * spells closures out. Asserted with the salon closed on one weekday, which
     * is the only way a date becomes unavailable now — and the reason has to come
     * from the slot endpoint rather than from prose on this page.
     */
    public function test_there_is_no_closure_prose_on_the_page(): void
    {
        $salon = SalonSetting::current();
        $salon->forceFill(['operating_hours' => ['monday' => ['09:00', '17:00']]])->save();

        $html = $this->html();

        foreach ([
            'Upcoming closure dates',
            'Provincial holiday',
            'Team training day',
            'Salon closed',
            'Dates we are closed',
        ] as $copy) {
            $this->assertStringNotContainsString($copy, $html);
        }
    }

    /* ------------------------------------------------------------------ */
    /* 3. …and the rules that are left are still enforced                  */
    /* ------------------------------------------------------------------ */

    /**
     * A closed weekday is refused on submit.
     *
     * This is the replacement for the blocked-date case that used to sit here:
     * the mechanism changed (operating hours rather than a blocked-date table) but
     * the guarantee did not — a day the salon is not open cannot be booked, and
     * the reason reaches the customer.
     */
    public function test_a_closed_weekday_is_still_refused_on_submit(): void
    {
        Notification::fake();

        $user = $this->makeUser();
        $service = $this->makeService();

        $salon = SalonSetting::current();
        $salon->forceFill(['operating_hours' => ['monday' => ['09:00', '17:00']]])->save();

        $sunday = Carbon::parse(today()->next(Carbon::SUNDAY))->toDateString();

        $this->actingAs($user)
            ->from(route('appointments.create'))
            ->post(route('appointments.store'), [
                'customer_name' => 'Ana Reyes',
                'customer_phone' => '09171234567',
                'preferred_date' => $sunday,
                'preferred_time' => '10:00',
                'special_request' => '',
                // The nested shape `StoreBookingRequest` actually validates.
                // A flat `[$id]` array — which is what this test used to post —
                // fails on `services.*.service_id` and would make the assertion
                // below pass for entirely the wrong reason.
                'services' => [[
                    'service_id' => $service->id,
                    'quantity' => 1,
                ]],
                'agree_terms' => '1',
            ])
            ->assertSessionHasErrors('preferred_date');

        // The failure is the date, not the basket: a payload the form would never
        // produce must not be what makes this test green.
        $this->assertArrayNotHasKey('services', session('errors')->getBag('default')->toArray());

        $this->assertDatabaseCount('appointments', 0);

        // And the server's wording is the closed-day one, so the customer is told
        // why rather than just being refused.
        $this->assertContains(
            'We are closed on Sundays.',
            \App\Services\BookingAvailability::make()->dateProblems($sunday),
        );
    }

    /**
     * The picker still carries its bounds and still explains a refused date.
     *
     * The reason line is the replacement for the list, and it now quotes the
     * server's own `problems` list from the slots endpoint — so it cannot describe
     * a date differently from what a submit would do.
     */
    public function test_the_picker_still_carries_its_bounds_and_explains_a_refused_date(): void
    {
        $html = $this->html();

        // The bounds come from the booking rules, not from any list.
        $this->assertStringContainsString('name="preferred_date"', $html);
        $this->assertStringContainsString('x-bind:min="minDate"', $html);
        $this->assertStringContainsString('name="preferred_time"', $html);

        // The live reason line is still there — it is what replaced the list, and
        // it is bound to `problems` rather than to a blocked-date map.
        $this->assertStringContainsString('dateBlockedReason', $html);
        $this->assertStringContainsString('data.problems', $html);
    }
    /* ------------------------------------------------------------------ */
    /* 4. There is no down payment step at all                            */
    /* ------------------------------------------------------------------ */

    /**
     * The step is gone rather than hidden behind a setting.
     *
     * Nothing about paying appears on the booking form any more: no heading, no
     * explanation of the percentage, no reference field, no running figure. The
     * salon takes no deposit for a web booking, so a box asking for one would be
     * worse than no box.
     */
    public function test_there_is_no_down_payment_step(): void
    {
        $html = $this->html();

        $this->assertStringNotContainsString('Down Payment', $html);
        $this->assertStringNotContainsString('down_payment_reference', $html);
        $this->assertStringNotContainsString('GCash Reference Number', $html);
        $this->assertStringNotContainsString('down payment', $html);
        $this->assertStringNotContainsString('GCash', $html);
        $this->assertStringNotContainsString('expectedDownPayment', $html);
        $this->assertStringNotContainsString('Awaiting Verification', $html);

        // The label it used to carry, so the section is not merely repainted.
        $this->assertStringNotContainsString('down-payment-heading', $html);

        // The summary is now the total and nothing else. The Reference Number
        // row is gone with the rest of the never-fillable-in fields: the
        // reference is still generated and still shown after the booking, where
        // it is a fact rather than a promise.
        $this->assertStringContainsString('Total Amount', $html);
        $this->assertStringNotContainsString('Reference Number', $html);
        $this->assertStringNotContainsString('Generated automatically on submit', $html);
    }

    /**
     * The deposit is off by default, not merely hidden.
     *
     * Left on, `BookingService` keeps computing `down_payment_amount` for every
     * new booking while the status reads `Not Required` — an amount with nothing
     * behind it that the admin's appointment screen would then have to explain.
     */
    public function test_the_salon_takes_no_deposit_by_default(): void
    {
        $this->assertFalse(SalonSetting::current()->down_payment_required);
    }

    /**
     * The page does not even compute the figure it used to show.
     *
     * `expectedDownPayment` and `downPaymentPercent` were removed from the Alpine
     * component and the controller's view data along with the step. Left in they
     * would be dead weight that looks live.
     */
    public function test_the_page_no_longer_computes_a_deposit_figure(): void
    {
        $this->assertStringNotContainsString('downPaymentPercent', $this->html());

        $controller = (string) file_get_contents(
            app_path('Http/Controllers/Customer/AppointmentController.php'),
        );

        $this->assertStringNotContainsString('expectedDownPayment', $controller);
    }

    /**
     * No payment control of any kind: no method picker, no receipt upload, and no
     * reference field either.
     */
    public function test_there_is_no_payment_control_of_any_kind(): void
    {
        $html = $this->html();

        $this->assertStringNotContainsString('name="payment_method"', $html);
        $this->assertStringNotContainsString('type="file"', $html);
        $this->assertStringNotContainsString('name="down_payment', $html);
    }
    /* ------------------------------------------------------------------ */
    /* 5. Nothing else moved                                              */
    /* ------------------------------------------------------------------ */

    /** The form still submits the same fields it always did. */
    public function test_the_booking_form_still_posts_every_field(): void
    {
        $html = $this->html();

        foreach ([
            'customer_name',
            'customer_phone',
            'preferred_date',
            'preferred_time',
            // `allergies` is no longer a posted field. It is the *column* the
            // salon reads, and step 4 now posts `allergy_preference` plus
            // `allergies_other`, which `StoreBookingRequest::healthNote()` folds
            // into it. Asserting the column name as a field name would pin a
            // hidden input that no longer exists, and would pass even if nothing
            // wrote the column any more.
            'allergy_preference',
            'allergies_other',
            'last_services_availed_note',
            'technician_id',
            'special_request',
            'agree_terms',
        ] as $field) {
            $this->assertStringContainsString('name="'.$field.'"', $html, "The {$field} field should still be posted.");
        }

        $this->assertStringContainsString(route('appointments.store'), $html);
        $this->assertStringContainsString('Confirm Booking', $html);
    }

    /**
     * Step 5 asks a first-timer what they had done before, and shows a returning
     * customer their own record instead.
     *
     * These are two different fields rather than one field shown more or less
     * prominently: there is nothing to read out of the system for somebody with
     * no history, and there is no reason to ask a returning customer to retype
     * what the system already holds.
     *
     * The old step offered a select of past services alongside the free-text box
     * for everybody. That is gone: a returning customer is shown the answer,
     * which is both more accurate than retyping it and available to the salon on
     * the booking either way.
     */
    public function test_step_five_asks_a_first_timer_and_informs_a_returning_one(): void
    {
        $firstTimer = $this->html();

        // Asked, in their own words, and required — "we have no idea what you
        // have had before" is not a record a stylist can work from.
        $this->assertStringContainsString('name="last_services_availed_note"', $firstTimer);
        $this->assertStringContainsString('first visit with us', $firstTimer);
        $this->assertStringNotContainsString('Welcome back', $firstTimer);

        $user = $this->makeUser();
        $this->makeAppointment($user, $this->makeService([
            'name' => 'Gloss Manicure',
        ]), ['status' => AppointmentStatus::Completed]);

        $returning = $this->actingAs($user)
            ->get(route('appointments.create'))
            ->assertOk()
            ->getContent();

        // Shown, with the service from their own record, and the visit it came
        // from so the salon is not quoting a guess.
        $this->assertStringContainsString('Gloss Manicure', $returning);

        // The greeting that stood above this list is gone. It restated the
        // heading two lines up, and told a returning customer they had returned,
        // which the list under it already shows by existing.
        $this->assertStringNotContainsString('Welcome back', $returning);

        // Submitted for them — the hidden input carries the same text the list
        // shows, so the booking is documented whether or not anyone retyped it.
        $this->assertMatchesRegularExpression(
            '/name="last_services_availed"[^>]*value="Gloss Manicure"/',
            $returning
        );

        // And not asked: no manual entry for somebody who did not need one.
        $this->assertStringNotContainsString('name="last_services_availed_note"', $returning);
        $this->assertStringNotContainsString('What services have you had elsewhere?', $returning);

        // The select the old step offered is gone for both cases.
        $this->assertStringNotContainsString('Pick from your history', $firstTimer);
        $this->assertStringNotContainsString('Pick from your history', $returning);
    }
}
