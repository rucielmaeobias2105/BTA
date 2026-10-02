@extends('layouts.auth')

@section('title', 'Admin Sign In')

@section('content')
    {{--
        The staff sign-in form.

        Four things that used to be here are gone, and one is fixed:

          - the "Staff Portal" pill. It sat above the heading as a label for a page
            that is obviously the staff portal — the URL is /admin/login, the
            brand panel next to it lists what an admin manages, and the tab title
            says ADMIN SIGN IN. Three labels for one fact.
          - "Sign in with your staff credentials." Same reason. The username field
            is labelled Username and the form is on the staff portal.
          - the "OR" rule and the "Back to the website" link. That pair exists to
            separate the submit from a second way out; with the second way removed
            the separator is a rule drawn across nothing.
          - and the Forgot password? placeholder was an inert `<span>`, so it read
            as a broken link. It is now a real route — see item 9.

        The submit's icon was a bare "↪" character. A text glyph is not an icon:
        it renders at whatever size and weight the button's font gives it, and it
        cannot inherit the button's colour the way an icon font can. It is now a
        Font Awesome `fa-right-to-bracket` — the sign-in mark, and the class the
        app's own stylesheet resolves (see the `all.min.css` import in
        `resources/css/app.css`, and the `.fa-right-to-bracket` rule in the built
        CSS).

        The old markup also spelled the icon as a bare `fa-…` class with no family,
        which is why nothing drew: `fa-right-to-bracket` on its own is the shape
        name, not a usable class. Font Awesome needs the family too —
        `fa-solid` — or the glyph is never given a font and renders as nothing.
    --}}
    <h1 class="admin-auth-card-title mb-8">Admin Login</h1>


    <form method="POST" action="{{ route('admin.login.store') }}" class="space-y-[23px]" novalidate>
        @csrf

        <x-ui.form.input
            name="username"
            label="Username"
            placeholder="Enter username"
            icon="heroicon-o-user-circle"
            required
            autocomplete="username"
        />

        <x-ui.form.password
            name="password"
            label="Password"
            placeholder="Enter password"
            icon="heroicon-o-lock-closed"
            :toggle-icon="'heroicon-o-eye'"
            required
            autocomplete="current-password"
        />

        <div class="mb-[27px] mt-1 flex items-center justify-between gap-5">
            <x-ui.form.checkbox name="remember" value="1" label="Remember me" wrapperClass="m-0" />

            <a href="{{ route('admin.password.request') }}" class="toggle-link shrink-0">
                Forgot password?
            </a>
        </div>

        <button type="submit" class="admin-auth-submit">
            {{-- `inline-flex` with a gap so the icon sits beside the label rather
                 than being part of the text: an icon as a raw glyph cannot be
                 spaced or sized, and this one could not be recoloured either.

                 The icon is an `<i>` rather than an inline `<svg>` because it comes
                 from Font Awesome, which is an icon font. The family class
                 (`fa-solid`) is what makes it resolve — see the note at the top. --}}
            <span class="inline-flex items-center justify-center gap-2">
                <i class="fa-solid fa-right-to-bracket text-base leading-none" aria-hidden="true"></i>
                Log In
            </span>
        </button>
    </form>
@endsection
