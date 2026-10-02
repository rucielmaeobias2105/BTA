@props([
    'icon',
    'label',
    'confirm' => null,
])

{{--
    An icon button that opens a small menu — used where a single row action has
    choices attached to it rather than one outcome.

    The panel is a slot, so the caller supplies its own forms. It is a sibling
    of the button rather than a child, so a click on a menu item never bubbles
    into the toggle, and `click.outside` closes it on any other click on the
    page.
--}}
<div class="relative inline-flex" x-data="{ open: false }" @click.outside="open = false">
    <button
        type="button"
        class="icon-action icon-action-secondary"
        @click="open = !open"
        :aria-expanded="open ? 'true' : 'false'"
        title="{{ $label }}"
        aria-label="{{ $label }}"
    >
        <x-dynamic-component :component="$icon" class="h-4 w-4" />
    </button>

    <div
        x-show="open"
        x-cloak
        x-transition.origin.top.right
        @keydown.escape.window="open = false"
        class="absolute right-0 z-20 mt-1 w-56 rounded-xl border border-primary/15 bg-cream p-1.5 shadow-panel"
    >
        <p class="px-2 py-1 text-[11px] font-semibold uppercase tracking-wide text-ink-muted">{{ $label }}</p>

        {{ $slot }}
    </div>
</div>
