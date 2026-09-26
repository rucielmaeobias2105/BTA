@extends('layouts.admin')

@section('title', 'Promo & Announcements')
@section('heading', 'Promo & Announcements')

@section('content')
    <x-ui.page-header
        eyebrow="Marketing"
        title="Promo & Announcements"
        description="Active promos appear as a banner on the site and can be pushed to customers as notifications."
    >
        <x-slot:actions>
            <a href="{{ route('admin.promos.create') }}" class="btn-primary btn-sm">
                <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="M12 4.5v15m7.5-7.5h-15"/></svg>
                New Promo
            </a>
        </x-slot:actions>
    </x-ui.page-header>

    <x-ui.alert type="error" class="mb-6" :dismissible="false">
        @foreach ($errors->all() as $error)
            <p>{{ $error }}</p>
        @endforeach
    </x-ui.alert>

    <div class="mb-5 grid gap-4 sm:grid-cols-3">
        <div class="bta-card p-4">
            <p class="text-xs font-semibold uppercase tracking-wider text-ink-muted">Active Now</p>
            <p class="mt-1 font-display text-2xl font-bold text-status-confirmed">{{ $activeCount }}</p>
        </div>
        <div class="bta-card p-4">
            <p class="text-xs font-semibold uppercase tracking-wider text-ink-muted">Reachable Customers</p>
            <p class="mt-1 font-display text-2xl font-bold text-primary">{{ $audienceSize }}</p>
        </div>
        <div class="bta-card p-4">
            <p class="text-xs font-semibold uppercase tracking-wider text-ink-muted">Total Promos</p>
            <p class="mt-1 font-display text-2xl font-bold text-primary">{{ $promos->total() }}</p>
        </div>
    </div>

    <form method="GET" action="{{ route('admin.promos.index') }}" class="bta-card mb-6 p-5">
        <div class="grid gap-4 md:grid-cols-12">
            <div class="md:col-span-7">
                <x-ui.form.input name="search" label="Search" placeholder="Title or description" :value="$filters['search'] ?? null" />
            </div>
            <div class="md:col-span-3">
                <x-ui.form.select
                    name="filter"
                    label="Filter"
                    :value="$filters['filter'] ?? ''"
                    :options="['' => 'All Promos', 'active' => 'Currently active', 'expired' => 'Expired']"
                />
            </div>
            <div class="flex items-end gap-2 md:col-span-2">
                <button type="submit" class="btn-primary flex-1">Search</button>
                @if (array_filter($filters))
                    <a href="{{ route('admin.promos.index') }}" class="btn-ghost">Clear</a>
                @endif
            </div>
        </div>
    </form>

    @if ($promos->isEmpty())
        <x-ui.empty title="No promos yet" description="Create a promo to announce an offer to your customers.">
            <x-slot:action>
                <a href="{{ route('admin.promos.create') }}" class="btn-primary">New Promo</a>
            </x-slot:action>
        </x-ui.empty>
    @else
        <div class="grid gap-5 sm:grid-cols-2 xl:grid-cols-3">
            @foreach ($promos as $promo)
                @php
                    $isActive = $promo->isCurrentlyValid();
                    $isUpcoming = ! $promo->is_active || $promo->starts_at->isFuture();
                @endphp

                <article @class([
                    'flex flex-col overflow-hidden rounded-card border bg-cream shadow-card',
                    'border-gold/40' => $isActive,
                    'border-primary/12' => ! $isActive,
                ])>
                    @if ($promo->image_url)
                        <img src="{{ $promo->image_url }}" alt="{{ $promo->title }}" class="aspect-[16/9] w-full object-cover">
                    @else
                        <div class="flex aspect-[16/9] w-full items-center justify-center bg-gradient-to-br from-linen to-gold-light/40">
                            <svg class="h-10 w-10 text-gold-dark/50" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 11.25v8.25a1.5 1.5 0 0 1-1.5 1.5H5.25a1.5 1.5 0 0 1-1.5-1.5V11.25m17.25 0a1.5 1.5 0 0 1-1.5 1.5H5.25a1.5 1.5 0 0 1-1.5-1.5m17.25 0V4.5A1.5 1.5 0 0 0 18.75 3H5.25A1.5 1.5 0 0 0 3.75 4.5v6.75m14.25-6.75H12m6.75 0H12m0 0H5.25M12 3v1.5"/></svg>
                        </div>
                    @endif

                    <div class="flex flex-1 flex-col p-4">
                        <div class="flex flex-wrap items-center gap-1.5">
                            @if ($isActive)
                                <x-ui.badge status="completed" label="Active" />
                            @elseif ($isUpcoming)
                                <x-ui.badge status="pending" label="Upcoming" />
                            @else
                                <x-ui.badge status="cancelled" label="Expired" />
                            @endif

                            @if (! $promo->is_active)
                                <x-ui.badge status="cancelled" label="Disabled" />
                            @endif

                            @if ($promo->notified)
                                <x-ui.badge status="gold" label="Announced" />
                            @endif
                        </div>

                        <h3 class="mt-2.5 font-display text-base font-semibold leading-snug text-primary">{{ $promo->title }}</h3>
                        <p class="mt-1.5 line-clamp-3 flex-1 text-sm text-ink-muted">{{ \Illuminate\Support\Str::limit(strip_tags($promo->description), 130) }}</p>
                        <p class="mt-3 text-xs font-medium text-gold-dark">{{ $promo->validity_label }}</p>

                        <div class="mt-4 flex flex-wrap gap-1.5 border-t border-primary/10 pt-3">
                            <a href="{{ route('admin.promos.edit', $promo) }}" class="btn-secondary btn-sm">Edit</a>

                            <form method="POST" action="{{ route('admin.promos.announce', $promo) }}"
                                  onsubmit="return confirm('Send this promo as a notification to your customers?')">
                                @csrf
                                <select name="audience" class="input w-28 py-1.5 text-xs" onchange="this.form.submit()" aria-label="Announce audience">
                                    <option value="all">All ({{ $audienceSize }})</option>
                                    <option value="active">Active only</option>
                                    <option value="recent">Last 90 days</option>
                                </select>
                                <noscript><button type="submit" class="btn-gold btn-sm">Announce</button></noscript>
                            </form>

                            <form method="POST" action="{{ route('admin.promos.destroy', $promo) }}"
                                  onsubmit="return confirm('Delete “{{ $promo->title }}”?')">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="btn-danger btn-sm">Delete</button>
                            </form>
                        </div>
                    </div>
                </article>
            @endforeach
        </div>

        <div class="mt-6">{{ $promos->links() }}</div>
    @endif
@endsection
