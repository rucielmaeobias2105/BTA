@extends('layouts.customer')

@section('title', 'Cancel Appointment')

@section('content')
    <div class="mx-auto max-w-2xl px-4 py-10 sm:px-6 lg:px-8">
        <a href="{{ route('appointments.show', $appointment) }}" class="mb-6 inline-flex items-center gap-1.5 text-sm text-ink-muted transition hover:text-primary">
            <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M10.5 19.5 3 12m0 0 7.5-7.5M3 12h18"/></svg>
            Back to appointment
        </a>

        <x-ui.page-header
            eyebrow="Cancellation"
            title="Cancel Appointment"
            description="Tell us why you'd like to cancel. This cannot be undone."
        />

        <x-ui.errors />

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
                @if ($policy)
                    <div class="max-h-64 space-y-3 overflow-y-auto pr-2 text-sm leading-relaxed text-ink [&_h2]:font-display [&_h2]:text-lg [&_h2]:font-semibold [&_h2]:text-primary [&_h3]:mt-4 [&_h3]:font-semibold [&_h3]:text-primary [&_li]:ml-4 [&_li]:list-disc [&_ol]:list-decimal [&_p]:mb-2">
                        {!! $policy->content !!}
                    </div>
                    <p class="mt-4 text-xs text-ink-muted">Version {{ $policy->version }} &middot; published {{ $policy->published_at?->format('M j, Y') }}</p>
                @else
                    <p class="text-sm text-ink-muted">
                        Our cancellation policy has not been published yet.
                        <a href="{{ route('terms.show', 'cancellation') }}" target="_blank" rel="noopener" class="font-medium text-primary underline underline-offset-2">Read the policy</a>.
                    </p>
                @endif
            </x-ui.card>

            <x-ui.card title="Confirmation">
                <x-ui.form.checkbox
                    name="agree_cancellation_policy"
                    value="1"
                    required
                    hint="I understand the consequences of cancelling this appointment."
                >
                    I agree to the
                    <a href="{{ route('terms.show', 'cancellation') }}" target="_blank" rel="noopener"
                       class="font-medium text-primary underline underline-offset-2 hover:text-primary-dark">Cancellation Policy</a>.
                </x-ui.form.checkbox>
            </x-ui.card>

            <div class="flex flex-col gap-3 sm:flex-row-reverse">
                <button type="submit" class="btn-danger sm:min-w-44">Cancel Appointment</button>
                <a href="{{ route('appointments.show', $appointment) }}" class="btn-ghost sm:min-w-44">Keep Appointment</a>
            </div>
        </form>
    </div>
@endsection
