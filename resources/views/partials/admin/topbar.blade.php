@php
    use App\Support\Nav;

    $current = Nav::adminCurrent();
@endphp

<header class="sticky top-0 z-30 flex h-20 shrink-0 items-center justify-between gap-4 border-b border-line/70 bg-cream/95 px-4 backdrop-blur sm:px-6 lg:px-8">
    <div class="flex min-w-0 items-center gap-3">
        <button type="button" @click="sidebar = true" class="-ml-1 rounded-lg p-2 text-primary transition hover:bg-linen lg:hidden" aria-label="Open navigation">
            <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"><path d="M3.75 6.75h16.5M3.75 12h16.5m-16.5 5.25h16.5"/></svg>
        </button>

        <div class="min-w-0">
            <p class="truncate font-display text-lg font-semibold text-primary">@yield('heading', 'Dashboard')</p>
            <p class="hidden truncate text-xs text-ink-muted sm:block">{{ now()->format('l, F j, Y') }}</p>
        </div>
    </div>

    <div class="flex shrink-0 items-center gap-2 sm:gap-3">
        {{-- Low-stock alert indicator --}}
        @php $lowStock = \App\Models\InventoryItem::lowStock()->count(); @endphp
        @if ($lowStock > 0)
            <a href="{{ route('admin.tags.index') }}" class="relative flex h-10 w-10 items-center justify-center rounded-full text-status-low-stock transition hover:bg-status-low-stock-bg/60" aria-label="{{ $lowStock }} low stock item(s)">
                <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"><path d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126ZM12 15.75h.007v.008H12v-.008Z"/></svg>
                <span class="absolute -right-0.5 -top-0.5 flex h-5 min-w-[1.25rem] items-center justify-center rounded-pill bg-status-low-stock px-1 text-[10px] font-semibold text-cream ring-2 ring-cream">
                    {{ $lowStock }}
                </span>
            </a>
        @endif

        <a href="{{ route('appointments.create') }}" target="_blank" rel="noopener" class="btn-secondary btn-sm hidden md:inline-flex">
            <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"><path d="M12 4.5v15m7.5-7.5h-15"/></svg>
            New Booking
        </a>
    </div>
</header>
