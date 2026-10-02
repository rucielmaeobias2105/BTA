@props([
    // A `TermsCategory` case, or its string value.
    'category',

    // Styles the trigger as an inline link, which is what every call site wants:
    // these sit inside a checkbox label, a dialog footer and a table cell.
    'class' => 'font-medium text-primary underline underline-offset-2 transition hover:text-gold',
])

@php
    $value = $category instanceof \App\Enums\TermsCategory ? $category->value : (string) $category;
@endphp

{{--
    A link that opens the Terms dialog.

    An `<a>` with a real `href` to the standalone terms page, intercepted by
    Alpine — rather than a `<button>` that dispatches an event. That choice is
    the whole reason this component can be dropped in wherever a link used to
    be:

      - with the bundle loaded, `preventDefault` opens the dialog, so the
        customer never leaves the form they are in the middle of filling in;
      - without it, nothing intercepts the click and the browser follows the
        href to `/terms/{category}`, which is a complete page of the same
        policy.

    So the dialog is the experience and the page is the floor under it, rather
    than the dialog being the only way to read the terms at all.
--}}
<a
    href="{{ route('terms.show', $value) }}"
    class="{{ $class }}"
    x-on:click.prevent="$dispatch('terms-modal', { category: @js($value) })"
>{{ $slot }}</a>
