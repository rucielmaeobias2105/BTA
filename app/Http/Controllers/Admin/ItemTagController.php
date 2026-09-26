<?php

namespace App\Http\Controllers\Admin;

use App\Enums\ItemTag;
use App\Http\Controllers\Controller;
use App\Models\InventoryItem;
use App\Models\Service;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * Admin Flow 7 — Low-Stock / Not-Available Tagging.
 *
 * Auto-suggests "Low Stock" when quantity <= reorder threshold, while allowing
 * a manual override for Sold Out / Best Seller.
 */
class ItemTagController extends Controller
{
    public function index(Request $request): View
    {
        $filter = $request->input('filter', 'attention');

        $query = InventoryItem::query()->with('services:id,name');

        match ($filter) {
            'low_stock' => $query->lowStock(),
            'sold_out' => $query->where('status_tag', ItemTag::SoldOut->value),
            'best_seller' => $query->where('status_tag', ItemTag::BestSeller->value),
            'override' => $query->whereNotNull('status_tag'),
            // Default: everything that needs a decision.
            'attention' => $query->where(function ($q) {
                $q->lowStock()
                    ->orWhere('status_tag', ItemTag::SoldOut->value)
                    ->orWhere('status_tag', ItemTag::BestSeller->value);
            }),
            default => $query,
        };

        $items = $query->orderBy('quantity')->paginate(20)->withQueryString();

        return view('admin.tags.index', [
            'items' => $items,
            'filter' => $filter,
            'tagOptions' => ItemTag::assignableOptions(),
            'suggestions' => $this->buildSuggestions(),
        ]);
    }

    /**
     * Assign a status tag to one item.
     */
    public function update(Request $request, InventoryItem $item): RedirectResponse
    {
        $data = $request->validate([
            'status_tag' => ['required', Rule::in(ItemTag::values())],
        ]);

        $item->update(['status_tag' => $data['status_tag']]);

        return back()->with(
            'status',
            "\"{$item->name}\" tagged as ".ItemTag::from($data['status_tag'])->label().'.',
        );
    }

    /**
     * Bulk-assign a tag across the selected items.
     */
    public function bulkUpdate(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'status_tag' => ['required', Rule::in(ItemTag::values())],
            'items' => ['required', 'array', 'min:1'],
            'items.*' => ['integer', Rule::exists('inventory_items', 'id')],
        ]);

        $count = DB::table('inventory_items')
            ->whereIn('id', $data['items'])
            ->update(['status_tag' => $data['status_tag'], 'updated_at' => now()]);

        return back()->with('status', $count.' item(s) tagged as '.ItemTag::from($data['status_tag'])->label().'.');
    }

    /**
     * Per-service suggestions: which services will break if a tagged item runs out.
     *
     * @return \Illuminate\Support\Collection<int, array{service: string, items: array<int, array{name: string, status: string}>}>
     */
    protected function buildSuggestions(): \Illuminate\Support\Collection
    {
        $services = Service::query()
            ->active()
            ->with(['inventoryItems' => fn ($q) => $q->whereIn('status_tag', [
                ItemTag::LowStock->value,
                ItemTag::SoldOut->value,
            ])])
            ->orderBy('name')
            ->get();

        return $services
            ->map(fn (Service $service) => [
                'service' => $service->name,
                'items' => $service->inventoryItems
                    ->map(fn (InventoryItem $item) => ['name' => $item->name, 'status' => $item->status_tag->value])
                    ->values()
                    ->all(),
            ])
            ->filter(fn (array $row) => $row['items'] !== [])
            ->values();
    }
}
