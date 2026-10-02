<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\TechnicianRequest;
use App\Models\Technician;
use App\Support\DataTable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;

/**
 * Technicians — the people a customer picks when booking.
 *
 * The list is the shared `x-ui.admin-table` card the catalogue screens use:
 * add button top-right inside the card, a live search box with the
 * entries-per-page select, a sortable table, and the pager below it. Rows are
 * rendered whole and filtered and paged in the browser, so only the sort
 * reaches the database.
 */
class TechnicianController extends Controller
{
    /** Columns the list lets an admin sort by. */
    private const SORTABLE = ['name', 'is_active', 'created_at'];

    public function index(Request $request): View
    {
        [$sort, $direction] = DataTable::sort($request, self::SORTABLE, 'name');

        $technicians = DataTable::applySort(
            Technician::query(),
            $sort,
            $direction,
            self::SORTABLE,
            'name',
        )
            ->orderBy('name')
            ->get();

        return view('admin.technicians.index', [
            'technicians' => $technicians,
            'search' => $request->input('search'),
            'sort' => $sort,
            'direction' => $direction,
        ]);
    }

    public function create(): View
    {
        return view('admin.technicians.create', [
            'technician' => new Technician,
        ]);
    }

    public function store(TechnicianRequest $request): RedirectResponse
    {
        $technician = Technician::create([
            'name' => $request->validated('name'),
            'photo_path' => $this->storePhoto($request),
            // Default to bookable when the field is absent altogether, so a
            // bare POST adds somebody to the booking form rather than adding
            // them and hiding them at the same time. The form's switch always
            // submits a value — a hidden 0 beside the checkbox — so this only
            // covers a request that never carried the field.
            'is_active' => $request->has('is_active') ? $request->boolean('is_active') : true,
        ]);

        return redirect()
            ->route('admin.technicians.index')
            ->with('status', "Technician \"{$technician->name}\" created.");
    }

    public function edit(Technician $technician): View
    {
        return view('admin.technicians.edit', [
            'technician' => $technician,
        ]);
    }

    public function update(TechnicianRequest $request, Technician $technician): RedirectResponse
    {
        $data = [
            'name' => $request->validated('name'),
            'is_active' => $request->boolean('is_active'),
        ];

        if ($photo = $this->storePhoto($request)) {
            $this->deletePhoto($technician);
            $data['photo_path'] = $photo;
        } elseif ($request->boolean('remove_photo')) {
            $this->deletePhoto($technician);
            $data['photo_path'] = null;
        }

        $technician->update($data);

        return redirect()
            ->route('admin.technicians.index')
            ->with('status', "Technician \"{$technician->name}\" updated.");
    }

    /**
     * Soft delete, so the appointments that name this technician keep a
     * resolvable row — the booking history is not something a delete in the
     * panel should be able to rewrite.
     */
    public function destroy(Technician $technician): RedirectResponse
    {
        $this->deletePhoto($technician);

        $name = $technician->name;
        $technician->delete();

        return redirect()
            ->route('admin.technicians.index')
            ->with('status', "Technician \"{$name}\" deleted.");
    }

    /**
     * Flip a technician on or off, for the switch in the Active column.
     *
     * A dedicated endpoint so flipping a row is one request with no optimistic
     * UI: whatever the table shows after the redirect is the truth. Switching
     * somebody off is how a technician "off today" is expressed — it takes them
     * out of the booking picker without touching the bookings they already have.
     */
    public function toggleActive(Technician $technician): RedirectResponse
    {
        $technician->update(['is_active' => ! $technician->is_active]);

        return back()->with('status', $technician->is_active
            ? "\"{$technician->name}\" is now bookable."
            : "\"{$technician->name}\" is no longer offered for new bookings.");
    }

    /* ------------------------------------------------------------------ */
    /* Photos                                                              */
    /* ------------------------------------------------------------------ */

    /**
     * Store an uploaded photo on the public disk, or null when there is none.
     */
    protected function storePhoto(TechnicianRequest $request): ?string
    {
        return $request->hasFile('photo')
            ? $request->file('photo')->store('technician-photos', 'public')
            : null;
    }

    /**
     * Remove a technician's photo file. Safe to call when there is none.
     */
    protected function deletePhoto(Technician $technician): void
    {
        if ($technician->photo_path) {
            Storage::disk('public')->delete($technician->photo_path);
        }
    }
}
