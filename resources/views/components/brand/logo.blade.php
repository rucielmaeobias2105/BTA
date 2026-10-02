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
        'sm' => ['name' => 'text-base', 'tag' => 'text-[9px]'],
        'md' => ['name' => 'text-xl', 'tag' => 'text-[10px]'],
        'lg' => ['name' => 'text-2xl', 'tag' => 'text-xs'],
    ];

    $s = $sizes[$size] ?? $sizes['md'];
@endphp

@if ($href)
    <a href="{{ $href }}" {{ $attributes->merge(['class' => 'group inline-flex items-center gap-3']) }}>
@else
    <span {{ $attributes->merge(['class' => 'inline-flex items-center gap-3']) }}>
@endif
    {{-- Circular emblem badge: gold ring, maroon face, serif wordmark. Falls
         back to the "BtA" monogram when no image is supplied. The emblem
         itself lives in x-brand.emblem so other surfaces can reuse the mark
         at their own size instead of rebuilding it. --}}
    <x-brand.emblem :image="$image" :name-label="$nameLabel" :size="$size" />

    @if ($showWordmark)
        <span class="min-w-0 leading-tight">
            <span class="block font-display {{ $s['name'] }} font-bold tracking-tight text-primary">{{ $nameLabel }}</span>
            <span class="block font-sans {{ $s['tag'] }} uppercase tracking-[0.18em] text-gold-dark">{{ $tagline }}</span>
        </span>
    @endif

@if ($href)</a>@else</span>@endif
