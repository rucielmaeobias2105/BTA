<?php

namespace App\Http\Controllers\Admin;

use App\Enums\TermsCategory;
use App\Http\Controllers\Controller;
use App\Models\TermsAndCondition;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * Admin Flow 10 — Terms & Conditions Editor.
 * Content is versioned per category; publishing controls what customers see.
 */
class TermsController extends Controller
{
    public function index(): View
    {
        $terms = TermsAndCondition::query()
            ->with('admin')
            ->orderBy('category')
            ->orderByDesc('version')
            ->get()
            ->groupBy(fn (TermsAndCondition $term) => $term->category->value);

        return view('admin.terms.index', [
            'grouped' => $terms,
            'categories' => TermsCategory::options(),
            'published' => collect(TermsCategory::cases())
                ->mapWithKeys(fn (TermsCategory $c) => [$c->value => TermsAndCondition::publishedFor($c)])
                ->all(),
        ]);
    }

    public function create(Request $request): View
    {
        $category = $request->query('category', TermsCategory::Booking->value);

        return view('admin.terms.create', [
            'category' => TermsCategory::tryFrom($category) ?? TermsCategory::Booking,
            'categories' => TermsCategory::options(),
        ]);
    }

    /**
     * Every save creates a new version rather than overwriting the old one.
     */
    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'category' => ['required', Rule::in(TermsCategory::values())],
            'content' => ['required', 'string', 'min:10'],
            'is_published' => ['nullable', 'boolean'],
        ]);

        $category = TermsCategory::from($data['category']);

        $existing = TermsAndCondition::publishedFor($category);

        if ($existing && $existing->content === $data['content']) {
            return back()->withInput()->withErrors([
                'content' => 'This is identical to the current published version.',
            ]);
        }

        $term = DB::transaction(function () use ($data, $category, $request) {
            $publish = $request->boolean('is_published');

            if ($publish) {
                TermsAndCondition::where('category', $category->value)->update(['is_published' => false]);
            }

            return TermsAndCondition::create([
                'category' => $category,
                'version' => TermsAndCondition::nextVersionFor($category),
                'content' => $data['content'],
                'is_published' => $publish,
                'published_at' => $publish ? now() : null,
                'created_by' => $request->user('admin')->id,
            ]);
        });

        return redirect()
            ->route('admin.terms.index')
            ->with('status', "{$category->label()} terms saved as version {$term->version}.");
    }

    public function edit(TermsAndCondition $term): View
    {
        return view('admin.terms.edit', [
            'term' => $term,
            'categories' => TermsCategory::options(),
        ]);
    }

    public function update(Request $request, TermsAndCondition $term): View|RedirectResponse
    {
        $data = $request->validate([
            'category' => ['required', Rule::in(TermsCategory::values())],
            'content' => ['required', 'string', 'min:10'],
            'is_published' => ['nullable', 'boolean'],
        ]);

        $category = TermsCategory::from($data['category']);
        $publish = $request->boolean('is_published');

        if ($term->is_published && ! $publish) {
            return back()->withInput()->withErrors([
                'is_published' => 'Unpublish directly from the list — published versions are immutable records.',
            ]);
        }

        if (! $term->is_published && $term->content !== $data['content']) {
            // Editing a draft mutates in place; published content is versioned.
            $term->update([
                'category' => $category,
                'content' => $data['content'],
            ]);

            return back()->with('status', "Draft updated (version {$term->version}).");
        }

        if ($term->is_published) {
            return $this->store($request);
        }

        return back()->with('status', 'Nothing to update.');
    }

    /**
     * Publish a specific version and retire the previous one.
     */
    public function publish(TermsAndCondition $term): RedirectResponse
    {
        DB::transaction(function () use ($term) {
            TermsAndCondition::where('category', $term->category->value)
                ->whereKeyNot($term->id)
                ->update(['is_published' => false]);

            $term->update(['is_published' => true, 'published_at' => now()]);
        });

        return back()->with('status', "Version {$term->version} is now live for customers.");
    }

    public function destroy(TermsAndCondition $term): RedirectResponse
    {
        if ($term->is_published) {
            return back()->withErrors([
                'term' => 'Unpublish this version before deleting it.',
            ]);
        }

        $version = $term->version;
        $term->delete();

        return back()->with('status', "Draft version {$version} deleted.");
    }
}
