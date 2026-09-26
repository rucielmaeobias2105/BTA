@props([
    'title' => null,
    'description' => null,
    'eyebrow' => null,
])

<div {{ $attributes->merge(['class' => 'mb-6 flex flex-wrap items-end justify-between gap-4']) }}>
    <div class="min-w-0">
        @if ($eyebrow)
            <p class="mb-1 text-[11px] font-semibold uppercase tracking-[0.2em] text-gold-dark">{{ $eyebrow }}</p>
        @endif

        @if ($title)
            <h1 class="font-display text-2xl font-bold tracking-tight text-primary sm:text-3xl">{{ $title }}</h1>
        @endif

        @if ($description)
            <p class="mt-1.5 max-w-2xl text-sm text-ink-muted">{{ $description }}</p>
        @endif
    </div>

    @isset($actions)
        <div class="flex shrink-0 flex-wrap items-center gap-2">{{ $actions }}</div>
    @endisset
</div>
