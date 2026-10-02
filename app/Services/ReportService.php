<?php

namespace App\Services;

use App\Enums\AppointmentStatus;
use App\Models\Appointment;
use App\Models\ContactMessage;
use App\Models\InventoryItem;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Read-only aggregation used by the admin dashboard and the Sales/Usage
 * Reports screen. Revenue counts completed + confirmed appointments.
 */
class ReportService
{
    /** Statuses that represent realised revenue. */
    public const REVENUE_STATUSES = [
        AppointmentStatus::Confirmed->value,
        AppointmentStatus::InProgress->value,
        AppointmentStatus::Completed->value,
    ];

    /**
     * Headline numbers for the dashboard stat cards.
     *
     * @return array<string, mixed>
     */
    public function summary(): array
    {
        $today = today();

        return [
            'today_appointments' => Appointment::query()
                ->whereDate('preferred_date', $today)
                ->whereNotIn('status', [AppointmentStatus::Cancelled->value])
                ->count(),
            'completed_month' => Appointment::query()
                ->where('status', AppointmentStatus::Completed)
                ->whereBetween('preferred_date', [$today->copy()->startOfMonth(), $today->copy()->endOfMonth()])
                ->count(),
            // `low_stock_count` used to be here, for the dashboard's Low Stock
            // Alerts card. The card is gone, and nothing else reads the key, so
            // the query went with it rather than being left running on every
            // dashboard load. The sidebar still badges Inventory with its own
            // count, so the alert itself is not gone — only this duplicate.
            'sold_out_count' => InventoryItem::query()->where('status_tag', 'sold_out')->count(),
            'revenue_month' => (float) Appointment::query()
                ->whereIn('status', self::REVENUE_STATUSES)
                ->whereBetween('preferred_date', [$today->copy()->startOfMonth(), $today->copy()->endOfMonth()])
                ->sum('total_amount'),
            'revenue_today' => (float) Appointment::query()
                ->whereIn('status', self::REVENUE_STATUSES)
                ->whereDate('preferred_date', $today)
                ->sum('total_amount'),
            'unread_messages' => ContactMessage::query()->unread()->count(),
        ];
    }

    /**
     * Revenue bucketed per day for the dashboard bar chart.
     *
     * @return array<int, array{label: string, total: float, count: int}>
     */
    public function revenueChart(int $days = 14): array
    {
        $from = today()->subDays($days - 1)->startOfDay();

        $rows = Appointment::query()
            ->whereIn('status', self::REVENUE_STATUSES)
            ->whereBetween('preferred_date', [$from->toDateString(), today()->toDateString()])
            ->get(['preferred_date', 'total_amount']);

        $grouped = [];

        foreach ($rows as $appointment) {
            $key = $appointment->preferred_date->toDateString();

            $grouped[$key]['total'] = ($grouped[$key]['total'] ?? 0) + (float) $appointment->total_amount;
            $grouped[$key]['count'] = ($grouped[$key]['count'] ?? 0) + 1;
        }

        $series = [];

        for ($i = 0; $i < $days; $i++) {
            $date = $from->copy()->addDays($i);
            $key = $date->toDateString();

            $series[] = [
                'label' => $date->format('M j'),
                'total' => (float) ($grouped[$key]['total'] ?? 0),
                'count' => (int) ($grouped[$key]['count'] ?? 0),
            ];
        }

        return $series;
    }

    /**
     * Per-status counts for the dashboard donut.
     *
     * @return array<string, int>
     */
    public function statusBreakdown(): array
    {
        return Appointment::query()
            ->selectRaw('status, COUNT(*) as total')
            ->groupBy('status')
            ->pluck('total', 'status')
            ->map(fn ($v) => (int) $v)
            ->all();
    }

    /**
     * Bucketed revenue for a report granularity.
     *
     * Bucketing happens in PHP rather than SQL so the same code path works on
     * MySQL and on the SQLite connection used by the test suite. `services` is
     * the number of service lines booked in the bucket, which is what the
     * Reports screen's third headline card counts.
     *
     * @return Collection<int, array{period: string, label: string, revenue: float, bookings: int, services: int}>
     */
    public function totalsByPeriod(string $granularity, Carbon $from, Carbon $to): Collection
    {
        $appointments = Appointment::query()
            // The range is compared as dates rather than as a between-range: the
            // column is a DATE on MySQL but a datetime on the SQLite connection
            // the tests run against, and a string range that stops at midnight
            // silently drops everything after it there.
            ->whereIn('status', self::REVENUE_STATUSES)
            ->whereDate('preferred_date', '>=', $from->toDateString())
            ->whereDate('preferred_date', '<=', $to->toDateString())
            ->withSum('serviceLines', 'quantity')
            ->orderBy('preferred_date')
            ->get(['preferred_date', 'total_amount']);

        $buckets = [];

        foreach ($appointments as $appointment) {
            $key = $this->periodKey($granularity, $appointment->preferred_date);

            $buckets[$key]['revenue'] = ($buckets[$key]['revenue'] ?? 0) + (float) $appointment->total_amount;
            $buckets[$key]['bookings'] = ($buckets[$key]['bookings'] ?? 0) + 1;
            $buckets[$key]['services'] = ($buckets[$key]['services'] ?? 0) + (int) $appointment->service_lines_sum_quantity;
            $buckets[$key]['date'] = $buckets[$key]['date'] ?? $appointment->preferred_date;
        }

        ksort($buckets);

        return collect($buckets)->map(fn (array $bucket, string $key) => [
            'period' => $key,
            'label' => $this->periodLabel($granularity, $bucket['date']),
            'revenue' => (float) $bucket['revenue'],
            'bookings' => (int) $bucket['bookings'],
            'services' => (int) $bucket['services'],
        ])->values();
    }

    protected function periodKey(string $granularity, Carbon $date): string
    {
        return match ($granularity) {
            'weekly' => $date->copy()->startOfWeek()->toDateString(),
            'monthly' => $date->format('Y-m'),
            default => $date->toDateString(),
        };
    }

    protected function periodLabel(string $granularity, Carbon $date): string
    {
        return match ($granularity) {
            'weekly' => 'Week of '.$date->copy()->startOfWeek()->format('M j, Y'),
            'monthly' => $date->format('F Y'),
            default => $date->format('M j, Y'),
        };
    }

    /**
     * The date range for a report, from whatever the admin typed.
     *
     * Two inputs, and only two. The granularity parameter this used to take is
     * gone: it only ever chose how the rows were grouped, and choosing it in the
     * UI let it contradict the range — "Monthly" over three days produced one
     * bucket and read as a whole month. `granularityFor()` derives the grouping
     * from the range instead.
     *
     * With nothing typed, the window is the last 30 days. That is the longest span
     * that is still one readable bucket-per-month, so it is the range where the
     * derived grouping and the summary agree.
     *
     * A start after the end is swapped rather than rejected — an admin who picks
     * the dates in the wrong order gets the report for the range they meant
     * instead of an error telling them to retype two fields.
     *
     * @return array{from: Carbon, to: Carbon}
     */
    public function resolveRange(?string $from, ?string $to): array
    {
        $toDate = $to ? Carbon::parse($to) : today();
        $fromDate = $from ? Carbon::parse($from) : $toDate->copy()->subDays(29);

        if ($fromDate->gt($toDate)) {
            [$fromDate, $toDate] = [$toDate, $fromDate];
        }

        return ['from' => $fromDate->startOfDay(), 'to' => $toDate->endOfDay()];
    }

    /**
     * How to bucket a range's rows.
     *
     * Derived rather than chosen, so the grouping cannot disagree with the range
     * the admin asked for. The thresholds are about readability rather than
     * arithmetic: past roughly two months of daily buckets a table is a wall of
     * near-identical rows nobody reads, and past roughly a year of weekly ones
     * there is a row for every week of the business.
     *
     * @return string one of daily|weekly|monthly
     */
    public function granularityFor(Carbon $from, Carbon $to): string
    {
        $days = $from->diffInDays($to) + 1;

        return match (true) {
            $days <= 62 => 'daily',
            $days <= 400 => 'weekly',
            default => 'monthly',
        };
    }

    /**
     * Display names for the groupings, kept beside the ones that produce them.
     *
     * @return array<string, string>
     */
    public static function granularityLabels(): array
    {
        return [
            'daily' => 'Day',
            'weekly' => 'Week',
            'monthly' => 'Month',
        ];
    }
}
