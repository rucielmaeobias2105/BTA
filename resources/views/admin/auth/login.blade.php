@extends('layouts.guest')

@section('title', 'Admin Sign In')

@section('content')
    <div class="mb-7 text-center lg:text-left">
        <p class="mb-2 inline-flex items-center gap-1.5 rounded-pill bg-gold/15 px-2.5 py-1 text-[10px] font-semibold uppercase tracking-wider text-gold-dark">
            <svg class="h-3 w-3" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M16.5 10.5V6.75a4.5 4.5 0 1 0-9 0v3.75m-.75 11.25h10.5a2.25 2.25 0 0 0 2.25-2.25v-6.75a2.25 2.25 0 0 0-2.25-2.25H6.75a2.25 2.25 0 0 0-2.25 2.25v6.75a2.25 2.25 0 0 0 2.25 2.25Z"/></svg>
            Staff Portal
        </p>

        <h1 class="font-display text-3xl font-bold tracking-tight text-primary">Admin Login</h1>
        <p class="mt-2 text-sm text-ink-muted">Sign in with your staff credentials.</p>
    </div>

    <x-ui.errors />

    <form method="POST" action="{{ route('admin.login.store') }}" class="space-y-4" novalidate>
        @csrf

        <x-ui.form.input
            name="username"
            label="Username"
            placeholder="admin"
            required
            autocomplete="username"
        />

        <x-ui.form.password
            name="password"
            label="Password"
            required
            autocomplete="current-password"
        />

        <x-ui.form.checkbox name="remember" value="1" label="Remember me" />

        <button type="submit" class="btn-primary w-full">Log In</button>
    </form>

    <p class="mt-6 text-center text-sm text-ink-muted">
        <a href="{{ route('home') }}" class="font-medium text-primary underline underline-offset-2 hover:text-primary-dark">Back to the website</a>
    </p>
@endsection
