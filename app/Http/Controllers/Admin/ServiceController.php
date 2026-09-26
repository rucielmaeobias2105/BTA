<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\ServiceRequest;
use App\Models\Service;
use App\Models\ServiceVariant;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * Admin Flow 5 — Service Management (Add / Edit / Delete) + Variants.
 */
class ServiceController extends Controller
{
    public function index(Request $request): View
    {
        $services = Service::query()
            ->with('variants')
            ->withCount(['inventoryItems', 'appointments'])
            ->search($request->input('search'))
            ->category($request->input('category'))
            ->orderBy('category')
            ->orderBy('name')
            ->paginate(15)
            ->withQueryString();

        return view('admin.services.index', [
            'services' => $services,
            'categories' => Service::query()->distinct()->orderBy('category')->pluck('category'),
            'filters' => $request->only(['search', 'category']),
        ]);
    }

    public function create(): View
    {
        return view('admin.services.create', [
            'service' => new Service,
            'categories' => $this->categorySuggestions(),
        ]);
    }

    public function store(ServiceRequest $request): RedirectResponse
    {
        $service = DB::transaction(function () use ($request) {
            $data = $request->safe()->except(['photo', 'variants']);

            $data['is_active'] = $request->boolean('is_active');
            $data['is_featured'] = $request->boolean('is_featured');

            if ($request->hasFile('photo')) {
                $data['photo_path'] = $request->file('photo')->store('service-photos', 'public');
            }

            $service = Service::create($data);

            $this->syncVariants($service, $request->input('variants', []));

            return $service;
        });

        return redirect()
            ->route('admin.services.index')
            ->with('status', "Service \"{$service->name}\" created.");
    }

    public function edit(Service $service): View
    {
        $service->load('variants');

        return view('admin.services.edit', [
            'service' => $service,
            'categories' => $this->categorySuggestions(),
        ]);
    }

    public function update(ServiceRequest $request, Service $service): RedirectResponse
    {
        DB::transaction(function () use ($request, $service) {
            $data = $request->safe()->except(['photo', 'variants']);

            $data['is_active'] = $request->boolean('is_active');
            $data['is_featured'] = $request->boolean('is_featured');

            if ($request->hasFile('photo')) {
                if ($service->photo_path) {
                    Storage::disk('public')->delete($service->photo_path);
                }

                $data['photo_path'] = $request->file('photo')->store('service-photos', 'public');
            }

            $service->update($data);

            $this->syncVariants($service, $request->input('variants', []));
        });

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

    /* ------------------------------------------------------------------ */
    /* Variants                                                           */
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
        $data = $request->validate([
            'name' => ['required', 'string', 'max:100', Rule::unique('service_variants', 'name')->where('service_id', $service->id)],
            'price' => ['required', 'numeric', 'min:0', 'max:999999'],
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
        $data = $request->validate([
            'name' => ['required', 'string', 'max:100', Rule::unique('service_variants', 'name')
                ->where('service_id', $variant->service_id)->ignore($variant->id)],
            'price' => ['required', 'numeric', 'min:0', 'max:999999'],
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
    /* Helpers                                                            */
    /* ------------------------------------------------------------------ */

    /**
     * Create/update/remove the variant rows submitted with the service form.
     *
     * @param  array<int, array<string, mixed>>  $variants
     */
    protected function syncVariants(Service $service, array $variants): void
    {
        $keepIds = [];

        foreach ($variants as $row) {
            if (blank($row['name'] ?? null)) {
                continue;
            }

            $attributes = [
                'name' => $row['name'],
                'price' => $row['price'],
                // Blank inputs are simply absent from the payload.
                'duration_minutes' => ($row['duration_minutes'] ?? null) ?: null,
                'is_default' => (bool) ($row['is_default'] ?? false),
            ];

            if (! empty($row['id'])) {
                $variant = $service->variants()->whereKey($row['id'])->first();

                if ($variant) {
                    if ($attributes['is_default']) {
                        $service->variants()->whereKeyNot($variant->id)->update(['is_default' => false]);
                    }

                    $variant->update($attributes);
                    $keepIds[] = $variant->id;

                    continue;
                }
            }

            if ($attributes['is_default']) {
                $service->variants()->update(['is_default' => false]);
            }

            $keepIds[] = $service->variants()->create($attributes)->id;
        }

        // Variants omitted from the form are removed.
        $service->variants()->whereNotIn('id', $keepIds ?: [0])->delete();
    }

    /**
     * @return array<int, string>
     */
    protected function categorySuggestions(): array
    {
        return array_values(array_unique(array_merge(
            Service::query()->distinct()->pluck('category')->all(),
            [
                'Hair Care', 'Hair Styling', 'Nail Care', 'Lash & Brow', 'Skincare',
                'Massage & Spa', 'Facial', 'Waxing & Threading', 'Makeup', 'Packages',
            ],
        )));
    }
}
