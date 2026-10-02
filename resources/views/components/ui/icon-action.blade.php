@props([
    'label',
    'icon',
    'href' => null,
    'action' => null,
    'method' => 'DELETE',
    'tone' => 'secondary',
    'confirm' => null,
])

{{--
    A single icon action for an admin table row — edit, delete, announce.

    A link renders as an anchor, anything else as a POST form carrying
    `@method`, so a destructive row is a real form submission rather than a
    `javascript:` handler, and works with JavaScript disabled. `confirm` is the
    browser's own prompt, which is what the rest of the admin already uses.
--}}
@php $classes = 'icon-action icon-action-'.(in_array($tone, ['primary', 'danger', 'ghost'], true) ? $tone : 'secondary'); @endphp

@if ($href)
    <a href="{{ $href }}" title="{{ $label }}" aria-label="{{ $label }}" {{ $attributes->merge(['class' => $classes]) }}>
        <x-dynamic-component :component="$icon" class="h-4 w-4" />
    </a>
@else
    <form method="POST" action="{{ $action }}" class="inline-flex" @if ($confirm) onsubmit="return confirm(@js($confirm))" @endif>
        @csrf
        @method($method)

        <button type="submit" title="{{ $label }}" aria-label="{{ $label }}" {{ $attributes->merge(['class' => $classes]) }}>
            <x-dynamic-component :component="$icon" class="h-4 w-4" />
        </button>
    </form>
@endif
