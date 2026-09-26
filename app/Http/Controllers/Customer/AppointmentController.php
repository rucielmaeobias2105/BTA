<?php

namespace App\Http\Controllers\Customer;

use App\Enums\AppointmentStatus;
use App\Enums\TermsCategory;
use App\Http\Controllers\Controller;
use App\Http\Requests\Customer\StoreBookingRequest;
use App\Models\Admin;
use App\Models\Appointment;
use App\Models\SalonSetting;
use App\Models\Service;
use App\Models\TermsAndCondition;
use App\Services\BookingAvailability;
use App\Services\BookingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class AppointmentController extends Controller
{
    public function __construct(protected BookingService $booking) {}

    /**
     * Customer Flow 5 (part 1) — My Appointments / Transactions.
     * List view with a status filter; action buttons only, no input fields.
     */
    public function index(Request $request): View
    {
        $status = $request->string('status')->toString();
        $status = array_key_exists($status, AppointmentStatus::filterOptions()) ? $status : 'all';

        $appointments = auth()->user()
            ->appointments()
            ->with('serviceLines', 'review')
            ->status($status)
            ->paginate(10)
            ->withQueryString();

        return view('customer.appointments.index', [
            'appointments' => $appointments,
            'status' => $status,
            'statusOptions' => AppointmentStatus::filterOptions(),
            'counts' => auth()->user()->appointments()
                ->selectRaw('status, COUNT(*) as total')
                ->groupBy('status')
                ->pluck('total', 'status'),
        ]);
    }

    /**
     * Read-only detail for a single appointment.
     */
    public function show(Appointment $appointment): View
    {
        $this->authorizeOwnership($appointment);

        $appointment->load(['serviceLines', 'statusHistory.changedBy', 'review']);

        return view('customer.appointments.show', [
            'appointment' => $appointment,
        ]);
    }

    /**
     * Customer Flow 5 (part 2) — Book Appointment form.
     */
    public function create(Request $request): View
    {
        $availability = BookingAvailability::make();
        $settings = SalonSetting::current();

        $services = Service::query()
            ->active()
            ->with('variants')
            ->orderBy('category')
            ->orderBy('name')
            ->get()
            ->groupBy('category');

        $selected = $this->preselectFromRequest($request, $services);

        // Default to the next day that actually has availability.
        $prefilledDate = $request->input('date') ?: $this->nextAvailableDate($availability);

        return view('customer.appointments.create', [
            'settings' => $settings,
            'groupedServices' => $services,
            'selected' => $selected,
            'stylists' => Admin::query()->therapists()->orderBy('first_name')->get(),
            'availability' => $availability,
            'prefilledDate' => $prefilledDate,
            'slots' => $availability->availableSlots($prefilledDate),
            'blockedRanges' => $availability->blockedRanges(),
            'terms' => TermsAndCondition::publishedFor(TermsCategory::Booking),
            'history' => auth()->check()
                ? auth()->user()->appointments()
                    ->with('serviceLines')
                    ->where('status', AppointmentStatus::Completed)
                    ->get()
                : collect(),
            'total' => $this->totalFor($selected, $availability),
            'expectedDownPayment' => $settings->down_payment_required
                ? $settings->expectedDownPaymentFor($this->totalFor($selected, $availability))
                : null,
        ]);
    }

    /**
     * First date within the booking horizon that still has an open slot.
     */
    protected function nextAvailableDate(BookingAvailability $availability): string
    {
        $cursor = $availability->firstBookableDate();
        $last = $availability->lastBookableDate();

        while ($cursor->lte($last)) {
            if ($availability->availableSlots($cursor->toDateString()) !== []) {
                return $cursor->toDateString();
            }

            $cursor->addDay();
        }

        return now()->format('Y-m-d');
    }

    /**
     * Customer Flow 5 (part 2) — Store booking, then show the summary.
     */
    public function store(StoreBookingRequest $request): RedirectResponse
    {
        $lines = $this->booking->resolveLines($request->input('services', []));

        $user = $request->user();

        $appointment = $this->booking->create(
            $request->safe()->except(['services', 'agree_terms']) + [
                'customer_email' => $user?->email,
            ],
            $lines,
            $user?->id,
        );

        if (! auth()->check()) {
            // Guests keep the reference number so they can follow up.
            session(['last_booking_reference' => $appointment->reference_number]);
        }

        return redirect()
            ->route('appointments.show', $appointment)
            ->with('status', 'Your booking request has been submitted. Reference: '.$appointment->reference_number);
    }

    /**
     * JSON slot lookup backing the booking form's date picker.
     */
    public function slots(Request $request): JsonResponse
    {
        $data = $request->validate([
            'date' => ['required', 'date'],
            'service_id' => ['nullable', 'integer', Rule::exists('services', 'id')],
        ]);

        $availability = BookingAvailability::make();

        $slots = $availability->availableSlots($data['date'], $data['service_id'] ?? null);

        return response()->json([
            'date' => $data['date'],
            'slots' => $slots,
            'open' => $availability->isDateAvailable($data['date'], $data['service_id'] ?? null),
            'problems' => $availability->dateProblems($data['date'], $data['service_id'] ?? null),
        ]);
    }

    /* ------------------------------------------------------------------ */
    /* Helpers                                                            */
    /* ------------------------------------------------------------------ */

    /**
     * Pre-select services passed via the query string (?services[]=slug).
     *
     * @param  \Illuminate\Support\Collection<string, \Illuminate\Support\Collection<int, Service>>  $grouped
     * @return array<string, array{service_id: int, service_variant_id: ?int, quantity: int}>
     */
    protected function preselectFromRequest(Request $request, $grouped): array
    {
        $selected = [];
        $slugs = (array) $request->input('services', []);

        foreach ($grouped->flatten() as $service) {
            if (in_array($service->slug, $slugs, true) || in_array((string) $service->id, array_map('strval', $slugs), true)) {
                $selected[$service->id] = [
                    'service_id' => $service->id,
                    'service_variant_id' => $service->variants->firstWhere('is_default', true)?->id
                        ?? $service->variants->first()?->id,
                    'quantity' => 1,
                ];
            }
        }

        return $selected;
    }

    /**
     * @param  array<string, array{service_id: int, service_variant_id: ?int, quantity: int}>  $selected
     */
    protected function totalFor(array $selected, BookingAvailability $availability): float
    {
        $lines = $this->booking->resolveLines(array_values($selected));

        return $this->booking->totalFor($lines);
    }

    protected function authorizeOwnership(Appointment $appointment): void
    {
        abort_unless(
            auth()->id() !== null && $appointment->user_id === auth()->id(),
            403,
            'This appointment belongs to another account.',
        );
    }
}
