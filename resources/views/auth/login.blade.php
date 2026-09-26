@extends('layouts.guest')

@section('title', 'Log In')

@section('content')
    <div class="mb-7 text-center lg:text-left">
        <h1 class="font-display text-3xl font-bold tracking-tight text-primary">Welcome Back</h1>
        <p class="mt-2 text-sm text-ink-muted">Log in to manage your appointments.</p>
    </div>

    <x-ui.errors />

    <form method="POST" action="{{ route('login') }}" class="space-y-4" novalidate>
        @csrf

        <x-ui.form.input
            name="login"
            label="Username or Email"
            placeholder="you@example.com"
            required
            autocomplete="username"
        />

        <div>
            <x-ui.form.password name="password" label="Password" required autocomplete="current-password" />
            <div class="mt-2 text-right">
                <a href="{{ route('password.request') }}" class="toggle-link">Forgot your password?</a>
            </div>
        </div>

        <x-ui.form.checkbox name="remember" value="1" label="Remember me" />

        <button type="submit" class="btn-primary w-full">Log In</button>
    </form>

    <p class="mt-6 text-center text-sm text-ink-muted">
        Don't have an account?
        <a href="{{ route('register') }}" class="font-medium text-primary underline underline-offset-2 hover:text-primary-dark">Register</a>
    </p>
@endsection
