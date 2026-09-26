<?php

namespace App\Http\Controllers\Admin;

use App\Enums\AppointmentStatus;
use App\Http\Controllers\Controller;
use App\Models\Appointment;
use App\Models\InventoryItem;
use App\Models\Review;
use App\Services\ReportService;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function __construct(protected ReportService $reports) {}

    /**
     * Admin Flow 2 — Admin Dashboard.
     * Read-only metrics: today's appointments, low-stock alerts, revenue chart,
     * upcoming appointments and quick stat cards.
     */
    public function __invoke(): View
    {
        $summary = $this->reports->summary();

        return view('admin.dashboard', [
            'summary' => $summary,
            'chart' => $this->reports->revenueChart(14),
            'statusBreakdown' => $this->reports->statusBreakdown(),
            'todayAppointments' => Appointment::query()
                ->with(['serviceLines', 'user'])
                ->whereDate('preferred_date', today())
                ->orderBy('preferred_time')
                ->get(),
            'upcoming' => Appointment::query()
                ->with('serviceLines')
                ->upcoming()
                ->orderBy('preferred_date')
                ->orderBy('preferred_time')
                ->take(6)
                ->get(),
            'lowStockItems' => InventoryItem::query()
                ->lowStock()
                ->orderBy('quantity')
                ->take(6)
                ->get(),
            'recentReviews' => Review::query()->with('service')->latest()->take(4)->get(),
            'averageRating' => round((float) Review::query()->avg('rating'), 1),
        ]);
    }
}
