@props([
    'type' => 'info',
    'title' => null,
    'dismissible' => true,
])

@php
    {{
        /*
         * Inline page banners only — for things that belong in the flow:
         * validation summaries, a standing note about a screen.
         *
         * There is deliberately no `success` here. Success feedback is a toast
         * (see `x-ui.toast-stack`); the banner that used to render
         * `session('status')` at the top of the admin and customer layouts is
         * gone, and dropping the variant is what stops the same two-system
         * split from being reintroduced one call site at a time.
         */
    }}
    $styles = [
        'error' => 'border-status-cancelled/30 bg-status-cancelled-bg/60 text-status-cancelled',
        'warning' => 'border-status-low-stock/30 bg-status-low-stock-bg/60 text-status-low-stock',
        'info' => 'border-primary/20 bg-primary/5 text-primary',
    ];

    $icons = [
        'error' => 'M12 9v3.75m9-.75a9 9 0 1 1-18 0 9 9 0 0 1 18 0Zm-9 3.75h.008v.008H12v-.008Z',
        'warning' => 'M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126ZM12 15.75h.007v.008H12v-.008Z',
        'info' => 'M11.25 11.25l.041-.02a.75.75 0 0 1 1.063.852l-.708 2.836a.75.75 0 0 0 1.063.853l.041-.021M21 12a9 9 0 1 1-18 0 9 0 0 1 18 0Zm-9-3.75h.008v.008H12V8.25Z',
    ];

    $class = $styles[$type] ?? $styles['info'];
    $path = $icons[$type] ?? $icons['info'];
@endphp

<div
    @if ($dismissible) x-data="{ show: true }" x-show="show" x-transition.opacity.duration.200ms @endif
    role="alert"
    {{ $attributes->merge(['class' => "flex items-start gap-3 rounded-xl border px-4 py-3 text-sm $class"]) }}
>
    <svg class="mt-0.5 h-4.5 w-4.5 shrink-0" style="width:1.125rem;height:1.125rem" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round">
        <path d="{{ $path }}" />
    </svg>

    <div class="min-w-0 flex-1">
        @if ($title)
            <p class="font-semibold">{{ $title }}</p>
        @endif
        <div class="{{ $title ? 'mt-0.5 opacity-90' : '' }}">{{ $slot }}</div>
    </div>

    @if ($dismissible)
        <button type="button" x-on:click="show = false" class="shrink-0 opacity-60 transition hover:opacity-100" aria-label="Dismiss">
            <svg class="h-4 w-4" viewBox="0 0 20 20" fill="currentColor"><path d="M6.28 5.22a.75.75 0 0 0-1.06 1.06L8.94 10l-3.72 3.72a.75.75 0 1 0 1.06 1.06L10 11.06l3.72 3.72a.75.75 0 1 0 1.06-1.06L11.06 10l3.72-3.72a.75.75 0 0 0-1.06-1.06L10 8.94 6.28 5.22Z"/></svg>
        </button>
    @endif
</div>
