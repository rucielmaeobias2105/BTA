@php
    $maxChart = max(array_map(fn ($point) => $point['total'], $chart ?: [['total' => 0]])) ?: 1;
    $statusTotal = array_sum($statusBreakdown) ?: 1;
@endphp

@extends('layouts.admin')

@section('title', 'Dashboard')
@section('heading', 'Admin Dashboard')

@section('content')
    <x-ui.page-header
        :eyebrow="'Overview'"
        :title="'Welcome back, '.auth('admin')->user()->first_name"
        description="Live snapshot of appointments, revenue and stock across the salon."
    >
        <x-slot:actions>
            <a href="{{ route('admin.appointments.index', ['status' => 'pending']) }}" class="btn-secondary btn-sm">
                Pending Requests
                @if ($summary['pending_appointments'] > 0)
                    <span class="rounded-pill bg-primary px-1.5 py-0.5 text-[10px] text-cream">{{ $summary['pending_appointments'] }}</span>
                @endif
            </a>
        </x-slot:actions>
    </x-ui.page-header>

    {{-- Quick stats --}}
    <div class="mb-6 grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
        @foreach ([
            ["Today's Appointments", $summary['today_appointments'], 'heroicon-o-calendar-days', 'text-primary', 'bg-primary/10'],
            ['Low Stock Alerts', $summary['low_stock_count'], 'heroicon-o-exclamation-triangle', 'text-status-low-stock', 'bg-status-low-stock-bg'],
            ['Completed (Month)', $summary['completed_month'], 'heroicon-o-check-badge', 'text-status-completed', 'bg-status-completed-bg'],
            ['Revenue (Month)', '₱'.number_format($summary['revenue_month'], 0), 'heroicon-o-banknotes', 'text-gold-dark', 'bg-gold/20'],
        ] as [$label, $value, $icon, $text, $bg])
            <div class="bta-card p-5">
                <div class="flex items-start justify-between gap-3">
                    <div class="min-w-0">
                        <p class="text-xs font-semibold uppercase tracking-wider text-ink-muted">{{ $label }}</p>
                        <p class="mt-2 truncate font-display text-3xl font-bold text-primary">{{ $value }}</p>
                    </div>
                    <span class="flex h-11 w-11 shrink-0 items-center justify-center rounded-full {{ $bg }} {{ $text }}">
                        <x-dynamic-component :component="$icon" class="h-5 w-5" />
                    </span>
                </div>
            </div>
        @endforeach
    </div>

    <div class="grid gap-6 xl:grid-cols-3">
        {{-- Revenue chart --}}
        <div class="xl:col-span-2">
            <x-ui.card title="Revenue Chart" subtitle="Last 14 days (confirmed, in-progress and completed bookings).">
                <x-slot:actions>
                    @can('admin.reports.view')
                        <a href="{{ route('admin.reports.index') }}" class="text-xs font-medium text-primary underline underline-offset-2">Full report</a>
                    @endcan
                </x-slot:actions>

                <div class="flex h-56 items-end gap-1.5 pt-4">
                    @foreach ($chart as $point)
                        @php $height = $maxChart > 0 ? max(2, round(($point['total'] / $maxChart) * 100)) : 2; @endphp
                        <div class="group relative flex flex-1 flex-col items-center justify-end" title="{{ $point['label'] }}: ₱{{ number_format($point['total'], 2) }} ({{ $point['count'] }} bookings)">
                            <div class="w-full rounded-t bg-gradient-to-t from-primary to-primary-light transition group-hover:from-gold group-hover:to-gold-light"
                                 style="height: {{ $height }}%"></div>
                        </div>
                    @endforeach
                </div>

                <div class="mt-2 flex justify-between text-[10px] text-ink-muted">
                    <span>{{ $chart[0]['label'] ?? '' }}</span>
                    <span>{{ $chart[count($chart) - 1]['label'] ?? '' }}</span>
                </div>
            </x-ui.card>
        </div>

        {{-- Status breakdown --}}
        <x-ui.card title="Appointments by Status">
            <ul class="space-y-3">
                @foreach (\App\Enums\AppointmentStatus::cases() as $case)
                    @php $count = $statusBreakdown[$case->value] ?? 0; $pct = round($count / $statusTotal * 100); @endphp
                    <li>
                        <div class="mb-1.5 flex items-center justify-between text-sm">
                            <x-ui.badge :status="$case->badge()" :label="$case->label()" />
                            <span class="text-ink-muted">{{ $count }} ({{ $pct }}%)</span>
                        </div>
                        <div class="h-2 overflow-hidden rounded-pill bg-linen">
                            <div @class([
                                'h-full rounded-pill',
                                'bg-status-pending' => $case->value === 'pending',
                                'bg-status-confirmed' => $case->value === 'confirmed',
                                'bg-status-progress' => $case->value === 'in_progress',
                                'bg-status-completed' => $case->value === 'completed',
                                'bg-status-cancelled' => $case->value === 'cancelled',
                            ]) style="width: {{ $pct }}%"></div>
                        </div>
                    </li>
                @endforeach
            </ul>
        </x-ui.card>
    </div>

    <div class="mt-6 grid gap-6 xl:grid-cols-3">
        {{-- Today's schedule --}}
        <div class="xl:col-span-2">
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
        </div>

        {{-- Low stock --}}
        <x-ui.card title="Low Stock Alerts" subtitle="Items at or below their reorder threshold.">
            <x-slot:actions>
                <a href="{{ route('admin.tags.index') }}" class="text-xs font-medium text-primary underline underline-offset-2">Tag items</a>
            </x-slot:actions>

            @if ($lowStockItems->isEmpty())
                <p class="text-sm text-ink-muted">All items are well stocked.</p>
            @else
                <ul class="space-y-2.5">
                    @foreach ($lowStockItems as $item)
                        <li class="flex items-center justify-between gap-3 rounded-xl bg-linen/60 px-3.5 py-2.5">
                            <div class="min-w-0">
                                <p class="truncate text-sm font-medium text-ink">{{ $item->name }}</p>
                                <p class="text-xs text-ink-muted">Reorder at {{ $item->reorder_threshold }} {{ $item->unit }}</p>
                            </div>
                            <x-ui.badge :status="$item->status_tag->badge()" :label="$item->status_tag->label()" />
                        </li>
                    @endforeach
                </ul>
            @endif
        </x-ui.card>
    </div>

    <div class="mt-6 grid gap-6 xl:grid-cols-2">
        {{-- Upcoming --}}
        <x-ui.card title="Upcoming Appointments">
            @if ($upcoming->isEmpty())
                <p class="text-sm text-ink-muted">No upcoming appointments.</p>
            @else
                <ul class="divide-y divide-primary/8">
                    @foreach ($upcoming as $appointment)
                        <li class="flex items-center justify-between gap-3 py-3 first:pt-0 last:pb-0">
                            <div class="min-w-0">
                                <a href="{{ route('admin.appointments.show', $appointment) }}" class="truncate text-sm font-medium text-primary hover:underline">{{ $appointment->customer_name }}</a>
                                <p class="truncate text-xs text-ink-muted">{{ $appointment->service_names }}</p>
                            </div>
                            <div class="shrink-0 text-right">
                                <p class="whitespace-nowrap text-xs font-medium text-primary">{{ $appointment->preferred_date->format('M j') }} · {{ $appointment->time_label }}</p>
                                <x-ui.badge :status="$appointment->status->badge()" :label="$appointment->status->label()" />
                            </div>
                        </li>
                    @endforeach
                </ul>
            @endif
        </x-ui.card>

        {{-- Recent reviews --}}
        <x-ui.card title="Recent Reviews" :subtitle="'Average rating: '.$averageRating.' / 5'">
            <x-slot:actions>
                <a href="{{ route('admin.reviews.index') }}" class="text-xs font-medium text-primary underline underline-offset-2">Moderate</a>
            </x-slot:actions>

            @if ($recentReviews->isEmpty())
                <p class="text-sm text-ink-muted">No reviews yet.</p>
            @else
                <ul class="space-y-3.5">
                    @foreach ($recentReviews as $review)
                        <li class="border-b border-primary/8 pb-3.5 last:border-0 last:pb-0">
                            <div class="flex items-center justify-between gap-3">
                                <p class="text-sm font-medium text-primary">{{ $review->customer_name }}</p>
                                <x-ui.star-rating :value="$review->rating" :interactive="false" size="sm" />
                            </div>
                            <p class="mt-1 line-clamp-2 text-sm text-ink-muted">{{ $review->message }}</p>
                        </li>
                    @endforeach
                </ul>
            @endif
        </x-ui.card>
    </div>
@endsection
