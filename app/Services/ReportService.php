<?php

namespace App\Services;

use App\Enums\AppointmentStatus;
use App\Models\Appointment;
use App\Models\InventoryItem;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

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
            'pending_appointments' => Appointment::query()
                ->where('status', AppointmentStatus::Pending)
                ->count(),
            'completed_month' => Appointment::query()
                ->where('status', AppointmentStatus::Completed)
                ->whereBetween('preferred_date', [$today->copy()->startOfMonth(), $today->copy()->endOfMonth()])
                ->count(),
            'low_stock_count' => InventoryItem::query()->lowStock()->count(),
            'sold_out_count' => InventoryItem::query()->where('status_tag', 'sold_out')->count(),
            'revenue_month' => (float) Appointment::query()
                ->whereIn('status', self::REVENUE_STATUSES)
                ->whereBetween('preferred_date', [$today->copy()->startOfMonth(), $today->copy()->endOfMonth()])
                ->sum('total_amount'),
            'revenue_today' => (float) Appointment::query()
                ->whereIn('status', self::REVENUE_STATUSES)
                ->whereDate('preferred_date', $today)
                ->sum('total_amount'),
            'unread_messages' => \App\Models\ContactMessage::query()->unread()->count(),
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
     * Revenue per service over a date range.
     *
     * @return \Illuminate\Support\Collection<int, array{service: string, bookings: int, quantity: int, revenue: float}>
     */
    public function revenueByService(Carbon|string $from, Carbon|string $to, ?int $serviceId = null): Collection
    {
        return DB::table('appointment_service')
            ->join('appointments', 'appointments.id', '=', 'appointment_service.appointment_id')
            ->whereNull('appointments.deleted_at')
            ->whereIn('appointments.status', self::REVENUE_STATUSES)
            ->whereBetween('appointments.preferred_date', [Carbon::parse($from)->toDateString(), Carbon::parse($to)->toDateString()])
            ->when($serviceId, fn ($q) => $q->where('appointment_service.service_id', $serviceId))
            ->groupBy('appointment_service.service_name')
            ->selectRaw('appointment_service.service_name as service,
                         COUNT(*) as bookings,
                         SUM(appointment_service.quantity) as quantity,
                         SUM(appointment_service.price * appointment_service.quantity) as revenue')
            ->orderByDesc('revenue')
            ->get()
            ->map(fn ($row) => [
                'service' => $row->service,
                'bookings' => (int) $row->bookings,
                'quantity' => (int) $row->quantity,
                'revenue' => (float) $row->revenue,
            ]);
    }

    /**
     * Item consumption implied by bookings (quantity_per_service x bookings).
     *
     * @return \Illuminate\Support\Collection<int, array{item: string, unit: string, used: float, remaining: float}>
     */
    public function itemUsage(Carbon|string $from, Carbon|string $to, ?int $itemId = null): Collection
    {
        return DB::table('appointments')
            ->join('appointment_service', 'appointment_service.appointment_id', '=', 'appointments.id')
            ->join('services', 'services.id', '=', 'appointment_service.service_id')
            ->join('service_inventory', 'service_inventory.service_id', '=', 'services.id')
            ->join('inventory_items', 'inventory_items.id', '=', 'service_inventory.inventory_item_id')
            ->whereNull('appointments.deleted_at')
            ->whereIn('appointments.status', self::REVENUE_STATUSES)
            ->whereBetween('appointments.preferred_date', [Carbon::parse($from)->toDateString(), Carbon::parse($to)->toDateString()])
            ->when($itemId, fn ($q) => $q->where('inventory_items.id', $itemId))
            ->groupBy('inventory_items.id', 'inventory_items.name', 'inventory_items.unit', 'inventory_items.quantity')
            ->selectRaw('inventory_items.name as item,
                         inventory_items.unit as unit,
                         inventory_items.quantity as remaining,
                         SUM(service_inventory.quantity_per_service * appointment_service.quantity) as used')
            ->orderByDesc('used')
            ->get()
            ->map(fn ($row) => [
                'item' => $row->item,
                'unit' => $row->unit,
                'used' => (float) $row->used,
                'remaining' => (float) $row->remaining,
            ]);
    }

    /**
     * Bucketed revenue for a report granularity.
     *
     * Bucketing happens in PHP rather than SQL so the same code path works on
     * MySQL and on the SQLite connection used by the test suite.
     *
     * @return \Illuminate\Support\Collection<int, array{period: string, label: string, revenue: float, bookings: int}>
     */
    public function totalsByPeriod(string $granularity, Carbon $from, Carbon $to): Collection
    {
        $appointments = Appointment::query()
            ->whereIn('status', self::REVENUE_STATUSES)
            ->whereBetween('preferred_date', [$from->toDateString(), $to->toDateString()])
            ->orderBy('preferred_date')
            ->get(['preferred_date', 'total_amount']);

        $buckets = [];

        foreach ($appointments as $appointment) {
            $key = $this->periodKey($granularity, $appointment->preferred_date);

            $buckets[$key]['revenue'] = ($buckets[$key]['revenue'] ?? 0) + (float) $appointment->total_amount;
            $buckets[$key]['bookings'] = ($buckets[$key]['bookings'] ?? 0) + 1;
            $buckets[$key]['date'] = $buckets[$key]['date'] ?? $appointment->preferred_date;
        }

        ksort($buckets);

        return collect($buckets)->map(fn (array $bucket, string $key) => [
            'period' => $key,
            'label' => $this->periodLabel($granularity, $bucket['date']),
            'revenue' => (float) $bucket['revenue'],
            'bookings' => (int) $bucket['bookings'],
        ])->values();
    }

    protected function periodKey(string $granularity, Carbon $date): string
    {
        return match ($granularity) {
            'weekly' => $date->copy()->startOfWeek()->toDateString(),
            'monthly' => $date->format('Y-m'),
            'annual' => $date->format('Y'),
            default => $date->toDateString(),
        };
    }

    protected function periodLabel(string $granularity, Carbon $date): string
    {
        return match ($granularity) {
            'weekly' => 'Week of '.$date->copy()->startOfWeek()->format('M j, Y'),
            'monthly' => $date->format('F Y'),
            'annual' => (string) $date->format('Y'),
            default => $date->format('M j, Y'),
        };
    }

    /**
     * Applies a report granularity to a date range.
     *
     * @return array{from: Carbon, to: Carbon}
     */
    public function resolveRange(?string $from, ?string $to, string $type): array
    {
        $toDate = $to ? Carbon::parse($to) : today();
        $fromDate = $from ? Carbon::parse($from) : match ($type) {
            'daily' => $toDate->copy()->subDays(6),
            'weekly' => $toDate->copy()->subWeeks(3),
            'monthly' => $toDate->copy()->subMonths(5)->startOfMonth(),
            'annual' => $toDate->copy()->subYears(2)->startOfYear(),
            default => $toDate->copy()->subDays(6),
        };

        if ($fromDate->gt($toDate)) {
            [$fromDate, $toDate] = [$toDate, $fromDate];
        }

        return ['from' => $fromDate->startOfDay(), 'to' => $toDate->endOfDay()];
    }
}
