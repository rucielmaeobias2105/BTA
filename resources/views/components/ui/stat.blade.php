@props([
    'label',
    'value',
    'icon' => null,
    'text' => 'text-primary',
    'bg' => 'bg-primary/10',
    'subtext' => null,
])

{{--
    A single headline figure: label top-left, big value under it, icon in a
    tinted circle top-right, and an optional subtext line underneath.

    This is the Dashboard's stat card, lifted out of that view so the Reports
    screen can put its three figures in exactly the same shape rather than
    re-deriving the classes — one definition, so the two can never drift.

    Markup contract:
        <x-ui.stat label="Total Revenue" :value="$total" icon="heroicon-o-banknotes"
                  text="text-status-completed" bg="bg-status-completed-bg"
                  subtext="Sep 1 – Sep 30" />

    `subtext` is optional: the Dashboard's four cards have no room for one at
    that grid width, and it reads as a caption rather than a second figure.
--}}
<div {{ $attributes->merge(['class' => 'bta-card p-5']) }}>
    <div class="flex items-start justify-between gap-3">
        <div class="min-w-0">
            <p class="text-xs font-semibold uppercase tracking-wider text-ink-muted">{{ $label }}</p>
            <p class="mt-2 truncate font-display text-3xl font-bold text-primary">{{ $value }}</p>

            @if ($subtext)
                <p class="mt-1 text-xs text-ink-muted">{{ $subtext }}</p>
            @endif
        </div>

        @if ($icon)
            <span class="flex h-11 w-11 shrink-0 items-center justify-center rounded-full {{ $bg }} {{ $text }}">
                <x-dynamic-component :component="$icon" class="h-5 w-5" />
            </span>
        @endif
    </div>
</div>
