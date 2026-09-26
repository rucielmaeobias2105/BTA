@php
    use App\Support\Nav;

    $admin = auth('admin')->user();
    $current = Nav::adminCurrent();
@endphp

<header class="sticky top-0 z-30 flex h-20 shrink-0 items-center justify-between gap-4 border-b border-primary/10 bg-cream/95 px-4 backdrop-blur sm:px-6 lg:px-8">
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

        <div class="relative" x-data="dropdown" @click.outside="close()">
            <button type="button" @click="toggle()" class="flex items-center gap-2 rounded-pill border border-primary/15 py-1 pl-1 pr-2.5 transition hover:border-gold hover:bg-linen/60" aria-haspopup="true" :aria-expanded="open">
                <span class="flex h-8 w-8 items-center justify-center rounded-full bg-primary text-[11px] font-semibold text-cream">
                    {{ Str::upper(Str::substr($admin->first_name, 0, 1).Str::substr($admin->last_name, 0, 1)) }}
                </span>
                <span class="hidden text-sm font-medium text-ink sm:block">{{ $admin->first_name }}</span>
                <svg class="h-3.5 w-3.5 text-ink-muted" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M5.22 8.22a.75.75 0 0 1 1.06 0L10 11.94l3.72-3.72a.75.75 0 1 1 1.06 1.06l-4.25 4.25a.75.75 0 0 1-1.06 0L5.22 9.28a.75.75 0 0 1 0-1.06Z" clip-rule="evenodd"/></svg>
            </button>

            <div x-show="open" x-transition.origin.top.right class="absolute right-0 mt-2 w-60 overflow-hidden rounded-card border border-primary/10 bg-cream shadow-card-hover">
                <div class="border-b border-primary/10 px-4 py-3">
                    <p class="truncate text-sm font-semibold text-primary">{{ $admin->full_name }}</p>
                    <p class="truncate text-xs text-ink-muted">{{ $admin->email }}</p>
                    <span class="badge badge-gold mt-2">{{ $admin->role->label() }}</span>
                </div>
                <div class="p-1.5">
                    <a href="{{ route('admin.profile.edit') }}" class="flex items-center gap-2.5 rounded-lg px-3 py-2 text-sm text-ink transition hover:bg-linen">My Profile</a>
                    <form method="POST" action="{{ route('admin.logout') }}" class="mt-1 border-t border-primary/10 pt-1">
                        @csrf
                        <button type="submit" class="flex w-full items-center gap-2.5 rounded-lg px-3 py-2 text-left text-sm text-status-cancelled transition hover:bg-status-cancelled-bg/50">Log Out</button>
                    </form>
                </div>
            </div>
        </div>
    </div>
</header>
