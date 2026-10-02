<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\InventoryItemRequest;
use App\Models\InventoryItem;
use App\Support\DataTable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Admin Flow 6 — Inventory / Item Management (Add / Edit / Delete).
 */
class InventoryController extends Controller
{
    /**
     * Columns the list lets an admin sort by.
     *
     * `reorder_threshold` and `supplier` are gone from the table, and
     * `services` is a relation rather than a column — none of the three can be
     * sorted on any more, so a crafted `?sort=` cannot reach one.
     *
     * `category` left for the same reason. Its column is gone from the list along
     * with the form field behind it, and every save writes
     * `InventoryItem::DEFAULT_CATEGORY`, so ordering by it would sort a constant:
     * a header offering to rearrange rows that all share one value is a control
     * that cannot change the answer.
     *
     * @var array<int, string>
     */
    private const SORTABLE = ['name', 'sku', 'quantity', 'status_tag', 'expiry_date', 'date_in'];

    public function index(Request $request): View
    {
        [$sort, $direction] = DataTable::sort($request, self::SORTABLE, 'name');

        // Rendered whole and filtered and paged in the browser, so a search is
        // not a round trip per keystroke. `search` only seeds the search box.
        //
        // `withCount('services')` is deliberately not loaded: which services
        // consume an item is no longer shown here, and the relationship itself
        // stays because booking a service still deducts the stock.
        $items = DataTable::applySort(
            InventoryItem::query(),
            $sort,
            $direction,
            self::SORTABLE,
            'name',
        )->get();

        return view('admin.inventory.index', [
            'items' => $items,
            'search' => $request->input('search'),
            'sort' => $sort,
            'direction' => $direction,
        ]);
    }

    public function create(): View
    {
        return view('admin.inventory.create', [
            'item' => new InventoryItem,
        ]);
    }

    public function store(InventoryItemRequest $request): RedirectResponse
    {
        $item = InventoryItem::create([
            ...$request->safe()->only(InventoryItemRequest::fields()),
            // Not form fields. Written rather than validated, so a crafted post
            // cannot smuggle values past the fields that are gone: `pcs` because
            // the salon counts in pieces, and one category because stock no longer
            // maintains its own parallel set of category names.
            'unit' => InventoryItem::DEFAULT_UNIT,
            'category' => InventoryItem::DEFAULT_CATEGORY,
        ]);

        // The tag is derived, not chosen: there is no override on the form, so
        // the only question left is whether this quantity is sold out or in
        // stock, against the threshold the item already carries.
        $item->forceFill([
            'status_tag' => $item->suggestedTag(),
        ])->save();

        return redirect()
            ->route('admin.inventory.index')
            ->with('status', "Item \"{$item->name}\" created.");
    }

    /**
     * The stock list as a CSV file.
     *
     * Lives here rather than in ReportController because it is the *inventory*
     * report and the query is the same one the list runs — same sort, same search,
     * same tag filter. Sharing the query is the point: an export that re-derived
     * its own filters would be a second report that quietly disagreed with the
     * screen it was exported from.
     *
     * No date range, unlike the sales export. Quantity is overwritten in place on
     * every booking rather than versioned, so there is no such thing as the stock
     * level on a given past date — a filter here would be a control that silently
     * does nothing, which is worse than not offering it.
     *
     * Soft-deleted items are excluded, matching the list. A row the salon has
     * removed is not part of its stock, and the list is what an admin is reading
     * when they press the button.
     */
    public function export(Request $request): StreamedResponse
    {
        [$sort, $direction] = DataTable::sort($request, self::SORTABLE, 'name');

        $items = DataTable::applySort(
            InventoryItem::query(),
            $sort,
            $direction,
            self::SORTABLE,
            'name',
        )
            ->search($request->input('search'))
            ->get();

        $filename = 'bta-inventory-report-'.now()->format('Ymd').'.csv';

        return response()->streamDownload(function () use ($items) {
            $out = fopen('php://output', 'w');

            fputcsv($out, ['Balai ti Arjud — Inventory Report']);
            fputcsv($out, ['Generated', now()->toDateTimeString()]);
            fputcsv($out, ['Items', count($items)]);
            fputcsv($out, []);

            fputcsv($out, ['INVENTORY']);
            fputcsv($out, ['Item', 'SKU', 'Stock', 'Unit', 'Status', 'Date In', 'Expiry']);

            foreach ($items as $item) {
                fputcsv($out, [
                    $item->name,
                    $item->sku,
                    rtrim(rtrim(number_format((float) $item->quantity, 2), '0'), '.'),
                    $item->unit,
                    $item->status_tag->label(),
                    $item->date_in?->toDateString() ?? '—',
                    $item->expiry_date?->toDateString() ?? '—',
                ]);
            }

            fputcsv($out, []);

            /*
             * A totals block rather than a total row, because quantities across
             * rows are not additive into a single figure — "total stock" would be
             * a number that means nothing to anyone reading the sheet. It used to
             * be argued from the units differing; now it is simply that no useful
             * total exists. The summary an admin opening this file is after is how
             * many items are flagged low or sold out, so those are the counters.
             */
            fputcsv($out, ['SUMMARY']);
            fputcsv($out, ['Low stock items', $items->filter->isLowOnStock()->count()]);
            fputcsv($out, ['Sold out items', $items->filter->isSoldOut()->count()]);

            fclose($out);
        }, $filename, [
            'Content-Type' => 'text/csv',
        ]);
    }

    public function edit(InventoryItem $inventory_item): View
    {
        return view('admin.inventory.edit', [
            'item' => $inventory_item,
        ]);
    }

    public function update(InventoryItemRequest $request, InventoryItem $inventory_item): RedirectResponse
    {
        $inventory_item->update([
            ...$request->safe()->only(InventoryItemRequest::fields()),
            // Same reasons as `store()`. The unit and the category are normalised
            // on every save, so a row cannot end up with a quantity in pieces
            // beside a stale unit, or be re-filed under whatever a retired
            // category said.
            'unit' => InventoryItem::DEFAULT_UNIT,
            'category' => InventoryItem::DEFAULT_CATEGORY,
        ]);

        return redirect()
            ->route('admin.inventory.index')
            ->with('status', "Item \"{$inventory_item->name}\" updated.");
    }

    public function destroy(InventoryItem $inventory_item): RedirectResponse
    {
        $name = $inventory_item->name;
        $inventory_item->delete();

        return redirect()
            ->route('admin.inventory.index')
            ->with('status', "Item \"{$name}\" deleted.");
    }
}
