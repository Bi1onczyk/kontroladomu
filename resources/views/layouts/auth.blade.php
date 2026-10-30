<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex">
    <title>@yield('title') | {{ __('app.brand') }}</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-screen bg-paper-2 text-ink-900 antialiased">
    <a class="sr-only focus:not-sr-only focus:fixed focus:left-4 focus:top-4 focus:z-50 focus:bg-paper focus:px-4 focus:py-3" href="#main">
        {{ __('app.skip_to_content') }}
    </a>

    {{-- Nagłówek ekranów auth: logo i jeden link powrotny, bez menu głównego (UX §0). --}}
    <header class="border-b border-line bg-paper">
        <div class="mx-auto flex max-w-3xl items-center justify-between gap-4 px-4 py-2">
            <x-brand />
            <a class="prose-link inline-flex min-h-11 items-center text-base" href="{{ route('home') }}">{{ __('auth.back_home') }}</a>
        </div>
    </header>

    <main id="main" tabindex="-1" class="mx-auto w-full px-0 py-6 sm:px-4 sm:py-12 {{ View::hasSection('wide') ? 'max-w-3xl' : 'max-w-[440px]' }}">
        <div class="border-y border-line bg-paper px-4 py-6 sm:rounded-lg sm:border sm:px-8 sm:py-8">
            @yield('content')
        </div>

        @hasSection('below')
            <div class="mt-6 px-4 text-center sm:px-0">
                @yield('below')
            </div>
        @endif

        <p class="mt-6 px-4 text-center text-sm text-ink-600 sm:px-0">
            {{ __('auth.help_line', ['email' => config('app.contact_email')]) }}
        </p>
    </main>

    @stack('scripts')
</body>
</html>
