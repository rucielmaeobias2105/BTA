<?php

namespace Tests\Feature;

use App\Enums\AppointmentStatus;
use App\Enums\BookingPreference;
use App\Models\Appointment;
use App\Models\Service;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

/**
 * The Book Appointment page after it became one wizard card.
 *
 * What is pinned here is the shape of the thing and the four steps that changed
 * their behaviour:
 *
 *   - the seven steps live inside one `bta-card`, not seven cards, and are moved
 *     between by a stepper rather than by a long scroll;
 *   - step 1 groups services by category and shows four per category before a
 *     "See More", the same way the Browse Services cards do;
 *   - step 4's primary input is a dropdown rather than a checkbox grid, and the
 *     answer is stored as its sentence;
 *   - step 5 asks a first-timer and informs a returning customer, and the server
 *     requires it only of the first;
 *   - step 7's checkbox is the one gate this form has to enforce itself, because
 *     the form is `novalidate`.
 *
 * The submission rules that were already here are covered by
 * `BookingCreationTest` and `BookingRulesTest`; this file is about the page.
 */
class BookingWizardTest extends TestCase
{
    use RefreshDatabase;

    private function html(?\App\Models\User $user = null): string
    {
        // `/book` is behind `auth.customer`, so there is no guest version of this
        // page to assert on. A signed-up customer with no completed appointments
        // is the first-timer case, and that is what a first-timer always is.
        return $this->actingAs($user ?: $this->makeUser())
            ->get(route('appointments.create'))
            ->assertOk()
            ->getContent();
    }

    private function payload(array $overrides = []): array
    {
        return array_merge([
            'services' => [['service_id' => $this->makeService(['price' => 500])->id, 'quantity' => 1]],
            'customer_name' => 'Ana Reyes',
            'customer_phone' => '09171234567',
            'preferred_date' => $this->bookableDate(),
            'preferred_time' => '10:00',
            'last_services_availed_note' => 'Glow Manicure',
            'agree_terms' => '1',
        ], $overrides);
    }

    /**
     * Post a booking as a customer.
     *
     * `/book` is behind `auth.customer`, so an unauthenticated post would be a
     * redirect to the login page and every assertion below would be measuring
     * that instead of the booking. Posting as a fresh account by default also
     * makes the first-timer case the default, which is the one most of these
     * tests are about.
     */
    private function book(array $overrides = [], ?\App\Models\User $user = null)
    {
        return $this->actingAs($user ?: $this->makeUser())
            ->post(route('appointments.store'), $this->payload($overrides));
    }

    /* ------------------------------------------------------------------ */
    /* 1. One card, not seven                                              */
    /* ------------------------------------------------------------------ */

    /**
     * The seven steps are sections of one card.
     *
     * Asserted as a single `bta-card` wrapping all seven headings, and as the
     * absence of the seven `x-ui.card`s it used to be. Counting the cards is the
     * part that matters: a single card around everything, with seven headings in
     * it, is the shape this is about, and a regression that put one step back in
     * its own card would still contain both headings.
     */
    public function test_all_seven_steps_live_in_one_card(): void
    {
        $this->makeService();

        $html = $this->html();

        foreach ([
            '1. Selected Service',
            '2. Your Details',
            '3. Preferred Date &amp; Time',
            '4. Preferences &amp; Health Notes',
            '5. Last Service(s) Availed',
            '6. Choose Technician &amp; Special Request',
            '7. Terms &amp; Conditions',
        ] as $heading) {
            $this->assertStringContainsString($heading, $html);
        }

        // One card shell holds the seven steps, and one other holds the Booking
        // Summary. That second one is deliberate — the summary is not a step, and
        // it has to stay visible while the steps change — so the count that
        // matters is "two", not "one".
        $this->assertSame(
            2,
            substr_count($html, 'class="bta-card overflow-hidden'),
            'The seven steps must share one card shell; only the summary keeps its own.'
        );

        // And no step is left in an `x-ui.card` of its own. This is what would
        // fail first if one step were put back into its own box.
        $this->assertStringNotContainsString('x-ui.card', $html);
    }

    /**
     * The steps are moved by Back and Continue, and the header only counts.
     *
     * A row of clickable step pills used to sit under the counter. It is gone: it
     * restated the counter, and on a phone it wrapped into two ragged rows that
     * read as a menu rather than as progress. What has to survive is the counter
     * and the two buttons.
     */
    public function test_the_steps_are_moved_by_back_and_continue(): void
    {
        $this->makeService();

        $html = $this->html();

        // Seven panels, each shown on its own step.
        for ($step = 1; $step <= 7; $step++) {
            $this->assertStringContainsString('x-show="step === '.$step.'"', $html);
        }

        // The counter stays, and is announced when it changes.
        $this->assertStringContainsString('Step <span x-text="step">1</span> of 7', $html);
        $this->assertStringContainsString('aria-live="polite"', $html);

        $this->assertStringContainsString('x-on:click="next()"', $html);
        $this->assertStringContainsString('x-on:click="back()"', $html);

        // No step pills: no list, no jump buttons, no per-chip state.
        $this->assertStringNotContainsString('aria-label="Booking steps"', $html);
        $this->assertStringNotContainsString('x-on:click="goTo(', $html);

        // And the count of steps is one number, sent from PHP rather than typed
        // into the markup as well, so the counter and the panels cannot disagree.
        $this->assertStringContainsString('"stepCount":7', $html);
    }

    /**
     * Nothing is gated, in the browser.
     *
     * Continue must always advance, the Confirm button must never be disabled,
     * and there must be no submit handler second-guessing the form. The server is
     * the only gate — which is why the rules below it are the ones that matter.
     */
    public function test_nothing_blocks_the_form_in_the_browser(): void
    {
        $this->makeService();

        $html = $this->html();

        // No client-side submit guard, and no "what is missing" nag. Asserted on
        // the call forms — `guardSubmit(event)`, `canSubmit()` — because these
        // names survive in the file's comments, where they are explained as
        // removed, and a bare substring search would match that explanation.
        $this->assertStringNotContainsString('x-on:submit=', $html);
        $this->assertStringNotContainsString('guardSubmit(', $html);
        $this->assertStringNotContainsString('canSubmit()', $html);
        $this->assertStringNotContainsString('missingMessage()', $html);
        $this->assertStringNotContainsString('firstProblemStep()', $html);

        // The submit button is never disabled — not even for an empty basket.
        $this->assertStringNotContainsString('x-bind:disabled="selectedIds.length === 0"', $html);

        // And `next()` is a plain move, with no guard on it.
        $this->assertMatchesRegularExpression(
            '/next\(\)\s*\{\s*this\.goTo\(this\.step \+ 1\);\s*\}/',
            $html
        );
    }

    /**
     * An empty, ungated form is still refused by the server.
     *
     * The point of removing the client gate: it is only safe because the server
     * is the real one. If this ever passes, a booking can be saved with nothing
     * in it.
     */
    public function test_an_empty_booking_is_still_refused_by_the_server(): void
    {
        $this->makeSalonSettings();

        // No services at all.
        $this->book(['services' => []])
            ->assertSessionHasErrors('services');

        $this->assertDatabaseCount('appointments', 0);
    }

    /**
     * A booking without a name, a phone number or the agreement is refused.
     *
     * The minimum the server keeps: the three fields without which a booking
     * cannot be acted on, plus a service. Each asserted on its own, because a
     * payload that omits all three at once would pass even if only one of them
     * were actually checked.
     */
    public function test_the_three_essentials_are_each_required(): void
    {
        $this->makeSalonSettings();

        $this->book(['customer_name' => ''])->assertSessionHasErrors('customer_name');
        $this->book(['customer_phone' => ''])->assertSessionHasErrors('customer_phone');
        $this->book(['agree_terms' => null])->assertSessionHasErrors('agree_terms');

        $this->assertDatabaseCount('appointments', 0);
    }

    /**
     * A booking with only the essentials is accepted.
     *
     * The flip side of the rule above: the server checks what a booking cannot
     * exist without, and nothing more. A first-timer's step-5 answer is one of
     * those; a preference, a special request and a technician are not.
     */
    public function test_only_the_essentials_are_required(): void
    {
        Notification::fake();

        $this->makeSalonSettings();
        $service = $this->makeService(['price' => 500]);

        $this->book([
            'services' => [['service_id' => $service->id, 'quantity' => 1]],
            // No preference, no special request, no technician.
        ])->assertSessionHasNoErrors();

        $this->assertDatabaseCount('appointments', 1);
    }

    /* ------------------------------------------------------------------ */
    /* 2. Step 1 — four per category, then See More                        */
    /* ------------------------------------------------------------------ */

    /**
     * Four services per category, then a toggle.
     *
     * The Browse Services page shows four rows per category card and hides the
     * rest behind "See All". This step does the same thing, so a customer who has
     * read the price list meets the same shape here instead of a flat grid of
     * thirty checkboxes.
     */
    public function test_step_one_shows_four_services_per_category_before_a_toggle(): void
    {
        $this->makeCategory('Manicure & Pedicure', 9);
        $this->makeCategory('Threading', 2);

        $html = $this->html();

        // The long category gets the disclosure, with the hidden count in its
        // label so the button says what it is going to show.
        $this->assertStringContainsString('See More (5 more)', $html);
        $this->assertStringContainsString('See Less', $html);

        // The short one does not: a toggle that reveals nothing is worse than no
        // toggle.
        $this->assertSame(1, substr_count($html, 'See More (5 more)'));

        // Both categories are labelled and counted.
        $this->assertStringContainsString('Manicure &amp; Pedicure', $html);
        $this->assertStringContainsString('9 services', $html);
        $this->assertStringContainsString('2 services', $html);
    }

    /**
     * A category of exactly four gets no disclosure.
     *
     * The boundary, because `> 4` and `>= 4` both pass the nine-service case and
     * only one of them is right.
     */
    public function test_a_category_of_exactly_four_gets_no_toggle(): void
    {
        $this->makeCategory('Threading', 4);

        $html = $this->html();

        $this->assertStringNotContainsString('See More', $html);
        $this->assertStringNotContainsString('See Less', $html);
    }

    /**
     * An opened category stays open.
     *
     * This is the whole point of step 1's disclosure state. It used to live in a
     * per-category Alpine scope holding a bare boolean, which is right for the
     * Browse Services cards — where a category card is the whole page — and wrong
     * here, because this list re-renders around it: ticking a service redraws the
     * basket in the summary, and every step change flips panel visibility. Either
     * one re-initialises nested scopes, the boolean goes back to false, and a
     * category the customer had opened silently collapsed.
     *
     * So the state is on the form's own scope, keyed by category.
     */
    public function test_an_opened_category_is_keyed_on_the_form_not_a_local_scope(): void
    {
        $this->makeCategory('Threading', 7);

        $html = $this->html();

        // Keyed state on the parent form, and the read/write pair that uses it.
        $this->assertStringContainsString('expandedCategories: {}', $html);
        $this->assertStringContainsString('isCategoryExpanded(key)', $html);
        $this->assertStringContainsString('toggleCategory(key)', $html);

        // The rows and the button both ask that key, not a bare `expanded`.
        $this->assertStringContainsString('x-show="isCategoryExpanded(\'threading\')"', $html);
        $this->assertStringContainsString('x-on:click="toggleCategory(\'threading\')"', $html);

        // And the old per-category scope is gone — it was the thing that reset.
        $this->assertStringNotContainsString('x-data="serviceCategory()"', $html);
        $this->assertStringNotContainsString('x-show="expanded"', $html);
    }

    /**
     * Two categories, two independent keys.
     *
     * A single shared boolean would pass the test above and still be wrong:
     * opening Manicure would close Threading. Asserted on the two slugs being two
     * distinct keys in the markup.
     */
    public function test_each_category_has_its_own_key(): void
    {
        $this->makeCategory('Manicure & Pedicure', 6);
        $this->makeCategory('Threading', 7);

        $html = $this->html();

        $this->assertStringContainsString('isCategoryExpanded(\'manicure-pedicure\')', $html);
        $this->assertStringContainsString('isCategoryExpanded(\'threading\')', $html);
    }

    /* ------------------------------------------------------------------ */
    /* 3. Step 4 — a dropdown                                              */
    /* ------------------------------------------------------------------ */

    /**
     * The primary input is a `<select>`, not a grid of checkboxes.
     *
     * This is the whole point of the step: one decision instead of eight, and a
     * closed set the server can validate.
     */
    public function test_step_four_is_a_dropdown_of_sensitivities(): void
    {
        $this->makeService();

        $html = $this->html();

        $this->assertMatchesRegularExpression(
            '/<select[^>]*name="allergy_preference"/',
            $html,
            'Step 4 must offer a dropdown.'
        );

        // The old grid is gone. Asserted on `allergy_0`, the only name the eight
        // checkbox row could have produced, rather than on one of the allergy
        // words — "latex" is still on the page, as a dropdown option, so a word
        // assertion would pass while the grid was still there.
        $this->assertStringNotContainsString('allergy_0', $html);
        $this->assertStringNotContainsString('select all that apply', $html);

        // Its options are the enum's own labels, which read as sentences.
        foreach (['Allergy to latex', 'Allergy to nickel', 'Sensitive to fragrance'] as $option) {
            $this->assertStringContainsString($option, $html);
        }

        // The free-text box stays, for the detail that will not fit a label.
        $this->assertStringContainsString('name="allergies_other"', $html);
    }

    /** The dropdown's answer reaches the column as words, not as a backing value. */
    public function test_the_dropdown_answer_is_stored_as_its_sentence(): void
    {
        Notification::fake();

        $this->makeSalonSettings();
        $service = $this->makeService(['price' => 500]);

        $this->book([
            'services' => [['service_id' => $service->id, 'quantity' => 1]],
            'allergy_preference' => BookingPreference::WeakOrBrittleNails->value,
        ])->assertSessionHasNoErrors();

        $appointment = Appointment::first();

        $this->assertSame(
            'Weak or brittle nails',
            $appointment->allergies,
            'The column is read by an admin and by the customer; neither wants a backing value.'
        );
    }

    /** "None" is the absence of information, so it is not written onto the booking. */
    public function test_declaring_no_sensitivities_writes_nothing(): void
    {
        Notification::fake();

        $this->makeSalonSettings();
        $service = $this->makeService(['price' => 500]);

        $this->book([
            'services' => [['service_id' => $service->id, 'quantity' => 1]],
            'allergy_preference' => BookingPreference::None->value,
        ])->assertSessionHasNoErrors();

        $this->assertNull(Appointment::first()->allergies);
    }

    /** A value outside the closed set is refused, so the column is not a free-text field. */
    public function test_a_forged_preference_is_refused(): void
    {
        $this->makeSalonSettings();
        $service = $this->makeService(['price' => 500]);

        $this->book([
            'services' => [['service_id' => $service->id, 'quantity' => 1]],
            'allergy_preference' => 'anything at all',
        ])->assertSessionHasErrors('allergy_preference');

        $this->assertDatabaseCount('appointments', 0);
    }

    /* ------------------------------------------------------------------ */
    /* 4. Step 5 — asked of a first-timer only                             */
    /* ------------------------------------------------------------------ */

    /** A first-timer is asked, and the server requires the answer. */
    public function test_a_first_timer_must_say_what_they_had_before(): void
    {
        $this->makeSalonSettings();

        // `null` rather than omitted: the field is present but empty, which is
        // what the browser sends for a textarea nobody typed in.
        $this->book(['last_services_availed_note' => null])
            ->assertSessionHasErrors('last_services_availed');

        $this->assertDatabaseCount('appointments', 0);
    }

    /**
     * A signed-up customer with no history is a first-timer.
     *
     * `/book` is behind `auth.customer` — guests browse the catalogue but must
     * register before booking — so "has a history" is the only thing that
     * separates the two cases here, and an account with no completed appointment
     * is the new one.
     */
    public function test_a_customer_with_no_history_is_a_first_timer(): void
    {
        $this->makeSalonSettings();
        $user = $this->makeUser();
        $service = $this->makeService(['price' => 500]);

        $payload = $this->payload(['services' => [['service_id' => $service->id, 'quantity' => 1]]]);
        unset($payload['last_services_availed_note']);

        $this->actingAs($user)
            ->post(route('appointments.store'), $payload)
            ->assertSessionHasErrors('last_services_availed');

        $this->assertStringContainsString(
            'first visit with us',
            $this->html($user),
            'A customer with no history must be asked, in those words.'
        );
    }

    /**
     * A returning customer is not asked, and the answer is filled in for them.
     *
     * The booking still carries the field — it is just not the customer who
     * supplies it. Asserted on the stored column, because "the form did not ask"
     * is worth much less than "the salon can still see it".
     */
    public function test_a_repeat_customer_is_not_asked_and_is_still_documented(): void
    {
        Notification::fake();

        $this->makeSalonSettings();
        $user = $this->makeUser();

        $this->makeAppointment($user, $this->makeService([
            'name' => 'Gloss Manicure',
        ]), ['status' => AppointmentStatus::Completed]);

        $service = $this->makeService(['price' => 500]);

        $payload = $this->payload(['services' => [['service_id' => $service->id, 'quantity' => 1]]]);
        unset($payload['last_services_availed_note']);

        $this->actingAs($user)
            ->post(route('appointments.store'), $payload)
            ->assertSessionHasNoErrors();

        $this->assertSame(
            'Gloss Manicure',
            Appointment::latest('id')->first()->last_services_availed,
            'The booking is documented from the customer\'s own record.'
        );
    }

    /** An answer the customer gave deliberately is never overwritten by the history. */
    public function test_a_typed_answer_beats_the_history(): void
    {
        Notification::fake();

        $this->makeSalonSettings();
        $user = $this->makeUser();

        $this->makeAppointment($user, $this->makeService([
            'name' => 'Gloss Manicure',
        ]), ['status' => AppointmentStatus::Completed]);

        $service = $this->makeService(['price' => 500]);

        $this->book([
            'services' => [['service_id' => $service->id, 'quantity' => 1]],
            'last_services_availed_note' => 'Actually, Colour & Highlights last month',
        ], $user)->assertSessionHasNoErrors();

        $this->assertSame(
            'Actually, Colour & Highlights last month',
            Appointment::latest('id')->first()->last_services_availed
        );
    }

    /**
     * A booking that was never completed is not a history.
     *
     * A pending booking says the customer intends to come and a cancelled one
     * says they decided not to. Neither is something that was done to their hair
     * and nails, so neither answers "what have you had done before" — and a
     * customer in that position is still asked.
     */
    public function test_an_unfinished_booking_is_not_a_history(): void
    {
        $this->makeSalonSettings();
        $user = $this->makeUser();

        $this->makeAppointment($user, $this->makeService(), ['status' => AppointmentStatus::Pending]);
        $this->makeAppointment($user, $this->makeService(), ['status' => AppointmentStatus::Cancelled]);

        $html = $this->html($user);

        $this->assertStringNotContainsString('Welcome back', $html);

        $service = $this->makeService(['price' => 500]);

        $payload = $this->payload(['services' => [['service_id' => $service->id, 'quantity' => 1]]]);
        unset($payload['last_services_availed_note']);

        $this->actingAs($user)
            ->post(route('appointments.store'), $payload)
            ->assertSessionHasErrors('last_services_availed');
    }

    /* ------------------------------------------------------------------ */
    /* 5. Step 7 — the gate this form has to enforce itself                */
    /* ------------------------------------------------------------------ */

    /**
     * The agreement is required, and the server is the gate.
     *
     * The browser is not: the form is `novalidate`, and the client-side
     * `guardSubmit` that used to compensate is gone. So `accepted` in
     * `StoreBookingRequest` is now the only thing refusing a booking with no
     * agreement, which is why this asserts the checkbox is present and that a
     * payload without it is refused.
     */
    public function test_the_terms_checkbox_is_required_and_the_server_refuses_without_it(): void
    {
        $this->makeService();

        $html = $this->html();

        $this->assertStringContainsString('name="agree_terms"', $html);
        $this->assertStringContainsString('required', $html);
        $this->assertStringContainsString('I agree to the', $html);

        // Nothing in the browser stands in front of it any more.
        $this->assertStringNotContainsString('x-on:submit=', $html);

        $this->book(['agree_terms' => null])
            ->assertSessionHasErrors('agree_terms');

        $this->assertDatabaseCount('appointments', 0);
    }

    /**
     * Step 7 is the checkbox, and nothing else.
     *
     * The block that stood here quoted the published terms' own heading and
     * last-updated date, a "Read the full General Terms & Conditions" button,
     * and a list of the Cancellation and Rescheduling policies. It repeated the
     * checkbox label, which already names all three documents and links each
     * one.
     */
    public function test_step_seven_is_the_checkbox_alone(): void
    {
        $this->makeSalonSettings();
        $this->makeService();

        \App\Models\TermsAndCondition::create([
            'category' => 'booking',
            'content' => '<p>These terms govern every appointment made with the salon.</p>',
            'is_published' => true,
            'published_at' => now(),
        ]);

        $html = $this->html();

        // The checkbox and its three links survive — the policies are still
        // readable, one click away, through the shared Terms dialog.
        $this->assertStringContainsString('name="agree_terms"', $html);
        $this->assertStringContainsString('terms-modal', $html);

        // The metadata block is gone.
        $this->assertStringNotContainsString('Booking Terms (v1)', $html);
        $this->assertStringNotContainsString('Last updated', $html);
        $this->assertStringNotContainsString('Read the full', $html);
        $this->assertStringNotContainsString('termsLead', $html);
    }

    /* ------------------------------------------------------------------ */

    /** N services in one named category. */
    private function makeCategory(string $category, int $count): void
    {
        for ($i = 1; $i <= $count; $i++) {
            $this->makeService([
                'name' => $category.' Service '.$i,
                'category' => $category,
            ]);
        }
    }
}
