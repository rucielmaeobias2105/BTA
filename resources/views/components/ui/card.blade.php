@props([
    'title' => null,
    'subtitle' => null,
    'padded' => true,
    'accent' => 'gold',
    'footer' => null,
])

@php
    $border = $accent === 'maroon' ? 'border-primary/20' : 'border-gold/25';
@endphp

<section {{ $attributes->merge(['class' => "rounded-card border $border bg-cream shadow-card"]) }}>
    @if ($title || $subtitle)
        <header class="flex flex-wrap items-start justify-between gap-3 border-b border-primary/10 px-5 py-4">
            <div class="min-w-0">
                @if ($title)
                    <h3 class="font-display text-lg font-semibold text-primary">{{ $title }}</h3>
                @endif
                @if ($subtitle)
                    <p class="mt-0.5 text-sm text-ink-muted">{{ $subtitle }}</p>
                @endif
            </div>

            @isset($actions)
                <div class="flex shrink-0 items-center gap-2">{{ $actions }}</div>
            @endisset
        </header>
    @endif

    <div class="{{ $padded ? 'p-5' : '' }}">
        {{ $slot }}
    </div>

    @if ($footer || isset($footerSlot))
        <footer class="border-t border-primary/10 bg-linen/40 px-5 py-3.5 text-sm">
            {{ $footer ?? $footerSlot }}
        </footer>
    @endif
</section>
