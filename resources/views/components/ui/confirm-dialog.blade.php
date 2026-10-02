@props([
    'title',
    'event',
    'action' => '',
    'subject' => '',
    // The sentence opens with this: "Delete 3 bookings?", "Archive 1 booking?".
    // A prop rather than the hardcoded "Delete" it started as, because the
    // appointments list asks the same question twice with different verbs and
    // "Delete 1 booking?" under an Archive heading reads as a mistake.
    'verb' => 'Delete',
    'warning' => null,
    'titleId' => 'confirm-dialog-title',
    'confirmLabel' => 'Yes',
    'cancelLabel' => 'No',
    // The HTTP verb the form spoofs. `DELETE` for every destructive use, which is
    // why it is the default and why nothing had to pass it until now.
    //
    // A prop rather than always-DELETE because the dialog is no longer only ever
    // destructive: the archive screen's bulk Restore is a POST, and spoofing
    // DELETE for it would either 405 or, worse, route to a different handler
    // than the one the confirmation text describes.
    //
    // The same trap caught the Archive dialog on the working appointments list:
    // `admin.appointments.archive` is a POST route, and this default had it
    // submitting DELETE, so every archive attempt answered "The DELETE method is
    // not supported". It passed every test, because the tests posted to the route
    // directly rather than reading the form. So any non-destructive caller has to
    // pass `method` explicitly, and FormMethodMatchesRouteTest checks every
    // concrete-action form in the app against the router.
    'method' => 'DELETE',
    // `danger` for anything irreversible, `primary` for a reversible action that
    // merely deserves a prompt. Paired with `method` — a red "Yes" on a restore
    // teaches admins that this dialog means "destroy", and then the one time it
    // really does they click through it.
    'confirmTone' => 'danger',
])

{{--
    A destructive confirmation, replacing the browser's own `confirm()`.

    Native `confirm()` cannot be styled, blocks the main thread and ignores the
    site's design language, so it is gone from anything that used this. The four
    hand-rolled admin dialogs follow the same shape; this component exists
    because a second and third caller wanted one, and duplicating the skeleton
    a sixth time would have been the alternative.

    Usage — the caller opens it by dispatching `event` as a window event:

        <button type="button" x-on:click="$dispatch('confirm-thing', {
            count: selected.length,
            ids: selected,
            action: @js(route('things.destroy', $thing)),
        })">…</button>

        <x-ui.confirm-dialog
            title="Delete things"
            event="confirm-thing"
            :action="route('things.destroy-many')"
            subject="thing"
            warning="This cannot be undone."
        />

    Every field of the prompt is optional apart from `title` and `event`. An
    `action` in the event detail overrides the prop, so one dialog serves both a
    per-row URL and a shared one. `ids` rides along as hidden `ids[]` inputs —
    pass it to delete a selected set, omit it to delete the single row the
    `action` names. So the same component covers both, and the two forms a
    caller would otherwise have to keep in step collapse into one.

    The one real form lives here rather than in the triggers, which is the point
    of the whole arrangement: a form per row would repeat a CSRF token and a
    `_method` spoof once per row. That also means the triggers are plain buttons
    and this is a dialog that needs JavaScript — the native prompt at least
    degraded to a plain submit when scripting was off.

    A trigger may also pass `name`, and the sentence then names the row rather
    than counting it: `x-on:click="$dispatch('confirm-delete-service', { id: 7,
    name: 'Glow Foot Spa' })"` reads "Delete Glow Foot Spa?" instead of "Delete 1
    service?". Omitted, the counted sentence is used exactly as before, so the
    bulk callers are unaffected.

    Note there is no close button in the header, unlike the admin dialogs. Two
    buttons, Yes and No, and dismissal is No, Escape, or the backdrop.
--}}
<div
    x-data="confirmDialog(@js(['action' => $action, 'subject' => $subject]))"
    x-on:{{ $event }}.window="ask($event.detail)"
    x-show="open"
    x-cloak
    x-on:keydown.escape.window="if (open) cancel()"
    class="fixed inset-0 z-[60] flex items-end justify-center overflow-y-auto p-4 sm:items-center"
    role="dialog"
    aria-modal="true"
    aria-labelledby="{{ $titleId }}"
>
    <div class="modal-backdrop fixed inset-0" x-on:click="cancel()" aria-hidden="true"></div>

    <div x-on:click.stop class="modal-panel max-w-md">
        <form method="POST" x-bind:action="action">
            @csrf
            @method($method)

            {{-- One hidden field per selected id. Nothing renders for a
                 single-row delete, which posts no `ids[]` at all — that is what
                 the single-delete route expects. --}}
            <template x-for="id in ids" x-bind:key="id">
                <input type="hidden" name="ids[]" x-bind:value="id">
            </template>

            <div class="flex items-start justify-between gap-4 border-b border-line/70 px-5 py-4">
                <h2 id="{{ $titleId }}" class="font-display text-lg font-semibold text-primary">
                    {{ $title }}
                </h2>
            </div>

            <div class="px-5 py-5">
                <p class="text-sm leading-relaxed text-ink">
                    {{ $verb }}
                    {{-- Two sentences, one of which is always hidden.

                         A counted prompt ("Delete 1 service?") when the caller
                         gave no name; a named one ("Delete Glow Foot Spa?") when
                         it did. Both are rendered so the count cannot be left
                         stranded next to a name, and the named branch is the one
                         a service deletion uses. --}}
                    <span x-show="! label">
                        <span class="font-semibold text-primary" x-text="count"></span>
                        <span x-text="subjectPhrase"></span>?
                    </span>
                    <span x-show="label" x-cloak>
                        <span class="font-semibold text-primary" x-text="label"></span>?
                    </span>
                    @if ($warning)
                        <span>{{ $warning }}</span>
                    @endif
                </p>
            </div>

            {{-- `x-ref="confirm"` is what the dialog focuses on open, so Enter
                 lands on Yes without the reader having to tab to it first. --}}
            <div class="flex flex-wrap justify-end gap-2 border-t border-line/70 bg-linen/50 px-5 py-4">
                <button type="button" class="btn-ghost" x-on:click="cancel()">{{ $cancelLabel }}</button>
                <button type="submit" @class(['btn-danger' => $confirmTone === 'danger', 'btn-primary' => $confirmTone !== 'danger']) x-ref="confirm">{{ $confirmLabel }}</button>
            </div>
        </form>
    </div>
</div>
