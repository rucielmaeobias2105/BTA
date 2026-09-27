<script setup>
import { computed, ref } from 'vue';

/**
 * Five-star rating, display-only or interactive.
 *
 * Ported from `components/ui/star-rating.blade.php`. The interactive variant's
 * `x-data="starRating(...)"` Alpine component became local `ref`s, and the
 * hidden `<input>` that carried the value is now `v-model`.
 */
const props = defineProps({
    modelValue: { type: Number, default: 0 },
    interactive: { type: Boolean, default: true },
    size: { type: String, default: 'md' },
    label: { type: String, default: 'Star rating' },
});

const emit = defineEmits(['update:modelValue']);

const SIZES = { sm: 'h-4 w-4', md: 'h-6 w-6', lg: 'h-9 w-9' };

const star = computed(() => SIZES[props.size] ?? SIZES.md);

// The Alpine component kept `hover` separate from `rating` so moving the mouse
// off a star restores the committed value rather than clearing it.
const hover = ref(0);
const display = computed(() => hover.value || props.modelValue);

const stars = [1, 2, 3, 4, 5];

const summary = computed(() => {
    if (!props.modelValue) return 'Not rated';

    return `${props.modelValue} ${props.modelValue === 1 ? 'star' : 'stars'}`;
});
</script>

<template>
    <div v-if="interactive" class="inline-flex items-center gap-1.5">
        <input type="hidden" name="rating" :value="modelValue">

        <div class="inline-flex items-center gap-1" role="radiogroup" :aria-label="label">
            <button
                v-for="position in stars"
                :key="position"
                type="button"
                role="radio"
                :aria-checked="modelValue === position ? 'true' : 'false'"
                :aria-label="`${position} star${position === 1 ? '' : 's'}`"
                class="rounded p-0.5 transition focus:outline-none focus-visible:ring-2 focus-visible:ring-gold"
                @mouseenter="hover = position"
                @mouseleave="hover = 0"
                @focus="hover = position"
                @blur="hover = 0"
                @click="emit('update:modelValue', position)"
            >
                <svg
                    class="transition-colors"
                    :class="[star, display >= position ? 'text-gold' : 'text-primary/20']"
                    viewBox="0 0 20 20"
                    fill="currentColor"
                >
                    <path d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.286 3.958a1 1 0 0 0 .95.69h4.162c.969 0 1.371 1.24.588 1.81l-3.367 2.446a1 1 0 0 0-.364 1.118l1.286 3.958c.3.921-.755 1.688-1.539 1.118l-3.367-2.446a1 1 0 0 0-1.175 0l-3.367 2.446c-.783.57-1.838-.197-1.538-1.118l1.286-3.958a1 1 0 0 0-.364-1.118L2.062 9.385c-.783-.57-.38-1.81.588-1.81h4.163a1 1 0 0 0 .95-.69l1.286-3.958Z" />
                </svg>
            </button>
        </div>

        <span class="ml-1 text-sm font-medium text-ink-muted">{{ summary }}</span>
    </div>

    <div v-else class="inline-flex items-center gap-0.5" :aria-label="`${label}: ${modelValue} out of 5`">
        <svg
            v-for="position in stars"
            :key="position"
            class="h-4 w-4"
            :class="position <= modelValue ? 'text-gold' : 'text-primary/20'"
            viewBox="0 0 20 20"
            fill="currentColor"
            aria-hidden="true"
        >
            <path d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.286 3.958a1 1 0 0 0 .95.69h4.162c.969 0 1.371 1.24.588 1.81l-3.367 2.446a1 1 0 0 0-.364 1.118l1.286 3.958c.3.921-.755 1.688-1.539 1.118l-3.367-2.446a1 1 0 0 0-1.175 0l-3.367 2.446c-.783.57-1.838-.197-1.538-1.118l1.286-3.958a1 1 0 0 0-.364-1.118L2.062 9.385c-.783-.57-.38-1.81.588-1.81h4.163a1 1 0 0 0 .95-.69l1.286-3.958Z" />
        </svg>
    </div>
</template>
