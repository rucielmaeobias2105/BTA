@php
    /*
     * An appointment notification says one thing: the status the booking moved
     * to. The wording is derived from the payload's tone by
     * `App\Support\NotificationRow` — the same helper that shapes the navbar
     * bell's live feed — so a row reads identically whether it arrived by poll
     * or by page load, and so neither copy has to be kept in step.
     *
     * Payloads with no `appointment_id` are the salon's own messages — the
     * welcome, a promo. Those are not status changes, so they keep their own
     * title and body.
     *
     * Reading lives here, and deleting with it.
     *
     * The bell used to be a dropdown whose opening was the read receipt, so this
     * page had nothing to read with: the only way to clear a badge was to open a
     * panel in the header, which said nothing about what it had just done. The
     * bell is a link here now, so "Mark as read" is on the row and "Mark all as
     * read" is on this page — where a customer can see what they are marking.
     *
     * Both are optimistic: the badge in the header and the "(2)" in the tab title
     * move on the click, not on the response, and are then corrected by the
     * count the server sends back. See `notificationRead` in resources/js/app.js
     * and `window.btaUnread` for why the count is shared rather than local.
     *
     * Every control is also a plain form post, so the page still works with
     * scripting off — the buttons below and the hidden forms are not two
     * implementations of one idea, they are one form each with a nicer path.
     *
     * Two ways to delete, one dialog. The per-row trash and the "Delete
     * selected" button are both plain buttons that dispatch the same window
     * event; `x-ui.confirm-dialog` at the foot of this file holds the one real
     * form and posts it on Yes. The event name is set once, here, because the
     * triggers and the dialog have to agree on it and a typo in either place
     * would leave the row silently inert.
     */
    $deleteEvent = 'confirm-notification-delete';

    /*
     * The unread rows on this page, for "Mark all as read" to settle in one tick.
     *
     * `__ID__` is replaced client-side so one template serves every per-row read
     * button, rather than the view rendering N near-identical URLs.
     */
    $unreadIds = $notifications
        ->filter(fn ($notification) => $notification->read_at === null)
        ->pluck('id')
        ->values()
        ->all();
@endphp

{{--
    Two scopes, deliberately nested rather than merged.

    `notificationRead` owns the read actions and the shared count; it sits on the
    card so the header's "Mark all as read" and every row's button can reach it.

    `notificationBulk` owns the tick-to-delete selection, and it has to wrap the
    header checkbox *and* the row checkboxes together — `selected` is shared
    between them, so they cannot be two separate scopes. It is therefore an inner
    element. The one thing that cannot live inside it is the confirm dialog,
    which is pushed to `@stack('modals')` below `</main>` and so listens on the
    window instead.
--}}
<div
    class="bta-card p-5"
    x-data="notificationRead(@js([
        'readAll' => route('notifications.read-all'),
        'readUrl' => route('notifications.read', ['notification' => '__ID__']),
        'unreadIds' => $unreadIds,
    ]))"
>
    @if ($notifications->isEmpty())
        <x-ui.empty
            title="No notifications"
            description="When an appointment changes status it will appear here."
        />
    @else
        <div x-data="notificationBulk()">
            <div class="flex flex-wrap items-center justify-between gap-3 border-b border-primary/10 pb-4">
            <label class="flex cursor-pointer items-center gap-2.5 text-sm text-ink-muted">
                <input
                    type="checkbox"
                    class="checkbox"
                    x-bind:checked="allSelected"
                    x-on:change="toggleAll($event.target.checked)"
                    aria-label="Select every notification on this page"
                >
                Select all on this page
            </label>

            <div class="flex flex-wrap items-center gap-2">
                {{-- Mark all as read: the no-JavaScript path. With scripting on,
                     Alpine intercepts the submit, patches the row and lets the
                     response correct the badge; without it, this posts and
                     redirects back here. Either way the badge ends up right —
                     which is the whole reason it is a form and not a button. --}}
                <form method="POST" action="{{ route('notifications.read-all') }}">
                    @csrf
                    @method('PATCH')
                    <button
                        type="submit"
                        class="btn-secondary btn-sm"
                        x-bind:disabled="unread === 0"
                        x-bind:class="unread === 0 ? 'opacity-50' : ''"
                        x-on:click.prevent="markAllRead()"
                    >
                        <x-heroicon-o-check class="h-4 w-4" />
                        Mark all as read
                    </button>
                </form>

                {{-- Only worth offering once something is ticked, so it stays out
                     of the way of the common case: reading the list. `x-cloak` holds
                     it hidden until Alpine has taken over, otherwise it flashes for
                     a moment with an empty count on first paint. --}}
                <div class="flex items-center gap-3" x-cloak x-show="selected.length > 0">
                    <span class="text-xs text-ink-muted">
                        <span x-text="selected.length"></span>
                        selected
                    </span>

                    <button
                        type="button"
                        class="btn-danger btn-sm"
                        x-on:click="$dispatch('{{ $deleteEvent }}', { count: selected.length, ids: selected })"
                    >
                        <x-heroicon-o-trash class="h-4 w-4" />
                        Delete selected
                    </button>
                </div>
            </div>
        </div>

        <ul class="divide-y divide-primary/8">
            @foreach ($notifications as $notification)
                @php
                    $data = $notification->data;
                    $isBooking = ! empty($data['appointment_id']);
                    $status = \App\Support\NotificationRow::statusFor($data);
                    $rowLabel = $isBooking
                        ? 'Appointment status updated: '.($status ?? 'Updated')
                        : ($data['title'] ?? 'Notification');
                    $isUnread = $notification->read_at === null;
                @endphp

                {{-- An unread row is tinted, the way the bell's dropdown tinted
                     its unread entries, so "which of these have I seen" is
                     answerable without reading the timestamps. --}}
                <li
                    class="flex items-start gap-4 py-4"
                    x-bind:class="'{{ $isUnread ? 'bg-gold/5' : '' }}'"
                    data-read="{{ $isUnread ? 'false' : 'true' }}"
                >
                    {{-- A heroicon rather than a Font Awesome `<i>`: the glyphs are
                         the only icons on the row, and the markup they replaced is
                         pinned by tests that keep decorative glyphs out. --}}
                    <label class="flex shrink-0 cursor-pointer items-center pt-1">
                        <input
                            type="checkbox"
                            class="checkbox"
                            name="ids[]"
                            value="{{ $notification->id }}"
                            x-model="selected"
                            aria-label="Select notification: {{ $rowLabel }}"
                        >
                    </label>

                    <div class="min-w-0 flex-1">
                        <p class="text-sm font-semibold text-ink">
                            @if ($isBooking)
                                Appointment status updated: {{ $status ?? 'Updated' }}
                            @else
                                {{ $data['title'] ?? 'Notification' }}
                            @endif
                        </p>

                        {{-- Only the salon's own messages carry a body. An
                             appointment row is the status line and the time,
                             nothing more. --}}
                        @unless ($isBooking)
                            <p class="mt-0.5 text-sm text-ink-muted">{{ $data['message'] ?? '' }}</p>
                        @endunless

                        <p class="mt-1 text-xs text-ink-muted/80">{{ $notification->created_at->diffForHumans() }}</p>
                    </div>

                    <div class="flex shrink-0 items-center gap-1">
                        {{-- Mark as read. Shown only while the row is unread, and
                             hidden on the click rather than on the response, so
                             the row visibly settles as the badge does. The form
                             underneath is the no-JavaScript path for the same
                             action. --}}
                        @if ($isUnread)
                            <form
                                method="POST"
                                action="{{ route('notifications.read', $notification->id) }}"
                                x-show="! marked('{{ $notification->id }}')"
                            >
                                @csrf
                                @method('PATCH')
                                <button
                                    type="submit"
                                    class="icon-action"
                                    title="Mark as read: {{ $rowLabel }}"
                                    aria-label="Mark as read: {{ $rowLabel }}"
                                    x-bind:disabled="pending['{{ $notification->id }}']"
                                    x-on:click.prevent="markRead('{{ $notification->id }}')"
                                >
                                    <x-heroicon-o-check class="h-4 w-4" />
                                </button>
                            </form>
                        @endif

                        {{-- No form of its own: it names the row's delete URL and
                             hands it to the dialog below, which is where the one
                             real form lives. --}}
                        <button
                            type="button"
                            class="icon-action icon-action-danger"
                            title="Delete notification: {{ $rowLabel }}"
                            aria-label="Delete notification: {{ $rowLabel }}"
                            x-on:click="$dispatch('{{ $deleteEvent }}', { count: 1, action: @js(route('notifications.destroy', $notification->id)) })"
                        >
                            <x-heroicon-o-trash class="h-4 w-4" />
                        </button>
                    </div>
                </li>
            @endforeach
        </ul>

        <div class="mt-6">{{ $notifications->links() }}</div>

        @push('modals')
            {{-- Rendered at `@stack('modals')` below `</main>` in the customer
                 layout, so it sits outside the card's scopes — which is why it
                 listens on the window and takes the count from the event rather
                 than reading `selected` itself.

                 One dialog for both entry points: the bulk button passes `ids`,
                 and the per-row trash passes `action` instead, so the same form
                 posts to `destroy-many` with a ticked set or to `destroy` with
                 the single row the button named.

                 Inside the `@else`, so an empty inbox does not carry a delete
                 dialog that nothing on the page can open. --}}
            <x-ui.confirm-dialog
                title="Delete notifications"
                :event="$deleteEvent"
                :action="route('notifications.destroy-many')"
                subject="notification"
                warning="This cannot be undone."
            />
        @endpush
        </div>
    @endif
</div>
