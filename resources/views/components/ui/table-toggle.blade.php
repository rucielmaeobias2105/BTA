@props([
    'action',
    'checked' => false,
    'label' => 'Toggle',
    'onLabel' => null,
    'offLabel' => null,
    'confirm' => null,
])

{{--
    An inline on/off switch for an admin table row.

    The whole track is the submit button of a PATCH form, so flipping a row is
    one request with no JavaScript and no optimistic UI to roll back. `role` and
    `aria-checked` carry the state for assistive technology, and the state text
    beside the track is what a sighted admin reads.
--}}
<form method="POST" action="{{ $action }}" class="inline-flex" @if ($confirm) onsubmit="return confirm(@js($confirm))" @endif>
    @csrf
    @method('PATCH')

    <button
        type="submit"
        role="switch"
        aria-checked="{{ $checked ? 'true' : 'false' }}"
        title="{{ $label }}"
        @class(['switch', 'switch-on' => $checked])
    >
        <span class="sr-only">{{ $label }}</span>
    </button>

    @if ($onLabel || $offLabel)
        <span @class(['ml-2.5 text-xs font-medium', 'text-status-confirmed' => $checked, 'text-ink-muted' => ! $checked])>
            {{ $checked ? $onLabel : $offLabel }}
        </span>
    @endif
</form>
