{{--
    The Facebook "f".

    Heroicons has no brand marks — they are deliberately logo-free — so the one
    glyph the salon actually needs lives here rather than being pasted inline at
    every place we link to the page. Not registered as an icon component because
    it is a brand mark rather than a UI symbol, and it is referenced by name in
    the About card and the footer rather than dispatched dynamically.
--}}
<svg
    {{ $attributes->merge(['class' => 'h-5 w-5']) }}
    viewBox="0 0 24 24"
    fill="currentColor"
    aria-hidden="true"
    focusable="false"
>
    <path d="M22 12.06C22 6.5 17.52 2 12 2S2 6.5 2 12.06c0 5.02 3.66 9.18 8.44 9.94v-7.03H7.9v-2.91h2.54V9.85c0-2.52 1.49-3.91 3.77-3.91 1.09 0 2.24.2 2.24.2v2.46h-1.26c-1.24 0-1.63.78-1.63 1.57v1.89h2.78l-.45 2.91h-2.33V22c4.78-.76 8.44-4.92 8.44-9.94Z"/>
</svg>
