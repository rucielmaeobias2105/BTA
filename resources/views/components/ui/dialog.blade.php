@props([
    'title',
    'tone' => 'maroon',
    'icon' => null,
    'maxWidth' => 'max-w-lg',
    'titleId' => 'dialog-title',
    'scrollable' => true,
])

{{--
    A dialog shell: toned header band, scrollable body, footer.

    Presentational on purpose — it reads `open` and `close()` from whatever
    Alpine scope encloses it rather than owning them, so each dialog keeps its own
    state where that state lives and this stays reusable. A caller must therefore
    wrap it in a scope that provides both:

        <div x-data="appointmentCancelPanel()">
            <x-ui.dialog title="Cancel Appointment" tone="red" icon="heroicon-o-x-circle">
                …
                <x-slot:footer>…</x-slot:footer>
            </x-ui.dialog>
        </div>

    `tone` is the only thing that changes the look of the band: `red` for the
    destructive dialog, `info` for Reschedule, `maroon` for neutral ones. Red and
    the cool `info` come from the status and accent tokens the badges and row
    actions already use, rather than from hexes pasted in for one screen.

    The body scrolls and the panel is capped at 90vh, so a long dialog scrolls
    inside itself instead of pushing the footer off the bottom of the viewport —
    the failure mode the View dialog hits with a full service list.
--}}
<div
    x-show="open"
    x-cloak
    x-on:keydown.escape.window="if (open) close()"
    class="fixed inset-0 z-[60] flex items-end justify-center overflow-y-auto p-4 sm:items-center"
    role="dialog"
    aria-modal="true"
    aria-labelledby="{{ $titleId }}"
>
    <div class="modal-backdrop fixed inset-0" x-on:click="close()" aria-hidden="true"></div>

    <div x-on:click.stop class="modal-panel flex max-h-[90vh] flex-col {{ $maxWidth }}">
        <div @class([
            'flex items-center gap-3 rounded-t-2xl px-5 py-4 text-cream',
            'bg-status-cancelled' => $tone === 'red',
            'bg-info' => $tone === 'info',
            'bg-primary' => ! in_array($tone, ['red', 'info'], true),
        ])>
            @if ($icon)
                <x-dynamic-component :component="$icon" class="h-5 w-5 shrink-0" />
            @endif

            <h2 id="{{ $titleId }}" class="font-display text-lg font-semibold">
                {{ $title }}
            </h2>

            <button
                type="button"
                x-on:click="close()"
                class="ml-auto -mr-1.5 flex h-8 w-8 items-center justify-center rounded-lg text-lg leading-none text-cream/80 transition hover:bg-cream/15 hover:text-cream"
                aria-label="Close"
            >&times;</button>
        </div>

        <div @class(['px-5 py-5', 'min-h-0 flex-1 overflow-y-auto' => $scrollable])>
            {{ $slot }}
        </div>

        @isset($footer)
            <div class="flex flex-wrap items-center justify-end gap-2 rounded-b-2xl border-t border-line/70 bg-linen/50 px-5 py-4">
                {{ $footer }}
            </div>
        @endisset
    </div>
</div>
