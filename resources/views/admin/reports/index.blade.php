@extends('layouts.admin')

@section('title', 'Reports')
@section('heading', 'Reports')

@section('content')
    {{-- Headline figures first, in the same stat card the Dashboard uses, then
         one card holding the filters and the rows they produce. --}}
    <div class="mb-6 grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
        <x-ui.stat
            label="Total Revenue"
            :value="'₱'.number_format($grandTotal, 2)"
            icon="heroicon-o-banknotes"
            text="text-gold-dark"
            bg="bg-gold/20"
            :subtext="$from->format('M j, Y').' – '.$to->format('M j, Y')"
        />
        <x-ui.stat
            label="Bookings"
            :value="$totalBookings"
            icon="heroicon-o-calendar-days"
            text="text-primary"
            bg="bg-primary/10"
            subtext="Confirmed, in-progress and completed"
        />
        <x-ui.stat
            label="Services"
            :value="$totalServices"
            icon="heroicon-o-scissors"
            text="text-status-completed"
            bg="bg-status-completed-bg"
            subtext="Service lines booked in this period"
        />
    </div>

    {{-- Filters and results in one card, in the same list-page pattern as the
         Services screen: the shared admin-table card with live search, entries
         per page, table and pager. The filters go in through its `filters`
         slot rather than into a card of their own.

         Two exports sit at the top of that card: the sales figures below, and the
         inventory stock list, which is a separate query and a separate report but
         belongs on this screen so an admin exporting anything finds both
         exports in the same place. --}}
    <x-ui.admin-table :search="$search" :per-page="12" empty-message="No revenue recorded in this range.">
        <x-slot:header>
            {{-- Both exports carry the resolved dates, so each file is exactly the range
                 on screen. The grouping is not a parameter: the server derives it
                 from the range, so a link cannot ask for a bucket size the dates
                 contradict. --}}
            <div class="mb-5 flex flex-wrap items-center justify-between gap-3">
                <p class="text-sm text-ink-muted">
                    {{ $from->format('M j, Y') }} – {{ $to->format('M j, Y') }} &middot; grouped by {{ strtolower($typeLabel) }}
                </p>
                <div class="flex flex-wrap items-center gap-2">
                    <a href="{{ route('admin.reports.export', $filters) }}" class="btn-secondary btn-sm">
                        <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M3 16.5v2.25A2.25 2.25 0 0 0 5.25 21h13.5A2.25 2.25 0 0 0 21 18.75V16.5M16.5 12 12 16.5m0 0L7.5 12m4.5 4.5V3"/></svg>
                        Export Sales CSV
                    </a>
                    <a href="{{ route('admin.inventory.export', $inventoryFilters) }}" class="btn-secondary btn-sm">
                        <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M3 16.5v2.25A2.25 2.25 0 0 0 5.25 21h13.5A2.25 2.25 0 0 0 21 18.75V16.5M16.5 12 12 16.5m0 0L7.5 12m4.5 4.5V3"/></svg>
                        Export Inventory CSV
                    </a>
                </div>
            </div>
        </x-slot:header>

        <x-slot:filters>
            {{-- Start and End, and nothing else.

                 There used to be a third control here, a "Period" dropdown
                 (Daily / Weekly / Monthly). It was removed because it duplicated
                 what these two dates already say and could contradict them —
                 "Monthly" over a three-day range produced a single bucket that
                 read like a whole month. The grouping is now derived from the
                 range on the server (`ReportService::granularityFor()`), so the
                 summary line above says which bucket size is in use and the rows
                 always match it.

                 None of this acts on the search box below; that one is
                 `adminTable`'s and runs in the browser. --}}
            <form method="GET" action="{{ route('admin.reports.index') }}" class="mb-6">
                <div class="grid gap-4 md:grid-cols-12">
                    <div class="md:col-span-4">
                        <x-ui.form.input name="from" type="date" label="Start Date" :value="$filters['from']" />
                    </div>
                    <div class="md:col-span-4">
                        <x-ui.form.input name="to" type="date" label="End Date" :value="$filters['to']" />
                    </div>
                    <div class="flex items-end gap-2 md:col-span-4">
                        <button type="submit" class="btn-primary flex-1">Generate Report</button>
                        <a href="{{ route('admin.reports.index') }}" class="btn-ghost">Reset</a>
                    </div>
                </div>
            </form>
        </x-slot:filters>

        <table class="bta-table">
<thead>
                    <tr>
                        {{-- Reads as the grouping rather than a fixed "Period",
                             because it is one: daily buckets for a short range,
                             months for a long one, and which is in use is stated in
                             the summary line above the table. --}}
                        <th>{{ ucfirst(strtolower($typeLabel)) }}</th>
                    <th class="text-center">Bookings</th>
                    <th class="text-center">Services</th>
                    <th class="text-right">Revenue</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($rows as $row)
                    <tr data-row x-show="isShown({{ $loop->index }})">
                        <td class="font-medium text-primary">{{ $row['label'] }}</td>
                        <td class="text-center text-ink">{{ $row['bookings'] }}</td>
                        <td class="text-center text-ink">{{ $row['services'] }}</td>
                        <td class="whitespace-nowrap text-right font-medium text-primary">
                            ₱{{ number_format($row['revenue'], 2) }}
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="4" class="py-12 text-center text-sm text-ink-muted">
                            No revenue recorded in this range.
                        </td>
                    </tr>
                @endforelse

                {{-- Shown when the search box has filtered every row away. --}}
                @if ($rows->isNotEmpty())
                    <tr x-show="total === 0" x-cloak>
                        <td colspan="4" class="py-12 text-center text-sm text-ink-muted">No data available</td>
                    </tr>
                @endif
            </tbody>
            <tfoot>
                <tr class="bg-linen/60">
                    <td class="font-semibold text-primary">Total</td>
                    <td class="text-center font-semibold text-primary">{{ $totalBookings }}</td>
                    <td class="text-center font-semibold text-primary">{{ $totalServices }}</td>
                    <td class="whitespace-nowrap text-right font-display text-lg font-bold text-primary">₱{{ number_format($grandTotal, 2) }}</td>
                </tr>
            </tfoot>
        </table>
    </x-ui.admin-table>
@endsection
