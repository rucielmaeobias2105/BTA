@extends('layouts.admin')

@section('title', 'Appointments')
@section('heading', 'Appointments')

@section('content')
    <x-ui.page-header
        eyebrow="Booking Management"
        title="Appointments"
        description="Review, approve, decline and update every booking."
    />

    {{-- Status tabs --}}
    <div class="mb-5 flex flex-wrap gap-2">
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

    {{-- Filters --}}
    <form method="GET" action="{{ route('admin.appointments.index') }}" class="bta-card mb-6 p-5">
        <div class="grid gap-4 md:grid-cols-12">
            <div class="md:col-span-4">
                <x-ui.form.input name="search" label="Search" placeholder="Reference, name, phone or email" :value="$filters['search'] ?? null" />
            </div>
            <div class="md:col-span-3">
                <x-ui.form.input name="from" type="date" label="From" :value="$filters['from'] ?? null" />
            </div>
            <div class="md:col-span-3">
                <x-ui.form.input name="to" type="date" label="To" :value="$filters['to'] ?? null" />
            </div>
            <div class="flex items-end gap-2 md:col-span-2">
                <input type="hidden" name="status" value="{{ $filters['status'] ?? '' }}">
                <button type="submit" class="btn-primary flex-1">Filter</button>
                @if (array_filter($filters))
                    <a href="{{ route('admin.appointments.index') }}" class="btn-ghost">Clear</a>
                @endif
            </div>
        </div>
    </form>

    @if ($appointments->isEmpty())
        <x-ui.empty title="No appointments found" description="Try adjusting your filters." />
    @else
        <div class="bta-card overflow-hidden">
            <div class="overflow-x-auto">
                <table class="bta-table">
                    <thead>
                        <tr>
                            <th>Reference</th>
                            <th>Customer</th>
                            <th>Service(s)</th>
                            <th>Date &amp; Time</th>
                            <th>Stylist</th>
                            <th>Down Payment</th>
                            <th>Status</th>
                            <th class="text-right">Total</th>
                            <th class="text-right">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($appointments as $appointment)
                            <tr>
                                <td class="whitespace-nowrap">
                                    <a href="{{ route('admin.appointments.show', $appointment) }}" class="font-mono text-xs font-semibold text-primary hover:underline">
                                        {{ $appointment->reference_number }}
                                    </a>
                                </td>
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
                                <td class="whitespace-nowrap text-ink">{{ $appointment->preferredStylist?->first_name ?? '—' }}</td>
                                <td><x-ui.badge :status="$appointment->down_payment_status->badge()" :label="$appointment->down_payment_status->label()" /></td>
                                <td><x-ui.badge :status="$appointment->status->badge()" :label="$appointment->status->label()" /></td>
                                <td class="whitespace-nowrap text-right font-medium text-primary">₱{{ number_format((float) $appointment->total_amount, 2) }}</td>
                                <td class="text-right">
                                    <div class="flex justify-end gap-1.5">
                                        @if ($appointment->status->value === 'pending')
                                            <form method="POST" action="{{ route('admin.appointments.status', $appointment) }}">
                                                @csrf @method('PATCH')
                                                <input type="hidden" name="status" value="confirmed">
                                                <button type="submit" class="btn-primary btn-sm" title="Approve">Approve</button>
                                            </form>
                                            <form method="POST" action="{{ route('admin.appointments.status', $appointment) }}">
                                                @csrf @method('PATCH')
                                                <input type="hidden" name="status" value="cancelled">
                                                <button type="submit" class="btn-danger btn-sm" title="Decline">Decline</button>
                                            </form>
                                        @endif

                                        <a href="{{ route('admin.appointments.show', $appointment) }}" class="btn-ghost btn-sm">Manage</a>
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
