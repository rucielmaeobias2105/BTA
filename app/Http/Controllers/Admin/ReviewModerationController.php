<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Review;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Admin Flow 11 — Review / Ratings Moderation.
 * View + delete only; there is deliberately no create or edit.
 */
class ReviewModerationController extends Controller
{
    public function index(Request $request): View
    {
        $rating = $request->input('rating');
        $term = $request->input('search');

        $reviews = Review::query()
            ->with(['service', 'user', 'appointment'])
            ->when($rating !== null && $rating !== '' && in_array((int) $rating, [1, 2, 3, 4, 5], true), fn ($q) => $q->where('rating', (int) $rating))
            ->when($term, function ($q, $term) {
                $like = '%'.str_replace('%', '\%', $term).'%';

                $q->where(fn ($inner) => $inner
                    ->where('message', 'like', $like)
                    ->orWhere('customer_name', 'like', $like)
                    ->orWhereHas('service', fn ($s) => $s->where('name', 'like', $like)));
            })
            ->latest()
            ->paginate(15)
            ->withQueryString();

        return view('admin.reviews.index', [
            'reviews' => $reviews,
            'filters' => ['search' => $term, 'rating' => $rating],
            'averageRating' => round((float) Review::query()->avg('rating'), 2),
            'totalReviews' => Review::query()->count(),
            'distribution' => Review::query()
                ->selectRaw('rating, COUNT(*) as total')
                ->groupBy('rating')
                ->pluck('total', 'rating')
                ->map(fn ($v) => (int) $v)
                ->all(),
            'lowRatedCount' => Review::query()->where('rating', '<=', 2)->count(),
        ]);
    }

    public function destroy(Review $review): RedirectResponse
    {
        $review->delete();

        return back()->with('status', 'Review removed.');
    }
}
