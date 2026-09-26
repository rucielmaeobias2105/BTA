@extends('layouts.guest')

@section('title', 'Forgot Password')

@section('content')
    @include('auth.passwords.steps', ['currentStep' => 1])

    <div class="mb-7 text-center lg:text-left">
        <h1 class="font-display text-3xl font-bold tracking-tight text-primary">Forgot Password</h1>
        <p class="mt-2 text-sm text-ink-muted">Enter your email and we'll send you a 6-digit verification code.</p>
    </div>

    <x-ui.errors />

    <form method="POST" action="{{ route('password.email') }}" class="space-y-4" novalidate>
        @csrf

        <x-ui.form.input
            name="email"
            type="email"
            label="Email Address"
            placeholder="you@example.com"
            required
            autocomplete="email"
        />

        <button type="submit" class="btn-primary w-full">Next Step</button>
    </form>

    <p class="mt-6 text-center text-sm text-ink-muted">
        <a href="{{ route('login') }}" class="font-medium text-primary underline underline-offset-2 hover:text-primary-dark">Back to log in</a>
    </p>
@endsection
