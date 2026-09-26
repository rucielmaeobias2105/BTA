@extends('layouts.guest')

@section('title', 'Verify Code')

@section('content')
    @include('auth.passwords.steps', ['currentStep' => 2])

    <div class="mb-7 text-center lg:text-left">
        <h1 class="font-display text-3xl font-bold tracking-tight text-primary">Verify Email</h1>
        <p class="mt-2 text-sm text-ink-muted">
            We sent a 6-digit code to <span class="font-medium text-primary">{{ session('password_reset.email') }}</span>.
            It expires in {{ \App\Services\PasswordResetService::EXPIRY_MINUTES }} minutes.
        </p>
    </div>

    <x-ui.alert type="error" class="mb-5" :dismissible="false">
        @foreach ($errors->all() as $error)
            <p>{{ $error }}</p>
        @endforeach
    </x-ui.alert>

    <form method="POST" action="{{ route('password.verify') }}" class="space-y-4" novalidate>
        @csrf

        <x-ui.form.input
            name="code"
            label="Verification Code"
            placeholder="000000"
            required
            inputmode="numeric"
            maxlength="6"
            autocomplete="one-time-code"
            class="text-center font-display text-2xl tracking-[0.5em]"
            x-data
            x-on:input="$el.value = $el.value.replace(/\D/g, '').slice(0, 6)"
        />

        <button type="submit" class="btn-primary w-full">Verify</button>
    </form>

    <div class="mt-6 flex flex-col items-center justify-between gap-2 text-sm">
        <a href="{{ route('password.request') }}" class="font-medium text-primary underline underline-offset-2 hover:text-primary-dark">Change email address</a>
        <form method="POST" action="{{ route('password.email') }}" class="inline">
            @csrf
            <input type="hidden" name="email" value="{{ session('password_reset.email') }}">
            <button type="submit" class="toggle-link">Resend code</button>
        </form>
    </div>
@endsection
