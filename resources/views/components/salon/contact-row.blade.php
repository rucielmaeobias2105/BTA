@props([
    'icon' => null,
    'href' => null,
    'label' => null,
    'compact' => false,
])

{{--
    One row of the salon's contact details: a round icon, a label, and a value.

    The About page's Find Us card, the contact page's detail cards and a future
    "get in touch" block all render the same facts — where we are, how to phone,
    where to find us on Facebook. They read them from `config/salon.php` rather
    than repeating the strings, which is the whole reason this exists: those
    views had already drifted apart, and the footer's `tel:` link dialled a
    number no visible text mentioned.

    Markup contract:
        <x-saloon.contact-detail icon="heroicon-o-map-pin" label="Location">
            {{ config('salon.address') }}
        </x-saloon.contact-detail>

    `href` turns the value into a link. `target="_blank"` is added automatically
    for anything that is not a `tel:` or `mailto:` link, so a Facebook page does
    not navigate the visitor away from a page they may want to come back to —
    and `rel="noopener"` comes with it.

    `compact` drops the icon from 44px to 36px, for the denser rows the footer's
    column wants.

    A caller whose glyph is not a Heroicon passes it through the `badge` slot
    instead of `icon`, and gets the same circle:

        <x-salon.contact-row label="Facebook" :href="…">
            <x-slot:badge><x-salon.facebook-icon /></x-slot:badge>
            {{ config('salon.facebook_label') }}
        </x-salon.contact-row>
--}}
@php
    // `tel:` and `mailto:` stay in the tab; anything else (the Facebook page)
    // opens alongside, so a visitor is not navigated away from a page they may
    // want to come back to.
    $external = $href && ! Str::startsWith($href, ['tel:', 'mailto:']);
@endphp

<div class="flex gap-3">
    {{-- The circular badge.

         Two ways in, because the icons here are not all the same kind of thing:
         the three Heroicons come in as `icon="heroicon-o-…"` and are dispatched
         dynamically, while the Facebook mark is a brand glyph with no Heroicons
         equivalent and is passed through the `badge` slot instead.

         Before that slot existed the Facebook row was the one row with no badge
         at all — it rendered as bare text beside three rows that each had a
         circle, which is what "make them all uniform" meant. A named slot
         rather than a second copy of the circle here, so the badge cannot drift
         between the two kinds of icon. --}}
    @if ($icon || isset($badge))
        <span @class([
            'flex shrink-0 items-center justify-center rounded-full bg-gold-light/50 text-gold-dark',
            'h-11 w-11' => ! $compact,
            'h-9 w-9' => $compact,
        ])>
            @isset($badge)
                {{ $badge }}
            @else
                <x-dynamic-component :component="$icon" class="h-5 w-5" />
            @endisset
        </span>
    @endif

    <div class="min-w-0">
        @if ($label)
            <p class="text-sm font-semibold text-primary">{{ $label }}</p>
        @endif

        <div @class([
            'text-sm text-ink-muted',
            'mt-0.5 leading-relaxed' => $label,
            'mt-1 leading-relaxed' => ! $label,
        ])>
            @if ($href)
                <a
                    href="{{ $href }}"
                    @if ($external) target="_blank" rel="noopener noreferrer" @endif
                    class="inline-flex items-center gap-1.5 transition hover:text-primary"
                >{{ $slot }}</a>
            @else
                {{ $slot }}
            @endif
        </div>
    </div>
</div>
