{{--
    Validation error summary. Renders nothing at all when there are no errors,
    so pages can drop it unconditionally instead of guarding with @if.

    Pass specific field names to narrow the list:
        <x-ui.errors :fields="['email', 'password']" />

    ------------------------------------------------------------------
    NOTHING RENDERS THIS ANY MORE. Do not reach for it on a new form.
    ------------------------------------------------------------------

    Every one of the 24 usages was removed when validation messages became
    toasts. `bootstrap/app.php` flashes the error bag through
    `App\Support\ValidationToast`, and `<x-ui.toast-stack />` — already mounted
    once per layout — is what shows it.

    It is kept rather than deleted because it is a working component and
    `x-ui.alert` still has its other callers, but it is now unused. The pattern
    it implements is the one the app moved away from: a red block pinned above
    the form, pushing the fields the person needs to correct down the page and
    then sitting there competing with them after it has been read.

    There is one case where it would be the right answer again — a page whose
    content is not a form at all, where there are no fields to point the person
    back to. Nothing in the app is like that today.

    If you are here because you want validation errors on a new form: don't
    render this. Validate as usual; the toast arrives on its own.
--}}
@props(['fields' => null, 'title' => 'Please fix the following:'])

{{--
    Narrowing has to go through MessageBag::get() per field. There is no
    MessageBag::only(), so the obvious $errors->only($fields) throws — and
    $errors->all() is already a flat list of strings, so it cannot be filtered
    by key either.
--}}
@php
    $messages = $fields
        ? collect($fields)->flatMap(fn ($field) => $errors->get($field))->values()
        : collect($errors->all());
@endphp

@if ($errors->any())
    <x-ui.alert type="error" class="{{ $attributes->get('class') }}" :dismissible="false">
        @if ($title)
            <p class="mb-1 font-semibold">{{ $title }}</p>
        @endif

        <ul class="list-inside list-disc space-y-0.5">
            @foreach ($messages as $error)
                <li>{{ $error }}</li>
            @endforeach
        </ul>
    </x-ui.alert>
@endif
