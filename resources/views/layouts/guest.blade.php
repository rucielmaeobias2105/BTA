<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="h-full">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="theme-color" content="#7A241B">
    <meta name="robots" content="noindex, nofollow">

    <title>@yield('title', 'Sign in') — {{ config('app.name') }}</title>

    <link rel="icon" href="{{ asset('images/tab_logo.png') }}">

    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @stack('head')
</head>
<body class="min-h-full bg-linen">
    <div
        class="auth-page"
        style="--auth-visual-image: url('{{ asset('images/'.($authImage ?? 'hero-salon.jpg')) }}')"
    >
        {{-- Brand panel: photo + centred wordmark. Each auth screen supplies
             its own headline so the panel speaks to that step. --}}
        <div class="auth-visual">
            <div class="auth-brand">
                <img src="{{ asset('images/tab_logo.png') }}" alt="Balai ti Arjud" class="auth-brand-logo">

                <span class="auth-brand-title">Balai ti Arjud</span>
                <span class="auth-brand-sub">Glow &amp; Co. Beauty Lounge</span>

                <h2 class="auth-brand-heading">{!! $panelTitle ?? 'Beauty, Relaxation,<br>and Hospitality' !!}</h2>
                <p class="auth-brand-script">{{ $panelScript ?? 'in one elegant experience.' }}</p>
            </div>
        </div>

        {{-- Form panel --}}
        <div class="auth-box">
            <div class="auth-form">
                <div class="mb-8 text-center lg:hidden">
                    <x-brand.logo href="{{ route('home') }}" size="md" />
                </div>

                @yield('content')
            </div>
        </div>
    </div>

    @stack('scripts')
</body>
</html>
