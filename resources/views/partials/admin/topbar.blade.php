{{--
    A single fixed-height bar: the page title on the left, the signed-in admin on
    the right behind a profile dropdown carrying Profile and Logout.

    The notification bell used to sit to the left of that, badged with the salon's
    work queue. It is gone: the count now lives only on the sidebar, where
    Appointments carries the bookings awaiting a decision and Messages carries the
    enquiries nobody has opened.

    That is a better home for it than the bar was. The number is a count of two
    different things, so putting it on two sidebar rows makes each row honest about
    what it is counting, and it puts the number next to the navigation that acts on
    it — an admin who sees "3" next to Appointments is already three clicks from the
    screen where they clear it. In the bar it was a number with no row behind it.

    The live behaviour did not go with the bell. `adminLiveNotifications` is mounted
    by the admin layout and still polls the same feed, still writes the tab title's
    "(n)" and still broadcasts the two sidebar counts; it just has no icon of its
    own now. See `layouts.admin`.

    The height and the type scale follow MCA's 60px bar and its semibold title; the
    colours come from this panel's own tokens so the bar still belongs to the rest
    of the admin design instead of importing a second palette. The date line that
    used to sit under the title is gone for the same reason MCA's bar carries no
    subtitle — there is no room for both at 60px.

    The light/dark toggle that briefly sat here was removed with the theme it
    controlled: leaving the mechanism in place would have let a stale
    `admin-theme` value pin the panel dark with no way back.
--}}
<header class="admin-topbar sticky top-0 z-30 flex h-[60px] shrink-0 items-center justify-between gap-4 border-b border-primary/10 bg-cream/95 px-4 backdrop-blur sm:px-6 lg:px-8">
    <div class="flex min-w-0 items-center gap-3">
        <button type="button" @click="sidebar = true" class="-ml-1 rounded-lg p-2 text-primary transition hover:bg-linen lg:hidden" aria-label="Open navigation">
            <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"><path d="M3.75 6.75h16.5M3.75 12h16.5m-16.5 5.25h16.5"/></svg>
        </button>

        <h2 class="truncate font-display text-base font-semibold text-primary">@yield('heading', 'Dashboard')</h2>
    </div>

    <div class="flex shrink-0 items-center gap-2 sm:gap-3">
        {{-- Admin identity + profile dropdown --}}
        <div class="relative" x-data="dropdown" @click.outside="close()">
            <button type="button" @click="toggle()" class="flex items-center gap-2 rounded-lg py-1 pl-1 pr-2 transition hover:bg-linen" aria-haspopup="true" :aria-expanded="open">
                <span class="flex h-[34px] w-[34px] items-center justify-center rounded-full bg-primary text-xs font-bold text-cream">
                    {{ Str::upper(Str::substr(auth('admin')->user()->first_name, 0, 1).Str::substr(auth('admin')->user()->last_name, 0, 1)) }}
                </span>
                <span class="hidden text-sm font-medium text-primary sm:block">{{ auth('admin')->user()->full_name }}</span>
                <svg class="hidden h-3.5 w-3.5 text-ink-muted sm:block" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M5.22 8.22a.75.75 0 0 1 1.06 0L10 11.94l3.72-3.72a.75.75 0 1 1 1.06 1.06l-4.25 4.25a.75.75 0 0 1-1.06 0L5.22 9.28a.75.75 0 0 1 0-1.06Z" clip-rule="evenodd"/></svg>
            </button>

            <div
                x-show="open"
                x-cloak
                x-transition.origin.top.right
                class="absolute right-0 mt-2 w-56 overflow-hidden rounded-card border border-primary/10 bg-cream shadow-card-hover"
            >
                <div class="border-b border-primary/10 px-4 py-3">
                    <p class="truncate text-sm font-semibold text-primary">{{ auth('admin')->user()->full_name }}</p>
                    <p class="truncate text-xs text-ink-muted">{{ auth('admin')->user()->email }}</p>
                </div>
                <div class="p-1.5">
                    <a href="{{ route('admin.profile.edit') }}" class="flex items-center gap-2.5 rounded-lg px-3 py-2 text-sm text-ink transition hover:bg-linen">
                        <svg class="h-4 w-4 text-ink-muted" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"><path d="M15.75 6a3.75 3.75 0 1 1-7.5 0 3.75 3.75 0 0 1 7.5 0ZM4.501 20.118a7.5 7.5 0 0 1 14.998 0A17.933 17.933 0 0 1 12 21.75c-2.676 0-5.216-.584-7.499-1.632Z"/></svg>
                        Profile
                    </a>
                    <form method="POST" action="{{ route('admin.logout') }}" class="mt-1 border-t border-primary/10 pt-1">
                        @csrf
                        <button type="submit" class="flex w-full items-center gap-2.5 rounded-lg px-3 py-2 text-left text-sm text-status-cancelled transition hover:bg-status-cancelled-bg/50">
                            <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"><path d="M15.75 9V5.25A2.25 2.25 0 0 0 13.5 3h-6a2.25 2.25 0 0 0-2.25 2.25v13.5A2.25 2.25 0 0 0 7.5 21h6a2.25 2.25 0 0 0 2.25-2.25V15M12 9l-3 3m0 0 3 3m-3-3h12.75"/></svg>
                            Logout
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </div>
</header>
