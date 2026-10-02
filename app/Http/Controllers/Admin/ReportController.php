<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\ReportService;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Admin Flow 12 — Sales Reports.
 *
 * One filter pair and one question: revenue between two dates. There used to be
 * a third control, a "Period" dropdown choosing Daily / Weekly / Monthly, and it
 * was redundant rather than useful — Start Date and End Date already state the
 * range exactly, and the dropdown only ever decided how those rows were grouped.
 * Worse, the two could contradict each other: picking "Monthly" with a three-day
 * range gave one bucket for three days and read as though a whole month had been
 * reported.
 *
 * The grouping still happens, it is just derived. `ReportService::granularityFor()`
 * picks a bucket size from the length of the range, so a week of dates reads as a
 * week and a quarter reads as three months without anybody choosing.
 */
class ReportController extends Controller
{
    public function __construct(protected ReportService $reports) {}

    public function index(Request $request): View
    {
        ['from' => $from, 'to' => $to] = $this->range($request);
        $type = $this->reports->granularityFor($from, $to);

        $rows = $this->reports->totalsByPeriod($type, $from, $to);

        return view('admin.reports.index', [
            'type' => $type,
            'typeLabel' => ReportService::granularityLabels()[$type],
            'from' => $from,
            'to' => $to,
            'filters' => [
                'from' => $from->toDateString(),
                'to' => $to->toDateString(),
            ],
            // The rows feed the shared admin-table card, which filters and pages
            // them in the browser; `search` only seeds its search box.
            'rows' => $rows,
            'search' => $request->input('search'),
            'grandTotal' => (float) $rows->sum('revenue'),
            'totalBookings' => (int) $rows->sum('bookings'),
            'totalServices' => (int) $rows->sum('services'),

            // The Inventory export lives on this screen too, so both reports are
            // reachable from one place rather than making an admin remember which
            // sidebar item holds the other one. It takes no date filter — stock is
            // a snapshot of now, and a "stock as it was on the 3rd" reading is not
            // something this schema can answer, since quantity is overwritten on
            // every booking rather than versioned.
            'inventoryFilters' => ['search' => $request->input('search')],
        ]);
    }

    /**
     * CSV export of the same figures shown on screen.
     *
     * Reads the range from the same two inputs as `index()` and derives the
     * grouping the same way, so a link built from the on-screen filter and a link
     * typed by hand produce the same file — the export is the screen, not a second
     * report that happens to look like it.
     */
    public function export(Request $request): StreamedResponse
    {
        ['from' => $from, 'to' => $to] = $this->range($request);
        $type = $this->reports->granularityFor($from, $to);
        $label = ReportService::granularityLabels()[$type];

        $rows = $this->reports->totalsByPeriod($type, $from, $to);

        $filename = 'bta-sales-report-'.$from->format('Ymd').'-'.$to->format('Ymd').'.csv';

        return response()->streamDownload(function () use ($rows, $from, $to, $label) {
            $out = fopen('php://output', 'w');

            fputcsv($out, ['Balai ti Arjud — Sales Report']);
            fputcsv($out, ['Range', $from->toDateString().' to '.$to->toDateString()]);
            fputcsv($out, ['Grouped by', $label]);
            fputcsv($out, ['Generated', now()->toDateTimeString()]);
            fputcsv($out, []);

            fputcsv($out, ['REVENUE']);
            fputcsv($out, [$label, 'Bookings', 'Services', 'Revenue (PHP)']);

            foreach ($rows as $row) {
                fputcsv($out, [$row['label'], $row['bookings'], $row['services'], number_format($row['revenue'], 2)]);
            }

            fputcsv($out, []);
            fputcsv($out, ['TOTAL', $rows->sum('bookings'), $rows->sum('services'), number_format((float) $rows->sum('revenue'), 2)]);

            fclose($out);
        }, $filename, [
            'Content-Type' => 'text/csv',
        ]);
    }

    /**
     * The date range from the request, normalised.
     *
     * Two inputs and nothing else: there is no granularity to read, so this is
     * simply the default window when the admin has not typed anything yet.
     *
     * @return array{from: \Illuminate\Support\Carbon, to: \Illuminate\Support\Carbon}
     */
    private function range(Request $request): array
    {
        return $this->reports->resolveRange($request->input('from'), $request->input('to'));
    }
}
