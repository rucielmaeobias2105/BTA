@extends('layouts.guest')

@section('title', 'Log In')

@section('content')
    {{-- Tabs: the reference puts sign-in and registration on one surface. --}}
    <nav class="auth-tabs" aria-label="Authentication">
        <a href="{{ route('login') }}" class="auth-tab auth-tab-active" aria-current="page">Login</a>
        <a href="{{ route('register') }}" class="auth-tab">Register</a>
    </nav>

    <h1 class="font-display text-2xl font-bold tracking-tight text-primary">Welcome Back</h1>
    <p class="mt-1.5 text-sm text-ink-muted">
        Log in to your account to book appointments and manage your bookings.
    </p>

    <x-ui.errors />

    <form method="POST" action="{{ route('login') }}" class="mt-6 space-y-4" novalidate>
        @csrf

        <x-ui.form.input
            name="login"
            label="Username or Email"
            placeholder="you@example.com"
            required
            autocomplete="username"
        />

        <x-ui.form.password name="password" label="Password" required autocomplete="current-password" />

        <div class="flex items-center justify-between gap-3 pt-1">
            <x-ui.form.checkbox name="remember" value="1" label="Remember me" class="mb-0" />
            <a href="{{ route('password.request') }}" class="toggle-link">Forgot your password?</a>
        </div>

        <button type="submit" class="btn-primary w-full">Log In</button>
    </form>

    <p class="mt-6 text-center text-sm text-ink-muted">
        Don't have an account?
        <a href="{{ route('register') }}" class="font-medium text-primary underline underline-offset-2 hover:text-primary-dark">Register</a>
    </p>
@endsection
