@extends('layouts.auth')

@section('title', 'Register')

@section('content')
    <div class="mb-4">
        <x-ui.button :href="route('home')" variant="ghost" size="sm" icon="heroicon-o-arrow-left">
            Back to Home
        </x-ui.button>
    </div>

    <nav class="auth-tabs" aria-label="Authentication">
        <a href="{{ route('login') }}" class="auth-tab">Login</a>
        <a href="{{ route('register') }}" class="auth-tab auth-tab-active" aria-current="page">Register</a>
    </nav>

    <h1 class="font-display text-2xl font-bold tracking-tight text-primary">Create Account</h1>
    <p class="mt-1.5 text-sm text-ink-muted">
        Create your customer account and start booking.
    </p>


    <form method="POST" action="{{ route('register') }}" class="mt-5 space-y-3" novalidate>
        @csrf

        <div class="grid gap-4 sm:grid-cols-2">
            <x-ui.form.input name="first_name" label="First Name" placeholder="Juan" required autocomplete="given-name" />
            <x-ui.form.input name="last_name" label="Last Name" placeholder="Dela Cruz" required autocomplete="family-name" />
        </div>

        <div class="grid gap-4 sm:grid-cols-2">
            <x-ui.form.input name="email" type="email" label="Email" placeholder="you@example.com" required autocomplete="email" />
            <x-ui.form.input name="contact_number" label="Contact Number" placeholder="09XX XXX XXXX" required autocomplete="tel" />
        </div>

        <div class="grid gap-4 sm:grid-cols-2">
            {{-- Both fields get an eye. Confirmation is where a mistyped character
                     actually bites, and "the two do not match" with no way to see
                     either is a frustrating thing to debug. --}}
            <x-ui.form.password
                name="password"
                label="Password"
                icon="heroicon-o-lock-closed"
                :toggle-icon="'heroicon-o-eye'"
                required
                autocomplete="new-password"
                hint="Minimum of 8 characters."
            />
            <x-ui.form.password
                name="password_confirmation"
                label="Confirm Password"
                icon="heroicon-o-lock-closed"
                :toggle-icon="'heroicon-o-eye'"
                required
                autocomplete="new-password"
            />
        </div>

        <x-ui.form.checkbox name="terms" value="1" required hint="I agree to the Terms and Conditions and Cancellation Policy.">
            <x-terms.link :category="'booking'">Terms and Conditions</x-terms.link>
        </x-ui.form.checkbox>

        <button type="submit" class="btn-primary w-full">Create Account</button>
    </form>

    <p class="mt-6 text-center text-sm text-ink-muted">
        Already have an account?
        <a href="{{ route('login') }}" class="font-medium text-primary underline underline-offset-2 hover:text-primary-dark">Log in</a>
    </p>
@endsection
