@props([
    'label' => null,
    'name' => null,
    'value' => null,
    'id' => null,
    'hint' => null,
    'required' => false,
    'placeholder' => null,
    'options' => [],
    'includeBlank' => false,
    'blankLabel' => 'Select an option',
])

@php
    $id ??= $name;
    $hasError = $name && $errors->has($name);
    $describedBy = collect([
        $hint && $id ? $id.'-hint' : null,
        $hasError && $id ? $id.'-error' : null,
    ])->filter()->join(' ');
@endphp

<div>
    @if ($label)
        <label for="{{ $id }}" class="label">
            {{ $label }}
            @if ($required)<span class="text-status-cancelled">*</span>@endif
        </label>
    @endif

    <select
        id="{{ $id }}"
        name="{{ $name }}"
        @if ($required) required @endif
        @if ($describedBy) aria-describedby="{{ $describedBy }}" @endif
        @if ($hasError) aria-invalid="true" @endif
        {{ $attributes->merge(['class' => 'input pr-9 '.($hasError ? 'input-error' : '')]) }}
    >
        @if ($includeBlank)
            <option value="">{{ $blankLabel }}</option>
        @endif

        @foreach ($options as $optionValue => $optionLabel)
            <option value="{{ $optionValue }}" @selected((string) old($name, $value) === (string) $optionValue)>
                {{ $optionLabel }}
            </option>
        @endforeach
    </select>

    @if ($hint)
        <p id="{{ $id }}-hint" class="input-hint">{{ $hint }}</p>
    @endif

    @error($name)
        <p id="{{ $id }}-error" class="input-error-text">
            <svg class="h-3.5 w-3.5 shrink-0" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M18 10A8 8 0 112 10a8 8 0 0116 0zm-9-4a1 1 0 112 0v4a1 1 0 11-2 0V6zm1 8a1 1 0 100-2 1 1 0 000 2z" clip-rule="evenodd"/></svg>
            {{ $message }}
        </p>
    @enderror
</div>
