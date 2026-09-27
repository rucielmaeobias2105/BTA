@props([
    'name' => 'profile_photo_path',
    'nameLabel' => 'Balai ti Arjud',
    'tagline' => 'Glow & Co. Beauty Lounge',
    'size' => 'md',
    'showWordmark' => true,
    'href' => null,
    'image' => null,
])

@php
    $sizes = [
        'sm' => ['mark' => 'h-9 w-9 text-[8px]', 'name' => 'text-base', 'tag' => 'text-[9px]'],
        'md' => ['mark' => 'h-12 w-12 text-[9px]', 'name' => 'text-xl', 'tag' => 'text-[10px]'],
        'lg' => ['mark' => 'h-16 w-16 text-[11px]', 'name' => 'text-2xl', 'tag' => 'text-xs'],
    ];

    $s = $sizes[$size] ?? $sizes['md'];
@endphp

@if ($href)
    <a href="{{ $href }}" {{ $attributes->merge(['class' => 'group inline-flex items-center gap-3']) }}>
@else
    <span {{ $attributes->merge(['class' => 'inline-flex items-center gap-3']) }}>
@endif
    {{-- Circular emblem badge: gold ring, maroon face, serif wordmark. Falls
         back to the "BtA" monogram when no image is supplied. --}}
    @if ($image)
        <img
            src="{{ asset($image) }}"
            alt="{{ $nameLabel }}"
            class="{{ $s['mark'] }} shrink-0 rounded-full object-cover ring-2 ring-gold ring-offset-2 ring-offset-cream shadow-card"
        >
    @else
        <span
            class="relative flex {{ $s['mark'] }} shrink-0 items-center justify-center rounded-full
                   bg-primary text-cream ring-2 ring-gold ring-offset-2 ring-offset-cream
                   shadow-card transition group-hover:ring-gold-light"
        >
            <span class="font-display font-semibold leading-none tracking-tight">Bt<span class="text-gold">A</span></span>
            <span class="absolute inset-[3px] rounded-full border border-gold/40"></span>
        </span>
    @endif

    @if ($showWordmark)
        <span class="min-w-0 leading-tight">
            <span class="block font-display {{ $s['name'] }} font-bold tracking-tight text-primary">{{ $nameLabel }}</span>
            <span class="block font-sans {{ $s['tag'] }} uppercase tracking-[0.18em] text-gold-dark">{{ $tagline }}</span>
        </span>
    @endif

@if ($href)</a>@else</span>@endif
