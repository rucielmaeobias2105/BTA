@extends('layouts.admin')

@section('title', 'Dashboard')
@section('heading', 'Admin Dashboard')

@section('content')
    {{-- Quick stats. `x-ui.stat` is the shared stat card, which the Reports
         screen's three headline figures use too. --}}
    <div class="mb-6 grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
        <x-ui.stat
            label="Today's Appointments"
            :value="$summary['today_appointments']"
            icon="heroicon-o-calendar-days"
            text="text-primary"
            bg="bg-primary/10"
        />
        {{-- The Low Stock Alerts stat card used to sit here. It is gone, but
             the count it showed is not: the sidebar still badges Inventory with
             it, and `LowStockTaggingTest` covers that. What went is the duplicate
             on the dashboard, not the alert. --}}
        <x-ui.stat
            label="Completed (Month)"
            :value="$summary['completed_month']"
            icon="heroicon-o-check-badge"
            text="text-status-completed"
            bg="bg-status-completed-bg"
        />
        <x-ui.stat
            label="Revenue (Month)"
            :value="'₱'.number_format($summary['revenue_month'], 0)"
            icon="heroicon-o-banknotes"
            text="text-gold-dark"
            bg="bg-gold/20"
        />
    </div>

    {{-- Today's schedule --}}
    <x-ui.card title="Today's Appointments" subtitle="{{ now()->format('l, F j, Y') }}">
        <x-slot:actions>
            <a href="{{ route('admin.appointments.index', ['from' => today()->toDateString(), 'to' => today()->toDateString()]) }}" class="text-xs font-medium text-primary underline underline-offset-2">Manage</a>
        </x-slot:actions>

        @if ($todayAppointments->isEmpty())
            <p class="text-sm text-ink-muted">Nothing scheduled today.</p>
        @else
            <div class="overflow-x-auto">
                <table class="bta-table">
                    <thead>
                        <tr>
                            <th>Time</th>
                            <th>Customer</th>
                            <th>Service(s)</th>
                            <th>Status</th>
                            <th class="text-right">Total</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($todayAppointments as $appointment)
                            <tr>
                                <td class="whitespace-nowrap font-medium text-primary">{{ $appointment->time_label }}</td>
                                <td>
                                    <a href="{{ route('admin.appointments.show', $appointment) }}" class="font-medium text-primary hover:underline">{{ $appointment->customer_name }}</a>
                                </td>
                                <td class="max-w-xs truncate text-ink">{{ $appointment->service_names }}</td>
                                <td><x-ui.badge :status="$appointment->status->badge()" :label="$appointment->status->label()" /></td>
                                <td class="whitespace-nowrap text-right font-medium text-primary">₱{{ number_format((float) $appointment->total_amount, 2) }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </x-ui.card>

@endsection
