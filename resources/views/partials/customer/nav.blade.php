@php
    use App\Support\Nav;

    $current = Nav::customerCurrent();
    $unread = auth()->check() ? auth()->user()->unreadNotifications()->count() : 0;
@endphp

<header x-data="{ mobileOpen: false, profileOpen: false }" class="sticky top-0 z-40 border-b border-primary/10 bg-cream/95 backdrop-blur supports-[backdrop-filter]:bg-cream/80">
    <div class="mx-auto flex h-20 max-w-7xl items-center justify-between gap-4 px-4 sm:px-6 lg:px-8">
        <x-brand.logo href="{{ route('home') }}" size="md" />

        {{-- Desktop nav --}}
        <nav class="hidden items-center gap-7 lg:flex" aria-label="Main">
            <a href="{{ route('home') }}" class="customer-nav-link {{ $current === 'home' ? 'customer-nav-link-active' : '' }}">Home</a>
            <a href="{{ route('services.index') }}" class="customer-nav-link {{ $current === 'services' ? 'customer-nav-link-active' : '' }}">Services</a>
            <a href="{{ route('home') }}#offers" class="customer-nav-link">Promo</a>
            <a href="{{ route('about') }}" class="customer-nav-link {{ $current === 'about' ? 'customer-nav-link-active' : '' }}">About Us</a>
            <a href="{{ route('contact.create') }}" class="customer-nav-link {{ $current === 'contact' ? 'customer-nav-link-active' : '' }}">Contact</a>
        </nav>

        <div class="flex items-center gap-2.5">
            @auth
                {{-- Notification bell with unread count --}}
                <a href="{{ route('notifications.index') }}" class="relative flex h-10 w-10 items-center justify-center rounded-full text-primary transition hover:bg-primary/5" aria-label="Notifications ({{ $unread }} unread)">
                    <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M14.857 17.082a23.848 23.848 0 0 0 5.454-1.31A8.967 8.967 0 0 1 18 9.75V9A6 6 0 0 0 6 9v.75a8.967 8.967 0 0 1-2.312 6.022c1.733.64 3.56 1.085 5.455 1.31m5.714 0a24.255 24.255 0 0 1-5.714 0m5.714 0a3 3 0 1 1-5.714 0"/>
                    </svg>

                    @if ($unread > 0)
                        <span @class([
                            'absolute -right-0.5 -top-0.5 flex h-5 min-w-[1.25rem] items-center justify-center rounded-full bg-primary px-1 text-[10px] font-semibold text-cream ring-2 ring-cream',
                            'animate-bell-ring' => $unread > 0,
                        ])>{{ $unread > 9 ? '9+' : $unread }}</span>
                    @endif
                </a>

                <div class="relative hidden sm:block" x-data="dropdown" @click.outside="close()">
                    <button type="button" @click="toggle()" class="flex items-center gap-2 rounded-pill border border-primary/15 py-1 pl-1 pr-3 transition hover:border-gold hover:bg-linen/60" aria-haspopup="true" :aria-expanded="open">
                        @if (auth()->user()->profile_photo_path)
                            <img src="{{ Storage::url(auth()->user()->profile_photo_path) }}" alt="" class="h-7 w-7 rounded-full object-cover">
                        @else
                            <span class="flex h-7 w-7 items-center justify-center rounded-full bg-primary text-[11px] font-semibold text-cream">
                                {{ Str::upper(Str::substr(auth()->user()->first_name, 0, 1).Str::substr(auth()->user()->last_name, 0, 1)) }}
                            </span>
                        @endif
                        <span class="text-sm font-medium text-ink">{{ Str::before(auth()->user()->full_name, ' ') }}</span>
                        <svg class="h-3.5 w-3.5 text-ink-muted" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M5.22 8.22a.75.75 0 0 1 1.06 0L10 11.94l3.72-3.72a.75.75 0 1 1 1.06 1.06l-4.25 4.25a.75.75 0 0 1-1.06 0L5.22 9.28a.75.75 0 0 1 0-1.06Z" clip-rule="evenodd"/></svg>
                    </button>

                    <div x-show="open" x-transition.origin.top.right class="absolute right-0 mt-2 w-56 overflow-hidden rounded-card border border-primary/10 bg-cream shadow-card-hover">
                        <div class="border-b border-primary/10 px-4 py-3">
                            <p class="truncate text-sm font-semibold text-primary">{{ auth()->user()->full_name }}</p>
                            <p class="truncate text-xs text-ink-muted">{{ auth()->user()->email }}</p>
                        </div>
                        <div class="p-1.5">
                            <a href="{{ route('dashboard') }}" class="flex items-center gap-2.5 rounded-lg px-3 py-2 text-sm text-ink transition hover:bg-linen">Dashboard</a>
                            <a href="{{ route('appointments.index') }}" class="flex items-center gap-2.5 rounded-lg px-3 py-2 text-sm text-ink transition hover:bg-linen">My Appointments</a>
                            <a href="{{ route('profile.edit') }}" class="flex items-center gap-2.5 rounded-lg px-3 py-2 text-sm text-ink transition hover:bg-linen">Profile</a>
                            <form method="POST" action="{{ route('logout') }}" class="mt-1 border-t border-primary/10 pt-1">
                                @csrf
                                <button type="submit" class="flex w-full items-center gap-2.5 rounded-lg px-3 py-2 text-left text-sm text-status-cancelled transition hover:bg-status-cancelled-bg/50">Log Out</button>
                            </form>
                        </div>
                    </div>
                </div>
            @else
                <a href="{{ route('login') }}" class="btn-secondary btn-sm hidden sm:inline-flex">Log In</a>
                <a href="{{ route('register') }}" class="btn-primary btn-sm">Register</a>
            @endauth

            <button type="button" @click="mobileOpen = ! mobileOpen" class="flex h-10 w-10 items-center justify-center rounded-lg text-primary lg:hidden" aria-label="Toggle menu" :aria-expanded="mobileOpen">
                <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"><path d="M3.75 6.75h16.5M3.75 12h16.5m-16.5 5.25h16.5"/></svg>
            </button>
        </div>
    </div>

    {{-- Mobile nav --}}
    <div x-show="mobileOpen" x-transition class="border-t border-primary/10 bg-cream lg:hidden">
        <nav class="mx-auto grid max-w-7xl gap-1 px-4 py-4 sm:px-6" aria-label="Mobile">
            <a href="{{ route('home') }}" class="rounded-lg px-3 py-2.5 text-sm font-medium text-ink hover:bg-linen">Home</a>
            <a href="{{ route('services.index') }}" class="rounded-lg px-3 py-2.5 text-sm font-medium text-ink hover:bg-linen">Services</a>
            <a href="{{ route('home') }}#offers" class="rounded-lg px-3 py-2.5 text-sm font-medium text-ink hover:bg-linen">Promo</a>
            <a href="{{ route('about') }}" class="rounded-lg px-3 py-2.5 text-sm font-medium text-ink hover:bg-linen">About Us</a>
            <a href="{{ route('contact.create') }}" class="rounded-lg px-3 py-2.5 text-sm font-medium text-ink hover:bg-linen">Contact</a>

            @auth
                <div class="my-2 bta-divider"></div>
                <a href="{{ route('dashboard') }}" class="rounded-lg px-3 py-2.5 text-sm font-medium text-ink hover:bg-linen">Dashboard</a>
                <a href="{{ route('appointments.index') }}" class="rounded-lg px-3 py-2.5 text-sm font-medium text-ink hover:bg-linen">My Appointments</a>
                <a href="{{ route('notifications.index') }}" class="rounded-lg px-3 py-2.5 text-sm font-medium text-ink hover:bg-linen">
                    Notifications
                    @if ($unread > 0)
                        <span class="ml-1 rounded-pill bg-primary px-1.5 py-0.5 text-[10px] font-semibold text-cream">{{ $unread }}</span>
                    @endif
                </a>
                <a href="{{ route('profile.edit') }}" class="rounded-lg px-3 py-2.5 text-sm font-medium text-ink hover:bg-linen">Profile</a>
                <form method="POST" action="{{ route('logout') }}">
                    @csrf
                    <button type="submit" class="w-full rounded-lg px-3 py-2.5 text-left text-sm font-medium text-status-cancelled hover:bg-status-cancelled-bg/50">Log Out</button>
                </form>
            @else
                <div class="mt-3 grid grid-cols-2 gap-2">
                    <a href="{{ route('login') }}" class="btn-secondary btn-sm">Log In</a>
                    <a href="{{ route('register') }}" class="btn-primary btn-sm">Register</a>
                </div>
            @endauth
        </nav>
    </div>
</header>
