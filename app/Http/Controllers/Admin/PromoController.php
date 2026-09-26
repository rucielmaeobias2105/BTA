<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Promo;
use App\Models\User;
use App\Notifications\PromoAnnouncementNotification;
use App\Support\PromoBanner;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * Admin Flow 13 — Promo / Announcements Management.
 * Active promos surface to customers as a footer banner and a notification.
 */
class PromoController extends Controller
{
    public function index(Request $request): View
    {
        $promos = Promo::query()
            ->when($request->input('filter') === 'active', fn ($q) => $q->active())
            ->when($request->input('filter') === 'expired', fn ($q) => $q->whereDate('ends_at', '<', today()))
            ->when($request->input('search'), function ($q, $term) {
                $like = '%'.str_replace('%', '\%', $term).'%';
                $q->where(fn ($inner) => $inner->where('title', 'like', $like)->orWhere('description', 'like', $like));
            })
            ->orderByDesc('starts_at')
            ->paginate(12)
            ->withQueryString();

        return view('admin.promos.index', [
            'promos' => $promos,
            'filters' => $request->only(['search', 'filter']),
            'activeCount' => Promo::query()->active()->count(),
            'audienceSize' => User::query()->where('is_active', true)->count(),
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

        $promo = DB::transaction(function () use ($data, $request) {
            if ($request->hasFile('image')) {
                $data['image_path'] = $request->file('image')->store('promo-images', 'public');
            }

            $data['is_active'] = $request->boolean('is_active');

            return Promo::create($data);
        });

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

        if ($request->hasFile('image')) {
            if ($promo->image_path) {
                Storage::disk('public')->delete($promo->image_path);
            }

            $data['image_path'] = $request->file('image')->store('promo-images', 'public');
        }

        $data['is_active'] = $request->boolean('is_active');
        $promo->update($data);

        PromoBanner::flush();

        return redirect()
            ->route('admin.promos.index')
            ->with('status', "Promo \"{$promo->title}\" updated.");
    }

    public function destroy(Promo $promo): RedirectResponse
    {
        $title = $promo->title;
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
            $user->notify(new PromoAnnouncementNotification($promo));
        }

        $promo->forceFill(['notified' => true])->save();

        return back()->with('status', "Promo announced to {$users->count()} customer(s).");
    }

    /**
     * @return array<string, mixed>
     */
    protected function validatePromo(Request $request, ?Promo $promo = null): array
    {
        return $request->validate([
            'title' => ['required', 'string', 'max:180'],
            'description' => ['required', 'string', 'max:3000'],
            'starts_at' => ['required', 'date'],
            'ends_at' => ['required', 'date', 'after_or_equal:starts_at'],
            'image' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:3072'],
            'is_active' => ['nullable', 'boolean'],
        ], [
            'ends_at.after_or_equal' => 'The end date cannot be before the start date.',
            'image.max' => 'The promo image may not be larger than 3 MB.',
        ]);
    }
}
