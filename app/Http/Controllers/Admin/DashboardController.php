<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Appointment;
use App\Services\ReportService;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function __construct(protected ReportService $reports) {}

    /**
     * Admin Flow 2 — Admin Dashboard.
     *
     * Read-only metrics: the quick stat cards from the report summary and
     * today's schedule. The revenue chart, the appointment status breakdown,
     * the low-stock list, the upcoming list and the recent activity used to be
     * queried here as well, but their cards were removed from the panel — this
     * deliberately stops fetching data no view renders.
     */
    public function __invoke(): View
    {
        return view('admin.dashboard', [
            'summary' => $this->reports->summary(),
            'todayAppointments' => Appointment::query()
                ->with(['serviceLines', 'user'])
                ->whereDate('preferred_date', today())
                ->orderBy('preferred_time')
                ->get(),
        ]);
    }
}
