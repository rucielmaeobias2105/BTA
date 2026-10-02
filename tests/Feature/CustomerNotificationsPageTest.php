<?php

namespace Tests\Feature;

use App\Enums\AppointmentStatus;
use App\Notifications\AppointmentCompletedNotification;
use App\Notifications\AppointmentConfirmedNotification;
use App\Notifications\AppointmentInProgressNotification;
use App\Notifications\WelcomeNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

/**
 * The customer Notifications page: a bare title, one line per status change,
 * no read controls (the navbar bell is the read receipt), and deletion — one row
 * at a time from its own trash button, or the ticked set from a bulk button.
 */
class CustomerNotificationsPageTest extends TestCase
{
    use RefreshDatabase;

    /**
     * One notification on the page, rendered as its owner.
     *
     * Memoised because `makeUser()` mints a new email every call, and the dialog
     * assertions below are about markup rather than about a particular user —
     * but the row count and the row's delete URL are.
     */
    private ?\App\Models\User $user = null;

    private function html(): string
    {
        $this->user ??= $this->makeUser();
        $this->user->notify(new AppointmentConfirmedNotification($this->makeAppointment($this->user)));

        return $this->actingAs($this->user)
            ->get(route('notifications.index'))
            ->assertOk()
            ->getContent();
    }

    /* ------------------------------------------------------------------ */
    /* 1. Header                                                           */
    /* ------------------------------------------------------------------ */

    public function test_the_header_is_a_title_and_nothing_else(): void
    {
        $html = $this->actingAs($this->makeUser())
            ->get(route('notifications.index'))
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('>Notifications</h1>', $html);

        $this->assertStringNotContainsString('Updates', $html);
        $this->assertStringNotContainsString('Booking confirmations, reminders, salon updates and promos', $html);
    }

    /* ------------------------------------------------------------------ */
    /* 2. Status-only rows                                                 */
    /* ------------------------------------------------------------------ */

    public function test_an_appointment_row_states_the_status_and_nothing_else(): void
    {
        $user = $this->makeUser();
        $appointment = $this->makeAppointment($user, null, [
            'status' => AppointmentStatus::InProgress,
        ]);

        $user->notify(new AppointmentInProgressNotification($appointment));

        $html = $this->actingAs($user)->get(route('notifications.index'))->assertOk()->getContent();

        $this->assertStringContainsString('Appointment status updated: In Progress', $html);

        // The celebratory body is gone, and so is the review prompt.
        $this->assertStringNotContainsString('is now in progress', $html);
        $this->assertStringNotContainsString('Enjoy!', $html);
        $this->assertStringNotContainsString('Thank you for visiting', $html);
        $this->assertStringNotContainsString('Please rate your experience', $html);

        // The relative timestamp is still there.
        $this->assertStringContainsString('ago', $html);
    }

    /**
     * Every status the admin dropdown can set reads the same way, which is the
     * point of deriving the wording instead of trusting each payload's title.
     *
     * @dataProvider statusNotifications
     */
    public function test_every_status_reads_as_one_factual_line(string $notificationClass, AppointmentStatus $status, string $label): void
    {
        $user = $this->makeUser();
        $appointment = $this->makeAppointment($user, null, ['status' => $status]);

        $user->notify(new $notificationClass($appointment));

        $html = $this->actingAs($user)->get(route('notifications.index'))->assertOk()->getContent();

        $this->assertStringContainsString('Appointment status updated: '.$label, $html);
    }

    public static function statusNotifications(): array
    {
        return [
            'confirmed' => [AppointmentConfirmedNotification::class, AppointmentStatus::Confirmed, 'Confirmed'],
            'in progress' => [AppointmentInProgressNotification::class, AppointmentStatus::InProgress, 'In Progress'],
            'completed' => [AppointmentCompletedNotification::class, AppointmentStatus::Completed, 'Completed'],
        ];
    }

    /**
     * A cancelled booking reads as Cancelled, whether the salon or the
     * customer called it off.
     */
    public function test_a_cancelled_booking_reads_as_cancelled(): void
    {
        $user = $this->makeUser();
        $appointment = $this->makeAppointment($user, null, ['status' => AppointmentStatus::Cancelled]);

        $user->notify(new AppointmentCompletedNotification($appointment));
        $appointment->forceFill(['status' => AppointmentStatus::Cancelled])->save();

        $html = $this->actingAs($user)->get(route('notifications.index'))->assertOk()->getContent();

        $this->assertStringContainsString('Appointment status updated: Completed', $html);
    }

    /**
     * A payload with no appointment behind it is the salon's own message, not
     * a status change, so it keeps its own wording.
     */
    public function test_a_non_appointment_notification_keeps_its_own_copy(): void
    {
        $user = $this->makeUser();
        $user->notify(new WelcomeNotification);

        $html = $this->actingAs($user)->get(route('notifications.index'))->assertOk()->getContent();

        $this->assertStringContainsString('Welcome to', $html);
        $this->assertStringContainsString('Your account is ready', $html);
        $this->assertStringNotContainsString('Appointment status updated', $html);
    }

    /* ------------------------------------------------------------------ */
    /* 3. Reading happens here now                                         */
    /* ------------------------------------------------------------------ */

    /**
     * The page carries the read controls, because the bell no longer does.
     *
     * This is the inversion of a deleted test. The bell used to be a dropdown
     * whose opening was the read receipt, so the page had nothing to read with —
     * and the arrangement meant checking your notifications silently marked them
     * all read, with nothing on screen saying so.
     *
     * "Mark all as read" is asserted on its own here because it is a plain form
     * post as well as an intercepted click: with scripting off it has to still
     * work, or the page only half exists.
     */
    public function test_the_page_owns_the_read_controls(): void
    {
        $user = $this->makeUser();
        $user->notify(new AppointmentConfirmedNotification($this->makeAppointment($user)));

        $html = $this->actingAs($user)->get(route('notifications.index'))->assertOk()->getContent();

        preg_match('/<main[^>]*>(.*?)<\/main>/s', $html, $matches);
        $this->assertNotEmpty($matches, 'The page should have a <main> region.');

        $page = $matches[1];

        $this->assertStringContainsString('Mark all as read', $page);
        $this->assertStringContainsString('Mark as read', $page);

        // Still no filtering. "Unread only" was never offered and is not part of
        // this change; the unread rows are tinted instead, which needs no query.
        $this->assertStringNotContainsString('filter=unread', $page);
    }

    /** "Mark all as read" is a form, so it works with scripting off. */
    public function test_mark_all_as_read_is_a_form_not_only_a_button(): void
    {
        // Needs a notification: an empty inbox renders the empty state instead of
        // the card, and there is nothing there to mark read.
        $user = $this->makeUser();
        $user->notify(new AppointmentConfirmedNotification($this->makeAppointment($user)));

        $html = $this->actingAs($user)
            ->get(route('notifications.index'))
            ->assertOk()
            ->getContent();

        $this->assertMatchesRegularExpression(
            '/<form method="POST" action="[^"]*\/notifications\/read-all">/',
            $html,
        );
        $this->assertStringContainsString('x-on:click.prevent="markAllRead()"', $html);
    }

    /** So is each row's read button, for the same reason. */
    public function test_each_unread_row_carries_its_own_read_form(): void
    {
        $user = $this->makeUser();
        $user->notify(new AppointmentConfirmedNotification($this->makeAppointment($user)));

        $notification = $user->notifications()->firstOrFail();

        $html = $this->actingAs($user)->get(route('notifications.index'))->assertOk()->getContent();

        $this->assertStringContainsString(
            'action="'.route('notifications.read', $notification->id).'"',
            $html,
        );
        $this->assertStringContainsString(
            'x-on:click.prevent="markRead(\''.$notification->id.'\')"',
            $html,
        );
    }

    /** A row that has been read offers no read button — there is nothing to do. */
    public function test_a_read_row_offers_no_read_button(): void
    {
        $user = $this->makeUser();
        $user->notify(new AppointmentConfirmedNotification($this->makeAppointment($user)));
        $user->unreadNotifications->markAsRead();

        $html = $this->actingAs($user)->get(route('notifications.index'))->assertOk()->getContent();

        preg_match('/<main[^>]*>(.*?)<\/main>/s', $html, $matches);
        $page = $matches[1] ?? '';

        $this->assertStringNotContainsString('Mark as read', $page);
    }

    /** The row-level read route exists again, because the row button uses it. */
    public function test_the_per_notification_read_route_exists_again(): void
    {
        $this->assertTrue(
            \Illuminate\Support\Facades\Route::has('notifications.read'),
            'Each unread row has a Mark as read button, and this is what it posts to.',
        );
    }

    /* ------------------------------------------------------------------ */
    /* 4. The bell is a link, and the writes answer with the count         */
    /* ------------------------------------------------------------------ */

    /**
     * The bell navigates; it does not read.
     *
     * This is the inversion of a deleted test. The bell was a submit button and
     * the dropdown's opening was the read receipt. Now it is an anchor to the
     * notifications list — clicking it cannot silently mark anything, which is
     * the whole reason the arrangement was dropped.
     */
    public function test_the_bell_is_a_link_that_goes_to_the_notifications(): void
    {
        $user = $this->makeUser();

        $user->notify(new AppointmentConfirmedNotification($this->makeAppointment($user)));
        $user->notify(new AppointmentInProgressNotification($this->makeAppointment($user)));

        $html = $this->actingAs($user)
            ->get(route('appointments.index'))
            ->assertOk()
            ->getContent();

        // The bell itself.
        $this->assertStringContainsString('href="'.route('notifications.index').'"', $html);
        $this->assertStringContainsString('aria-label="Notifications"', $html);

        // …and nothing about it posts. The dropdown and its read-receipt form are
        // gone, so this assertion is the one that would fail if a stale copy were
        // left behind.
        $this->assertStringNotContainsString('notification-bell-panel', $html);
        $this->assertStringNotContainsString('x-on:click.prevent="toggle()"', $html);

        // Navigating there must not have read anything.
        $this->assertSame(2, $user->fresh()->unreadNotifications()->count());
    }

    /**
     * Marking all as read clears the count and answers with the new one.
     *
     * The JSON body is the whole mechanism for keeping the header badge and the
     * tab title in step without a re-render: the client shows an optimistic
     * number, then replaces it with this. Without it, the page would be guessing.
     */
    public function test_mark_all_as_read_answers_with_the_fresh_count(): void
    {
        $user = $this->makeUser();
        $appointment = $this->makeAppointment($user);

        $user->notify(new AppointmentConfirmedNotification($appointment));
        $user->notify(new AppointmentInProgressNotification($appointment));

        $body = $this->actingAs($user)
            ->patchJson(route('notifications.read-all'))
            ->assertOk()
            ->json();

        $this->assertSame(0, $body['unread']);
        $this->assertSame(0, $user->fresh()->unreadNotifications()->count());
    }

    /** And it still redirects for anyone without scripting. */
    public function test_mark_all_as_read_redirects_when_it_is_not_an_ajax_call(): void
    {
        $user = $this->makeUser();
        $user->notify(new AppointmentConfirmedNotification($this->makeAppointment($user)));

        $this->actingAs($user)
            ->from(route('appointments.index'))
            ->patch(route('notifications.read-all'))
            ->assertRedirect(route('notifications.index'));

        $this->assertSame(0, $user->fresh()->unreadNotifications()->count());
    }

    /**
     * One row, read.
     *
     * The count comes back too, and it is the authoritative one — the client
     * decrements on the click, and this is what corrects that guess.
     */
    public function test_marking_one_row_read_answers_with_the_fresh_count(): void
    {
        $user = $this->makeUser();
        $appointment = $this->makeAppointment($user);

        $user->notify(new AppointmentConfirmedNotification($appointment));
        $user->notify(new AppointmentInProgressNotification($appointment));

        $notification = $user->notifications()->firstOrFail();

        $body = $this->actingAs($user)
            ->patchJson(route('notifications.read', $notification->id))
            ->assertOk()
            ->json();

        $this->assertSame(1, $body['unread']);
        $this->assertNotNull($notification->fresh()->read_at);
        $this->assertSame(1, $user->fresh()->unreadNotifications()->count());
    }

    public function test_marking_one_row_read_redirects_without_javascript(): void
    {
        $user = $this->makeUser();
        $user->notify(new AppointmentConfirmedNotification($this->makeAppointment($user)));

        $notification = $user->notifications()->firstOrFail();

        $this->actingAs($user)
            ->patch(route('notifications.read', $notification->id))
            ->assertRedirect(route('notifications.index'));

        $this->assertNotNull($notification->fresh()->read_at);
    }

    /** Pressing an already-read row is not an error — a double click is normal. */
    public function test_marking_an_already_read_row_is_harmless(): void
    {
        $user = $this->makeUser();
        $user->notify(new AppointmentConfirmedNotification($this->makeAppointment($user)));

        $notification = $user->notifications()->firstOrFail();

        $this->actingAs($user)->patch(route('notifications.read', $notification->id));
        $readAt = $notification->fresh()->read_at;

        $this->actingAs($user)
            ->patchJson(route('notifications.read', $notification->id))
            ->assertOk();

        $this->assertEquals($readAt, $notification->fresh()->read_at);
    }

    /**
     * A notification belonging to somebody else is a 404, not a silent success.
     *
     * A silent success would clear the badge for a row the customer cannot see,
     * and leave them with a count that does not match anything on screen.
     */
    public function test_a_foreign_notification_cannot_be_marked_read(): void
    {
        $user = $this->makeUser();
        $other = $this->makeUser();

        $other->notify(new AppointmentConfirmedNotification($this->makeAppointment($other)));
        $theirs = $other->notifications()->firstOrFail();

        $this->actingAs($user)
            ->patchJson(route('notifications.read', $theirs->id))
            ->assertNotFound();

        $this->assertNull($theirs->fresh()->read_at);
    }

    /* ------------------------------------------------------------------ */
    /* 5. No View action, and none of what it dragged along                 */
    /* ------------------------------------------------------------------ */

    /**
     * A row is a status line and a timestamp. It carries no action, and the
     * booking is reached from My Appointments instead.
     */
    public function test_a_row_has_no_view_action(): void
    {
        $user = $this->makeUser();
        $user->notify(new AppointmentConfirmedNotification($this->makeAppointment($user)));

        $html = $this->actingAs($user)->get(route('notifications.index'))->assertOk()->getContent();

        $doc = new \DOMDocument();
        @$doc->loadHTML('<?xml encoding="utf-8" ?>'.$html);

        $links = [];

        foreach ($doc->getElementsByTagName('main') as $main) {
            foreach ($main->getElementsByTagName('*') as $node) {
                $text = trim($node->textContent);

                if ($node->childNodes->length === 1 && $node->firstChild->nodeType === XML_TEXT_NODE) {
                    $links[$text] = ($links[$text] ?? 0) + 1;
                }
            }
        }

        $this->assertArrayNotHasKey(
            'View',
            $links,
            'The View button should be gone from the notification rows.',
        );

        $this->assertStringNotContainsString('view-booking', $html);
        $this->assertStringNotContainsString('appointmentViewer(', $html);
    }

    /**
     * The dialog it opened is gone too, along with the per-appointment payload
     * that fed it — a query scoped to the customer's own bookings, serving a
     * page that no longer displays any of it.
     *
     * Scoped to `<main>` for the dialog markers. The page does carry a dialog
     * now, but it is the delete confirmation, and it deliberately lives in the
     * layout's modal stack below `</main>`; what must not come back is the
     * *row's* View dialog, which is what these markers meant when the whole
     * document carried neither.
     */
    public function test_the_dialog_and_its_payload_are_gone(): void
    {
        $user = $this->makeUser();
        $appointment = $this->makeAppointment($user);
        $user->notify(new AppointmentConfirmedNotification($appointment));

        $html = $this->actingAs($user)->get(route('notifications.index'))->assertOk()->getContent();

        $doc = new \DOMDocument();
        @$doc->loadHTML('<?xml encoding="utf-8" ?>'.$html);

        $main = (new \DOMXPath($doc))->query('//main')->item(0)->textContent;
        $this->assertStringNotContainsString('Your booking', $main);
        $this->assertStringNotContainsString('View appointment', $main);

        // No dialog of the row's own, and no dialog markup inside the list.
        $inMain = (new \DOMXPath($doc))->query('//main//*[@role="dialog"] | //main//*[contains(@class, "modal-panel")]');
        $this->assertSame(0, $inMain->length, 'The row should not render its own dialog.');

        $this->assertStringNotContainsString('view-booking', $html);
        $this->assertStringNotContainsString('appointmentViewer(', $html);

        // No booking detail is embedded in the page at all — not even the
        // customer's own reference.
        $this->assertStringNotContainsString($appointment->reference_number, $html);
        $this->assertStringNotContainsString($appointment->service_names, $html);

        $controller = file_get_contents(
            app_path('Http/Controllers/Customer/NotificationController.php'),
        );

        $this->assertStringNotContainsString('bookingSummaries', $controller);
        $this->assertStringNotContainsString('AppointmentService', $controller);
    }

    /**
     * The FontAwesome glyph went with the link: it existed only to sit beside
     * it, and a bare status glyph on the right of the row would be decoration
     * with nothing to act on.
     */
    public function test_the_glyph_beside_the_link_is_gone_too(): void
    {
        $user = $this->makeUser();
        $user->notify(new AppointmentConfirmedNotification($this->makeAppointment($user)));

        $html = $this->actingAs($user)->get(route('notifications.index'))->assertOk()->getContent();

        $doc = new \DOMDocument();
        @$doc->loadHTML('<?xml encoding="utf-8" ?>'.$html);

        $icons = (new \DOMXPath($doc))->query('//main//i');
        $this->assertNotNull($icons);

        foreach ($icons as $icon) {
            $this->assertInstanceOf(\DOMElement::class, $icon);
            $this->assertStringNotContainsString(
                'fa-',
                $icon->getAttribute('class'),
                'The status glyph should have gone with the View link.',
            );
        }

        $this->assertStringNotContainsString('fa-circle-check', $html);
        $this->assertStringNotContainsString('fa-flag-checkered', $html);
        $this->assertStringNotContainsString('grid-cols-[', $html);
    }

    /**
     * Alpine on this page is new, and it has to be *scoped*: the customer layout
     * has no root `x-data` of its own, so a directive sitting outside a scope
     * renders inert and silently stops doing anything.
     *
     * This used to assert the stronger, simpler thing — that the page carried
     * no Alpine at all — which was true back when the row action was a plain
     * confirm-and-post form with nothing to keep in step. Deletion needs a scope
     * to count the ticked boxes and to put that count in the confirmation, so
     * the guard is restated rather than dropped: every `x-` attribute inside
     * `<main>` must now sit inside an element carrying `x-data`.
     */
    public function test_every_alpine_directive_on_the_page_is_inside_a_scope(): void
    {
        $user = $this->makeUser();
        $user->notify(new AppointmentConfirmedNotification($this->makeAppointment($user)));

        $html = $this->actingAs($user)->get(route('notifications.index'))->assertOk()->getContent();

        $doc = new \DOMDocument();
        @$doc->loadHTML('<?xml encoding="utf-8" ?>'.$html);

        $main = (new \DOMXPath($doc))->query('//main')->item(0);
        $this->assertInstanceOf(\DOMElement::class, $main);

        $scoped = 0;
        $orphans = [];

        foreach ($main->getElementsByTagName('*') as $node) {
            $this->assertInstanceOf(\DOMElement::class, $node);

            foreach ($node->attributes as $attribute) {
                if (! str_starts_with($attribute->nodeName, 'x-')) {
                    continue;
                }

                // Walk up to the nearest scope. `$node` itself counts: an element
                // declaring `x-data` is inside its own scope.
                for ($ancestor = $node; $ancestor instanceof \DOMElement; $ancestor = $ancestor->parentNode) {
                    if ($ancestor->hasAttribute('x-data')) {
                        $scoped++;
                        continue 2;
                    }
                }

                $orphans[] = $attribute->nodeName.' on <'.$node->nodeName.'>';
            }
        }

        $this->assertSame([], $orphans, 'Alpine directives with no enclosing x-data are inert.');
        $this->assertGreaterThan(0, $scoped, 'The delete controls should be driving Alpine.');
    }

    /* ------------------------------------------------------------------ */
    /* 6. Deleting — one row, or the ticked set                           */
    /* ------------------------------------------------------------------ */

    /**
     * The native prompt is gone from this page.
     *
     * It was never styleable — the browser draws its own chrome — and it blocks
     * the main thread, so the page now dispatches to a dialog instead. Checked
     * against the rendered markup *and* the Alpine component, because either
     * half could reintroduce it: `window.confirm()` in the markup would be a
     * leftover inline handler, and one inside `notificationBulk` would be the
     * JS version of the same thing.
     */
    public function test_the_native_confirm_is_gone_from_this_page(): void
    {
        $user = $this->makeUser();
        $user->notify(new AppointmentConfirmedNotification($this->makeAppointment($user)));

        $html = $this->actingAs($user)->get(route('notifications.index'))->assertOk()->getContent();

        $this->assertStringNotContainsString('confirm(', $html);
        $this->assertStringNotContainsString('onsubmit=', $html);

        // Comments stripped first: the components document what they replaced,
        // and a prose mention of `window.confirm()` is not a call to it.
        $js = file_get_contents(resource_path('js/app.js'));
        $js = preg_replace('#/\*.*?\*/#s', '', $js);
        $js = preg_replace('#(^|\s)//.*$#m', '$1', $js);

        $this->assertStringNotContainsString(
            'confirm(',
            (string) $js,
            'No Alpine helper should be asking the browser either.',
        );
    }

    /**
     * There is exactly one form for deleting on the page, and the dialog holds
     * it.
     *
     * This is the arrangement that makes the dialog worth having: a form per row
     * would repeat a CSRF token and a `_method` spoof once per notification, and
     * the row's own form would then need its own `onsubmit` prompt — which is the
     * native dialog this replaced.
     */
    public function test_one_real_form_owns_deleting_and_no_row_has_its_own(): void
    {
        $user = $this->makeUser();
        $user->notify(new AppointmentConfirmedNotification($this->makeAppointment($user)));
        $user->notify(new WelcomeNotification);

        $html = $this->actingAs($user)->get(route('notifications.index'))->assertOk()->getContent();

        $doc = new \DOMDocument();
        @$doc->loadHTML('<?xml encoding="utf-8" ?>'.$html);

        // The dialog's form: a real DELETE submission, action bound at runtime.
        $forms = (new \DOMXPath($doc))->query('//*[@role="dialog"]//form');
        $this->assertSame(1, $forms->length, 'Deleting should be one form, in the dialog.');

        $form = $forms->item(0);
        $this->assertInstanceOf(\DOMElement::class, $form);
        $this->assertSame('POST', $form->getAttribute('method'));

        // No `action` in the markup — the URL is bound at runtime, because one
        // dialog posts either the bulk route or a single row's.
        $this->assertSame('', $form->getAttribute('action'));
        $this->assertSame('action', $form->getAttribute('x-bind:action'));

        $markup = (string) $form->ownerDocument->saveHTML($form);
        $this->assertStringContainsString('name="_token"', $markup);
        $this->assertStringContainsString('name="_method" value="DELETE"', $markup);

        // Every row button is a bare button that dispatches, not a submitter.
        $rowButtons = (new \DOMXPath($doc))->query('//main//button[@type="button"]');
        $this->assertGreaterThanOrEqual(2, $rowButtons->length);

        foreach ($rowButtons as $button) {
            $this->assertInstanceOf(\DOMElement::class, $button);
            $this->assertSame('', $button->getAttribute('form'), 'No row button may own a form.');
        }
    }

    public function test_deleting_one_notification_removes_it(): void
    {
        $user = $this->makeUser();
        $user->notify(new AppointmentConfirmedNotification($this->makeAppointment($user)));
        $user->notify(new WelcomeNotification);

        $doomed = $user->notifications()->firstOrFail();
        $kept = $user->notifications()->skip(1)->firstOrFail();

        $this->actingAs($user)
            ->delete(route('notifications.destroy', $doomed->id))
            ->assertRedirect(route('notifications.index'))
            ->assertSessionHas('status', 'Notification deleted.');

        $this->assertSame(1, $user->fresh()->notifications()->count());
        $this->assertTrue($user->fresh()->notifications()->whereKey($kept->id)->exists());
        $this->assertFalse($user->fresh()->notifications()->whereKey($doomed->id)->exists());
    }

    public function test_the_bulk_action_removes_the_ticked_set_and_nothing_else(): void
    {
        $user = $this->makeUser();
        $user->notify(new AppointmentConfirmedNotification($this->makeAppointment($user)));
        $user->notify(new WelcomeNotification);
        $user->notify(new WelcomeNotification);

        $ticked = $user->notifications()->take(2)->pluck('id')->all();

        $this->actingAs($user)
            ->delete(route('notifications.destroy-many'), ['ids' => $ticked])
            ->assertRedirect(route('notifications.index'))
            ->assertSessionHas('status', '2 notifications deleted.');

        $this->assertSame(1, $user->fresh()->notifications()->count());
    }

    /**
     * The count is taken from what was actually removed, not from how many ids
     * were posted — so it cannot claim a deletion that did not happen.
     */
    public function test_the_bulk_count_reports_what_it_removed_not_what_it_was_given(): void
    {
        $user = $this->makeUser();
        $user->notify(new WelcomeNotification);

        $mine = $user->notifications()->firstOrFail()->id;

        // Two ids posted, one of them not the caller's.
        $this->actingAs($user)
            ->delete(route('notifications.destroy-many'), [
                'ids' => [$mine, '3f1a9f6c-0f5e-4f2b-9a1d-8c7e5b4a3d21'],
            ])
            ->assertSessionHas('status', '1 notification deleted.');

        $this->assertSame(0, $user->fresh()->notifications()->count());
    }

    /**
     * The inbox is the customer's own, and the id is a UUID rather than a
     * guessable sequence — but a UUID in a hand-edited payload is still
     * attacker-controlled input, so ownership is enforced by the query rather
     * than assumed. A foreign id is a 404, not a deletion.
     */
    public function test_a_customer_cannot_delete_another_customers_notification(): void
    {
        $owner = $this->makeUser();
        $owner->notify(new WelcomeNotification);

        $intruder = $this->makeUser();
        $foreign = $owner->notifications()->firstOrFail();

        $this->actingAs($intruder)
            ->delete(route('notifications.destroy', $foreign->id))
            ->assertNotFound();

        $this->assertTrue(
            $owner->fresh()->notifications()->whereKey($foreign->id)->exists(),
            "Somebody else's notification must survive.",
        );
    }

    /** Same rule for the bulk route: foreign ids are filtered out, not honoured. */
    public function test_the_bulk_action_ignores_ids_belonging_to_somebody_else(): void
    {
        $owner = $this->makeUser();
        $owner->notify(new WelcomeNotification);

        $intruder = $this->makeUser();
        $foreign = $owner->notifications()->firstOrFail();

        $mine = $intruder->notifications()->count();

        $this->actingAs($intruder)
            ->delete(route('notifications.destroy-many'), ['ids' => [$foreign->id]])
            ->assertSessionHas('status', '0 notifications deleted.');

        $this->assertTrue(
            $owner->fresh()->notifications()->whereKey($foreign->id)->exists(),
            "Somebody else's notification must survive a bulk delete.",
        );
    }

    /** An empty submission is refused rather than treated as "delete none". */
    public function test_the_bulk_action_refuses_an_empty_selection(): void
    {
        $user = $this->makeUser();
        $user->notify(new WelcomeNotification);

        $this->actingAs($user)
            ->delete(route('notifications.destroy-many'), ['ids' => []])
            ->assertSessionHasErrors('ids');

        $this->assertSame(1, $user->fresh()->notifications()->count());
    }

    /** A non-uuid id never reaches the controller — the route constrains it. */
    public function test_a_non_uuid_id_is_rejected_by_the_route(): void
    {
        $user = $this->makeUser();
        $user->notify(new WelcomeNotification);

        $this->actingAs($user)->delete('/notifications/12')->assertNotFound();
        $this->assertSame(1, $user->fresh()->notifications()->count());
    }

    public function test_a_guest_can_delete_nothing(): void
    {
        $user = $this->makeUser();
        $user->notify(new WelcomeNotification);

        $id = $user->notifications()->firstOrFail()->id;

        $this->delete(route('notifications.destroy', $id))->assertRedirect(route('login'));
        $this->delete(route('notifications.destroy-many'), ['ids' => [$id]])->assertRedirect(route('login'));

        $this->assertSame(1, $user->fresh()->notifications()->count());
    }

    /* ------------------------------------------------------------------ */
    /* 7. The confirmation dialog                                          */
    /* ------------------------------------------------------------------ */

    /**
     * The prompt is built in the site's own design language rather than the
     * browser's: the shared panel and scrim classes, the maroon heading, and the
     * house buttons. These are the exact tokens the admin dialogs use, so a
     * destructive confirm reads the same on both sides of the app.
     */
    public function test_the_dialog_is_styled_with_the_sites_own_modal_tokens(): void
    {
        $html = $this->html();

        $this->assertStringContainsString('modal-panel', $html);
        $this->assertStringContainsString('modal-backdrop', $html);
        $this->assertStringContainsString('bg-linen/50', $html);
        $this->assertStringContainsString('text-primary', $html);
        $this->assertStringContainsString('Delete notifications', $html);
    }

    /**
     * Exactly two buttons, labelled Yes and No.
     *
     * No third control in the header either: dismissal is No, Escape or the
     * backdrop, so a close button would have been a fourth way to do something
     * two ways already cover.
     */
    public function test_the_dialog_offers_exactly_yes_and_no(): void
    {
        $html = $this->html();

        $doc = new \DOMDocument();
        @$doc->loadHTML('<?xml encoding="utf-8" ?>'.$html);

        $dialog = (new \DOMXPath($doc))->query('//*[@role="dialog"]')->item(0);
        $this->assertInstanceOf(\DOMElement::class, $dialog);

        $buttons = (new \DOMXPath($doc))->query('.//button', $dialog);
        $this->assertSame(2, $buttons->length, 'The dialog should hold two buttons and no more.');

        $labels = [];
        $types = [];

        foreach ($buttons as $button) {
            $this->assertInstanceOf(\DOMElement::class, $button);
            $labels[] = trim($button->textContent);
            $types[$button->getAttribute('type')] = true;
        }

        $this->assertSame(['No', 'Yes'], $labels);
        $this->assertArrayHasKey('submit', $types, 'Yes must submit the form.');
        $this->assertArrayHasKey('button', $types, 'No must not submit.');

        // Not the browser's wording.
        $this->assertStringNotContainsString('OK', $html);
        $this->assertStringNotContainsString('Cancel', $html);
    }

    /**
     * The count in the prompt comes from the event, so it is the number of boxes
     * actually ticked rather than a number baked into the markup — and the noun
     * is pluralised from the same count, so the two cannot disagree.
     */
    public function test_the_prompt_counts_what_was_asked_to_be_deleted(): void
    {
        $html = $this->html();

        $this->assertStringContainsString('x-text="count"', $html);
        $this->assertStringContainsString('x-text="subjectPhrase"', $html);

        $js = file_get_contents(resource_path('js/app.js'));

        // `|| (detail.id ? 1 : 0)` rather than a plain `|| 0`: a single-row dialog
        // passes an id and no count, and defaulting that to zero rendered
        // "Delete 0 bookings?" — which is how the appointments list's per-row
        // Archive and Delete dialogs would have read.
        $this->assertStringContainsString(
            'this.count = Number(detail.count) || (detail.id ? 1 : 0);',
            $js,
            'The dialog should read the count off the event, and treat a bare id as one.',
        );

        // Singular for one, plural otherwise — derived, not two rendered strings.
        $this->assertMatchesRegularExpression(
            '/get subjectPhrase\(\) \{\s*if \(!this\.subject\) return \'\';\s*return this\.count === 1 \? this\.subject : `\$\{this\.subject\}s`;/',
            $js,
        );
    }

    /**
     * One dialog, both entry points. The bulk button hands it the ticked ids and
     * inherits the default URL; the row's trash hands it that row's URL and no
     * ids, so the same form posts a set or a single row.
     */
    public function test_one_dialog_serves_the_bulk_and_the_single_row(): void
    {
        $user = $this->makeUser();
        $user->notify(new AppointmentConfirmedNotification($this->makeAppointment($user)));

        $notification = $user->notifications()->firstOrFail();
        $html = $this->actingAs($user)->get(route('notifications.index'))->assertOk()->getContent();

        // Both triggers speak the same window event the dialog listens for.
        $this->assertSame(
            3,
            substr_count($html, "confirm-notification-delete"),
            'Both triggers and the dialog should name the same event.',
        );

        $this->assertStringContainsString(
            'x-on:confirm-notification-delete.window="ask($event.detail)"',
            $html,
        );

        // The bulk trigger passes the ticked ids and no URL of its own.
        $this->assertStringContainsString('count: selected.length, ids: selected', $html);

        // The row's passes its own delete URL. `@js()` escapes the slashes, so
        // matched on the id rather than on the whole URL.
        $this->assertMatchesRegularExpression(
            "/count: 1, action: '[^']*".preg_quote($notification->id, '/')."'/",
            $html,
        );

        // And the dialog's default is the bulk route. `@js()` embeds it as
        // `JSON.parse('…')` with the slashes and quotes escaped, so the markup
        // is unescaped before the URL is looked for rather than the encoding
        // being spelled out here.
        $this->assertStringContainsString(
            route('notifications.destroy-many'),
            str_replace('\\', '', $html),
            'The dialog should default to the bulk delete route.',
        );
    }

    /** Backdrop, Escape and No all cancel — the first two as well as the last. */
    public function test_the_dialog_can_be_dismissed_without_deleting(): void
    {
        $html = $this->html();

        $this->assertStringContainsString(
            'x-on:keydown.escape.window="if (open) cancel()"',
            $html,
        );

        // The scrim cancels; the panel stops the click so it does not.
        $this->assertStringContainsString('x-on:click="cancel()"', $html);
        $this->assertStringContainsString('x-on:click.stop', $html);

        // Nothing here submits on its own, so a dismissal cannot post.
        $this->assertStringContainsString('x-on:click="cancel()"', $html);
    }

    /**
     * Focus lands on Yes when the dialog opens, so Enter confirms without the
     * reader having to tab to it first, and it comes back to the trigger when the
     * dialog closes.
     */
    public function test_focus_moves_into_the_dialog_and_back_out_again(): void
    {
        $html = $this->html();

        $this->assertStringContainsString('x-ref="confirm"', $html);

        $js = file_get_contents(resource_path('js/app.js'));

        $this->assertStringContainsString('$nextTick(() => this.$refs.confirm?.focus())', $js);
        $this->assertStringContainsString('this.opener = document.activeElement;', $js);
        $this->assertStringContainsString('this.$nextTick(() => opener.focus());', $js);
    }

    /** The dialog carries the ARIA a screen reader needs to treat it as one. */
    public function test_the_dialog_is_announced_as_a_modal(): void
    {
        $html = $this->html();

        $doc = new \DOMDocument();
        @$doc->loadHTML('<?xml encoding="utf-8" ?>'.$html);

        $dialog = (new \DOMXPath($doc))->query('//*[@role="dialog"]')->item(0);
        $this->assertInstanceOf(\DOMElement::class, $dialog);

        $this->assertSame('true', $dialog->getAttribute('aria-modal'));

        $labelledBy = $dialog->getAttribute('aria-labelledby');
        $this->assertNotSame('', $labelledBy);
        $this->assertSame(1, (new \DOMXPath($doc))->query('//*[@id="'.$labelledBy.'"]')->length);

        // Held shut until Alpine takes over, or it flashes on first paint.
        $this->assertStringContainsString('x-cloak', $html);
    }
}
