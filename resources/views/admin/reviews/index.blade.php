@extends('layouts.admin')

@section('title', 'Review Moderation')
@section('heading', 'Review Moderation')

@section('content')
    <x-ui.page-header
        eyebrow="Feedback"
        title="Review Moderation"
        description="Read every customer review and delete anything inappropriate. There is no create or edit here by design."
    />

    {{-- Rating distribution --}}
    <div class="mb-6 grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
        <div class="bta-card flex items-center gap-4 p-5">
            <span class="flex h-11 w-11 items-center justify-center rounded-full bg-gold/20 text-gold-dark">
                <svg class="h-5 w-5" viewBox="0 0 24 24" fill="currentColor"><path d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.286 3.958a1 1 0 0 0 .95.69h4.162c.969 0 1.371 1.24.588 1.81l-3.367 2.446a1 1 0 0 0-.364 1.118l1.286 3.958c.3.921-.755 1.688-1.539 1.118l-3.367-2.446a1 1 0 0 0-1.175 0l-3.367 2.446c-.783.57-1.838-.197-1.538-1.118l1.286-3.958a1 1 0 0 0-.364-1.118L2.062 9.385c-.783-.57-.38-1.81.588-1.81h4.163a1 1 0 0 0 .95-.69l1.286-3.958Z"/></svg>
            </span>
            <div>
                <p class="text-xs font-semibold uppercase tracking-wider text-ink-muted">Average</p>
                <p class="font-display text-2xl font-bold text-primary">{{ $averageRating }} / 5</p>
            </div>
        </div>

        <div class="bta-card flex items-center gap-4 p-5">
            <span class="flex h-11 w-11 items-center justify-center rounded-full bg-primary/10 text-primary">
                <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"><path d="M7.5 21h9m-9 0a1.5 1.5 0 0 1-1.5-1.5m1.5 1.5V21m-9-3.5h13.5A1.5 1.5 0 0 0 21 16V8.25a1.5 1.5 0 0 0-1.5-1.5H4.5A1.5 1.5 0 0 0 3 8.25V16a1.5 1.5 0 0 0 1.5 1.5Z"/></svg>
            </span>
            <div>
                <p class="text-xs font-semibold uppercase tracking-wider text-ink-muted">Total Reviews</p>
                <p class="font-display text-2xl font-bold text-primary">{{ $totalReviews }}</p>
            </div>
        </div>

        <div class="bta-card p-5 lg:col-span-2">
            <p class="mb-3 text-xs font-semibold uppercase tracking-wider text-ink-muted">Distribution</p>
            <div class="space-y-1.5">
                @for ($star = 5; $star >= 1; $star--)
                    @php
                        $count = $distribution[$star] ?? 0;
                        $pct = $totalReviews > 0 ? round($count / $totalReviews * 100) : 0;
                    @endphp
                    <div class="flex items-center gap-2.5">
                        <span class="w-10 shrink-0 text-xs text-ink-muted">{{ $star }} star{{ $star === 1 ? '' : 's' }}</span>
                        <div class="h-2 flex-1 overflow-hidden rounded-pill bg-linen">
                            <div class="h-full rounded-pill bg-gold" style="width: {{ $pct }}%"></div>
                        </div>
                        <span class="w-14 shrink-0 text-right text-xs text-ink-muted">{{ $count }} ({{ $pct }}%)</span>
                    </div>
                @endfor
            </div>
        </div>
    </div>

    @if ($lowRatedCount > 0)
        <x-ui.alert type="warning" class="mb-6">
            {{ $lowRatedCount }} review{{ $lowRatedCount === 1 ? '' : 's' }} rated 2 stars or below — worth reviewing.
        </x-ui.alert>
    @endif

    <form method="GET" action="{{ route('admin.reviews.index') }}" class="bta-card mb-6 p-5">
        <div class="grid gap-4 md:grid-cols-12">
            <div class="md:col-span-7">
                <x-ui.form.input name="search" label="Search" placeholder="Message, customer or service" :value="$filters['search'] ?? null" />
            </div>
            <div class="md:col-span-3">
                <x-ui.form.select
                    name="rating"
                    label="Rating"
                    :value="$filters['rating'] ?? ''"
                    :options="['' => 'All Ratings', '5' => '5 stars', '4' => '4 stars', '3' => '3 stars', '2' => '2 stars', '1' => '1 star']"
                />
            </div>
            <div class="flex items-end gap-2 md:col-span-2">
                <button type="submit" class="btn-primary flex-1">Filter</button>
                @if (array_filter($filters))
                    <a href="{{ route('admin.reviews.index') }}" class="btn-ghost">Clear</a>
                @endif
            </div>
        </div>
    </form>

    @if ($reviews->isEmpty())
        <x-ui.empty title="No reviews found" description="Customer reviews will appear here once appointments are completed." />
    @else
        <div class="space-y-4">
            @foreach ($reviews as $review)
                <article class="bta-card p-5">
                    <div class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
                        <div class="min-w-0 flex-1">
                            <div class="flex flex-wrap items-center gap-2.5">
                                <p class="font-semibold text-primary">{{ $review->customer_name }}</p>
                                <x-ui.star-rating :value="$review->rating" :interactive="false" size="sm" />
                                <span class="text-xs text-ink-muted">{{ $review->created_at->format('M j, Y g:i A') }}</span>
                            </div>

                            <p class="mt-2.5 text-sm leading-relaxed text-ink">{{ $review->message }}</p>

                            <div class="mt-3 flex flex-wrap items-center gap-3 text-xs text-ink-muted">
                                <span class="rounded-pill bg-linen px-2.5 py-1">{{ $review->service_name }}</span>
                                @if ($review->appointment)
                                    <a href="{{ route('admin.appointments.show', $review->appointment) }}" class="font-medium text-primary underline underline-offset-2">
                                        {{ $review->appointment->reference_number }}
                                    </a>
                                @endif
                                @if ($review->user)
                                    <a href="{{ route('admin.users.show', $review->user) }}" class="font-medium text-primary underline underline-offset-2">View customer</a>
                                @endif
                            </div>
                        </div>

                        @can('admin.reviews.manage')
                            <form method="POST" action="{{ route('admin.reviews.destroy', $review) }}" class="shrink-0"
                                  onsubmit="return confirm('Delete this review? The customer will not be able to re-submit it.')">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="btn-danger btn-sm">Delete</button>
                            </form>
                        @endcan
                    </div>
                </article>
            @endforeach
        </div>

        <div class="mt-6">{{ $reviews->links() }}</div>
    @endif
@endsection
