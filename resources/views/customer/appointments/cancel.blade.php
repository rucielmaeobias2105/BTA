@extends('layouts.customer')

@section('title', 'Cancel Appointment')

@section('content')
    <div class="mx-auto max-w-2xl px-4 py-10 sm:px-6 lg:px-8">
        <a href="{{ route('appointments.index', ['view' => $appointment->id]) }}" class="mb-6 inline-flex items-center gap-1.5 text-sm text-ink-muted transition hover:text-primary">
            <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M10.5 19.5 3 12m0 0 7.5-7.5M3 12h18"/></svg>
            Back to appointment
        </a>

        <x-ui.page-header
            eyebrow="Cancellation"
            title="Cancel Appointment"
            description="Tell us why you'd like to cancel. This cannot be undone."
        />


        {{-- Reference auto-filled from context (read-only) --}}
        <div class="bta-card mb-6 p-5">
            <div class="grid gap-4 sm:grid-cols-2">
                <div>
                    <p class="text-xs font-semibold uppercase tracking-wider text-ink-muted">Appointment Reference</p>
                    <p class="mt-1 font-mono text-sm font-semibold text-primary">{{ $appointment->reference_number }}</p>
                </div>
                <div>
                    <p class="text-xs font-semibold uppercase tracking-wider text-ink-muted">Scheduled</p>
                    <p class="mt-1 text-sm font-medium text-primary">{{ $appointment->date_time_label }}</p>
                </div>
                <div class="sm:col-span-2">
                    <p class="text-xs font-semibold uppercase tracking-wider text-ink-muted">Service(s)</p>
                    <p class="mt-1 text-sm text-ink">{{ $appointment->service_names }}</p>
                </div>
            </div>
        </div>

        <form method="POST" action="{{ route('appointments.cancel.update', $appointment) }}" class="space-y-6" novalidate>
            @csrf
            @method('PATCH')

            <x-ui.card title="Reason for Cancellation">
                {{-- Optional: dropdown of quick reasons + free text --}}
                <x-ui.form.select
                    name="reason_preset"
                    label="Reason (optional)"
                    :includeBlank="true"
                    blankLabel="Choose a reason…"
                    :options="collect($reasons)->mapWithKeys(fn ($r) => [$r => $r])->all()"
                />

                <div class="mt-4">
                    <x-ui.form.textarea
                        name="reason"
                        label="Or tell us more"
                        rows="3"
                        :value="old('reason')"
                        placeholder="Add any detail that would help us improve…"
                    />
                </div>

                <p class="mt-4 rounded-xl bg-linen/70 px-4 py-3 text-xs text-ink-muted">
                    Cancelling free of charge up to 24 hours before your appointment. Later cancellations
                    may forfeit the down payment because your slot and products are reserved for you.
                </p>
            </x-ui.card>

            <x-ui.card title="Cancellation Policy" accent="maroon">
                {{--
                    The policy is a dialog now, not a block of text on the page.

                    It used to be inlined here in a `max-h-64` scroll box, which
                    meant the customer was looking at a wall of policy *and* the
                    reason field *and* the confirm button — with the thing they
                    were being asked to agree to competing with the form for
                    attention. `x-terms.modal` is mounted by the layout, so all
                    this card needs is a way in, and the "not published yet"
                    branch now belongs inside the dialog too: an unpublished
                    policy is a fact about the salon, and saying it here next to
                    an empty card was worse than saying it where the customer was
                    looking.
                --}}
                <p class="text-sm text-ink-muted">
                    @if ($policy)
                        Read the full policy before you go ahead — the 24-hour
                        window and what happens after it are worth knowing first.
                    @else
                        Our cancellation policy has not been published yet.
                    @endif

                    <x-terms.link :category="'cancellation'">
                        {{ $policy ? 'Read the Cancellation Policy' : 'Read the policy' }}
                    </x-terms.link>
                </p>
            </x-ui.card>

            <x-ui.card title="Confirmation">
                <x-ui.form.checkbox
                    name="agree_cancellation_policy"
                    value="1"
                    required
                    hint="I understand the consequences of cancelling this appointment."
                >
                    I agree to the
                    <x-terms.link :category="'cancellation'">Cancellation Policy</x-terms.link>.
                </x-ui.form.checkbox>
            </x-ui.card>

            <div class="flex flex-col gap-3 sm:flex-row-reverse">
                <button type="submit" class="btn-danger sm:min-w-44">Cancel Appointment</button>
                <a href="{{ route('appointments.index', ['view' => $appointment->id]) }}" class="btn-ghost sm:min-w-44">Keep Appointment</a>
            </div>
        </form>
    </div>
@endsection
