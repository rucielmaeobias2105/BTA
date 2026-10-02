{{--
    The one place a confirmation appears in the whole app, mounted once per
    layout.

    A toast is the only success style the app has. There used to be two: this,
    and `x-ui.alert type="success"` pinned in the page flow at the top of the
    admin and customer layouts. The banner is gone — success feedback floats,
    auto-dismisses and never shifts the content it is confirming.

    Two flash keys land here, and which one wins is deliberate:

      - `session('toast')` is the structured form: `['type' => …, 'message' => …]`.
        Used by everything that needs to pick a tone.
      - `session('status')` is the plain string form, from the many controllers
        that flash a one-line confirmation. Resolved as a success toast so those
        keep working unchanged rather than being rewritten one at a time.

    `toast` is checked first and the two are never combined, so an action that
    flashed both produces one toast, not two.

    Read the session here rather than taking it as a prop so no layout can
    forget to wire it up — which is how the two systems drifted apart.

    Also the bus for toasts raised without a page load:

        window.btaToast.show('success', 'Date blocked successfully.')

    Deliberately outside every dialog's DOM: a toast rendered inside the modal
    that raised it disappears with the modal.

    Each toast sets its own timer, so several stacked toasts dismiss
    independently rather than all vanishing together.
--}}
@props([
    // Explicit override, mainly for tests and one-off use. Normally the flash
    // is read from the session below.
    'toast' => null,
])

@php
    // One place for both dismissal paths — the flashed toast and the live one —
    // so they cannot drift apart.
    //
    // Errors get longer. A success toast is usually one short sentence the person
    // already expected, so 4s is generous. A validation toast is the reason they
    // are still looking at the form, and it usually arrives at the moment the
    // page reloads and their attention is elsewhere — a message that vanishes
    // while they are finding the field it is about is worse than no message,
    // because it reads as the form accepting the input.
    $duration = 4000;
    $errorDuration = 8000;
    $fade = 300;

    $flash = $toast ?? session('toast');

    if ($flash === null && is_string(session('status')) && session('status') !== '') {
        $flash = ['type' => 'success', 'message' => session('status')];
    }

    // The flashed toast's own timer, chosen by its tone.
    $flashDuration = (($flash['type'] ?? 'info') === 'error') ? $errorDuration : $duration;
@endphp

<div
    class="pointer-events-none fixed bottom-4 right-4 z-[80] flex w-[calc(100%-2rem)] max-w-sm flex-col gap-2"
    data-toast-container
    x-data="{}"
    x-on:bta:toast.window="window.btaToast.render($event.detail)"
>
    @if ($flash)
        {{--
            The flashed toast fades out on the same schedule as the live one
            rather than blinking away, so a redirect does not feel cheaper than
            an in-page action.
        --}}
        <div
            data-toast
            x-data
            x-init="setTimeout(() => { $el.classList.add('opacity-0'); setTimeout(() => $el.remove(), {{ $fade }}); }, {{ $flashDuration }});"
            class="transition-opacity duration-300 motion-reduce:transition-none"
        >
            <x-ui.toast :type="$flash['type'] ?? 'info'" :message="$flash['message'] ?? null" dismissible />
        </div>
    @endif
</div>

@once
    @push('scripts')
        {{--
            Toast bus. `render()` is the listener above's entry point. The markup
            is built in JS rather than server-side so a toast can appear without
            a request, and the icon paths are inlined here because the PHP
            component cannot render into a JS template.
        --}}
        <script>
            window.btaToast = (function () {
                const ICONS = {
                    success: '<path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75 11.25 15 15 9.75M21 12a9 9 0 1 1-18 0 9 0 0 1 18 0Z"/>',
                    warning: '<path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126ZM12 15.75h.007v.008H12v-.008Z"/>',
                    error: '<path stroke-linecap="round" stroke-linejoin="round" d="M9.75 9.75l4.5 4.5m0-4.5l-4.5 4.5M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z"/>',
                    info: '<path stroke-linecap="round" stroke-linejoin="round" d="M11.25 11.25l.041-.02a.75.75 0 0 1 1.063.852l-.708 2.836a.75.75 0 0 0 1.063.853l.041-.021M21 12a9 9 0 1 1-18 0 9 0 0 1 18 0Zm-9-3.75h.008v.008H12V8.25Z"/>',
                };

                const FILLS = {
                    success: 'bg-status-completed',
                    warning: 'bg-status-low-stock',
                    error: 'bg-status-cancelled',
                    info: 'bg-primary',
                };

                function container() {
                    return document.querySelector('[data-toast-container]');
                }

                function render(detail) {
                    const host = container();
                    if (!host) return;

                    const type = ICONS[detail.type] ? detail.type : 'info';

                    // An explicit duration always wins; otherwise the tone decides,
                    // and errors outlive successes for the reason given above.
                    const fallback = type === 'error' ? @json($errorDuration) : @json($duration);
                    const duration = detail.duration || fallback;

                    const wrap = document.createElement('div');
                    wrap.setAttribute('data-toast', '');
                    // `opacity-0` then a frame later to the settled state, so the
                    // transition actually runs instead of being coalesced away.
                    wrap.className = 'pointer-events-auto opacity-0 transition-opacity duration-300 motion-reduce:transition-none';
                    wrap.setAttribute('role', 'status');
                    wrap.setAttribute('aria-live', 'polite');
                    wrap.innerHTML =
                        '<div class="pointer-events-auto flex w-full max-w-sm items-start gap-3 rounded-card px-4 py-3 text-cream shadow-card-hover ' +
                        FILLS[type] +
                        '">' +
                        '<span class="mt-0.5 shrink-0"><svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="1.6" viewBox="0 0 24 24">' +
                        ICONS[type] +
                        '</svg></span>' +
                        '<p class="min-w-0 flex-1 text-sm font-medium leading-snug"></p>' +
                        '<button type="button" class="-mr-1 shrink-0 rounded p-1 text-cream/70 transition hover:bg-cream/10 hover:text-cream" aria-label="Dismiss notification">&times;</button>' +
                        '</div>';

                    // textContent, not innerHTML: the message is text, and some
                    // of it is a customer-supplied block reason.
                    wrap.querySelector('p').textContent = detail.message || '';

                    wrap.querySelector('button').addEventListener('click', () => dismiss(wrap));

                    host.appendChild(wrap);
                    requestAnimationFrame(() => wrap.classList.remove('opacity-0'));

                    if (duration > 0) setTimeout(() => dismiss(wrap), duration);
                }

                /**
                 * The documented entry point: `show('success', 'Saved.')`.
                 *
                 * Kept separate from `render()` because `render` takes the
                 * event payload shape (`{type, message}`) that the
                 * `bta:toast` window event hands it, while callers pass loose
                 * arguments. Aliasing the two — as this used to — meant every
                 * toast raised from JavaScript rendered with an empty body,
                 * since `detail.message` was undefined.
                 */
                function show(type, message, duration) {
                    render({ type: type, message: message, duration: duration });
                }

                function dismiss(node) {
                    if (!node || !node.isConnected) return;
                    node.classList.add('opacity-0');
                    setTimeout(() => node.remove(), @json($fade));
                }

                return { show: show, render: render, dismiss: dismiss };
            })();
        </script>
    @endpush
@endonce
