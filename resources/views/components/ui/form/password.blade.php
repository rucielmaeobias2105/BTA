@props([
    'label' => null,
    'name' => null,
    'id' => null,
    'hint' => null,
    'required' => false,
    'placeholder' => null,
    'autocomplete' => 'current-password',
    'icon' => null,
    // Which icon marks the toggle when it is closed. Its `-slash` twin is
    // rendered alongside it automatically, so a caller names one icon rather than
    // two — see the note on the markup below.
    'toggleIcon' => null,
    'toggleLabel' => 'Show',
])

@php
    $id ??= $name;
    $hasError = $name && $errors->has($name);
@endphp

<div>
    @if ($label)
        <label for="{{ $id }}" class="label">
            {{ $label }}
            @if ($required)<span class="text-status-cancelled">*</span>@endif
        </label>
    @endif

    <div class="relative">
        @if ($icon)
            <span class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3.5 text-ink-muted">
                <x-dynamic-component :component="$icon" class="h-4 w-4" />
            </span>
        @endif

        <input
            id="{{ $id }}"
            name="{{ $name }}"
            type="password"
            placeholder="{{ $placeholder }}"
            autocomplete="{{ $autocomplete }}"
            @if ($required) required @endif
            @if ($hasError) aria-invalid="true" @endif
            {{ $attributes->merge([
                'class' => 'input '.($icon ? 'pl-10 ' : '').($hasError ? 'input-error' : '').($toggleIcon ? 'pr-12' : ' pr-16'),
            ]) }}
        />

        {{--
            Two toggle presentations, and both are bound by the same attribute.

              - the default: a small text link, the house style. Its label swaps
                between "Show" and "Hide".
              - `toggleIcon`: an icon button inside the field. Passing it switches
                the class and renders *both* the open and closed-eye marks — see
                below.

            `data-password-toggle-for` is what `initPasswordToggles()` in
            `resources/js/app.js` binds on. It was the only attribute on this
            button before, and the script was listening for a plain
            `data-password-toggle` that nothing rendered anywhere in the app — so
            `querySelectorAll` returned an empty set and the eye did nothing on
            any page. One name, used by both sides, is what fixes it.

            The two eye marks are both rendered rather than one being swapped in
            by script: replacing an SVG's markup from JavaScript means either
            holding a template string next to the component or cloning a node, and
            both drift from the Blade that drew it. Two icons with CSS deciding
            which is visible keeps the markup the single source, and costs one
            extra <svg> that is never laid out.
        --}}
        <button
            type="button"
            data-password-toggle-for="{{ $id }}"
            aria-pressed="false"
            @if ($toggleIcon)
                data-password-toggle-icon
                class="admin-auth-eye-toggle absolute inset-y-0 right-0 flex w-11 items-center justify-center"
            @else
                class="toggle-link absolute inset-y-0 right-3 my-auto h-fit"
            @endif
        >
            @if ($toggleIcon)
                {{-- Open eye: shown while the password is hidden, i.e. the state
                     the field starts in. `data-password-eye-open` /
                     `data-password-eye-closed` are what the stylesheet keys off,
                     so the script never touches either element. --}}
                <x-dynamic-component
                    :component="$toggleIcon"
                    class="admin-auth-eye"
                    data-password-eye-open
                />

                {{-- Closed eye: the same mark with a slash through it, shown once
                     the password is revealed. Derived from the caller's icon by
                     name — `heroicon-o-eye` → `heroicon-o-eye-slash` — with a
                     fallback for an icon that has no slashed twin. --}}
                @php
                    $slashed = str_ends_with($toggleIcon, '-slash')
                        ? $toggleIcon
                        : preg_replace('/-o-/', '-o-', $toggleIcon).'-slash';
                    $slashed = str_contains($slashed, '-slash') ? $slashed : $toggleIcon;
                @endphp

                <x-dynamic-component
                    :component="$slashed"
                    class="admin-auth-eye-slash"
                    data-password-eye-closed
                />

                <span class="sr-only">Show password</span>
            @else
                {{ $toggleLabel }}
            @endif
        </button>
    </div>

    @if ($hint)
        <p class="input-hint">{{ $hint }}</p>
    @endif

    @error($name)
        <p class="input-error-text">
            <svg class="h-3.5 w-3.5 shrink-0" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M18 10A8 8 0 112 10a8 8 0 0116 0zm-9-4a1 1 0 112 0v4a1 1 0 11-2 0V6zm1 8a1 1 0 100-2 1 1 0 000 2z" clip-rule="evenodd"/></svg>
            {{ $message }}
        </p>
    @enderror
</div>