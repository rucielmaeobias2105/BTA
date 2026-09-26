@props([
    'label' => null,
    'name' => null,
    'value' => null,
    'id' => null,
    'hint' => null,
    'required' => false,
    'wrapperClass' => '',
])

@php
    $id ??= $name;
    $hasError = $name && $errors->has($name);
@endphp

<div class="{{ $wrapperClass }}">
    <div class="flex items-start gap-2.5">
        <input
            id="{{ $id }}"
            name="{{ $name }}"
            type="checkbox"
            value="{{ $value ?? 1 }}"
            @checked(old($name, (bool) $value)) @if ($required) required @endif
            @if ($hasError) aria-invalid="true" @endif
            {{ $attributes->merge(['class' => 'checkbox mt-0.5 '.($hasError ? 'ring-2 ring-status-cancelled/40' : '')]) }}
        />

        <div class="min-w-0">
            @if ($label || ! $slot->isEmpty())
                <label for="{{ $id }}" class="cursor-pointer select-none text-sm text-ink">
                    {{ $label }}
                    @if ($required)<span class="text-status-cancelled">*</span>@endif
                    {{-- Extra inline content (e.g. a link to the T&C text). --}}
                    @if (! $slot->isEmpty())<span>{{ $slot }}</span>@endif
                </label>
            @endif

            @if ($hint)
                <p class="mt-0.5 text-xs text-ink-muted">{{ $hint }}</p>
            @endif
        </div>
    </div>

    @error($name)
        <p class="input-error-text">
            <svg class="h-3.5 w-3.5 shrink-0" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M18 10A8 8 0 112 10a8 8 0 0116 0zm-9-4a1 1 0 112 0v4a1 1 0 11-2 0V6zm1 8a1 1 0 100-2 1 1 0 000 2z" clip-rule="evenodd"/></svg>
            {{ $message }}
        </p>
    @enderror
</div>
