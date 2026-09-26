@extends('layouts.admin')

@section('title', 'Reports')
@section('heading', 'Sales & Usage Reports')

@section('content')
    <x-ui.page-header
        eyebrow="Analytics"
        title="Sales & Usage Reports"
        description="Revenue and item consumption for a chosen date range, bucketed by your chosen period."
    >
        <x-slot:actions>
            <a href="{{ route('admin.reports.export', $filters) }}" class="btn-secondary btn-sm">
                <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M3 16.5v2.25A2.25 2.25 0 0 0 5.25 21h13.5A2.25 2.25 0 0 0 21 18.75V16.5M16.5 12 12 16.5m0 0L7.5 12m4.5 4.5V3"/></svg>
                Export CSV
            </a>
        </x-slot:actions>
    </x-ui.page-header>

    {{-- Filters --}}
    <form method="GET" action="{{ route('admin.reports.index') }}" class="bta-card mb-6 p-5">
        <div class="grid gap-4 md:grid-cols-12">
            <div class="md:col-span-2">
                <x-ui.form.select name="type" label="Report Type" :value="$type" :options="$typeOptions" />
            </div>
            <div class="md:col-span-3">
                <x-ui.form.input name="from" type="date" label="From" :value="$filters['from']" />
            </div>
            <div class="md:col-span-3">
                <x-ui.form.input name="to" type="date" label="To" :value="$filters['to']" />
            </div>
            <div class="md:col-span-2">
                <x-ui.form.select
                    name="service_id"
                    label="Filter by Service"
                    :includeBlank="true"
                    blankLabel="All Services"
                    :value="$filters['service_id']"
                    :options="$services->mapWithKeys(fn ($s) => [$s->id => $s->name])->all()"
                />
            </div>
            <div class="flex items-end gap-2 md:col-span-2">
                <button type="submit" class="btn-primary flex-1">Run Report</button>
                <a href="{{ route('admin.reports.index') }}" class="btn-ghost">Reset</a>
            </div>
        </div>

        <div class="mt-4 border-t border-primary/10 pt-4">
            <div class="max-w-sm">
                <x-ui.form.select
                    name="item_id"
                    label="Filter by Item (usage table only)"
                    :includeBlank="true"
                    blankLabel="All Items"
                    :value="$filters['item_id']"
                    :options="$items->mapWithKeys(fn ($i) => [$i->id => $i->name.' ('.$i->unit.')'])->all()"
                />
            </div>
        </div>
    </form>

    {{-- Headline figures --}}
    <div class="mb-6 grid gap-4 sm:grid-cols-3">
        <div class="bta-card p-5">
            <p class="text-xs font-semibold uppercase tracking-wider text-ink-muted">Total Revenue</p>
            <p class="mt-1.5 font-display text-3xl font-bold text-primary">₱{{ number_format($grandTotal, 2) }}</p>
            <p class="mt-1 text-xs text-ink-muted">{{ $from->format('M j, Y') }} – {{ $to->format('M j, Y') }}</p>
        </div>
        <div class="bta-card p-5">
            <p class="text-xs font-semibold uppercase tracking-wider text-ink-muted">Bookings</p>
            <p class="mt-1.5 font-display text-3xl font-bold text-primary">{{ $totalBookings }}</p>
            <p class="mt-1 text-xs text-ink-muted">Confirmed, in-progress and completed</p>
        </div>
        <div class="bta-card p-5">
            <p class="text-xs font-semibold uppercase tracking-wider text-ink-muted">Average Booking</p>
            <p class="mt-1.5 font-display text-3xl font-bold text-primary">
                ₱{{ number_format($totalBookings > 0 ? $grandTotal / $totalBookings : 0, 2) }}
            </p>
        </div>
    </div>

    {{-- Period chart --}}
    <x-ui.card title="Revenue by Period" :subtitle="$typeOptions[$type].' buckets'">
        @if ($totals->isEmpty())
            <p class="text-sm text-ink-muted">No revenue recorded in this range.</p>
        @else
            <div class="flex h-56 items-end gap-1.5 pt-4">
                @foreach ($totals as $row)
                    @php $height = $maxPeriodRevenue > 0 ? max(2, round($row['revenue'] / $maxPeriodRevenue * 100)) : 2; @endphp
                    <div class="group relative flex flex-1 flex-col items-center justify-end"
                         title="{{ $row['label'] }}: ₱{{ number_format($row['revenue'], 2) }} ({{ $row['bookings'] }} bookings)">
                        <div class="w-full rounded-t bg-gradient-to-t from-primary to-primary-light transition group-hover:from-gold group-hover:to-gold-light"
                             style="height: {{ $height }}%"></div>
                    </div>
                @endforeach
            </div>
        @endif
    </x-ui.card>

    <div class="mt-6 grid gap-6 2xl:grid-cols-2">
        {{-- Revenue by service --}}
        <x-ui.card title="Revenue by Service" subtitle="Ranked by total revenue.">
            @if ($byService->isEmpty())
                <p class="text-sm text-ink-muted">No service revenue in this range.</p>
            @else
                <div class="overflow-x-auto">
                    <table class="bta-table">
                        <thead>
                            <tr>
                                <th>Service</th>
                                <th class="text-center">Bookings</th>
                                <th class="text-center">Qty</th>
                                <th class="text-right">Revenue</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($byService as $row)
                                <tr>
                                    <td class="text-ink">{{ $row['service'] }}</td>
                                    <td class="text-center text-ink">{{ $row['bookings'] }}</td>
                                    <td class="text-center text-ink">{{ $row['quantity'] }}</td>
                                    <td class="whitespace-nowrap text-right font-medium text-primary">₱{{ number_format($row['revenue'], 2) }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </x-ui.card>

        {{-- Item usage --}}
        <x-ui.card title="Item Usage" subtitle="Consumption implied by bookings, with stock remaining.">
            @if ($itemUsage->isEmpty())
                <p class="text-sm text-ink-muted">No linked item usage in this range.</p>
            @else
                <div class="overflow-x-auto">
                    <table class="bta-table">
                        <thead>
                            <tr>
                                <th>Item</th>
                                <th class="text-right">Used</th>
                                <th class="text-right">Remaining</th>
                                <th class="text-center">Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($itemUsage as $row)
                                @php $low = $row['remaining'] <= 0; @endphp
                                <tr @class(['bg-status-low-stock-bg/25' => $low])>
                                    <td class="text-ink">{{ $row['item'] }}</td>
                                    <td class="whitespace-nowrap text-right font-medium text-primary">
                                        {{ rtrim(rtrim(number_format($row['used'], 2), '0'), '.') }} {{ $row['unit'] }}
                                    </td>
                                    <td class="whitespace-nowrap text-right text-ink">
                                        {{ rtrim(rtrim(number_format($row['remaining'], 2), '0'), '.') }} {{ $row['unit'] }}
                                    </td>
                                    <td class="text-center">
                                        <x-ui.badge :status="$low ? 'sold_out' : 'confirmed'" :label="$low ? 'Depleted' : 'In stock'" />
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </x-ui.card>
    </div>

    {{-- Period totals table --}}
    <x-ui.card title="Summary Table" class="mt-6">
        @if ($totals->isEmpty())
            <p class="text-sm text-ink-muted">Nothing to summarise for this range.</p>
        @else
            <div class="overflow-x-auto">
                <table class="bta-table">
                    <thead>
                        <tr>
                            <th>Period</th>
                            <th class="text-center">Bookings</th>
                            <th class="text-right">Revenue</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($totals as $row)
                            <tr>
                                <td class="text-ink">{{ $row['label'] }}</td>
                                <td class="text-center text-ink">{{ $row['bookings'] }}</td>
                                <td class="whitespace-nowrap text-right font-medium text-primary">₱{{ number_format($row['revenue'], 2) }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                    <tfoot>
                        <tr class="bg-linen/60">
                            <td class="font-semibold text-primary">Total</td>
                            <td class="text-center font-semibold text-primary">{{ $totalBookings }}</td>
                            <td class="whitespace-nowrap text-right font-display text-lg font-bold text-primary">₱{{ number_format($grandTotal, 2) }}</td>
                        </tr>
                    </tfoot>
                </table>
            </div>
        @endif
    </x-ui.card>
@endsection
