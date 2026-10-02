@props([
    'title' => 'Adjust Your Photo',
    'maxWidth' => 'max-w-lg',
    'titleId' => 'photo-crop-title',
])

{{--
    The crop dialog for a profile picture.

    A shell, like `x-ui.dialog`: it reads `open`, `close()` and `busy` from the
    `profilePhotoCrop` scope that encloses it, so the state stays in one place and
    this stays reusable. It renders the cropper's target image and the two buttons;
    everything about the crop itself — the aspect ratio, the output size, what
    happens to the file — lives in that component.

    The image needs a real height before Cropper attaches to it: `x-ui.dialog`'s
    body is a scrolling flex child, and a cropper in an auto-height box collapses
    to nothing. Hence the explicit `h-[22rem]` on the wrapper rather than padding
    around an unsized element.

    The icon is a viewfinder rather than the reference project's scissors: it is
    the frame the user is adjusting, which is what the dialog is about, and
    Heroicons has no crop glyph — `heroicon-o-crop` does not exist and naming it
    takes the whole page down with a 500.
--}}
<x-ui.dialog :title="$title" :max-width="$maxWidth" :title-id="$titleId" :scrollable="false" icon="heroicon-o-viewfinder-circle">
    <div class="flex h-[22rem] items-center justify-center overflow-hidden rounded-xl bg-linen/60">
        {{-- Cropper replaces this element's parent with its own markup on
             attach, so it must be a plain <img> with no Tailwind sizing: any
             width/height set here would fight the cropper's own layout. --}}
        <img
            id="profile-photo-crop"
            alt="Crop preview"
            class="max-h-full max-w-full"
        >
    </div>

    <x-slot:footer>
        <button
            type="button"
            class="btn-secondary"
            x-on:click="close()"
        >Cancel</button>

        <button
            type="button"
            class="btn-primary"
            x-bind:disabled="busy"
            x-on:click="apply()"
        >
            Apply Crop
        </button>
    </x-slot:footer>
</x-ui.dialog>
