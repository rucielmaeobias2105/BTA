@php
    use App\Support\Nav;

    $current = Nav::adminCurrent();
    $pendingCount = \App\Models\Appointment::where('status', 'pending')->count();
    $lowStockCount = \App\Models\InventoryItem::lowStock()->count();

    $links = [
        ['route' => 'admin.dashboard', 'label' => 'Dashboard', 'icon' => 'heroicon-o-squares-2x2', 'active' => 'dashboard'],
        ['route' => 'admin.appointments.index', 'label' => 'Appointments', 'icon' => 'heroicon-o-calendar-days', 'active' => 'appointments', 'badge' => $pendingCount],
        ['route' => 'admin.catalog.index', 'label' => 'Services & Items', 'icon' => 'heroicon-o-sparkles', 'active' => 'catalog'],
        ['route' => 'admin.services.index', 'label' => 'Services', 'icon' => 'heroicon-o-scissors', 'active' => 'services'],
        ['route' => 'admin.inventory.index', 'label' => 'Inventory', 'icon' => 'heroicon-o-archive-box', 'active' => 'inventory', 'badge' => $lowStockCount, 'badgeStyle' => 'warning'],
        ['route' => 'admin.tags.index', 'label' => 'Low-Stock Tags', 'icon' => 'heroicon-o-tag', 'active' => 'tags'],
        ['route' => 'admin.calendar.index', 'label' => 'Calendar', 'icon' => 'heroicon-o-calendar', 'active' => 'calendar'],
        ['route' => 'admin.users.index', 'label' => 'Registered Users', 'icon' => 'heroicon-o-users', 'active' => 'users'],
        ['route' => 'admin.terms.index', 'label' => 'Terms & Conditions', 'icon' => 'heroicon-o-document-text', 'active' => 'terms'],
        ['route' => 'admin.reviews.index', 'label' => 'Reviews', 'icon' => 'heroicon-o-star', 'active' => 'reviews'],
        ['route' => 'admin.reports.index', 'label' => 'Reports', 'icon' => 'heroicon-o-chart-bar', 'active' => 'reports'],
        ['route' => 'admin.promos.index', 'label' => 'Promo', 'icon' => 'heroicon-o-megaphone', 'active' => 'promos'],
    ];
@endphp

<aside
    x-show="sidebar"
    x-cloak
    x-transition:enter="transition ease-out duration-200"
    x-transition:enter-start="-translate-x-full"
    x-transition:enter-end="translate-x-0"
    x-transition:leave="transition ease-in duration-150"
    x-transition:leave-start="translate-x-0"
    x-transition:leave-end="-translate-x-full"
    @resize.window="if (window.innerWidth >= 1024) sidebar = false"
    class="fixed inset-y-0 left-0 z-50 flex w-72 shrink-0 flex-col border-r border-primary/10 bg-cream lg:static lg:!transform lg:translate-x-0"
    aria-label="Admin navigation"
>
    <div class="flex h-20 shrink-0 items-center justify-between border-b border-primary/10 px-5">
        <x-brand.logo href="{{ route('admin.dashboard') }}" size="sm" />

        <button type="button" @click="sidebar = false" class="rounded-lg p-1.5 text-ink-muted hover:bg-linen lg:hidden" aria-label="Close navigation">
            <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"><path d="M6 18 18 6M6 6l12 12"/></svg>
        </button>
    </div>

    <nav class="flex-1 space-y-1 overflow-y-auto px-3 py-4" aria-label="Admin">
        @foreach ($links as $link)
            @php $isActive = $current === $link['active']; @endphp
            <a
                href="{{ route($link['route']) }}"
                @class(['admin-nav-link', 'admin-nav-link-active' => $isActive])
                @if ($isActive) aria-current="page" @endif
            >
                <x-dynamic-component :component="$link['icon']" class="h-5 w-5 shrink-0" />
                <span class="flex-1 truncate">{{ $link['label'] }}</span>

                @if (! empty($link['badge']))
                    <span @class([
                        'rounded-pill px-1.5 py-0.5 text-[10px] font-semibold',
                        'bg-cream/25 text-cream' => $isActive,
                        'bg-primary text-cream' => ! $isActive && ($link['badgeStyle'] ?? null) !== 'warning',
                        'bg-status-low-stock-bg text-status-low-stock' => ! $isActive && ($link['badgeStyle'] ?? null) === 'warning',
                    ])>{{ $link['badge'] }}</span>
                @endif
            </a>
        @endforeach
    </nav>

    <div class="shrink-0 space-y-1 border-t border-primary/10 p-3">
        <a href="{{ route('home') }}" target="_blank" rel="noopener" class="admin-nav-link">
            <svg class="h-5 w-5 shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><path d="M13.5 6H5.25A2.25 2.25 0 0 0 3 8.25v10.5A2.25 2.25 0 0 0 5.25 21h10.5A2.25 2.25 0 0 0 18 18.75V10.5m-10.5 6L21 3m0 0h-5.25M21 3v5.25"/></svg>
            <span class="flex-1">View Site</span>
        </a>

        <form method="POST" action="{{ route('admin.logout') }}">
            @csrf
            <button type="submit" class="admin-nav-link w-full text-status-cancelled hover:bg-status-cancelled-bg/50 hover:text-status-cancelled">
                <svg class="h-5 w-5 shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><path d="M15.75 9V5.25A2.25 2.25 0 0 0 13.5 3h-6a2.25 2.25 0 0 0-2.25 2.25v13.5A2.25 2.25 0 0 0 7.5 21h6a2.25 2.25 0 0 0 2.25-2.25V15M12 9l-3 3m0 0 3 3m-3-3h12.75"/></svg>
                <span>Logout</span>
            </button>
        </form>
    </div>
</aside>
