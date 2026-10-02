@props([
    'label' => null,
    'name' => 'is_active',
    'id' => null,
    'hint' => null,
    'checked' => false,
    'value' => 1,
    'wrapperClass' => '',
])

{{--
    An on/off switch for a form.

    A real checkbox behind a styled track, not a `<div>` with a click handler:
    it submits without JavaScript, keeps working under a screen reader and
    inside a browser's own form restore, and the paired hidden `0` is what makes
    an unchecked box reach the controller as false rather than as absent.
--}}
@php
    $id ??= $name;
    $isChecked = (bool) old($name, $checked);
@endphp

<div class="{{ $wrapperClass }}">
    <div class="flex items-start gap-3">
        <input type="hidden" name="{{ $name }}" value="0">

        <input
            type="checkbox"
            id="{{ $id }}"
            name="{{ $name }}"
            value="{{ $value }}"
            class="switch-input sr-only"
            @checked($isChecked)
            @error($name) aria-invalid="true" @enderror
        >

        {{-- A second label for the same input, so the track itself is the click
             target. `aria-hidden` because the text label below already names
             the control. --}}
        <label for="{{ $id }}" class="switch mt-0.5" aria-hidden="true"></label>

        <div class="min-w-0">
            @if ($label)
                <label for="{{ $id }}" class="cursor-pointer select-none text-sm font-medium text-ink">
                    {{ $label }}
                </label>
            @endif

            @if ($hint)
                <p class="mt-0.5 text-xs text-ink-muted">{{ $hint }}</p>
            @endif
        </div>
    </div>

    @error($name)
        <p class="input-error-text">
            <svg class="h-3.5 w-3.5 shrink-0" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M18 10A8 8 0 11 2 10a8 8 0 0116 0zm-9-4a1 1 0 112 0v4a1 1 0 11-2 0V6zm1 8a1 1 0 100-2 1 1 0 000 2z" clip-rule="evenodd"/></svg>
            {{ $message }}
        </p>
    @enderror
</div>
