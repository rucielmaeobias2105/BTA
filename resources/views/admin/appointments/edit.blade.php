@extends('layouts.admin')

@section('title', 'Reschedule '.$appointment->reference_number)
@section('heading', 'Reschedule Appointment')

@section('content')
    <a href="{{ route('admin.appointments.show', $appointment) }}" class="mb-5 inline-flex items-center gap-1.5 text-sm text-ink-muted transition hover:text-primary">
        <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M10.5 19.5 3 12m0 0 7.5-7.5M3 12h18"/></svg>
        Back to appointment
    </a>

    <x-ui.page-header
        eyebrow="{{ $appointment->reference_number }}"
        title="Reschedule / Update"
        :description="'Currently '.$appointment->date_time_label.' — '.$appointment->service_names"
    />

    <x-ui.errors />

    <form method="POST" action="{{ route('admin.appointments.update', $appointment) }}" class="max-w-3xl space-y-6" novalidate>
        @csrf
        @method('PUT')

        <x-ui.card title="New Schedule" subtitle="The new slot is re-validated against operating hours and blocked dates.">
            <div class="grid gap-5 sm:grid-cols-2">
                <x-ui.form.input
                    name="preferred_date"
                    type="date"
                    label="Preferred Date"
                    required
                    :value="old('preferred_date', $appointment->preferred_date->toDateString())"
                />
                <x-ui.form.input
                    name="preferred_time"
                    type="time"
                    label="Preferred Time"
                    required
                    :value="old('preferred_time', \Illuminate\Support\Carbon::parse($appointment->preferred_time)->format('H:i'))"
                />
            </div>

            <div class="mt-5">
                <x-ui.form.select name="status" label="Status" required :value="old('status', $appointment->status->value)" :options="$statusOptions" />
            </div>

            <div class="mt-5">
                <label for="admin_notes" class="label">
                    Admin Notes
                    <span class="ml-1.5 rounded-pill bg-primary/10 px-2 py-0.5 text-[10px] font-semibold uppercase tracking-wider text-primary">Internal only</span>
                </label>
                <textarea id="admin_notes" name="admin_notes" rows="3" class="input" placeholder="Only visible to staff.">{{ old('admin_notes', $appointment->admin_notes) }}</textarea>
            </div>
        </x-ui.card>

        <div class="flex flex-col gap-3 sm:flex-row-reverse">
            <button type="submit" class="btn-primary sm:min-w-44">Save Changes</button>
            <a href="{{ route('admin.appointments.show', $appointment) }}" class="btn-ghost sm:min-w-44">Cancel</a>
        </div>
    </form>
@endsection
