@extends('layouts.admin')

@section('title', 'Appointments')
@section('heading', 'Appointments')

@section('content')
    {{-- Status tabs. "All" also clears the search/date filters, so it doubles
         as the reset now that the filter card is gone. --}}
    {{-- Status tabs. "All" also clears the search/date filters, so it doubles
         as the reset now that the filter card is gone.

         The Archived pill on the right is a link to a separate screen, not a
         filter on this list: an archived booking is out of the working list
         whatever is searched for, so offering it here would suggest it is still
         in it. --}}
    <div class="mb-5 flex flex-wrap items-center justify-between gap-2">
        <div class="flex flex-wrap gap-2">
            <a href="{{ route('admin.appointments.index') }}"
               class="rounded-pill px-3.5 py-1.5 text-sm font-medium transition {{ empty($filters['status']) ? 'bg-primary text-cream' : 'bg-cream text-ink hover:bg-linen' }}">
                All
            </a>
            @foreach ($statusOptions as $value => $label)
                <a href="{{ route('admin.appointments.index', ['status' => $value]) }}"
                   class="rounded-pill px-3.5 py-1.5 text-sm font-medium transition {{ ($filters['status'] ?? '') === $value ? 'bg-primary text-cream' : 'bg-cream text-ink hover:bg-linen' }}">
                    {{ $label }}
                    @if (($counts[$value] ?? 0) > 0)
                        <span class="ml-1 opacity-70">{{ $counts[$value] }}</span>
                    @endif
                </a>
            @endforeach
        </div>

        <a href="{{ route('admin.appointments.archived') }}" class="btn-secondary btn-sm">
            <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M20.25 7.5l-.625 10.632a2.25 2.25 0 01-2.247 2.118H6.622a2.25 2.25 0 01-2.247-2.118L3.75 7.5M10 11.25h4M3.375 7.5h17.25c.621 0 1.125-.504 1.125-1.125v-1.5c0-.621-.504-1.125-1.125-1.125H3.375c-.621 0-1.125.504-1.125 1.125v1.5c0 .621.504 1.125 1.125 1.125z"/></svg>
            Archived
        </a>
    </div>

    @if ($appointments->isEmpty())
        <x-ui.empty title="No appointments found" description="Try adjusting your filters." />
    @else
        {{-- The Reference and Down Payment columns are gone from this table.
             The data is untouched and still lives on the Manage screen, which
             is where an admin goes when they need the booking number or the
             GCash verification. --}}
        <div class="bta-card overflow-hidden">
            <div class="overflow-x-auto">
                <table class="bta-table">
                    <thead>
                        <tr>
                            <th>Customer</th>
                            <th>Service(s)</th>
                            <th>Date &amp; Time</th>
                            <th>Technician</th>
                            <th>Status</th>
                            <th class="text-right">Total</th>
                            <th class="text-right">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($appointments as $appointment)
                            @php $choices = $statusChoices[$appointment->id]; @endphp

                            <tr>
                                <td>
                                    <p class="whitespace-nowrap font-medium text-ink">{{ $appointment->customer_name }}</p>
                                    <p class="whitespace-nowrap text-xs text-ink-muted">{{ $appointment->customer_phone }}</p>
                                </td>
                                <td class="max-w-xs">
                                    <p class="truncate text-ink">{{ $appointment->service_names }}</p>
                                </td>
                                <td class="whitespace-nowrap">
                                    <p class="text-ink">{{ $appointment->preferred_date->format('M j, Y') }}</p>
                                    <p class="text-xs text-ink-muted">{{ $appointment->time_label }}</p>
                                </td>
                                <td class="whitespace-nowrap text-ink">{{ $appointment->technicianLabel() }}</td>
                                <td class="whitespace-nowrap">
                                    @can('admin.appointments.manage')
                                        {{-- The status menu replaces the Approve /
                                             Decline buttons. The form is real, so
                                             the row still submits without
                                             JavaScript; `appointmentStatus`
                                             intercepts the change event only to
                                             report a refused transition in
                                             place. --}}
                                        <div
                                            x-data="appointmentStatus({
                                                endpoint: @js(route('admin.appointments.status', $appointment)),
                                                current: @js($appointment->status->value),
                                            })"
                                        >
                                            <form method="POST" action="{{ route('admin.appointments.status', $appointment) }}">
                                                @csrf
                                                @method('PATCH')

                                                <select
                                                    name="status"
                                                    @class([
                                                        'status-select',
                                                        'status-select-'.$appointment->status->badge(),
                                                        'status-select-locked' => $appointment->status->isLocked(),
                                                    ])
                                                    aria-label="Status for {{ $appointment->customer_name }} on {{ $appointment->preferred_date->format('M j, Y') }}"
                                                    @disabled($appointment->status->isLocked())
                                                    x-on:change="update($event.target.value, $event.target)"
                                                >
                                                    @foreach ($choices as $choice)
                                                        {{-- The first option is where the booking is now: it is the
                                                             selected value, and it is not selectable, so the
                                                             control cannot post the state it is already in. --}}
                                                        <option
                                                            value="{{ $choice['value'] }}"
                                                            @selected(! $choice['selectable'])
                                                            @disabled(! $choice['selectable'])
                                                        >{{ $choice['label'] }}</option>
                                                    @endforeach
                                                </select>

                                                {{-- Enter submits the form for anyone without JavaScript; with
                                                     it the change event has already fired. --}}
                                                <button type="submit" class="sr-only">Save status</button>
                                            </form>

                                            {{-- Why the control is dead, since a greyed-out
                                                 dropdown on its own reads as a bug. Only
                                                 for a locked status, and only visible
                                                 when it would otherwise be unexplained. --}}
                                            @if ($appointment->status->isLocked())
                                                <p class="mt-1 max-w-[10rem] text-[11px] leading-snug text-ink-muted">
                                                    A completed service is final and cannot be changed.
                                                </p>
                                            @endif
                                        </div>
                                    @else
                                        <x-ui.badge :status="$appointment->status->badge()" :label="$appointment->status->label()" />
                                    @endcan
                                </td>
                                <td class="whitespace-nowrap text-right font-medium text-primary">₱{{ number_format((float) $appointment->total_amount, 2) }}</td>
                                <td>
                                    {{-- Three row actions, and the first two are only
                                         rendered when the booking's status allows
                                         them — `canBeArchivedByAdmin()` and
                                         `canBeDeletedByAdmin()`, which are the same
                                         settled-status rule the routes re-check.

                                         An in-progress booking therefore shows View
                                         and nothing else, which is the honest
                                         presentation: there is no way to remove a
                                         booking that is happening right now. --}}
                                    <div class="flex items-center justify-end gap-1.5">
                                        <x-ui.icon-action
                                            label="View {{ $appointment->customer_name }}'s booking on {{ $appointment->preferred_date->format('M j, Y') }}"
                                            icon="heroicon-o-eye"
                                            tone="secondary"
                                            href="#"
                                            x-on:click.prevent="$dispatch('view-appointment', {{ $appointment->id }})"
                                        />

                                        @if ($appointment->canBeArchivedByAdmin())
                                            {{-- A button that opens the confirmation dialog rather than a
                                                 form of its own: the one real form lives in the dialog
                                                 below, so this costs no CSRF token and no `_method`
                                                 spoof per row. The id rides along as the dialog's
                                                 action, which is how the shared component knows which
                                                 row it is confirming. --}}
                                            <x-ui.icon-action
                                                label="Archive this booking"
                                                icon="heroicon-o-archive-box"
                                                tone="secondary"
                                                href="#"
                                                x-on:click.prevent="$dispatch('confirm-archive', {
                                                    id: {{ $appointment->id }},
                                                })"
                                            />
                                        @endif

                                        @if ($appointment->canBeDeletedByAdmin())
                                            <x-ui.icon-action
                                                label="Delete this booking permanently"
                                                icon="heroicon-o-trash"
                                                tone="danger"
                                                href="#"
                                                x-on:click.prevent="$dispatch('confirm-delete-booking', {
                                                    id: {{ $appointment->id }},
                                                })"
                                            />
                                        @endif
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>

        <div class="mt-6">{{ $appointments->links() }}</div>
    @endif
@endsection

@push('modals')
    {{--
        Read-only view of a single booking.

        Deliberately no fields, no form and no way out to another screen:
        services with their line totals, who is serving it, date, time and
        whatever the customer typed at booking time. The status dropdown beside
        it is the only thing on this page that changes anything.

        The dialog renders outside the table — `@stack('modals')` is below
        `<main>` in the layout — so it is not clipped by the table's
        `overflow-x-auto`. The row payloads are handed to it once as a map
        keyed by appointment id, so opening it costs no request: the list
        already renders everything the dialog shows.
    --}}
    <div
        x-data="appointmentViewer(@js($viewRows))"
        x-on:view-appointment.window="show($event.detail)"
        x-on:keydown.escape.window="if (open) close()"
        x-show="open"
        x-cloak
        class="fixed inset-0 z-[60] flex items-end justify-center overflow-y-auto p-4 sm:items-center"
        role="dialog"
        aria-modal="true"
        aria-labelledby="view-appointment-title"
    >
        <div class="modal-backdrop fixed inset-0" x-on:click="close()" aria-hidden="true"></div>

            <div x-on:click.stop class="modal-panel max-w-xl">
            <div class="flex items-start justify-between gap-4 border-b border-line/70 px-5 py-4">
                <div class="min-w-0">
                    <h2 id="view-appointment-title" class="font-display text-lg font-semibold text-primary">Booking Details</h2>
                    <p class="mt-0.5 truncate text-xs text-ink-muted" x-text="detail?.customer"></p>
                </div>
                <button type="button" x-on:click="close()" class="btn-ghost btn-sm" aria-label="Close">&times;</button>
            </div>

            <div class="max-h-[60vh] space-y-6 overflow-y-auto px-5 py-5">
                <section>
                    <h3 class="text-xs font-semibold uppercase tracking-wider text-ink-muted">Services booked</h3>

                    <ul class="mt-2 space-y-2">
                        <template x-for="line in (detail?.services ?? [])" :key="line.name + line.meta">
                            <li class="flex items-start justify-between gap-3 rounded-xl bg-linen/60 px-4 py-3">
                                <div class="min-w-0">
                                    <p class="font-medium text-ink" x-text="line.name"></p>
                                    <p class="text-xs text-ink-muted" x-text="line.meta"></p>
                                </div>
                                <p class="shrink-0 font-semibold text-primary" x-text="line.total"></p>
                            </li>
                        </template>
                    </ul>

                    <p class="mt-3 text-sm text-ink-muted" x-show="(detail?.services ?? []).length === 0" x-cloak>No services recorded.</p>

                    <p class="mt-3 text-right text-sm text-ink-muted">
                        Total:
                        <span class="font-display text-lg font-bold text-primary" x-text="detail?.total"></span>
                    </p>
                </section>

                <section class="grid gap-4 sm:grid-cols-3">
                    <div>
                        <h3 class="text-xs font-semibold uppercase tracking-wider text-ink-muted">Date</h3>
                        <p class="mt-1 font-medium text-ink" x-text="detail?.date"></p>
                    </div>
                    <div>
                        <h3 class="text-xs font-semibold uppercase tracking-wider text-ink-muted">Time</h3>
                        <p class="mt-1 font-medium text-ink" x-text="detail?.time"></p>
                    </div>
                    <div>
                        <h3 class="text-xs font-semibold uppercase tracking-wider text-ink-muted">Technician</h3>
                        <p class="mt-1 font-medium text-ink" x-text="detail?.technician"></p>
                    </div>
                </section>

                <section>
                    <h3 class="text-xs font-semibold uppercase tracking-wider text-ink-muted">Customer request</h3>

                    <dl class="mt-2 space-y-3">
                        <template x-for="note in (detail?.notes ?? [])" :key="note.label">
                            <div class="rounded-xl bg-linen/60 px-4 py-3">
                                <dt class="text-xs font-semibold uppercase tracking-wider text-ink-muted" x-text="note.label"></dt>
                                <dd class="mt-1 whitespace-pre-line text-sm text-ink" x-text="note.value"></dd>
                            </div>
                        </template>
                    </dl>

                    <p class="mt-2 text-sm text-ink-muted" x-show="(detail?.notes ?? []).length === 0" x-cloak>
                        No allergies, preferences or requests were left on this booking.
                    </p>
                </section>
            </div>

            <div class="flex flex-wrap justify-end gap-2 border-t border-line/70 bg-linen/50 px-5 py-4">
                <button type="button" class="btn-ghost" x-on:click="close()">Close</button>
            </div>
        </div>
    </div>

    {{--
        Archive and delete confirmations.

        Both are the shared `x-ui.confirm-dialog`, which is what the notifications
        list and My Appointments already use — so this is the same Yes/No question
        the rest of the app asks, not a browser `confirm()` and not a third
        hand-rolled dialog. The dialog holds the one real form, so the rows need
        only to announce themselves with the label to show and the URL to post to.

        One dialog each, so the action is a template with `{id}` in it rather than a
        per-row URL. The component substitutes the id the row passed along when the
        event fires — see `confirmDialog.ask()`. That is what keeps this to a single
        form instead of one CSRF token and one `_method` spoof per row, and it is
        why two components are mounted rather than one configured twice.

        `method` and `confirmTone` differ between the two because the verbs do.
        The archive route is a POST and the component spoofs DELETE by default,
        which 405s against it; a "danger" button on a reversible action likewise
        teaches admins this dialog means destroy.
    --}}
    <x-ui.confirm-dialog
        title="Archive booking"
        event="confirm-archive"
        :action="route('admin.appointments.index').'/{id}/archive'"
        subject="booking"
        verb="Archive"
        method="POST"
        confirm-tone="primary"
        warning="It leaves the working list and moves to Archived. You can restore it from there at any time."
        title-id="confirm-archive-title"
        confirm-label="Archive"
    />

    <x-ui.confirm-dialog
        title="Delete booking"
        event="confirm-delete-booking"
        :action="route('admin.appointments.index').'/{id}'"
        subject="booking"
        warning="This removes the booking from the salon&rsquo;s records. It cannot be undone from this screen."
        title-id="confirm-delete-booking-title"
        confirm-label="Delete"
    />
@endpush
