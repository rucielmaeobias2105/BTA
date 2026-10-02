<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\ServiceRequest;
use App\Models\Service;
use App\Models\ServiceCategory;
use App\Models\ServiceVariant;
use App\Support\DataTable;
use App\Support\PriceFormatter;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * Admin Flow 5 — Service Management (Add / Edit / Delete) + Variants.
 */
class ServiceController extends Controller
{
    /** Columns the list lets an admin sort by.
     *
     * `base_price` rather than `price`: price is the advertised string, so
     * ordering by it would be a lexical sort that puts "100+" ahead of "50+".
     */
    private const SORTABLE = ['name', 'category', 'base_price', 'is_active', 'created_at'];

    /**
     * A pasted price arrives with spaces around it, and a hand-built request
     * arrives as an int rather than the string a form posts. Normalising before
     * validating keeps the strict `string` rule about the value, not the
     * transport.
     */
    private function normaliseVariantPrice(Request $request): void
    {
        if ($request->has('price')) {
            $request->merge(['price' => trim((string) $request->input('price'))]);
        }
    }

    public function index(Request $request): View
    {
        [$sort, $direction] = DataTable::sort($request, self::SORTABLE, 'name');

        // The whole row set is rendered and the browser filters and pages it
        // live, so a search is not a round trip per keystroke. `search` is only
        // read back out to seed the search box.
        $services = DataTable::applySort(
            Service::query(),
            $sort,
            $direction,
            self::SORTABLE,
            'name',
        )
            // Name is a tiebreaker so rows with the same value keep a stable
            // order between requests. Applied only when it is not the sort
            // itself: appended unconditionally it becomes the primary key and
            // the chosen sort never takes effect.
            ->when($sort !== 'name', fn (Builder $query) => $query->orderBy('name'))
            ->get();

        return view('admin.services.index', [
            'services' => $services,
            'search' => $request->input('search'),
            'sort' => $sort,
            'direction' => $direction,
        ]);
    }

    public function create(): View
    {
        return view('admin.services.create', [
            'service' => new Service,
            'categories' => ServiceCategory::active(),
        ]);
    }

    public function store(ServiceRequest $request): RedirectResponse
    {
        $service = Service::create([
            ...$request->safe()->all(),
            'is_active' => $request->boolean('is_active'),
        ]);

        return redirect()
            ->route('admin.services.index')
            ->with('status', "Service \"{$service->name}\" Added.");
    }

    public function edit(Service $service): View
    {
        return view('admin.services.edit', [
            'service' => $service,
            // A category the admin has since switched off is no longer offered,
            // but this service is still filed under it — so it stays selectable
            // here rather than making the service unsaveable.
            'categories' => ServiceCategory::active()
                ->push($service->serviceCategory ?? new ServiceCategory(['name' => $service->category]))
                ->unique('name')
                ->sortBy([['sort_order', 'asc'], ['name', 'asc']])
                ->values(),
        ]);
    }

    public function update(ServiceRequest $request, Service $service): RedirectResponse
    {
        $service->update([
            ...$request->safe()->all(),
            'is_active' => $request->boolean('is_active'),
        ]);

        return redirect()
            ->route('admin.services.index')
            ->with('status', "Service \"{$service->name}\" updated.");
    }

    /**
     * Soft delete, so historical appointment lines keep a resolvable service.
     */
    public function destroy(Service $service): RedirectResponse
    {
        $name = $service->name;
        $service->delete();

        return redirect()
            ->route('admin.services.index')
            ->with('status', "Service \"{$name}\" deleted.");
    }

    /**
     * Flip a service between available and hidden from the list.
     *
     * A dedicated endpoint so the switch in the Available column is one request
     * with no optimistic UI: whatever the row shows after the redirect is the
     * truth.
     */
    public function toggleAvailability(Service $service): RedirectResponse
    {
        $service->update(['is_active' => ! $service->is_active]);

        return back()->with('status', $service->is_active
            ? "\"{$service->name}\" is now available."
            : "\"{$service->name}\" is now hidden.");
    }

    /* ------------------------------------------------------------------ */
    /* Variants */
    /* ------------------------------------------------------------------ */

    public function variants(Service $service): View
    {
        $service->load('variants');

        return view('admin.services.variants', [
            'service' => $service,
        ]);
    }

    public function storeVariant(Request $request, Service $service): RedirectResponse
    {
        $this->normaliseVariantPrice($request);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:100', Rule::unique('service_variants', 'name')->where('service_id', $service->id)],
            'price' => PriceFormatter::rules(),
            'duration_minutes' => ['nullable', 'integer', 'min:5', 'max:1440'],
            'is_default' => ['nullable', 'boolean'],
        ]);

        DB::transaction(function () use ($data, $service) {
            if ($data['is_default'] ?? false) {
                $service->variants()->update(['is_default' => false]);
            }

            $service->variants()->create([
                'name' => $data['name'],
                'price' => $data['price'],
                'duration_minutes' => $data['duration_minutes'] ?? null,
                'is_default' => $data['is_default'] ?? false,
            ]);
        });

        return back()->with('status', "Variant \"{$data['name']}\" added to {$service->name}.");
    }

    public function updateVariant(Request $request, ServiceVariant $variant): RedirectResponse
    {
        $this->normaliseVariantPrice($request);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:100', Rule::unique('service_variants', 'name')
                ->where('service_id', $variant->service_id)->ignore($variant->id)],
            'price' => PriceFormatter::rules(),
            'duration_minutes' => ['nullable', 'integer', 'min:5', 'max:1440'],
            'is_default' => ['nullable', 'boolean'],
        ]);

        DB::transaction(function () use ($data, $variant) {
            if ($data['is_default'] ?? false) {
                $variant->service->variants()->update(['is_default' => false]);
            }

            $variant->update([
                'name' => $data['name'],
                'price' => $data['price'],
                'duration_minutes' => $data['duration_minutes'] ?? null,
                'is_default' => $data['is_default'] ?? false,
            ]);
        });

        return back()->with('status', "Variant \"{$variant->name}\" updated.");
    }

    public function destroyVariant(ServiceVariant $variant): RedirectResponse
    {
        $name = $variant->name;
        $variant->delete();

        return back()->with('status', "Variant \"{$name}\" removed.");
    }

    /* ------------------------------------------------------------------ */
    /* Helpers */
    /* ------------------------------------------------------------------ */
}
