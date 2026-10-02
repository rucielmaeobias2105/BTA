@props([
    // Bound expression for the current count, e.g. `unread` or `badge`. Kept as
    // a string and emitted through `x-on:` so the component stays presentational
    // and the enclosing Alpine scope keeps ownership of the number.
    'for' => 'count',

    // Optional Alpine expression appended when the count drops to zero, for the
    // brief highlight. Empty on the customer bell, which rings instead.
    'highlight' => null,
])

{{--
    The corner badge that hangs off the customer navbar's bell icon.

    It was written out by hand in `partials.customer.nav` *and* in
    `partials.admin.topbar`, which is exactly the arrangement that let the two
    drift: the admin one was `size-4` while the customer one was `h-5`, and only
    the customer one rang. Extracting it meant the two were the same badge by
    construction.

    The admin bell has since been removed and its count moved to the sidebar, so
    this is currently used once. It stays a component rather than being inlined
    back into the navbar because the sidebar's `x-ui.count-badge` deliberately
    does *not* use it — that one sits in a text row and is a pill, while this is
    pinned to an icon's corner — and the two being visibly different is the point.
    If a second icon ever needs one, it should be this, not a third hand-rolled
    copy.

    Presentational, like `x-ui.dialog`: the caller owns the number and this only
    reads it, so the count can be driven by a poller, an optimistic write, or
    both without this caring which.

    Markup contract:
        <a class="relative ...">…icon…
            <x-ui.icon-count-badge for="unread" />
        </a>
--}}
<span
    x-show="{{ $for }} > 0"
    x-cloak
    x-bind:class="{ 'animate-bell-ring': {{ $for }} > 1 }"
    @if ($highlight)
        x-transition:leave="transition-opacity duration-300 ease-out"
        x-transition:leave-start="opacity-100"
        x-transition:leave-end="opacity-0"
    @endif
    {{-- `h-5 min-w-[1.25rem]` rather than `size-4`: two digits at `text-[10px]`
         need ~18px, and a fixed square clips "10" into "1(". The ring in the
         surface colour is what separates the badge from the icon underneath. --}}
    class="absolute -right-0.5 -top-0.5 flex h-5 min-w-[1.25rem] items-center justify-center rounded-full bg-primary px-1 text-[10px] font-semibold text-cream ring-2 ring-cream"
><span x-text="{{ $for }} > 9 ? '9+' : {{ $for }}"></span></span>
