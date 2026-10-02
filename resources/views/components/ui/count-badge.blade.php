@props([
    'count',
    'tone' => 'default',
    // Window event whose `detail` is the current count. Set only where the
    // number is also being fetched by something else on the page, so the two
    // cannot drift — see the note below.
    'liveEvent' => null,
])
@php
    /*
     * One count badge for the admin panel.
     *
     * The customer-facing bell in `partials.customer.nav` draws an equivalent
     * corner badge by hand, because it hangs off an icon and needs a different
     * geometry; this is the in-line pill the sidebar's nav rows use.
     *
     * Renders nothing when the count is falsy, so "hide at zero" is one decision
     * rather than one per call site. Note `empty()` is what makes a literal `0`
     * hide — the panel should never show a badge reading zero.
     *
     * `liveEvent` exists for one caller. The sidebar's Appointments count and
     * the topbar bell count the same pending bookings, and the bell polls them —
     * so without this the sidebar would sit there rendering a number the bell
     * beside it has already watched change, which is worse than either being
     * right on its own. Listening for the poll's broadcast keeps the two in step
     * from one request.
     *
     * A live badge is emitted even at zero, because it has to be in the DOM for
     * the event to bind to; `x-show` is what hides it. A static one is omitted,
     * as before. The attributes are assembled here rather than with Blade
     * directives written between them — an `@if` inside an opening tag compiles
     * into something that silently renders nothing at all.
     */
    $live = $liveEvent !== null;

    $classes = 'inline-flex items-center justify-center rounded-pill px-1.5 py-0.5 text-[10px] font-semibold '
        .($tone === 'warning' ? 'bg-status-low-stock-bg text-status-low-stock' : 'bg-primary text-cream');

    if ($live) {
        $extra = 'x-data="{ count: '.(int) $count.' }"'
            .' x-on:'.$liveEvent.'.window="count = $event.detail"'
            .' x-show="count > 0"'
            .' x-text="count"'
            // `x-cloak` so the seeded number does not paint before Alpine has
            // taken over; `hidden` is the no-JavaScript backstop at zero.
            .' x-cloak'
            .(empty($count) ? ' hidden' : '');
    } else {
        $extra = empty($count) ? null : '';
    }
@endphp

@if ($live || ! empty($count))
    <span {!! $extra !!} class="{{ $classes }}">{!! $live ? '' : $count !!}</span>
@endif
