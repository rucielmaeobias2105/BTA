<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ServiceCategory;
use App\Support\DataTable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * Admin Flow 5b — Service Categories.
 *
 * The list is Name / Active / Actions: the colour a category once carried as an
 * editable field is still on the row, because the admin calendar legend and its
 * chips read it, but it is no longer something an admin picks by hand. New rows
 * take the next unused salon palette colour on the way in, which is what the
 * palette helpers below are for.
 *
 * The form also takes a photo, which is the picture the Services page shows
 * beside the category's price list. It is optional and it is not the only source
 * of that picture — see `ServiceCategory::imagePath()`, whose fallback chain
 * keeps every category that has never had one rendering exactly as it did.
 */
class ServiceCategoryController extends Controller
{
    /** Columns the list lets an admin sort by. */
    private const SORTABLE = ['name', 'is_active', 'sort_order', 'created_at'];

    public function index(Request $request): View
    {
        [$sort, $direction] = DataTable::sort($request, self::SORTABLE, 'sort_order');

        // Rendered whole and filtered and paged in the browser, so a search is
        // not a round trip per keystroke. `search` only seeds the search box.
        $categories = DataTable::applySort(
            ServiceCategory::query()
                ->withCount(['services' => fn ($q) => $q->whereNull('deleted_at')]),
            $sort,
            $direction,
            self::SORTABLE,
            'sort_order',
        )
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();

        return view('admin.categories.index', [
            'categories' => $categories,
            'sort' => $sort,
            'direction' => $direction,
            'search' => $request->input('search'),
        ]);
    }

    public function create(): View
    {
        return view('admin.categories.create', [
            'category' => new ServiceCategory,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validated($request);

        $data['is_active'] = $request->boolean('is_active');
        $data['sort_order'] = (int) ServiceCategory::max('sort_order') + 1;
        $data['color'] = $this->nextUnusedColor();
        $data['photo'] = $this->storePhoto($request);

        ServiceCategory::create($data);

        return redirect()
            ->route('admin.categories.index')
            ->with('toast', [
                'type' => 'success',
                'message' => 'Category added.',
            ]);
    }

    public function edit(ServiceCategory $category): View
    {
        return view('admin.categories.edit', [
            'category' => $category,
        ]);
    }

    public function update(Request $request, ServiceCategory $category): RedirectResponse
    {
        $data = $this->validated($request, $category);

        $data['is_active'] = $request->boolean('is_active');

        /*
         * `photo` is touched only when this request actually says something
         * about it: a new file replaces the old one, and `remove_photo` clears
         * it. An edit that submits neither leaves the picture alone, which is the
         * only way "rename this category" does not silently empty its photo.
         */
        if ($photo = $this->storePhoto($request)) {
            $this->deletePhoto($category);
            $data['photo'] = $photo;
        } elseif ($request->boolean('remove_photo')) {
            $this->deletePhoto($category);
            $data['photo'] = null;
        }

        // `color` is not in `$data`: the admin no longer picks one, and an
        // existing row keeps the colour the calendar is already painting it
        // with rather than being handed a new hue by an unrelated edit.
        $category->update($data);

        return redirect()
            ->route('admin.categories.index')
            ->with('toast', [
                'type' => 'success',
                'message' => 'Category updated.',
            ]);
    }

    public function destroy(ServiceCategory $category): RedirectResponse
    {
        // The file goes with the row. A category that is deleted and recreated
        // should not inherit the picture of the one that was deleted, and a
        // photo nobody references is a photo somebody has to find later.
        $this->deletePhoto($category);

        // `services.service_category_id` is nullOnDelete, so the services
        // themselves survive; they fall back to the palette lookup by name.
        $category->delete();

        return redirect()
            ->route('admin.categories.index')
            ->with('toast', [
                'type' => 'success',
                'message' => 'Category deleted. Its services keep their names.',
            ]);
    }

    /**
     * Flip a category between active and inactive from the list.
     *
     * A separate endpoint rather than a checkbox post so the switch in the table
     * is a single request with no optimistic UI: whatever the row says after the
     * redirect is the truth.
     */
    public function toggleActive(ServiceCategory $category): RedirectResponse
    {
        $category->update(['is_active' => ! $category->is_active]);

        return back()->with('toast', [
            'type' => 'success',
            'message' => $category->is_active
                ? "{$category->name} is now active."
                : "{$category->name} is now inactive.",
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    protected function validated(Request $request, ?ServiceCategory $category = null): array
    {
        $data = $request->validate([
            'name' => [
                'required',
                'string',
                'max:120',
                // Same name is fine when editing the same row, but two
                // categories sharing a name would make a block's scope
                // ambiguous.
                Rule::unique('service_categories', 'name')->ignore($category?->id),
            ],

            /*
             * JPG/PNG/WEBP, matching every other photo field in the panel. The
             * 4 MB ceiling is larger than the technician photo's 2 MB — a headshot
             * is shown in a 7rem circle, while this one fills a panel down the
             * side of the Services page — and smaller than the 10 MB the customer
             * profile photo allows, because an admin uploading this is picking a
             * picture for a salon, not exporting one from a camera roll.
             *
             * `remove_photo` is only meaningful when the file input carries no
             * replacement, and it is read as a boolean rather than validated as
             * one: a checkbox that is not ticked sends nothing at all.
             */
            'photo' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:4096'],
            'remove_photo' => ['nullable', 'boolean'],
        ], [
            'photo.image' => 'The category photo must be an image.',
            'photo.mimes' => 'The category photo must be a JPG, PNG or WEBP file.',
            'photo.max' => 'The category photo may not be larger than 4 MB.',
        ]);

        $data['name'] = trim($data['name']);

        // A file input is never a validatable value: it must not reach `update()`
        // as a string, where it would be written straight into the column.
        unset($data['photo'], $data['remove_photo']);

        return $data;
    }

    /** The uploaded photo's path on the public disk, or null if none arrived. */
    protected function storePhoto(Request $request): ?string
    {
        return $request->hasFile('photo')
            ? $request->file('photo')->store('categories', 'public')
            : null;
    }

    protected function deletePhoto(ServiceCategory $category): void
    {
        if ($category->photo) {
            Storage::disk('public')->delete($category->photo);
        }
    }

    protected function nextUnusedColor(): string
    {
        return ServiceCategory::nextColorFromPalette(
            ServiceCategory::query()->pluck('color')->all()
        );
    }
}
