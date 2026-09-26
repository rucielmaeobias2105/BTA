@props([
    'label' => null,
    'name' => null,
    'type' => 'text',
    'value' => null,
    'id' => null,
    'hint' => null,
    'required' => false,
    'icon' => null,
    'wrapperClass' => '',
    'prefix' => null,
])

@php
    $id ??= $name;
    $hasError = $name && $errors->has($name);
    $describedBy = collect([
        $hint && $id ? $id.'-hint' : null,
        $hasError && $id ? $id.'-error' : null,
    ])->filter()->join(' ');
@endphp

<div class="{{ $wrapperClass }}">
    @if ($label)
        <label for="{{ $id }}" class="label">
            {{ $label }}
            @if ($required)<span class="text-status-cancelled">*</span>@endif
        </label>
    @endif

    <div class="relative">
        @if ($prefix)
            <span class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3.5 text-sm text-ink-muted">
                {{ $prefix }}
            </span>
        @elseif ($icon)
            <span class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3.5 text-ink-muted">
                <x-dynamic-component :component="$icon" class="h-4 w-4" />
            </span>
        @endif

        <input
            id="{{ $id }}"
            name="{{ $name }}"
            type="{{ $type }}"
            value="{{ old($name, $value) }}"
            @if ($required) required @endif
            @if ($describedBy) aria-describedby="{{ $describedBy }}" @endif
            @if ($hasError) aria-invalid="true" @endif
            {{ $attributes->merge([
                'class' => 'input '.($hasError ? 'input-error ' : '').($icon || $prefix ? 'pl-10 ' : ''),
            ]) }}
        />
    </div>

    @if ($hint)
        <p id="{{ $id }}-hint" class="input-hint">{{ $hint }}</p>
    @endif

    @error($name)
        <p id="{{ $id }}-error" class="input-error-text">
            <svg class="h-3.5 w-3.5 shrink-0" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M18 10A8 8 0 11 2 10a8 8 0 0116 0zm-9-4a1 1 0 112 0v4a1 1 0 11-2 0V6zm1 8a1 1 0 100-2 1 1 0 000 2z" clip-rule="evenodd"/></svg>
            {{ $message }}
        </p>
    @enderror
</div>
