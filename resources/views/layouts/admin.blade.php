<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="h-full">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="theme-color" content="#6E211B">
    <meta name="robots" content="noindex, nofollow">

    <title>@yield('title', 'Admin') — {{ config('app.name') }} Admin</title>

    <link rel="icon" href="{{ asset('images/tab_logo.png') }}">

    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @stack('head')
</head>
<body class="min-h-full bg-linen">
    <div x-data="{ sidebar: false }" class="min-h-screen lg:flex">
        {{-- Mobile sidebar backdrop --}}
        <div
            x-show="sidebar"
            x-transition.opacity
            x-cloak
            @click="sidebar = false"
            class="fixed inset-0 z-40 bg-primary/40 backdrop-blur-sm lg:hidden"
            aria-hidden="true"
        ></div>

        @include('partials.admin.sidebar')

        <div class="flex min-w-0 flex-1 flex-col">
            @include('partials.admin.topbar')

            <main class="flex-1 px-4 py-6 sm:px-6 lg:px-8 lg:py-8">
                @if (session('status'))
                    <div class="mb-5">
                        <x-ui.alert type="success">{{ session('status') }}</x-ui.alert>
                    </div>
                @endif

                @yield('content')
            </main>

            <footer class="border-t border-primary/10 px-4 py-4 text-center text-xs text-ink-muted sm:px-6 lg:px-8">
                {{ config('app.name') }} Admin &middot; Signed in as
                <span class="font-medium text-primary">{{ auth('admin')->user()->full_name }}</span>
            </footer>
        </div>
    </div>

    @stack('modals')
    @stack('scripts')
</body>
</html>
