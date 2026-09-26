<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="h-full">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="theme-color" content="#6E211B">
    <meta name="robots" content="noindex, nofollow">

    <title>@yield('title', 'Sign in') — {{ config('app.name') }}</title>

    <link rel="icon" href="{{ asset('images/tab_logo.png') }}">

    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @stack('head')
</head>
<body class="flex min-h-full flex-col bg-linen">
    <div class="flex min-h-screen">
        {{-- Brand panel (desktop) --}}
        <div class="relative hidden w-1/2 overflow-hidden bg-primary lg:block">
            <div class="absolute inset-0 bg-[radial-gradient(circle_at_30%_20%,rgba(201,162,75,0.28),transparent_55%),radial-gradient(circle_at_75%_80%,rgba(232,201,192,0.22),transparent_50%)]"></div>

            <div class="relative flex h-full flex-col justify-between p-12">
                <x-brand.logo size="lg" class="text-cream" />

                <div class="max-w-md">
                    <h2 class="font-display text-4xl font-bold leading-tight text-cream">
                        {{ $panelTitle ?? 'Glow & Luxury,<br>Reserved For You.' }}
                    </h2>
                    <p class="mt-4 text-sm leading-relaxed text-cream/75">
                        {{ $panelText ?? 'Book your treatments, track your appointments, and enjoy a beauty experience tailored around you.' }}
                    </p>
                </div>

                <p class="text-xs text-cream/50">{{ config('app.name') }} &middot; {{ now()->year }}</p>
            </div>
        </div>

        {{-- Form panel --}}
        <div class="flex w-full flex-col justify-center px-5 py-12 sm:px-10 lg:w-1/2 lg:px-16">
            <div class="mx-auto w-full max-w-md">
                <div class="mb-8 lg:hidden">
                    <x-brand.logo href="{{ route('home') }}" size="md" />
                </div>

                @yield('content')
            </div>
        </div>
    </div>

    @stack('scripts')
</body>
</html>
