@props([
    'name' => 'rating',
    'value' => 0,
    'interactive' => true,
    'size' => 'md',
    'label' => 'Star rating',
])

@php
    $sizes = ['sm' => 'h-4 w-4', 'md' => 'h-6 w-6', 'lg' => 'h-9 w-9'];
    $star = $sizes[$size] ?? $sizes['md'];
@endphp

@if ($interactive)
    <div x-data="starRating({{ (int) old($name, $value) }})" class="inline-flex items-center gap-1.5">
        <input type="hidden" name="{{ $name }}" x-model="rating" :value="rating" value="{{ old($name, $value) }}">

        <div class="inline-flex items-center gap-1" role="radiogroup" aria-label="{{ $label }}">
            @for ($i = 1; $i <= 5; $i++)
                <button
                    type="button"
                    role="radio"
                    :aria-checked="rating === {{ $i }} ? 'true' : 'false'"
                    aria-label="{{ $i }} star{{ $i === 1 ? '' : 's' }}"
                    @mouseenter="hover = {{ $i }}"
                    @mouseleave="hover = 0"
                    @focus="hover = {{ $i }}"
                    @click="set({{ $i }})"
                    class="rounded p-0.5 transition focus:outline-none focus-visible:ring-2 focus-visible:ring-gold"
                >
                    <svg
                        class="{{ $star }} transition-colors"
                        :class="display >= {{ $i }} ? 'text-gold' : 'text-primary/20'"
                        viewBox="0 0 20 20"
                        fill="currentColor"
                    >
                        <path d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.286 3.958a1 1 0 0 0 .95.69h4.162c.969 0 1.371 1.24.588 1.81l-3.367 2.446a1 1 0 0 0-.364 1.118l1.286 3.958c.3.921-.755 1.688-1.539 1.118l-3.367-2.446a1 1 0 0 0-1.175 0l-3.367 2.446c-.783.57-1.838-.197-1.538-1.118l1.286-3.958a1 1 0 0 0-.364-1.118L2.062 9.385c-.783-.57-.38-1.81.588-1.81h4.163a1 1 0 0 0 .95-.69l1.286-3.958Z"/>
                    </svg>
                </button>
            @endfor
        </div>

        <span class="ml-1 text-sm font-medium text-ink-muted" x-text="rating === 0 ? 'Not rated' : rating + (rating === 1 ? ' star' : ' stars')"></span>
    </div>
@else
    <div class="inline-flex items-center gap-0.5" aria-label="{{ $label }}: {{ (int) $value }} out of 5">
        @for ($i = 1; $i <= 5; $i++)
            <svg class="{{ $star }} {{ $i <= (int) $value ? 'text-gold' : 'text-primary/20' }}" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                <path d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.286 3.958a1 1 0 0 0 .95.69h4.162c.969 0 1.371 1.24.588 1.81l-3.367 2.446a1 1 0 0 0-.364 1.118l1.286 3.958c.3.921-.755 1.688-1.539 1.118l-3.367-2.446a1 1 0 0 0-1.175 0l-3.367 2.446c-.783.57-1.838-.197-1.538-1.118l1.286-3.958a1 1 0 0 0-.364-1.118L2.062 9.385c-.783-.57-.38-1.81.588-1.81h4.163a1 1 0 0 0 .95-.69l1.286-3.958Z"/>
            </svg>
        @endfor
    </div>
@endif
