<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="h-full">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="theme-color" content="#6E211B">

    <title>@yield('title', 'Glow & Beauty Lounge') — {{ config('app.name') }}</title>

    <link rel="icon" href="{{ asset('images/tab_logo.png') }}">

    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @stack('head')
</head>
<body class="flex min-h-full flex-col bg-linen">
    <a href="#main" class="sr-only focus:not-sr-only focus:absolute focus:left-4 focus:top-4 focus:z-50 focus:rounded-lg focus:bg-primary focus:px-4 focus:py-2 focus:text-cream">
        Skip to content
    </a>

    @include('partials.customer.nav')

    <main id="main" class="flex-1">
        @if (session('status'))
            <div class="mx-auto w-full max-w-7xl px-4 pt-5 sm:px-6 lg:px-8">
                <x-ui.alert type="success">{{ session('status') }}</x-ui.alert>
            </div>
        @endif

        @yield('content')
    </main>

    @include('partials.customer.footer')

    @stack('modals')
    @stack('scripts')
</body>
</html>
