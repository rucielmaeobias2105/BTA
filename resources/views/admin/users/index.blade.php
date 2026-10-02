@extends('layouts.admin')

@section('title', 'Registered Users')
@section('heading', 'Registered Users')

@section('content')
    {{--
        The same card the Services list sits in: a live search box, the
        entries-per-page select beside it, the table, then the pager below.

        Customers register themselves, so there is no add button. There is now a
        Delete action per row — the one write on this screen — and it goes through
        the shared Yes/No dialog rather than the browser's own `confirm()`.

        What a delete does is stated in the dialog rather than in this comment,
        because it is the thing an admin has to understand before pressing Yes:
        the account is anonymised and soft-deleted, and their bookings stay on
        record. See `App\Services\UserAnonymizer`.
    --}}
    <x-ui.admin-table :search="$search">
        <table class="bta-table">
            <thead>
                <tr>
                    <x-ui.sortable-th column="last_name" label="Name" :sort="$sort" :direction="$direction" :action="route('admin.users.index')" :params="['search' => $search]" />
                    <x-ui.sortable-th column="email" label="Email" :sort="$sort" :direction="$direction" :action="route('admin.users.index')" :params="['search' => $search]" />
                    <x-ui.sortable-th column="contact_number" label="Contact" :sort="$sort" :direction="$direction" :action="route('admin.users.index')" :params="['search' => $search]" />
                    <th class="text-right">Actions</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($users as $user)
                    <tr data-row x-show="isShown({{ $loop->index }})">
                        {{--
                            The name is the link: it is the one column that means
                            "this record", and it is the way into the profile. The
                            username sits under it.
                        --}}
                        <td>
                            <a href="{{ route('admin.users.show', $user) }}" class="block min-w-0">
                                <p class="truncate font-medium text-primary underline-offset-2 hover:underline">{{ $user->full_name }}</p>
                                <p class="truncate text-xs text-ink-muted">{{ '@'.$user->username }}</p>
                            </a>
                        </td>
                        <td class="text-ink">{{ $user->email }}</td>
                        <td class="whitespace-nowrap text-ink">{{ $user->contact_number }}</td>
                        <td>
                            {{-- A button, not a link and not a form: the real one is
                                 inside the dialog below, so a form per row would repeat
                                 a CSRF token and a `_method` spoof once per customer. --}}
                            <div class="flex justify-end">
                                <button
                                    type="button"
                                    class="icon-action icon-action-danger"
                                    title="Delete this account"
                                    aria-label="Delete {{ $user->full_name }}'s account"
                                    x-on:click.prevent="$dispatch('confirm-delete-user', { id: {{ $user->id }} })"
                                >
                                    <x-heroicon-o-trash class="h-4 w-4" />
                                </button>
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="4" class="py-12 text-center text-sm text-ink-muted">
                            No users found.
                        </td>
                    </tr>
                @endforelse

                {{-- Shown when the search box has filtered every row away. --}}
                @if ($users->isNotEmpty())
                    <tr x-show="total === 0" x-cloak>
                        <td colspan="4" class="py-12 text-center text-sm text-ink-muted">No data available</td>
                    </tr>
                @endif
            </tbody>
        </table>
    </x-ui.admin-table>
@endsection

@push('modals')
    {{--
        The Yes/No confirmation, and a bespoke dialog around it.

        The shared `x-ui.confirm-dialog` does the work — it owns the state, the
        focus handling and the single real form, and it is the same dialog the
        notifications list and My Appointments ask with. What it cannot do is say
        *what* deleting means, which is the part that matters here: this is not a
        row disappearing, it is an account being anonymised while its bookings
        stay. So the shared component is mounted inside a wrapper that fills in the
        name and the booking count, and the two share one dialog.

        The payload comes from `admin.users.delete-confirmation` rather than being
        baked into the row, so the wording is the server's and there is one source
        of it.
    --}}
    <div
        x-data="{
            open: false,
            id: null,
            name: '',
            email: '',
            appointments: 0,
            action: '',
            loading: false,
            error: '',
            ask(detail) {
                this.id = detail.id;
                this.open = false;
                this.loading = true;
                this.error = '';

                fetch(this.endpoint(detail.id), {
                    headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                })
                    .then((response) => {
                        if (!response.ok) throw new Error('unreachable');

                        return response.json();
                    })
                    .then((payload) => {
                        this.name = payload.name;
                        this.email = payload.email;
                        this.appointments = payload.appointments;
                        this.action = payload.action;
                        this.open = true;
                    })
                    .catch(() => {
                        // A failure here means the dialog cannot describe what is
                        // about to happen, and an admin should not be asked to
                        // confirm an action whose consequences are unknown. The
                        // toast says so rather than opening a blank prompt.
                        window.btaToast?.show('error', 'Could not load the delete confirmation. Try again.');
                    })
                    .finally(() => {
                        this.loading = false;
                    });
            },
            /**
                 * The confirmation URL for one row.
                 *
                 * A `USER_ID` template on the wrapper's data attribute rather
                 * than built here: a route() call inside this scope would need its
                 * placeholder escaped through Blade *and* through the JavaScript
                 * parser, and it cannot use a `{id}` token at all — the URL
                 * generator reads a brace placeholder back as a parameter name and
                 * throws.
                 */
                endpoint(id) {
                    return this.$root.dataset.confirmationTemplate.replace('USER_ID', id);
                },
            get subjectPhrase() {
                return 'customer account';
            },
        }"
        {{-- `USER_ID`, not `{id}`: the URL generator scans a finished path for
             `{parameter}` tokens, so a brace placeholder inside the value it was
             given is read back as a parameter name and the route() call throws. --}}
        data-confirmation-template="{{ route('admin.users.delete-confirmation', ['user' => 'USER_ID']) }}"
        x-on:confirm-delete-user.window="ask($event.detail)"
        x-on:keydown.escape.window="if (open) cancel()"
    >
        <div
            x-show="open"
            x-cloak
            class="fixed inset-0 z-[60] flex items-end justify-center overflow-y-auto p-4 sm:items-center"
            role="dialog"
            aria-modal="true"
            aria-labelledby="confirm-delete-user-title"
        >
            <div class="modal-backdrop fixed inset-0" x-on:click="cancel()" aria-hidden="true"></div>

            <div x-on:click.stop class="modal-panel max-w-md">
                <form method="POST" x-bind:action="action">
                    @csrf
                    @method('DELETE')

                    <div class="flex items-start justify-between gap-4 border-b border-line/70 px-5 py-4">
                        <h2 id="confirm-delete-user-title" class="font-display text-lg font-semibold text-primary">
                            Delete Customer Account
                        </h2>
                        <button type="button" x-on:click="cancel()" class="btn-ghost btn-sm" aria-label="Close">&times;</button>
                    </div>

                    <div class="px-5 py-5">
                        {{-- The two questions an admin has to be able to answer from
                             this dialog rather than from memory: which account, and
                             what survives. --}}
                        <p class="text-sm leading-relaxed text-ink">
                            Delete
                            <span class="font-semibold text-primary" x-text="name"></span>
                            <span class="text-ink-muted" x-text="'(' + email + ')'"></span>?
                        </p>

                        <div class="mt-4 rounded-card border border-primary/10 bg-linen/60 p-4 text-sm text-ink">
                            <p class="font-semibold text-primary">What this does</p>
                            <ul class="mt-2 space-y-1.5 text-xs leading-relaxed text-ink-muted">
                                <li>
                                    The name, email, phone number and profile photo are
                                    erased. The account cannot be signed into again.
                                </li>
                                <li x-show="appointments > 0">
                                    Their bookings stay on the salon&rsquo;s record
                                    &mdash;
                                    <span class="font-semibold text-ink" x-text="appointments"></span>
                                    <span x-text="appointments === 1 ? 'booking' : 'bookings'"></span>,
                                    with dates, services and amounts intact.
                                </li>
                                <li x-show="appointments === 0">
                                    They have no bookings on record.
                                </li>
                                <li>
                                    This is reversible: nothing is permanently erased,
                                    so an admin who deletes the wrong row can put it
                                    back from the database.
                                </li>
                            </ul>
                        </div>
                    </div>

                    {{-- `x-ref="confirm"` is what the dialog focuses on open, so
                         Enter lands on Yes without the reader having to tab. --}}
                    <div class="flex flex-wrap justify-end gap-2 border-t border-line/70 bg-linen/50 px-5 py-4">
                        <button type="button" class="btn-ghost" x-on:click="cancel()">No</button>
                        <button type="submit" class="btn-danger" x-ref="confirm">Yes</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
@endpush