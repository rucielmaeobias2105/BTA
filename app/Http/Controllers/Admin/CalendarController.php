<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\BlockedDate;
use App\Models\SalonSetting;
use App\Models\Service;
use App\Services\BookingAvailability;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * Admin Flow 8 — Calendar & Blocked Dates.
 * Blocking a date here is what the customer booking form validates against.
 */
class CalendarController extends Controller
{
    public function index(Request $request): View
    {
        $month = $request->filled('month')
            ? Carbon::parse($request->input('month').'-01')
            : today()->startOfMonth();

        $month = $month->startOfMonth();
        $gridStart = $month->copy()->startOfMonth()->startOfWeek();
        $gridEnd = $month->copy()->endOfMonth()->endOfWeek();

        $blocked = BlockedDate::query()
            ->with('service:id,name')
            ->whereDate('start_date', '<=', $gridEnd->toDateString())
            ->whereDate('end_date', '>=', $gridStart->toDateString())
            ->orderBy('start_date')
            ->get();

        $availability = BookingAvailability::make();
        $settings = SalonSetting::current();

        // Day-level closures for the grid.
        $closures = [];

        foreach ($blocked as $entry) {
            $cursor = Carbon::parse($entry->start_date)->startOfDay();
            $end = Carbon::parse($entry->end_date)->startOfDay();

            while ($cursor->lte($end)) {
                $closures[$cursor->toDateString()][] = $entry;
                $cursor->addDay();
            }
        }

        return view('admin.calendar.index', [
            'month' => $month,
            'gridStart' => $gridStart,
            'gridEnd' => $gridEnd,
            'blocked' => $blocked,
            'closures' => $closures,
            'settings' => $settings,
            'services' => Service::query()->active()->orderBy('name')->get(['id', 'name']),
            'upcomingBlocks' => BlockedDate::query()
                ->with('service:id,name')
                ->whereDate('end_date', '>=', today())
                ->orderBy('start_date')
                ->get(),
            'dayNames' => SalonSetting::dayNames(),
            'firstBookable' => $availability->firstBookableDate(),
            'lastBookable' => $availability->lastBookableDate(),
        ]);
    }

    /**
     * Block a single date or a range, scoped to all services or one service.
     */
    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'start_date' => ['required', 'date'],
            'end_date' => ['nullable', 'date', 'after_or_equal:start_date'],
            'service_id' => ['nullable', Rule::exists('services', 'id')->whereNull('deleted_at')],
            'reason' => ['nullable', 'string', 'max:500'],
        ], [
            'end_date.after_or_equal' => 'The end date cannot be before the start date.',
        ]);

        $start = Carbon::parse($data['start_date'])->startOfDay();
        $end = isset($data['end_date']) && $data['end_date'] !== ''
            ? Carbon::parse($data['end_date'])->startOfDay()
            : $start;

        if ($end->diffInDays($start) > 365) {
            return back()->withInput()->withErrors([
                'end_date' => 'You cannot block more than one year at a time.',
            ]);
        }

        $duplicate = BlockedDate::query()
            ->where(function ($q) use ($start, $end) {
                $q->whereDate('start_date', '<=', $end->toDateString())
                    ->whereDate('end_date', '>=', $start->toDateString());
            })
            ->when(
                isset($data['service_id']) && $data['service_id'] !== '',
                fn ($q) => $q->where('service_id', $data['service_id']),
                fn ($q) => $q->whereNull('service_id'),
            )
            ->exists();

        if ($duplicate) {
            return back()->withInput()->withErrors([
                'start_date' => 'That range is already blocked for this scope.',
            ]);
        }

        BlockedDate::create([
            'start_date' => $start,
            'end_date' => $end,
            'service_id' => $data['service_id'] ?? null,
            'reason' => $data['reason'] ?? null,
            'created_by' => $request->user('admin')->id,
        ]);

        return back()->with('status', 'Blocked '.$start->format('M j, Y')
            .($start->isSameDay($end) ? '' : ' – '.$end->format('M j, Y')).'.');
    }

    public function destroy(BlockedDate $blockedDate): RedirectResponse
    {
        $label = $blockedDate->range_label;
        $blockedDate->delete();

        return back()->with('status', "Unblocked {$label}.");
    }

    /**
     * Operating hours / booking horizon / down-payment rules.
     */
    public function updateSettings(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:150'],
            'address' => ['nullable', 'string', 'max:255'],
            'phone' => ['nullable', 'string', 'max:32'],
            'email' => ['nullable', 'email:rfc', 'max:255'],
            'slot_interval_minutes' => ['required', 'integer', 'min:15', 'max:240'],
            'booking_lead_days' => ['required', 'integer', 'min:1', 'max:365'],
            'down_payment_required' => ['nullable', 'boolean'],
            'down_payment_percentage' => ['required', 'integer', 'min:0', 'max:100'],
            'hours' => ['nullable', 'array'],
            'hours.*.open' => ['nullable', 'date_format:H:i'],
            'hours.*.close' => ['nullable', 'date_format:H:i'],
        ]);

        $hours = [];

        foreach (SalonSetting::dayNames() as $key => $label) {
            $open = $data['hours'][$key]['open'] ?? null;
            $close = $data['hours'][$key]['close'] ?? null;

            // Both ends must be present and the close must be after the open.
            if ($open && $close && $close > $open) {
                $hours[$key] = [$open, $close];
            }
        }

        if ($hours === []) {
            return back()->withInput()->withErrors([
                'hours' => 'Set operating hours for at least one day.',
            ]);
        }

        $settings = SalonSetting::current();
        $settings->update([
            'name' => $data['name'],
            'address' => $data['address'] ?? null,
            'phone' => $data['phone'] ?? null,
            'email' => $data['email'] ?? null,
            'slot_interval_minutes' => $data['slot_interval_minutes'],
            'booking_lead_days' => $data['booking_lead_days'],
            'down_payment_required' => $request->boolean('down_payment_required'),
            'down_payment_percentage' => $data['down_payment_percentage'],
            'operating_hours' => $hours,
        ]);

        return back()->with('status', 'Salon settings updated.');
    }
}
