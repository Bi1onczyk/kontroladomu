<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="description" content="@yield('meta_description', __('app.home.description'))">
    @hasSection('robots')
        <meta name="robots" content="@yield('robots')">
    @endif
    @hasSection('canonical')
        <link rel="canonical" href="@yield('canonical')">
    @endif
    <title>@hasSection('title')@yield('title') | {{ __('app.brand') }}@else{{ __('app.brand') }}@endif</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-screen bg-paper text-ink-900 antialiased">
    <a class="sr-only focus:not-sr-only focus:fixed focus:left-4 focus:top-4 focus:z-50 focus:bg-paper focus:px-4 focus:py-3" href="#main">
        {{ __('app.skip_to_content') }}
    </a>

    <header class="border-b border-line bg-paper">
        <div class="mx-auto flex max-w-6xl flex-wrap items-center justify-between gap-x-6 gap-y-1 px-5 py-3">
            <x-brand />
            <nav class="flex flex-wrap items-center justify-end gap-x-5 gap-y-1 text-base font-medium" aria-label="{{ __('app.navigation.main') }}">
                @if (request()->routeIs('home'))
                    <a class="inline-flex min-h-11 items-center text-ink-600 underline-offset-4 hover:text-brand-700 hover:underline" href="#dla-wlascicieli">
                        {{ __('app.navigation.owners') }}
                    </a>
                    <a class="inline-flex min-h-11 items-center text-ink-600 underline-offset-4 hover:text-brand-700 hover:underline" href="#dla-fachowcow">
                        {{ __('app.navigation.specialists') }}
                    </a>
                @endif
                @auth
                    <a class="inline-flex min-h-11 items-center font-semibold text-brand-700 underline-offset-4 hover:underline" href="{{ route('dashboard') }}">
                        {{ __('ui.to_dashboard') }}
                    </a>
                @else
                    <a class="inline-flex min-h-11 items-center text-ink-900 underline-offset-4 hover:text-brand-700 hover:underline" href="{{ route('login') }}">
                        {{ __('ui.login') }}
                    </a>
                    <x-button :href="route('register')" class="min-h-11 py-1.5">{{ __('ui.register') }}</x-button>
                @endauth
            </nav>
        </div>
    </header>

    <main id="main" tabindex="-1">
        @yield('content')
    </main>

    <footer class="border-t border-line bg-paper-2">
        {{-- DO WERYFIKACJI PRZEZ PRAWNIKA --}}
        <div class="mx-auto flex max-w-6xl flex-col gap-3 px-5 py-6 text-base text-ink-600 sm:flex-row sm:items-center sm:justify-between">
            <span>{{ __('app.brand') }}: {{ __('app.home.footer') }}</span>
            @include('partials.legal-links')
        </div>
    </footer>

    @stack('scripts')
</body>
</html>
