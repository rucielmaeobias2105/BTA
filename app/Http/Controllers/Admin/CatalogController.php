<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\InventoryItem;
use App\Models\Service;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Admin Flow 4 — Service overview.
 *
 * The combined "Services & Items" catalogue used to list inventory items
 * alongside services and offered an "Add Item" button here. Items are managed
 * under Inventory, so this is now a services-only list and no longer queries
 * the stockroom at all.
 */
class CatalogController extends Controller
{
    public function index(Request $request): View
    {
        $term = $request->input('search');
        $category = $request->input('category');
        $type = in_array($request->input('type'), ['service', 'item'], true) ? $request->input('type') : 'all';

        $services = Service::query()
            ->with('variants')
            ->withCount('inventoryItems')
            ->search($term)
            ->category($category)
            ->when($type === 'item', fn ($q) => $q->whereRaw('1 = 0'))
            ->orderBy('name')
            ->paginate(20)
            ->withQueryString();

        return view('admin.catalog.index', [
            'services' => $services,
            'filters' => ['search' => $term, 'category' => $category, 'type' => $type],
            'serviceCategories' => Service::query()->distinct()->orderBy('category')->pluck('category'),
            'itemCategories' => InventoryItem::query()->distinct()->orderBy('category')->pluck('category'),
            'lowStockCount' => InventoryItem::query()->lowStock()->count(),
        ]);
    }

    /**
     * Jump straight to the relevant editor for a row in the combined list.
     */
    public function edit(string $type, int $id)
    {
        return match ($type) {
            'service' => redirect()->route('admin.services.edit', $id),
            'item' => redirect()->route('admin.inventory.edit', $id),
            default => redirect()->route('admin.catalog.index'),
        };
    }
}
