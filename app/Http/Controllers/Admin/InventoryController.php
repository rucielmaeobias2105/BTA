<?php

namespace App\Http\Controllers\Admin;

use App\Enums\ItemTag;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\InventoryItemRequest;
use App\Models\InventoryItem;
use App\Models\Service;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

/**
 * Admin Flow 6 — Inventory / Item Management (Add / Edit / Delete).
 */
class InventoryController extends Controller
{
    public function index(Request $request): View
    {
        $tag = in_array($request->input('tag'), ItemTag::values(), true) ? $request->input('tag') : null;

        $items = InventoryItem::query()
            ->with('services:id,name')
            ->withCount('services')
            ->search($request->input('search'))
            ->category($request->input('category'))
            ->tagged($tag)
            ->orderBy('name')
            ->paginate(15)
            ->withQueryString();

        return view('admin.inventory.index', [
            'items' => $items,
            'filters' => ['search' => $request->input('search'), 'category' => $request->input('category'), 'tag' => $tag],
            'categories' => InventoryItem::query()->distinct()->orderBy('category')->pluck('category'),
            'tagOptions' => ItemTag::assignableOptions(),
            'lowStockCount' => InventoryItem::query()->lowStock()->count(),
            'soldOutCount' => InventoryItem::query()->where('status_tag', ItemTag::SoldOut->value)->count(),
        ]);
    }

    public function create(): View
    {
        return view('admin.inventory.create', [
            'item' => new InventoryItem,
            'units' => InventoryItem::UNITS,
            'categories' => $this->categorySuggestions(),
            'allServices' => Service::query()->active()->orderBy('name')->get(['id', 'name']),
        ]);
    }

    public function store(InventoryItemRequest $request): RedirectResponse
    {
        $item = DB::transaction(function () use ($request) {
            $data = $request->safe()->except(['services', 'quantities', 'status_tag']);

            $data['is_active'] = $request->boolean('is_active');
            $data['status_tag'] = $this->resolveTag($request, (float) $request->input('quantity'), (float) $request->input('reorder_threshold'));

            $item = InventoryItem::create($data);

            $this->syncServices($item, $request);

            return $item;
        });

        return redirect()
            ->route('admin.inventory.index')
            ->with('status', "Item \"{$item->name}\" created.");
    }

    public function edit(InventoryItem $inventory_item): View
    {
        $inventory_item->load('services');

        return view('admin.inventory.edit', [
            'item' => $inventory_item,
            'units' => InventoryItem::UNITS,
            'categories' => $this->categorySuggestions(),
            'allServices' => Service::query()->active()->orderBy('name')->get(['id', 'name']),
            'linkedServiceIds' => $inventory_item->services->pluck('id')->all(),
        ]);
    }

    public function update(InventoryItemRequest $request, InventoryItem $inventory_item): RedirectResponse
    {
        DB::transaction(function () use ($request, $inventory_item) {
            $data = $request->safe()->except(['services', 'quantities', 'status_tag']);

            $data['is_active'] = $request->boolean('is_active');
            $data['status_tag'] = $this->resolveTag(
                $request,
                (float) $request->input('quantity'),
                (float) $request->input('reorder_threshold'),
                $inventory_item->status_tag,
            );

            $inventory_item->update($data);

            $this->syncServices($inventory_item, $request);
        });

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

    /**
     * Sync the many-to-many pivot, honouring per-service consumption amounts.
     */
    protected function syncServices(InventoryItem $item, InventoryItemRequest $request): void
    {
        $serviceIds = (array) $request->input('services', []);
        $quantities = (array) $request->input('quantities', []);

        $sync = [];

        foreach ($serviceIds as $serviceId) {
            $sync[$serviceId] = ['quantity_per_service' => max(0.01, (float) ($quantities[$serviceId] ?? 1))];
        }

        $item->services()->sync($sync);
    }

    /**
     * Resolve the status tag: an explicit choice is a manual override,
     * otherwise it is derived from quantity vs. reorder threshold.
     */
    protected function resolveTag(
        InventoryItemRequest $request,
        float $quantity,
        float $threshold,
        ?ItemTag $current = null,
    ): string {
        if ($request->filled('status_tag')) {
            return (string) $request->input('status_tag');
        }

        if ($current === ItemTag::BestSeller) {
            // A marketing tag survives quantity changes until it runs out.
            return $quantity <= 0 ? ItemTag::SoldOut->value : ItemTag::BestSeller->value;
        }

        if ($quantity <= 0) {
            return ItemTag::SoldOut->value;
        }

        return $quantity <= $threshold ? ItemTag::LowStock->value : ItemTag::Available->value;
    }

    /**
     * @return array<int, string>
     */
    protected function categorySuggestions(): array
    {
        return array_values(array_unique(array_merge(
            InventoryItem::query()->distinct()->pluck('category')->all(),
            InventoryItem::CATEGORIES,
        )));
    }
}
