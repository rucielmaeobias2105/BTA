@props([
    'label' => null,
    'name' => null,
    'id' => null,
    'hint' => null,
    'required' => false,
    'placeholder' => null,
    'autocomplete' => 'current-password',
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
        <input
            id="{{ $id }}"
            name="{{ $name }}"
            type="password"
            placeholder="{{ $placeholder }}"
            autocomplete="{{ $autocomplete }}"
            @if ($required) required @endif
            @if ($hasError) aria-invalid="true" @endif
            {{ $attributes->merge(['class' => 'input pr-16 '.($hasError ? 'input-error' : '')]) }}
        />

        {{-- Show/Hide is deliberately a small text link, not a button. --}}
        <button
            type="button"
            data-password-toggle-for="{{ $id }}"
            aria-pressed="false"
            class="toggle-link absolute inset-y-0 right-3 my-auto h-fit"
        >{{ $toggleLabel }}</button>
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
