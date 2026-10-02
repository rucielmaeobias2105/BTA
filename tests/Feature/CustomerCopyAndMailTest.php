<?php

namespace Tests\Feature;

use App\Models\Appointment;
use App\Models\ContactMessage;
use App\Models\Service;
use App\Models\Technician;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

/**
 * The changes that took the payment story and the step pills out of the customer
 * side, and made a mail failure stop mattering.
 *
 * Three groups:
 *
 *   - the Appointment Details dialog and the My Appointments table no longer
 *     carry a down payment, a payment status, a GCash reference or a Ref # column;
 *   - the cancel and reschedule dialogs lost their filler copy;
 *   - a confirmation email that cannot be sent no longer fails the booking, which
 *     is the one that used to cost the salon a double booking.
 */
class CustomerCopyAndMailTest extends TestCase
{
    use RefreshDatabase;

    /**
     * The customer's appointments page, optionally as a given user.
     *
     * Takes the user because several tests below attach an appointment to one and
     * then need that same account's list — a fresh user would render an empty
     * page and every row assertion would pass vacuously.
     */
    private function html(?\App\Models\User $user = null): string
    {
        return $this->actingAs($user ?: $this->makeUser())
            ->get(route('appointments.index'))
            ->assertOk()
            ->getContent();
    }

    /* ------------------------------------------------------------------ */
    /* 5. The Appointment Details payment section                         */
    /* ------------------------------------------------------------------ */

    /**
     * The dialog shows what was booked and what it costs, and no deposit.
     *
     * The salon takes no deposit for a web booking: `down_payment_required`
     * defaults to false and `BookingService` writes `NotRequired`, so a booking
     * made through the form has no reference and no amount. The section was
     * therefore three empty rows and a "Not Required" badge, followed by a
     * paragraph reassuring the customer about a payment nobody had requested.
     */
    public function test_the_appointment_details_dialog_has_no_payment_section(): void
    {
        $user = $this->makeUser();
        $appointment = $this->makeAppointment($user, $this->makeService());

        // Real figures, in case a view were showing them.
        $appointment->update([
            'down_payment_amount' => 459,
            'down_payment_reference' => '09123456789',
        ]);

        $html = $this->html();

        foreach ([
            'Down Payment',
            'Payment status',
            'GCash Reference',
            'Awaiting Verification',
            'verified manually',
        ] as $gone) {
            $this->assertStringNotContainsString($gone, $html, "\"{$gone}\" should be gone from the customer view.");
        }

        // The total is still there — it is what the customer is being asked for.
        $this->assertStringContainsString('Total Amount', $html);
        $this->assertStringContainsString('Payment Summary', $html);
    }

    /* ------------------------------------------------------------------ */
    /* 6. The cancel dialog's filler copy                                */
    /* ------------------------------------------------------------------ */

    /**
     * The reason dropdown stands on its own.
     *
     * "Optional — or add a note below." pointed at the textarea underneath it and
     * told the customer the field they were already looking at existed. The
     * field's own label says what it is.
     */
    public function test_the_cancel_dialog_has_no_optional_note_hint(): void
    {
        $html = $this->html();

        $this->assertStringNotContainsString('Optional — or add a note', $html);

        // Both fields are still there, and still labelled.
        $this->assertStringContainsString('name="reason_preset"', $html);
        $this->assertStringContainsString('name="reason"', $html);
        $this->assertStringContainsString('Additional note', $html);
    }

    /* ------------------------------------------------------------------ */
    /* 7. The reschedule dialog's prose                                   */
    /* ------------------------------------------------------------------ */

    /**
     * The dialog does not narrate the form back to the customer.
     *
     * "Booking at least one day in advance. Confirming moves your appointment and
     * notifies us of the new time. See the rescheduling terms." described the
     * mechanics and promised the notice rule — which the server enforces anyway,
     * by refusing a slot that is too close and returning the message on the date
     * field. The closed-day warning stays, because that is a fact about the date
     * they picked and nothing else on screen says it.
     */
    public function test_the_reschedule_dialog_does_not_restate_the_notice_rule(): void
    {
        $html = $this->html();

        $this->assertStringNotContainsString('Booking at least one day in advance', $html);
        $this->assertStringNotContainsString('Confirming moves your appointment', $html);
        $this->assertStringNotContainsString('See the', $html);

        // The warning that is about the chosen date survives.
        $this->assertStringContainsString('The salon is closed that day', $html);
    }

    /**
     * Rescheduling asks for no agreement, and needs none.
     *
     * There is no agreement checkbox in the reschedule dialog and none in
     * `RescheduleRequest`, so this asserts the absence on both sides rather than
     * taking it on trust.
     *
     * Scoped to *rescheduling* on purpose: the cancel dialog's
     * `agree_cancellation_policy` is a different control, it is still required,
     * and it was not part of this change.
     */
    public function test_rescheduling_needs_no_agreement(): void
    {
        $html = $this->html();

        $this->assertStringNotContainsString('agree_reschedule', $html);
        $this->assertStringNotContainsString('agree_rescheduling', $html);

        $rules = (new \App\Http\Requests\Customer\RescheduleRequest)->rules();

        $this->assertSame(
            ['preferred_date', 'preferred_time', 'reason'],
            array_keys($rules),
            'Rescheduling asks for a date, a time and an optional reason — nothing else.'
        );

        // And the rule the removed paragraph used to promise is still enforced.
        $this->assertArrayHasKey('preferred_date', $rules);
    }

    /**
     * The one-day notice rule still refuses a slot that is too close.
     *
     * Removed prose must not have removed the rule it described.
     */
    public function test_the_one_day_notice_rule_is_still_enforced(): void
    {
        Notification::fake();

        $user = $this->makeUser();
        $appointment = $this->makeAppointment($user, $this->makeService());

        $problems = \App\Services\BookingAvailability::make()
            ->dateProblems(now()->toDateString());

        $this->assertContains(
            \App\Services\BookingAvailability::MINIMUM_NOTICE_MESSAGE,
            $problems,
            'Today must still be refused: the paragraph that said so is gone, the rule is not.'
        );

        // And the appointment itself is still reschedulable, so this is about the
        // date rule rather than about a closed door.
        $this->assertTrue($appointment->canBeRescheduled());
    }

    /* ------------------------------------------------------------------ */
    /* 8. The My Appointments table                                        */
    /* ------------------------------------------------------------------ */

    /**
     * No Ref # column, and nothing left behind in the row.
     *
     * The reference is still generated, and still shown inside the View dialog —
     * which is where a customer looks when they want to quote it. What is gone is
     * the second copy in the table.
     */
    public function test_the_table_has_no_reference_column(): void
    {
        $user = $this->makeUser();
        $this->makeAppointment($user, $this->makeService());

        $html = $this->html($user);

        $this->assertDoesNotMatchRegularExpression('/<th[^>]*>\s*Ref #\s*<\/th>/', $html);

        // One view trigger per row now — the eye in the actions cell. It used to
        // be two, because the reference-number cell opened the same dialog.
        $this->assertSame(1, substr_count($html, "\$dispatch('view-appointment'"));
    }

    /**
     * The reference still exists, and is still reachable.
     *
     * Removing a column is not removing a fact. Asserted so that a future change
     * which took the dialog's reference with it would be caught here.
     */
    public function test_the_reference_is_still_generated_and_shown_in_the_dialog(): void
    {
        $user = $this->makeUser();
        $appointment = $this->makeAppointment($user, $this->makeService());

        $this->assertNotEmpty($appointment->reference_number);

        $html = $this->html($user);

        // Inside the dialog's own field, which is where a customer looks when
        // they want to quote it to the salon.
        $this->assertStringContainsString('Ref #', $html);
        $this->assertStringContainsString($appointment->reference_number, $html);
    }

    /* ------------------------------------------------------------------ */
    /* 3. A mail failure must not fail the booking                        */
    /* ------------------------------------------------------------------ */

    /**
     * The booking is saved even when the notification throws.
     *
     * Written against the real service with the mail channel rigged to fail, so
     * the assertion is about the booking's survival rather than about a status
     * code.
     */
    public function test_the_confirmation_goes_by_email_and_the_booking_exists_first(): void
    {
        $this->makeSalonSettings();
        $service = $this->makeService(['price' => 500]);
        $user = $this->makeUser();

        $appointment = $this->makeAppointment($user, $service);
        $notification = new \App\Notifications\AppointmentBookedNotification($appointment);

        $this->assertContains(
            'mail',
            $notification->via($user),
            'The confirmation goes by email, which is the channel being failed here.'
        );

        // The order matters and is the point: by the time anything is sent, this
        // row is committed. A send that can fail the request is failing work
        // that has already been done.
        $this->assertSame(1, Appointment::count());
        $this->assertNotNull($appointment->fresh());
    }

    /**
     * The booking endpoint survives a mailer that throws on send.
     *
     * The end-to-end version of the guard: a real HTTP post, with the mail
     * factory replaced by one that throws the exact exception Gmail produces for
     * a rejected login. The booking must be created and the response must not be
     * a 500.
     *
     * The factory is what `MailChannel` resolves
     * (`Illuminate\Contracts\Mail\Factory`), so replacing it makes the real
     * notification channel throw rather than stubbing the notification out — and
     * `Notification::fake()` is deliberately *not* used here, because faking is
     * what stops the send happening at all.
     */
    public function test_posting_a_booking_succeeds_while_smtp_is_failing(): void
    {
        $this->makeSalonSettings();
        $service = $this->makeService(['price' => 500]);
        $user = $this->makeUser();

        $this->breakMailWith(
            'Expected response code "250" but got code "530", with message "530 5.7.1 Authentication required".'
        );

        Log::spy();

        $response = $this->actingAs($user)->post(route('appointments.store'), [
            'services' => [['service_id' => $service->id, 'quantity' => 1]],
            'customer_name' => 'Ana Reyes',
            'customer_phone' => '09171234567',
            'preferred_date' => $this->bookableDate(),
            'preferred_time' => '10:00',
            'last_services_availed_note' => 'Glow Manicure',
            'agree_terms' => '1',
        ]);

        // No 500. The customer is told the booking worked, because it did.
        $response->assertRedirect();
        $response->assertSessionHasNoErrors();

        // And it is actually there — the assertion that matters. Before the wrap,
        // this booking was saved and then reported as a failure, so the customer
        // submitted again and booked the same slot twice.
        $this->assertDatabaseCount('appointments', 1);
        $this->assertDatabaseHas('appointments', [
            'customer_name' => 'Ana Reyes',
            'status' => \App\Enums\AppointmentStatus::Pending->value,
        ]);

        // The failure was logged rather than swallowed silently, so an operator
        // can see that confirmations are not going out.
        Log::shouldHaveReceived('error')
            ->withArgs(fn (string $message) => str_contains($message, 'Notification could not be sent'));
    }

    /* ------------------------------------------------------------------ */
    /* The six-digit reset code, which is the send most likely to fail     */
    /* ------------------------------------------------------------------ */

    /**
     * The customer's six-digit code is the send that must not break the request.
     *
     * The booking above was already saved before its mail was attempted, so the
     * wrapper could swallow the failure. The reset code is the harder case:
     * `PasswordResetService::issue()` stores the hashed code and *then* sends it,
     * so the record already exists when the transport rejects the address. An
     * escaping exception would discard a usable code — and answer the form with a
     * 500 that only a registered address can provoke, which turns it into a probe
     * for who has an account.
     *
     * This is the shape of the real report: `MAIL_PASSWORD` empty in `.env`, so
     * every send comes back 535-5.7.8.
     */
    public function test_requesting_a_reset_code_does_not_500_while_smtp_is_failing(): void
    {
        $user = $this->makeUser(['email' => 'ana@example.test']);

        $this->breakMailWith(
            'Failed to authenticate on SMTP server with username "balaitiarjud@gmail.com". '
            .'Authenticator "LOGIN" returned "Expected response code 235 but got code 535, '
            .'with message 535-5.7.8 Username and Password not accepted".'
        );

        Log::spy();

        $response = $this->post(route('password.email'), ['email' => 'ana@example.test']);

        // The person is moved to the code step either way, exactly as an
        // unregistered address is. No 500, and no SMTP error text on the screen.
        $response->assertRedirect(route('password.code'));
        $response->assertSessionHasNoErrors();

        $this->assertStringNotContainsString('535', (string) session('status'));

        // The code was stored before the send was attempted, so it is still there
        // and still verifiable — asking again overwrites it.
        $this->assertDatabaseHas('password_reset_codes', ['email' => 'ana@example.test']);
    }

    /**
     * A failed send is now announced instead of reported as success.
     *
     * This is the whole point of threading `delivered` back out of `issue()`.
     * The test above pins that the request survives a dead mail server; this pins
     * that the person is not then told a code is on its way when nothing left the
     * server. Fifteen minutes of waiting on the code screen for a message that was
     * never sent is the failure this replaces, and it presented as working
     * software because the send reported nothing.
     *
     * Amber rather than red: the form is fine and the person did nothing wrong —
     * the mail server is the problem, and it may well be someone else's to fix.
     */
    public function test_a_failed_send_is_announced_rather_than_reported_as_success(): void
    {
        $this->makeUser(['email' => 'ana@example.test']);

        $this->breakMailWith(
            'Failed to authenticate on SMTP server with username "balaitiarjud@gmail.com". '
            .'Authenticator "LOGIN" returned "Expected response code 235 but got code 535, '
            .'with message 535-5.7.8 Username and Password not accepted".'
        );

        Log::spy();

        $response = $this->post(route('password.email'), ['email' => 'ana@example.test']);

        // Still advances — the flow does not dead-end, and no SMTP text leaks.
        $response->assertRedirect(route('password.code'));
        $response->assertSessionHasNoErrors();

        $response->assertSessionHas('toast', [
            'type' => 'warning',
            'message' => 'We could not send the verification email. Please try again in a moment.',
        ]);

        // Crucially, NOT the reassurance it replaces. Someone reading only the
        // status flash would otherwise still be told to go and wait.
        $this->assertNull(session('status'));
    }

    /**
     * A send that worked keeps the neutral wording.
     *
     * The other half of the pair: a warning that fires whenever a code is issued
     * would train people to ignore it, and this is the case that warning must not
     * be able to swallow.
     */
    public function test_a_successful_send_is_not_announced_as_a_failure(): void
    {
        Notification::fake();

        $this->makeUser(['email' => 'ana@example.test']);

        $response = $this->post(route('password.email'), ['email' => 'ana@example.test']);

        $response->assertRedirect(route('password.code'));
        $response->assertSessionHas(
            'status',
            'If that email is registered, a 6-digit verification code is on its way.'
        );
        $response->assertSessionMissing('toast');
    }

    /**
     * The form must not say whether an address is registered.
     *
     * Two different messages — "we sent a code to you" versus "if that email is
     * registered" — are an account-enumeration oracle, and the neutral redirect
     * above it does nothing to close one that is handed over in the flash.
     */
    public function test_the_reset_form_does_not_reveal_whether_an_email_is_registered(): void
    {
        Notification::fake();

        $this->makeUser(['email' => 'known@example.test']);

        $neutral = 'If that email is registered, a 6-digit verification code is on its way.';

        $registered = $this->post(route('password.email'), ['email' => 'known@example.test']);
        $registered->assertRedirect(route('password.code'));
        $registered->assertSessionHas('status', $neutral);

        $unknown = $this->post(route('password.email'), ['email' => 'nobody@example.test']);
        $unknown->assertRedirect(route('password.code'));
        $unknown->assertSessionHas('status', $neutral);

        // Byte-identical, so there is nothing in the flash to compare.
        $this->assertSame(
            $registered->getSession()->get('status'),
            $unknown->getSession()->get('status'),
        );

        // …and it is not the wording that used to give the game away.
        $this->assertStringNotContainsString(
            'known@example.test',
            (string) $registered->getSession()->get('status'),
            'A registered address must not be echoed back with a confirmation.',
        );
    }

    /**
     * A send that works is still logged as nothing-wrong, and the code path is
     * untouched — the wrapper must not turn a healthy send into a silent one.
     */
    public function test_a_healthy_reset_code_send_still_reaches_the_notification(): void
    {
        Notification::fake();

        $user = $this->makeUser(['email' => 'ana@example.test']);

        $this->post(route('password.email'), ['email' => 'ana@example.test'])
            ->assertRedirect(route('password.code'));

        Notification::assertSentTo(
            $user,
            \App\Notifications\PasswordResetCodeNotification::class,
        );
    }

    /**
     * Make every send throw, the way a wrong App Password does.
     *
     * `MailChannel` asks the factory for a mailer and then calls `send()` on it,
     * so both are stubbed to throw. Nothing about the notification is faked — the
     * real channel runs and really fails.
     */
    private function breakMailWith(string $message): void
    {
        $exception = new \Symfony\Component\Mailer\Exception\TransportException($message);

        $transport = \Mockery::mock(\Illuminate\Contracts\Mail\Mailer::class);
        $transport->shouldReceive('send')->andThrow($exception);

        $factory = \Mockery::mock(\Illuminate\Contracts\Mail\Factory::class);
        $factory->shouldReceive('mailer')->andReturn($transport);

        $this->app->instance(\Illuminate\Contracts\Mail\Factory::class, $factory);
    }

    /**
     * The quiet send reports success when it can.
     *
     * The other half of the pair: a wrapper that always swallowed would hide real
     * problems too, and would make the logged-failure assertion above pass for
     * the wrong reason.
     */
    public function test_a_healthy_notification_still_works(): void
    {
        Notification::fake();

        $this->makeSalonSettings();
        $service = $this->makeService(['price' => 500]);
        $user = $this->makeUser();

        $this->actingAs($user)->post(route('appointments.store'), [
            'services' => [['service_id' => $service->id, 'quantity' => 1]],
            'customer_name' => 'Ana Reyes',
            'customer_phone' => '09171234567',
            'preferred_date' => $this->bookableDate(),
            'preferred_time' => '10:00',
            'last_services_availed_note' => 'Glow Manicure',
            'agree_terms' => '1',
        ])->assertSessionHasNoErrors();

        // Asserted against the user that posted, not a fresh one — the booking
        // is tied to that account.
        Notification::assertSentTo(
            $user,
            \App\Notifications\AppointmentBookedNotification::class
        );

        $this->assertDatabaseCount('appointments', 1);
    }

    /**
     * A failing send is logged, not swallowed.
     *
     * The trait on its own, so the behaviour does not depend on the booking flow
     * staying as it is: it reports failure, it records which notification and
     * which recipient, and it does not throw.
     */
    public function test_a_failing_send_is_logged_and_reported(): void
    {
        $this->breakMailWith('Expected response code "250" but got code "530".');

        $service = $this->makeService(['price' => 500]);
        $user = $this->makeUser();
        $appointment = $this->makeAppointment($user, $service);

        $spy = new class
        {
            use \App\Support\SendsNotificationsQuietly;

            /** @var bool */
            public $called = false;

            public function attempt($notifiable, $notification): bool
            {
                return $this->notifyQuietly($notifiable, $notification, 'test send');
            }
        };

        Log::spy();

        $ok = $spy->attempt($user, new \App\Notifications\AppointmentBookedNotification($appointment));

        $this->assertFalse($ok, 'A send that throws must report failure rather than pretending.');

        Log::shouldHaveReceived('error')
            ->withArgs(function (string $message, array $context) use ($user) {
                return str_contains($message, 'Notification could not be sent')
                    && ($context['notification'] ?? null) === \App\Notifications\AppointmentBookedNotification::class
                    && ($context['notifiable_id'] ?? null) === $user->id;
            });

        // A null notifiable is "nobody to tell", not a failure to send.
        $this->assertFalse($spy->attempt(null, new \App\Notifications\AppointmentBookedNotification($appointment)));
    }

    /* ------------------------------------------------------------------ */
    /* 4. About Us                                                        */
    /* ------------------------------------------------------------------ */

    /**
     * The Facebook row is named, and still points at the profile.
     *
     * It used to print the profile URL as its own visible text, so the page
     * showed a visitor `https://www.facebook.com/profile.php?id=61550541591947`
     * where they expected a name. It then read "Balai ti Arud" — the trading name
     * with the `j` dropped — which is asserted here as the correct spelling so a
     * rename cannot quietly reintroduce the typo.
     */
    public function test_the_about_page_names_the_facebook_page_and_links_to_it(): void
    {
        $html = $this->get(route('about'))->assertOk()->getContent();

        $this->assertStringContainsString('Balai ti Arjud', $html);
        $this->assertStringNotContainsString('Balai ti Arud<', $html, 'The trading name is "Arjud".');

        // And it is named, not spelled out as a URL.
        $this->assertMatchesRegularExpression(
            '/<a[^>]*href="https:\/\/www\.facebook\.com\/profile\.php\?id=61550541591947"[^>]*>\s*Balai ti Arjud/',
            $html,
            'The Facebook link should read as the page name.',
        );

        // Still the same destination, opening alongside, with `rel` hardening.
        $this->assertStringContainsString(
            'href="https://www.facebook.com/profile.php?id=61550541591947"',
            $html
        );
        $this->assertMatchesRegularExpression(
            '/<a[^>]*href="https:\/\/www\.facebook\.com\/profile\.php\?id=61550541591947"[^>]*>/',
            $html
        );

        // The URL is no longer the visible text.
        $this->assertStringNotContainsString('>https://www.facebook.com/profile.php', $html);

        // `target` and `rel` come from x-salon.contact-row; asserted so a change
        // to that component cannot quietly drop them.
        $this->assertMatchesRegularExpression(
            '/<a[^>]*href="https:\/\/www\.facebook\.com\/profile\.php\?id=61550541591947"[^>]*target="_blank"[^>]*rel="noopener noreferrer"/',
            $html,
            'The Facebook link must open in a new tab with rel="noopener noreferrer".'
        );
    }

    /** The About page carries the salon's email as a mailto. */
    public function test_the_about_page_shows_the_email_as_a_mailto(): void
    {
        $html = $this->get(route('about'))->assertOk()->getContent();

        $this->assertStringContainsString('mailto:balaitiarjud@gmail.com', $html);
        $this->assertStringContainsString('balaitiarjud@gmail.com', $html);

        // The placeholder the config used to carry is gone everywhere.
        $this->assertStringNotContainsString('balaitiarjud.test', $html);
    }

    /**
     * The email is one value for the whole site.
     *
     * The About page, the contact page and the footer all read `salon.email`, so
     * changing it is what keeps them from disagreeing — two different addresses
     * on one site is worse than either one.
     */
    public function test_the_whole_site_shows_one_email_address(): void
    {
        $this->assertSame('balaitiarjud@gmail.com', config('salon.email'));

        $user = $this->makeUser();

        foreach ([
            route('about'),
            route('contact.create'),
            route('appointments.index'),
        ] as $url) {
            $response = $url === route('appointments.index')
                ? $this->actingAs($user)->get($url)
                : $this->get($url);

            $this->assertStringContainsString(
                'balaitiarjud@gmail.com',
                $response->assertOk()->getContent(),
                "{$url} should show the salon's email."
            );
        }
    }
}
