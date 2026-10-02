@extends('layouts.auth')

@section('title', 'New Password')

@section('content')
    @include('auth.passwords.steps', ['currentStep' => 3])

    <div class="mb-6 text-center lg:text-left">
        <h1 class="font-display text-2xl font-bold tracking-tight text-primary">New Password</h1>
        <p class="mt-2 text-sm text-ink-muted">Choose a strong password you haven't used before.</p>
    </div>


    <form method="POST" action="{{ route('password.update') }}" class="space-y-4" novalidate>
        @csrf

        {{-- Eye toggles, same as every other password field in the app. --}}
        <x-ui.form.password
            name="password"
            label="New Password"
            icon="heroicon-o-lock-closed"
            :toggle-icon="'heroicon-o-eye'"
            required
            autocomplete="new-password"
            hint="Minimum of 8 characters."
        />

        <x-ui.form.password
            name="password_confirmation"
            label="Confirm New Password"
            icon="heroicon-o-lock-closed"
            :toggle-icon="'heroicon-o-eye'"
            required
            autocomplete="new-password"
        />

        <button type="submit" class="btn-primary w-full">Reset Password</button>
    </form>
@endsection
