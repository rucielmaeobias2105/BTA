{{--
    The circular emblem from the brand logo, on its own.

    `x-brand.logo` composes this next to the wordmark. It is also usable alone
    anywhere the mark is needed at a size the logo's own sm/md/lg steps do not
    cover — the admin sign-in panel uses `xl`.

    Supply `image` to render the real logo asset. Without it this falls back to
    the "BtA" monogram, which is why the navbar, footer, admin sidebar and
    sign-in panel all look like the same mark.
--}}
@props([
    'image' => null,
    'nameLabel' => 'Balai ti Arjud',
    'size' => 'md',
    'ring' => true,
    'ringOffset' => 'ring-offset-cream',
])

@php
    $sizes = [
        'sm' => ['mark' => 'h-9 w-9 text-[8px]'],
        'md' => ['mark' => 'h-12 w-12 text-[9px]'],
        'lg' => ['mark' => 'h-16 w-16 text-[11px]'],
        'xl' => ['mark' => 'h-[122px] w-[122px] text-[13px]'],
    ];

    $s = $sizes[$size] ?? $sizes['md'];
    $ringClasses = $ring ? 'ring-2 ring-gold ring-offset-2 '.$ringOffset.' shadow-card' : '';
@endphp

@if ($image)
    <img
        src="{{ asset($image) }}"
        alt="{{ $nameLabel }}"
        {{ $attributes->merge([
            'class' => $s['mark'].' shrink-0 rounded-full object-cover '.$ringClasses,
        ]) }}
    >
@else
    <span
        {{ $attributes->merge([
            'class' => 'relative flex '.$s['mark'].' shrink-0 items-center justify-center rounded-full
                       bg-primary text-cream ring-2 ring-gold ring-offset-2 ring-offset-cream
                       shadow-card transition group-hover:ring-gold-light',
        ]) }}
    >
        <span class="font-display font-semibold leading-none tracking-tight">Bt<span class="text-gold">A</span></span>
        <span class="absolute inset-[3px] rounded-full border border-gold/40"></span>
    </span>
@endif
