@extends('layouts.guest')

@section('title', 'Register')
@section('heading', 'Create your account')

@section('content')
    <div class="mb-7 text-center lg:text-left">
        <h1 class="font-display text-3xl font-bold tracking-tight text-primary">Register</h1>
        <p class="mt-2 text-sm text-ink-muted">Create your account to book and manage your appointments.</p>
    </div>

    <x-ui.alert type="error" class="mb-5" :dismissible="false">
        @foreach ($errors->all() as $error)
            <p>{{ $error }}</p>
        @endforeach
    </x-ui.alert>

    <form method="POST" action="{{ route('register') }}" class="space-y-4" novalidate>
        @csrf

        <div class="grid gap-4 sm:grid-cols-2">
            <x-ui.form.input name="first_name" label="First Name" placeholder="Juan" required autocomplete="given-name" />
            <x-ui.form.input name="last_name" label="Last Name" placeholder="Dela Cruz" required autocomplete="family-name" />
        </div>

        <x-ui.form.input name="email" type="email" label="Email" placeholder="you@example.com" required autocomplete="email" />
        <x-ui.form.input name="contact_number" label="Contact Number" placeholder="09XX XXX XXXX" required autocomplete="tel" />

        <x-ui.form.password name="password" label="Password" required autocomplete="new-password" hint="Minimum of 8 characters." />
        <x-ui.form.password name="password_confirmation" label="Confirm Password" required autocomplete="new-password" />

        <x-ui.form.checkbox name="terms" value="1" required hint="I agree to the Terms and Conditions and Cancellation Policy.">
            <a href="{{ route('terms.show', 'booking') }}" target="_blank" class="font-medium text-primary underline underline-offset-2 hover:text-primary-dark">Terms and Conditions</a>
        </x-ui.form.checkbox>

        <button type="submit" class="btn-primary w-full">Register</button>
    </form>

    <p class="mt-6 text-center text-sm text-ink-muted">
        Already have an account?
        <a href="{{ route('login') }}" class="font-medium text-primary underline underline-offset-2 hover:text-primary-dark">Log in</a>
    </p>
@endsection
