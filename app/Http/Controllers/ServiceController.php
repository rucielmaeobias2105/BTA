<?php

namespace App\Http\Controllers;

use App\Models\Review;
use App\Models\Service;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Customer Flows 4 & 15 — Browse Services / Refined Grid View.
 * Public and read-only: no auth required to view.
 */
class ServiceController extends Controller
{
    /**
     * Customer Flow 4 — Browse Services (search + category + price range filters).
     */
    public function index(Request $request): View
    {
        $services = $this->filtered($request)->paginate(12)->withQueryString();

        return view('services.index', [
            'services' => $services,
            'categories' => Service::categories(),
            'filters' => $request->only(['search', 'category', 'min_price', 'max_price']),
            'priceRange' => $this->priceBounds(),
        ]);
    }

    /**
     * Customer Flow 15 — Refined Grid View (category-tabbed service grid).
     */
    public function refined(Request $request): View
    {
        $categories = Service::categories();

        $active = $request->string('category')->toString();

        if ($active !== '' && ! in_array($active, $categories, true)) {
            $active = 'all';
        }

        $services = Service::query()
            ->active()
            ->with('variants')
            ->withAvg('reviews', 'rating')
            ->withCount('reviews')
            ->search($request->input('search'))
            ->category($active === 'all' ? null : $active)
            ->orderBy('is_featured', 'desc')
            ->orderBy('name')
            ->paginate(18)
            ->withQueryString();

        return view('services.refined', [
            'services' => $services,
            'categories' => $categories,
            'activeCategory' => $active,
            'search' => $request->input('search'),
        ]);
    }

    /**
     * Service detail with its variants and customer reviews.
     */
    public function show(Service $service): View
    {
        abort_unless($service->is_active, 404);

        $service->load('variants');

        return view('services.show', [
            'service' => $service,
            'related' => Service::query()
                ->active()
                ->where('category', $service->category)
                ->whereKeyNot($service->id)
                ->take(4)
                ->get(),
            'reviews' => Review::query()
                ->where('service_id', $service->id)
                ->latest()
                ->take(6)
                ->get(),
        ]);
    }

    /**
     * Shared filter pipeline for the list view.
     */
    private function filtered(Request $request)
    {
        return Service::query()
            ->active()
            ->with('variants')
            ->withAvg('reviews', 'rating')
            ->withCount('reviews')
            ->search($request->input('search'))
            ->category($request->input('category'))
            ->when($request->filled('min_price'), fn ($q) => $q->where('price', '>=', (float) $request->input('min_price')))
            ->when($request->filled('max_price'), fn ($q) => $q->where('price', '<=', (float) $request->input('max_price')))
            ->orderBy('category')
            ->orderBy('name');
    }

    /**
     * @return array{min: float, max: float}
     */
    private function priceBounds(): array
    {
        $prices = Service::query()->active()->selectRaw('MIN(price) as min_price, MAX(price) as max_price')->first();

        return [
            'min' => (float) ($prices?->min_price ?? 0),
            'max' => (float) ($prices?->max_price ?? 0),
        ];
    }
}
