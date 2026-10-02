<?php

namespace Tests\Feature;

use App\Enums\AppointmentStatus;
use App\Notifications\AppointmentConfirmedNotification;
use App\Notifications\AppointmentInProgressNotification;
use App\Notifications\WelcomeNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Live notifications: the bell updates itself, without a reload.
 *
 * Polling, not pushing. There is no WebSocket server in this project — no
 * Reverb, no Pusher — so "real-time" is a `fetch` of one small JSON endpoint
 * every few seconds. These tests pin what that buys and what it must not cost:
 * a status change lands in the bell without the customer touching anything, and
 * an idle tab costs a cursor lookup rather than a re-read of the inbox.
 */
class LiveNotificationsTest extends TestCase
{
    use RefreshDatabase;

    private function unescaped(string $html): string
    {
        return str_replace('\\', '', str_replace('\u0022', '"', $html));
    }

    /* ------------------------------------------------------------------ */
    /* 1. The feed endpoint                                                */
    /* ------------------------------------------------------------------ */

    public function test_the_feed_returns_the_unread_count_and_the_latest_rows(): void
    {
        $user = $this->makeUser();
        $appointment = $this->makeAppointment($user);

        $user->notify(new AppointmentConfirmedNotification($appointment));
        $user->notify(new WelcomeNotification());

        $payload = $this->actingAs($user)
            ->getJson(route('notifications.feed'))
            ->assertOk()
            ->json();

        $this->assertSame(2, $payload['unread']);
        $this->assertCount(2, $payload['notifications']);

        // The booking row is a status line; the salon's own message keeps its
        // body and its own title. Same wording the notifications page uses,
        // because both shape rows through `App\Support\NotificationRow`.
        $titles = array_column($payload['notifications'], 'title');
        $this->assertContains('Welcome to '.config('app.name'), $titles);
        $this->assertContains('Appointment status updated: Confirmed', $titles);

        // And the rows carry the cursor the poller needs to advance.
        $this->assertNotNull($payload['since']);
        $this->assertNotEmpty($payload['since']);
    }

    public function test_the_feed_only_returns_this_customers_notifications(): void
    {
        $mine = $this->makeUser();
        $theirs = $this->makeUser();

        $mine->notify(new WelcomeNotification());
        $theirs->notify(new WelcomeNotification());
        $theirs->notify(new WelcomeNotification());

        $payload = $this->actingAs($mine)->getJson(route('notifications.feed'))->assertOk()->json();

        $this->assertSame(1, $payload['unread']);
        $this->assertCount(1, $payload['notifications']);
    }

    public function test_a_guest_cannot_read_the_feed(): void
    {
        $this->getJson(route('notifications.feed'))->assertUnauthorized();
    }

    /**
     * `since` is what keeps an idle tab cheap.
     *
     * The poller sends the newest id it already has, so the response is a diff
     * rather than the whole inbox — and, just as importantly, an unchanged
     * inbox produces no rows to re-render.
     */
    public function test_since_turns_the_feed_into_a_diff(): void
    {
        $user = $this->makeUser();
        $appointment = $this->makeAppointment($user);

        $user->notify(new AppointmentConfirmedNotification($appointment));
        $user->notify(new AppointmentInProgressNotification($appointment));

        $all = $this->actingAs($user)->getJson(route('notifications.feed'))->assertOk()->json();
        $this->assertCount(2, $all['notifications']);

        $cursor = $all['since'];
        $this->assertNotEmpty($cursor);

        /*
         * The feed filters on `created_at >= since` rather than `>`, so rows
         * that already arrived in the same second come back again. That is why an
         * idle tab sees a repeat rather than an empty response — and it is the
         * right trade: re-rendering a row the client already de-duplicates by id
         * costs nothing, whereas `>` would silently drop every status change but
         * the first of a burst.
         */
        $quiet = $this->actingAs($user)
            ->getJson(route('notifications.feed', ['since' => $cursor]))
            ->assertOk()
            ->json();

        $this->assertSame([], array_diff(array_column($quiet['notifications'], 'id'), array_column($all['notifications'], 'id')));

        // The count still answers the question the badge asked.
        $this->assertSame(2, $quiet['unread']);

        // And the cursor never walks backwards, so a clock that jumps back
        // mid-session cannot make the poller re-read the inbox from the start.
        $this->assertSame($cursor, $quiet['since']);

        // And a new row does come back through the cursor.
        $user->notify(new WelcomeNotification());
        $newest = 'Welcome to '.config('app.name');

        $later = $this->actingAs($user)
            ->getJson(route('notifications.feed', ['since' => $cursor]))
            ->assertOk()
            ->json();

        // The two earlier rows come back too, because the filter is inclusive;
        // what matters is that the new one arrived and the cursor did not rewind.
        $this->assertNotEmpty($later['notifications']);
        $this->assertContains($newest, array_column($later['notifications'], 'title'));
        $this->assertGreaterThanOrEqual($cursor, $later['since']);
    }

    /* ------------------------------------------------------------------ */
    /* 2. The bell                                                        */
    /* ------------------------------------------------------------------ */

    /**
     * The bell is wired to the feed and seeds itself with what the page was
     * rendered with, so the first tick is a diff and not a re-render.
     */
    public function test_the_bell_polls_and_is_seeded_from_the_server(): void
    {
        $user = $this->makeUser();
        $user->notify(new AppointmentConfirmedNotification($this->makeAppointment($user)));

        $html = $this->actingAs($user)->get(route('appointments.index'))->assertOk()->getContent();
        $unescaped = $this->unescaped($html);

        $this->assertStringContainsString('notificationBell(', $html);
        $this->assertStringContainsString(route('notifications.feed'), $unescaped);

        // Seeded: the count the badge shows.
        $this->assertStringContainsString('"unread":1', $unescaped);

        // The rows are NOT passed in any more. The bell is a link to the
        // notifications page and that page renders its own list, so seeding eight
        // rows here would mean querying every notification on every page load to
        // show a number.
        $this->assertStringNotContainsString('Appointment status updated: Confirmed', $unescaped);
        $this->assertStringNotContainsString('"rows"', $unescaped);
    }

    /**
     * One poller for the whole header, not one per badge.
     *
     * The desktop bell and the mobile nav row both show the count. Giving each
     * its own scope would start two timers hitting the same endpoint for the
     * same number, so the state is spread into the header's existing object.
     */
    public function test_the_header_hosts_one_poller_for_both_badges(): void
    {
        $user = $this->makeUser();

        $html = $this->actingAs($user)->get(route('appointments.index'))->assertOk()->getContent();

        $this->assertSame(1, substr_count($html, 'notificationBell('));
        $this->assertStringContainsString('...notificationBell(', $html);
    }

    /**
     * A signed-out visitor pays nothing.
     *
     * The component is still mounted on the shared header — it is one element and
     * the layout does not fork on auth — but it is handed a null config, and the
     * first thing it does with that is return without starting a timer or
     * holding an endpoint.
     */
    public function test_a_guest_gets_a_bell_with_no_endpoint_to_poll(): void
    {
        $html = $this->get(route('home'))->assertOk()->getContent();

        $this->assertStringContainsString('...notificationBell(null)', $html);
        $this->assertStringNotContainsString('notification-bell-panel', $html);
        $this->assertStringNotContainsString('notifications/feed', $html);
    }

    /* ------------------------------------------------------------------ */
    /* 3. The bell is a link, and reading moved to the list                 */
    /* ------------------------------------------------------------------ */

    /**
     * The bell navigates rather than reading.
     *
     * The inversion of a deleted test. The bell was a submit button inside the
     * read-all form, so a visitor with scripting off could still clear their
     * badge from the navbar. That was a workaround for the dropdown being
     * JavaScript-only; with the dropdown gone the bell is an anchor to the list
     * and the read actions are forms on that list, which is a better no-JS path
     * than the one it replaces: the action and its effect are on the same screen.
     */
    public function test_the_bell_links_to_the_notifications_and_posts_nothing(): void
    {
        $user = $this->makeUser();

        $html = $this->actingAs($user)->get(route('appointments.index'))->assertOk()->getContent();

        $this->assertStringContainsString('href="'.route('notifications.index').'"', $html);
        $this->assertStringNotContainsString('notification-bell-panel', $html);
        $this->assertStringNotContainsString('x-on:click.prevent="toggle()"', $html);

        // No form posts to the read receipt from the header any more.
        $this->assertDoesNotMatchRegularExpression(
            '/<form method="POST" action="[^"]*\/notifications\/read-all">/',
            $html,
            'The header should carry no read-receipt form; the list owns reading now.',
        );
    }

    /** …and the list is where those forms moved to, so no-JS reading still works. */
    public function test_the_read_receipts_live_on_the_notifications_page(): void
    {
        $user = $this->makeUser();
        $user->notify(new AppointmentConfirmedNotification($this->makeAppointment($user)));

        $html = $this->actingAs($user)->get(route('notifications.index'))->assertOk()->getContent();

        /*
         * Matched with `\s+` rather than a literal space: the per-row form is
         * written across several lines in the Blade source and those newlines
         * survive into the rendered HTML, so `<form method="POST" action="` is
         * never a contiguous string for it. The `s` flag is not needed — this
         * only has to span whitespace, not arbitrary content.
         */
        $this->assertMatchesRegularExpression(
            '/<form\s+method="POST"\s+action="[^"]*\/notifications\/read-all"/',
            $html,
        );
        $this->assertMatchesRegularExpression(
            '/<form\s+method="POST"\s+action="[^"]*\/notifications\/[^"]+\/read"/',
            $html,
        );
    }

    /* ------------------------------------------------------------------ */
    /* 3b. Clicking the bell clears the badge                              */
    /* ------------------------------------------------------------------ */

    /**
     * Both bells clear the badge on click, and both still navigate.
     *
     * The desktop bell and the mobile nav row are the same link with the same
     * count, and both are asserted because they are separately written markup:
     * a phone user opening the bell from the menu would otherwise be left with a
     * badge they had to open twice to get rid of.
     *
     * `x-on:click` with no `.prevent` is the load-bearing part. The bell is a
     * link to the notifications page and has to stay one — this clears the badge
     * on the way there rather than replacing the trip.
     */
    public function test_clicking_either_bell_clears_the_badge_and_still_navigates(): void
    {
        $user = $this->makeUser();
        $user->notify(new WelcomeNotification());

        $html = $this->actingAs($user)->get(route('appointments.index'))->assertOk()->getContent();

        $this->assertSame(2, substr_count(
            $html,
            'x-on:click="markAllRead()"'
        ), 'The desktop bell and the mobile nav row should both clear on click.');

        $this->assertStringNotContainsString(
            'x-on:click.prevent="markAllRead()"',
            $html,
            'The bell is a link; clearing the badge must not cancel the navigation.',
        );

        $this->assertStringContainsString('href="'.route('notifications.index').'"', $html);
    }

    /**
     * The header is handed the read-all URL, so it posts the write the page
     * already knows how to post.
     *
     * One endpoint with two callers rather than a second way of expressing the
     * same write. Asserted against the generated route — unescaped, because
     * `@js()` writes `http:\/\/…` — so a route change fails this rather than a
     * `fetch` that quietly 404s after the badge has already been zeroed.
     */
    public function test_the_header_is_handed_the_existing_read_all_endpoint(): void
    {
        $user = $this->makeUser();

        $html = $this->actingAs($user)->get(route('appointments.index'))->assertOk()->getContent();

        $this->assertStringContainsString(route('notifications.read-all'), $this->unescaped($html));

        // The feed URL comes from the same object, so the two cannot drift apart
        // and leave the bell polling one place while clearing another.
        $this->assertStringContainsString(route('notifications.feed'), $this->unescaped($html));
    }

    /**
     * The badge goes first and the write follows, kept alive across the
     * navigation.
     *
     * "Immediately" is the whole requirement, so the order is pinned: the count
     * is zeroed before the `fetch`, not after its response. `keepalive` is pinned
     * with it because the click that triggers the write also navigates away, and
     * a browser cancels an ordinary in-flight request when the document that
     * made it starts unloading — which would leave the badge cleared and the
     * notifications unread, the exact state this is meant to end.
     */
    public function test_the_bell_zeroes_the_badge_before_it_posts_and_survives_the_navigation(): void
    {
        $source = (string) file_get_contents(base_path('resources/js/app.js'));

        // Scoped to the bell's own block: the notifications page's component
        // shares the method name, and this is about the header's copy.
        $bell = Str::between($source, "Alpine.data('notificationBell'", "Alpine.data('adminLiveNotifications'");

        $this->assertStringContainsString('async markAllRead()', $bell);
        $this->assertStringContainsString("method: 'PATCH'", $bell);
        $this->assertStringContainsString('keepalive: true', $bell);

        $zeroedAt = strpos($bell, 'window.btaUnread?.set(0)');
        $fetchedAt = strpos($bell, 'const response = await fetch(this.readAll');

        $this->assertIsInt($zeroedAt);
        $this->assertIsInt($fetchedAt);
        $this->assertLessThan(
            $fetchedAt,
            $zeroedAt,
            'The badge must be zeroed before the request, not after it answers.',
        );

        // And a poll already on the wire cannot put the badge back: it was
        // answered before the write landed, so it is the stale half of a race.
        $this->assertStringContainsString('if (this.clearing) {', $bell);
        $this->assertStringContainsString('this.clearing = false;', $bell);
    }

    /**
     * …and the write it fires really does clear the notifications.
     *
     * The endpoint is pre-existing and covered by the notifications page's own
     * tests, so what is being asserted here is that the bell is wired to *that*
     * one — a bell whose badge clears while the rows stay unread would look
     * exactly like success and be none.
     */
    public function test_the_write_the_bell_fires_marks_the_notifications_read(): void
    {
        $user = $this->makeUser();
        $user->notify(new WelcomeNotification());

        $this->assertSame(1, $user->unreadNotifications()->count());

        $this->actingAs($user)
            ->patchJson(route('notifications.read-all'))
            ->assertOk()
            ->assertJson(['unread' => 0]);

        $this->assertSame(0, $user->unreadNotifications()->count());
    }

    /* ------------------------------------------------------------------ */
    /* 4. The admin side                                                  */
    /* ------------------------------------------------------------------ */

    /**
     * Behind the admin guard, and closed to customers.
     *
     * The panel's guard redirects rather than 401s, which is right for a browser
     * but means a JSON caller gets the login page. Asserted as "not the feed",
     * which is the property that matters: a signed-in customer cannot read the
     * salon's work queue by posting the admin URL with the customer session.
     */
    public function test_the_admin_feed_is_closed_to_guests_and_to_customers(): void
    {
        $this->getJson(route('admin.notifications.feed'))->assertRedirect();

        $response = $this->actingAs($this->makeUser())
            ->getJson(route('admin.notifications.feed'));

        $response->assertRedirect();
        $response->assertDontSee('"pending"', false);
    }

    /**
     * Exactly one work-queue control, and it is the sidebar's.
     *
     * The Appointments and Reports list views each sit under the same chrome, and
     * a second count inside a list body — beside Export CSV / Generate Report —
     * would be a duplicate badge showing a number that could disagree with the
     * one in the nav. Scoped to `<main>` so the assertion is about the list view
     * and not the chrome every admin page shares.
     *
     * The topbar bell used to be that single control. It has been removed in
     * favour of the two sidebar rows that own the two halves of the count, so
     * "exactly one" is now asserted as: nothing anywhere renders a bell, and the
     * counts are on the nav rows.
     */
    public function test_the_appointment_and_report_lists_carry_no_bell_of_their_own(): void
    {
        $admin = $this->makeAdmin();

        foreach (['/admin/appointments', '/admin/reports'] as $path) {
            $html = $this->actingAs($admin, 'admin')->get($path)->assertOk()->getContent();

            $main = substr($html, (int) strpos($html, '<main'), (int) strpos($html, '</main>') - (int) strpos($html, '<main'));

            $this->assertStringNotContainsString('aria-label="Notifications"', $html, "{$path} should carry no bell icon.");
            $this->assertStringNotContainsString('admin-notification-panel', $html);
            $this->assertStringNotContainsString('adminNotificationBell(', $main, "{$path} should not carry its own poller.");
            $this->assertStringNotContainsString('Waiting on you', $main, "{$path} should not carry a second work-queue panel.");

            // …and the count is on the nav, so the control has not simply gone.
            $this->assertStringContainsString('admin-pending', $html);
        }
    }

    /** The poller is seeded from the same counts the endpoint counts. */
    public function test_the_admin_poller_is_seeded_with_its_counts(): void
    {
        $admin = $this->makeAdmin();
        $this->makeAppointment($this->makeUser(), null, ['status' => AppointmentStatus::Pending]);

        $html = $this->actingAs($admin, 'admin')->get('/admin')->assertOk()->getContent();

        $this->assertStringContainsString('"pending":1', $this->unescaped($html));
    }

    /* ------------------------------------------------------------------ */
    /* 5. Live updates without a reload                                     */
    /* ------------------------------------------------------------------ */

    /**
     * The whole point of the panel: a booking lands and the badge and the list
     * change, with nobody touching the page.
     */
    public function test_a_new_pending_booking_shows_up_in_the_polled_feed(): void
    {
        $admin = $this->makeAdmin();

        $before = $this->actingAs($admin, 'admin')->getJson(route('admin.notifications.feed'))->json();
        $this->assertSame(0, $before['pending']);
        $this->assertSame([], $before['items']);

        $appointment = $this->makeAppointment($this->makeUser(), null, [
            'status' => AppointmentStatus::Pending,
            'preferred_date' => today()->addDay(),
        ]);

        $after = $this->actingAs($admin, 'admin')->getJson(route('admin.notifications.feed'))->json();

        $this->assertSame(1, $after['pending']);
        $this->assertCount(1, $after['items']);

        // The row carries what the dropdown renders: the kind, the customer and
        // their services as one line, when it is for, and where it links.
        $this->assertSame('appointment', $after['items'][0]['kind']);
        $this->assertStringContainsString($appointment->customer_name, $after['items'][0]['detail']);
        $this->assertStringContainsString($appointment->service_names, $after['items'][0]['detail']);
        $this->assertStringContainsString('at', $after['items'][0]['when']);
        $this->assertSame(route('admin.appointments.index'), $after['items'][0]['url']);
    }

    /**
     * The badge is one number, not two competing ones.
     *
     * Pending bookings and unopened enquiries are both "waiting on you", and the
     * badge adds them — so a single enquiry plus a single booking reads 2, and
     * that is what the tab title reads too.
     */
    public function test_the_badge_adds_pending_bookings_and_unread_enquiries(): void
    {
        $admin = $this->makeAdmin();

        $this->makeAppointment($this->makeUser(), null, ['status' => AppointmentStatus::Pending]);
        \App\Models\ContactMessage::create([
            'name' => 'Ana',
            'email' => 'ana@example.test',
            'topic' => \App\Enums\InquiryTopic::Others,
            'message' => 'Do you have a slot on Friday?',
        ]);

        $payload = $this->actingAs($admin, 'admin')->getJson(route('admin.notifications.feed'))->json();

        $this->assertSame(1, $payload['pending']);
        $this->assertSame(1, $payload['messages']);
        $this->assertCount(2, $payload['items']);
    }

    /**
     * A booking the salon has already looked at is not in the feed.
     *
     * Was "a booking that needs no decision" — the feed excluded anything not in
     * Pending, on the grounds that a settled booking is not work. That is still
     * true as a matter of work, but it was never the reason the badge could not be
     * cleared: the badge counted a status, so opening the Appointments page did
     * nothing to it and the number sat there all day.
     *
     * So the feed now counts *unseen* bookings instead. A booking that has been
     * approved without ever being read is still unseen, and still belongs in the
     * feed: nobody has looked at it, which is the only thing the count is about.
     */
    public function test_a_booking_the_salon_has_already_seen_is_not_in_the_feed(): void
    {
        $admin = $this->makeAdmin();

        foreach ([AppointmentStatus::Confirmed, AppointmentStatus::InProgress, AppointmentStatus::Completed] as $status) {
            $this->makeAppointment($this->makeUser(), null, [
                'status' => $status,
                'admin_seen_at' => now(),
            ]);
        }

        $payload = $this->actingAs($admin, 'admin')->getJson(route('admin.notifications.feed'))->json();

        $this->assertSame(0, $payload['pending']);
        $this->assertSame([], $payload['items']);
    }

    /**
     * Seen and pending are different questions.
     *
     * The pair that used to be one query. An admin who has looked at a booking
     * still has it Pending — it still needs deciding — and the badge must not
     * claim otherwise, because a booking nothing is surfacing is a booking that
     * gets forgotten.
     *
     * And the reverse: a booking approved without ever being read is still unseen,
     * and still badged. Filtering either way would re-couple the two ideas.
     */
    public function test_seen_and_pending_are_separate_questions(): void
    {
        $admin = $this->makeAdmin();

        // Pending, but the salon has looked at it.
        $this->makeAppointment($this->makeUser(), null, [
            'status' => AppointmentStatus::Pending,
            'admin_seen_at' => now(),
        ]);

        // Approved, and nobody ever looked at it.
        $this->makeAppointment($this->makeUser(), null, [
            'status' => AppointmentStatus::Confirmed,
            'admin_seen_at' => null,
        ]);

        $payload = $this->actingAs($admin, 'admin')->getJson(route('admin.notifications.feed'))->json();

        // One, and it is the one nobody has looked at.
        $this->assertSame(1, $payload['pending']);

        $ids = array_column(
            array_values(array_filter($payload['items'], fn ($item) => $item['kind'] === 'appointment')),
            'id'
        );

        $this->assertCount(1, $ids);
    }

/**
 * The feed's count and the sidebar's badge are the same number.
 *
 * They are two separate queries on the same rows, and the bell broadcasts its
 * count to the sidebar — so if the two scopes ever diverge the panel shows two
 * different numbers for one thing, with no way for a user to tell which is
 * right. Asserted together because that is the property, not either count alone.
 */
public function test_the_feed_count_agrees_with_the_sidebar_badge(): void
{
    $admin = $this->makeAdmin();

    // Two the salon has not looked at, and two it has.
    $this->makeAppointment($this->makeUser(), null, ['status' => AppointmentStatus::Pending]);
    $this->makeAppointment($this->makeUser(), null, ['status' => AppointmentStatus::Pending]);
    $this->makeAppointment($this->makeUser(), null, ['status' => AppointmentStatus::Confirmed, 'admin_seen_at' => now()]);
    $this->makeAppointment($this->makeUser(), null, ['status' => AppointmentStatus::Completed, 'admin_seen_at' => now()]);

    $unseen = \App\Models\Appointment::unseenForAdminCount();

    $this->assertSame(2, $unseen, 'Seen rows must not count towards the badge.');

    $payload = $this->actingAs($admin, 'admin')->getJson(route('admin.notifications.feed'))->json();

    $this->assertSame($unseen, $payload['pending']);

    // And the sidebar renders that same number, rather than its own idea of it.
    $html = $this->actingAs($admin, 'admin')->get('/admin')->assertOk()->getContent();

    $this->assertStringContainsString('"pending":'.$unseen, $this->unescaped($html));
}

    /**
     * It polls, and the poll is what keeps the badge current.
     *
     * Asserted on the source because "it updates without a reload" is a runtime
     * property; what can be pinned in a test is that the timer exists and calls
     * the endpoint, which is the mechanism that delivers it.
     */
    public function test_the_admin_poller_polls_on_a_timer(): void
    {
        $source = file_get_contents(base_path('resources/js/app.js'));

        $this->assertStringContainsString("Alpine.data('adminLiveNotifications'", $source);
        $this->assertStringContainsString('setInterval(() => this.poll()', $source);
        $this->assertStringContainsString("'visibilitychange'", $source, 'A tab coming back to the foreground should catch up immediately.');
    }
}
