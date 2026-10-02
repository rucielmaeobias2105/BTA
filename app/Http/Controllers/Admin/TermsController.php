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
 *
 * One row per category. A save overwrites that row in place; publishing is what
 * decides whether customers see the text.
 */
class TermsController extends Controller
{
    public function index(): View
    {
        $terms = TermsAndCondition::query()
            ->with('admin')
            ->orderBy('category')
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
        $resolved = TermsCategory::tryFrom($request->query('category')) ?? TermsCategory::Booking;

        return view('admin.terms.create', [
            // The shared form partial always operates on a model. A category that
            // already has a row edits that row rather than pre-filling an empty
            // one, so "New" for a policy that exists lands the admin on the text
            // that is actually live instead of a blank box that would wipe it.
            'term' => TermsAndCondition::forCategory($resolved) ?? new TermsAndCondition(['category' => $resolved]),
            'category' => $resolved,
            'categories' => TermsCategory::options(),
        ]);
    }

    /**
     * A save overwrites the category's row rather than appending a new one.
     */
    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'category' => ['required', Rule::in(TermsCategory::values())],
            'content' => ['required', 'string', 'min:10'],
            'is_published' => ['nullable', 'boolean'],
        ]);

        $category = TermsCategory::from($data['category']);
        $publish = $request->boolean('is_published');
        $existing = TermsAndCondition::forCategory($category);

        if ($existing && $existing->content === $data['content'] && $existing->is_published === $publish) {
            return back()->withInput()->withErrors([
                'content' => 'This is identical to the current saved text.',
            ]);
        }

        $term = DB::transaction(function () use ($data, $category, $publish, $request, $existing) {
            // A row that was published keeps its original `published_at`, so the
            // date the customer-facing text last became live is not silently
            // rewritten every time an admin saves an unrelated tweak.
            $attributes = [
                'category' => $category,
                'content' => $data['content'],
                'is_published' => $publish,
                'published_at' => $publish ? ($existing?->published_at ?? now()) : null,
                'created_by' => $existing?->created_by ?? $request->user('admin')->id,
            ];

            if ($existing) {
                $existing->update($attributes);

                return $existing;
            }

            return TermsAndCondition::create($attributes);
        });

        return redirect()
            ->route('admin.terms.index')
            ->with('status', "{$category->label()} terms saved.");
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
            /*
             * A live policy cannot be quietly demoted from the edit form.
             *
             * This used to read "Unpublish directly from the list", and it sent
             * the admin to a screen that no longer exists, on the one path where
             * the checkbox had just been unticked. The checkbox is the publish
             * control, so the message now says what to do with it instead.
             */
            return back()->withInput()->withErrors([
                'is_published' => 'This policy is live. Leave "Publish this policy" ticked and save to keep it live for customers.',
            ]);
        }

        if ($term->is_published && $term->category !== $category) {
            /*
             * Moving a live policy to another category would unpublish one
             * category and publish another in a single save — the same surprise
             * as demoting it — so it is refused rather than half-done.
             */
            return back()->withInput()->withErrors([
                'category' => 'This policy is live and cannot be moved to a different category.',
            ]);
        }

        if ($term->category !== $category && TermsAndCondition::forCategory($category)) {
            // A category owns exactly one row, so a draft can only move into one
            // that does not already have a row.
            return back()->withInput()->withErrors([
                'category' => 'That category already has terms. Delete them before moving this policy across.',
            ]);
        }

        $term->update([
            'category' => $category,
            'content' => $data['content'],
            'is_published' => $publish,
            'published_at' => $publish ? ($term->published_at ?? now()) : null,
        ]);

        return back()->with('status', "{$category->label()} terms saved.");
    }

    public function publish(TermsAndCondition $term): RedirectResponse
    {
        DB::transaction(function () use ($term) {
            $term->update(['is_published' => true, 'published_at' => now()]);
        });

        return back()->with('status', "{$term->category->label()} terms are now live for customers.");
    }

    public function destroy(TermsAndCondition $term): RedirectResponse
    {
        if ($term->is_published) {
            return back()->withErrors([
                'term' => 'Unpublish this policy before deleting it.',
            ]);
        }

        $term->delete();

        return back()->with('status', "{$term->category->label()} terms deleted.");
    }
}
