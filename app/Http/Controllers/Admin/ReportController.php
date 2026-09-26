<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\InventoryItem;
use App\Models\Service;
use App\Services\ReportService;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ReportController extends Controller
{
    public const TYPES = [
        'daily' => 'Daily',
        'weekly' => 'Weekly',
        'monthly' => 'Monthly',
        'annual' => 'Annual',
    ];

    public function __construct(protected ReportService $reports) {}

    /**
     * Admin Flow 12 — Sales / Usage Reports.
     */
    public function index(Request $request): View
    {
        $type = $request->input('type', 'daily');

        if (! array_key_exists($type, self::TYPES)) {
            $type = 'daily';
        }

        ['from' => $from, 'to' => $to] = $this->reports->resolveRange(
            $request->input('from'),
            $request->input('to'),
            $type,
        );

        $serviceId = $request->input('service_id') !== '' ? $request->input('service_id') : null;
        $itemId = $request->input('item_id') !== '' ? $request->input('item_id') : null;

        $totals = $this->reports->totalsByPeriod($type, $from, $to);

        return view('admin.reports.index', [
            'type' => $type,
            'typeOptions' => self::TYPES,
            'from' => $from,
            'to' => $to,
            'filters' => [
                'from' => $from->toDateString(),
                'to' => $to->toDateString(),
                'service_id' => $serviceId,
                'item_id' => $itemId,
            ],
            'totals' => $totals,
            'byService' => $this->reports->revenueByService($from, $to, $serviceId),
            'itemUsage' => $this->reports->itemUsage($from, $to, $itemId),
            'grandTotal' => (float) $totals->sum('revenue'),
            'totalBookings' => (int) $totals->sum('bookings'),
            'maxPeriodRevenue' => (float) ($totals->max('revenue') ?? 0),
            'services' => Service::query()->orderBy('name')->get(['id', 'name']),
            'items' => InventoryItem::query()->orderBy('name')->get(['id', 'name', 'unit']),
        ]);
    }

    /**
     * CSV export of the same figures shown on screen.
     */
    public function export(Request $request): StreamedResponse
    {
        $type = array_key_exists($request->input('type', 'daily'), self::TYPES)
            ? $request->input('type', 'daily')
            : 'daily';

        ['from' => $from, 'to' => $to] = $this->reports->resolveRange(
            $request->input('from'),
            $request->input('to'),
            $type,
        );

        $serviceId = $request->input('service_id') !== '' ? $request->input('service_id') : null;
        $itemId = $request->input('item_id') !== '' ? $request->input('item_id') : null;

        $filename = "bta-{$type}-report-".$from->format('Ymd').'-'.$to->format('Ymd').'.csv';

        return response()->streamDownload(function () use ($from, $to, $type, $serviceId, $itemId) {
            $out = fopen('php://output', 'w');

            fputcsv($out, ['Balai ti Arjud — '.self::TYPES[$type].' Report']);
            fputcsv($out, ['Range', $from->toDateString().' to '.$to->toDateString()]);
            fputcsv($out, ['Generated', now()->toDateTimeString()]);
            fputcsv($out, []);

            fputcsv($out, ['SUMMARY BY PERIOD']);
            fputcsv($out, ['Period', 'Bookings', 'Revenue (PHP)']);

            foreach ($this->reports->totalsByPeriod($type, $from, $to) as $row) {
                fputcsv($out, [$row['label'], $row['bookings'], number_format($row['revenue'], 2)]);
            }

            fputcsv($out, []);
            fputcsv($out, ['REVENUE BY SERVICE']);
            fputcsv($out, ['Service', 'Bookings', 'Quantity', 'Revenue (PHP)']);

            foreach ($this->reports->revenueByService($from, $to, $serviceId) as $row) {
                fputcsv($out, [$row['service'], $row['bookings'], $row['quantity'], number_format($row['revenue'], 2)]);
            }

            fputcsv($out, []);
            fputcsv($out, ['ITEM USAGE']);
            fputcsv($out, ['Item', 'Unit', 'Used', 'Remaining']);

            foreach ($this->reports->itemUsage($from, $to, $itemId) as $row) {
                fputcsv($out, [$row['item'], $row['unit'], $row['used'], $row['remaining']]);
            }

            fclose($out);
        }, $filename, [
            'Content-Type' => 'text/csv',
        ]);
    }
}
