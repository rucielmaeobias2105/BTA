<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Promo;
use App\Models\User;
use App\Notifications\PromoAnnouncementNotification;
use App\Support\DataTable;
use App\Support\PromoBanner;
use App\Support\SendsNotificationsQuietly;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * Admin Flow 13 — Promo / Announcements Management.
 * Active promos surface to customers as a footer banner and a notification.
 */
class PromoController extends Controller
{
    use SendsNotificationsQuietly;

    /** Columns the list lets an admin sort by. */
    private const SORTABLE = ['title', 'starts_at', 'ends_at', 'is_active', 'created_at'];

    public function index(Request $request): View
    {
        [$sort, $direction] = DataTable::sort($request, self::SORTABLE, 'starts_at');

        // Rendered whole and filtered and paged in the browser, so a search is
        // not a round trip per keystroke. `search` only seeds the search box.
        $promos = DataTable::applySort(
            Promo::query(),
            $sort,
            $direction,
            self::SORTABLE,
            'starts_at',
        )
            ->orderByDesc('starts_at')
            ->get();

        return view('admin.promos.index', [
            'promos' => $promos,
            'search' => $request->input('search'),
            'sort' => $sort,
            'direction' => $direction,
        ]);
    }

    public function create(): View
    {
        return view('admin.promos.create', [
            'promo' => new Promo,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validatePromo($request);

        $promo = Promo::create([
            ...$data,
            'image_path' => $this->storeImage($request),
            'is_active' => $request->boolean('is_active'),
        ]);

        PromoBanner::flush();

        return redirect()
            ->route('admin.promos.index')
            ->with('status', "Promo \"{$promo->title}\" created.");
    }

    public function edit(Promo $promo): View
    {
        return view('admin.promos.edit', [
            'promo' => $promo,
        ]);
    }

    public function update(Request $request, Promo $promo): RedirectResponse
    {
        $data = $this->validatePromo($request, $promo);

        /*
         * `image_path` is touched only when this request actually says something
         * about the picture: a new file replaces the old one, and `remove_image`
         * clears it. Without that condition, "rename this promo" would silently
         * empty its picture, because an absent file input is not the same thing
         * as an empty one and the admin did not ask for either.
         */
        if ($image = $this->storeImage($request)) {
            $this->deleteImage($promo);
            $data['image_path'] = $image;
        } elseif ($request->boolean('remove_image')) {
            $this->deleteImage($promo);
            $data['image_path'] = null;
        }

        $promo->update([
            ...$data,
            'is_active' => $request->boolean('is_active'),
        ]);

        PromoBanner::flush();

        return redirect()
            ->route('admin.promos.index')
            ->with('status', "Promo \"{$promo->title}\" updated.");
    }

    public function destroy(Promo $promo): RedirectResponse
    {
        $title = $promo->title;

        // Delete the file before the row. The row is soft-deleted, so it can be
        // restored — and a restored promo whose file has been deleted from disk
        // would show a broken picture. Either way the upload is unreferenced now.
        $this->deleteImage($promo);

        $promo->delete();

        PromoBanner::flush();

        return redirect()
            ->route('admin.promos.index')
            ->with('status', "Promo \"{$title}\" deleted.");
    }

    /**
     * Push the promo to customers as a notification.
     */
    public function announce(Request $request, Promo $promo): RedirectResponse
    {
        $data = $request->validate([
            'audience' => ['required', Rule::in(['all', 'active', 'recent'])],
        ]);

        $users = match ($data['audience']) {
            'active' => User::query()->where('is_active', true)->get(),
            'recent' => User::query()
                ->where('is_active', true)
                ->where('created_at', '>=', now()->subDays(90))
                ->get(),
            default => User::query()->where('is_active', true)->get(),
        };

        foreach ($users as $user) {
            $this->notifyQuietly($user, new PromoAnnouncementNotification($promo), 'promo announcement');
        }

        $promo->forceFill(['notified' => true])->save();

        return back()->with('status', "Promo announced to {$users->count()} customer(s).");
    }

    /**
     * @return array<string, mixed>
     */
    protected function validatePromo(Request $request, ?Promo $promo = null): array
    {
        $data = $request->validate([
            'title' => ['required', 'string', 'max:180'],
            'description' => ['required', 'string', 'max:3000'],
            'starts_at' => ['required', 'date'],
            'ends_at' => ['required', 'date', 'after_or_equal:starts_at'],
            'is_active' => ['nullable', 'boolean'],

            // Optional: a promo with no picture is a perfectly good promo, and
            // the customer card falls back to a flourish. 4 MB and JPG/PNG/WEBP
            // match every other photo field in the panel, so an admin who has
            // already uploaded a service photo is not surprised here.
            'image' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:4096'],
            'remove_image' => ['nullable', 'boolean'],
        ], [
            'ends_at.after_or_equal' => 'The end date cannot be before the start date.',
            'image.image' => 'The promo image must be an image.',
            'image.mimes' => 'The promo image must be a JPG, PNG or WEBP file.',
            'image.max' => 'The promo image may not be larger than 4 MB.',
        ]);

        // Neither is a column: the file is moved to disk by `storeImage()` and
        // `remove_image` is an instruction, not data.
        unset($data['image'], $data['remove_image']);

        return $data;
    }

    /** The uploaded image's path on the public disk, or null if none arrived. */
    protected function storeImage(Request $request): ?string
    {
        return $request->hasFile('image')
            ? $request->file('image')->store(Promo::IMAGE_DIRECTORY, 'public')
            : null;
    }

    protected function deleteImage(Promo $promo): void
    {
        if ($path = $promo->imagePath()) {
            Storage::disk('public')->delete($path);
        }
    }
}
