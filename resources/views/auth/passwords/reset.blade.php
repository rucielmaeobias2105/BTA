@extends('layouts.guest')

@section('title', 'New Password')

@section('content')
    @include('auth.passwords.steps', ['currentStep' => 3])

    <div class="mb-7 text-center lg:text-left">
        <h1 class="font-display text-3xl font-bold tracking-tight text-primary">New Password</h1>
        <p class="mt-2 text-sm text-ink-muted">Choose a strong password you haven't used before.</p>
    </div>

    <x-ui.alert type="error" class="mb-5" :dismissible="false">
        @foreach ($errors->all() as $error)
            <p>{{ $error }}</p>
        @endforeach
    </x-ui.alert>

    <form method="POST" action="{{ route('password.update') }}" class="space-y-4" novalidate>
        @csrf

        <x-ui.form.password
            name="password"
            label="New Password"
            required
            autocomplete="new-password"
            hint="Minimum of 8 characters."
        />

        <x-ui.form.password
            name="password_confirmation"
            label="Confirm New Password"
            required
            autocomplete="new-password"
        />

        <button type="submit" class="btn-primary w-full">Reset Password</button>
    </form>
@endsection
