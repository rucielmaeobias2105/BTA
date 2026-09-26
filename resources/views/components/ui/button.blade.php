@props([
    'variant' => 'primary',
    'size' => 'md',
    'href' => null,
    'type' => 'button',
    'icon' => null,
])

@php
    $classes = 'btn-'.($variant === 'primary' || $variant === 'secondary' || $variant === 'ghost' || $variant === 'danger' ? $variant : 'gold');

    if ($size === 'sm') {
        $classes .= ' btn-sm';
    } elseif ($size === 'lg') {
        $classes .= ' btn-lg';
    }
@endphp

@if ($href)
    <a href="{{ $href }}" {{ $attributes->merge(['class' => $classes]) }}>
        @if ($icon)<x-dynamic-component :component="$icon" class="h-4 w-4" />@endif
        {{ $slot }}
    </a>
@else
    <button type="{{ $type }}" {{ $attributes->merge(['class' => $classes]) }}>
        @if ($icon)<x-dynamic-component :component="$icon" class="h-4 w-4" />@endif
        {{ $slot }}
    </button>
@endif
