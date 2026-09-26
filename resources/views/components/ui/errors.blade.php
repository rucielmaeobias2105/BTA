{{--
    Validation error summary. Renders nothing at all when there are no errors,
    so pages can drop it unconditionally instead of guarding with @if.

    Pass specific field names to narrow the list:
        <x-ui.errors :fields="['email', 'password']" />
--}}
@props(['fields' => null, 'title' => 'Please fix the following:'])

@if ($errors->any())
    <x-ui.alert type="error" class="{{ $attributes->get('class') }}" :dismissible="false">
        @if ($title)
            <p class="mb-1 font-semibold">{{ $title }}</p>
        @endif

        <ul class="list-inside list-disc space-y-0.5">
            @foreach ($fields ? $errors->only($fields) : $errors->all() as $error)
                <li>{{ $error }}</li>
            @endforeach
        </ul>
    </x-ui.alert>
@endif
