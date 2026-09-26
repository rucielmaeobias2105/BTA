<?php

namespace App\Http\Controllers\Customer;

use App\Enums\AppointmentStatus;
use App\Http\Controllers\Controller;
use App\Models\Appointment;
use App\Models\Review;
use Illuminate\View\View;

class DashboardController extends Controller
{
    /**
     * Customer Flow 13 — Customer Dashboard.
     * Snapshot of the upcoming appointment, recent activity and quick links.
     */
    public function __invoke(): View
    {
        $user = auth()->user();

        $upcoming = $user->appointments()
            ->with('serviceLines', 'preferredStylist')
            ->upcoming()
            ->orderBy('preferred_date')
            ->orderBy('preferred_time')
            ->first();

        $recent = $user->appointments()
            ->with('serviceLines')
            ->whereIn('status', [
                AppointmentStatus::Completed,
                AppointmentStatus::Cancelled,
            ])
            ->take(4)
            ->get();

        $stats = [
            'upcoming' => $user->appointments()->upcoming()->count(),
            'completed' => $user->appointments()
                ->where('status', AppointmentStatus::Completed)
                ->count(),
            'cancelled' => $user->appointments()
                ->where('status', AppointmentStatus::Cancelled)
                ->count(),
            'pending' => $user->appointments()
                ->where('status', AppointmentStatus::Pending)
                ->count(),
        ];

        return view('customer.dashboard', [
            'user' => $user,
            'upcoming' => $upcoming,
            'recent' => $recent,
            'stats' => $stats,
            'unreadCount' => $user->unreadNotifications()->count(),
            'awaitingRating' => $user->reviewableAppointments()->count(),
            'reviews' => Review::query()->count(),
            'averageRating' => round((float) Review::query()->avg('rating'), 1),
        ]);
    }
}
