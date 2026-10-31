@php
    /** @var \App\Models\User $user */
    $user = auth()->user();
    $navRole = $user->primaryRole();
    // Tryb wymuszony (konto bez wymaganej weryfikacji dwuetapowej): menu ukryte, dostępne tylko wylogowanie.
    $minimal = View::hasSection('minimal');

    $items = match ($navRole?->value) {
        'owner' => [
            ['label' => __('dashboard.nav.dashboard'), 'icon' => 'home', 'href' => route('dashboard'), 'active' => request()->routeIs('dashboard')],
            ['label' => __('dashboard.nav.search'), 'icon' => 'search'],
            ['label' => __('dashboard.nav.bookings'), 'icon' => 'clipboard'],
            ['label' => __('dashboard.nav.properties'), 'icon' => 'building'],
        ],
        'specialist' => [
            ['label' => __('dashboard.nav.today'), 'icon' => 'clock', 'href' => route('dashboard'), 'active' => request()->routeIs('dashboard')],
            ['label' => __('dashboard.nav.calendar'), 'icon' => 'calendar'],
            ['label' => __('dashboard.nav.requests'), 'icon' => 'inbox'],
            ['label' => __('dashboard.nav.history'), 'icon' => 'chart'],
            ['label' => __('dashboard.nav.profile'), 'icon' => 'user', 'href' => route('specialist.profile.edit'), 'active' => request()->routeIs('specialist.profile.*', 'specialist.certificates.*')],
        ],
        'admin' => [
            ['label' => __('dashboard.nav.dashboard'), 'icon' => 'home', 'href' => route('dashboard'), 'active' => request()->routeIs('dashboard')],
            ['label' => __('dashboard.nav.specialists'), 'icon' => 'user'],
            ['label' => __('dashboard.nav.reports'), 'icon' => 'flag'],
            ['label' => __('dashboard.nav.log'), 'icon' => 'list'],
        ],
        'technician' => [
            ['label' => __('dashboard.nav.my_day'), 'icon' => 'clock', 'href' => route('dashboard'), 'active' => request()->routeIs('dashboard')],
        ],
        default => [],
    };
@endphp
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="robots" content="noindex">
    <title>@yield('title') | {{ __('app.brand') }}</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="bg-paper-2 text-ink-900 antialiased">
    <a class="sr-only focus:not-sr-only focus:fixed focus:left-4 focus:top-4 focus:z-50 focus:bg-paper focus:px-4 focus:py-3" href="#main">
        {{ __('dashboard.nav.skip') }}
    </a>

    <div class="min-h-screen md:grid md:grid-cols-[15rem_minmax(0,1fr)]">
        {{-- Desktop: lewa kolumna 240 px (logo, menu, blok użytkownika) --}}
        <aside class="no-print hidden border-r border-line bg-paper md:sticky md:top-0 md:flex md:h-screen md:flex-col">
            <div class="px-3 pt-3">
                <x-brand :href="route('dashboard')" />
            </div>

            @unless ($minimal)
                <nav class="mt-4 flex-1 space-y-1 overflow-y-auto px-3" aria-label="{{ __('dashboard.nav.main_label') }}">
                    @foreach ($items as $item)
                        <x-nav-item :label="$item['label']" :icon="$item['icon']" :href="$item['href'] ?? null" :active="$item['active'] ?? false" />
                    @endforeach
                </nav>
            @else
                <div class="flex-1"></div>
            @endunless

            <div class="border-t border-line p-3">
                @include('partials.account-links', ['minimal' => $minimal, 'showIdentity' => true])
            </div>
        </aside>

        <div class="flex min-h-screen min-w-0 flex-col pb-24 md:pb-0">
            {{-- Telefon: górny pasek z logo i menu konta (Konto otwiera listę, nie jest pozycją dolnego paska) --}}
            <header class="no-print sticky top-0 z-20 border-b border-line bg-paper md:hidden">
                <div class="flex items-center justify-between gap-2 px-4 py-1">
                    <x-brand :href="route('dashboard')" />
                    @if ($minimal)
                        <form method="POST" action="{{ route('logout') }}">
                            @csrf
                            <button type="submit" class="inline-flex min-h-11 items-center gap-2 rounded-md px-2 text-base font-semibold text-brand-700">
                                <x-icon name="logout" />{{ __('dashboard.account_menu.logout') }}
                            </button>
                        </form>
                    @else
                        <details class="relative">
                            <summary class="inline-flex min-h-11 cursor-pointer list-none items-center gap-1.5 rounded-md border border-line px-3 text-base font-semibold [&::-webkit-details-marker]:hidden" aria-label="{{ __('ui.account_menu_open', ['name' => $user->name]) }}">
                                <x-icon name="user" />
                                {{ __('dashboard.nav.account') }}
                                <x-icon name="chevron-down" class="size-4" />
                            </summary>
                            <div class="absolute right-0 top-full z-30 mt-2 w-72 max-w-[calc(100vw-2rem)] rounded-lg border border-line bg-paper p-3">
                                @include('partials.account-links', ['minimal' => false, 'showIdentity' => true])
                            </div>
                        </details>
                    @endif
                </div>
            </header>

            <main id="main" tabindex="-1" class="mx-auto w-full max-w-4xl flex-1 px-4 py-6 md:px-8 md:py-10">
                {{-- Strony z długimi sekcjami (np. Profil) ustawiają sekcję flash_sticky: komunikat zostaje widoczny po przeskoku do kotwicy. --}}
                <x-flash :class="'mb-6 '.(View::hasSection('flash_sticky') ? 'sticky top-16 z-10 md:top-2' : '')" />
                @yield('content')
            </main>

            <footer class="no-print border-t border-line bg-paper px-4 py-5 text-base text-ink-600 md:px-8">
                {{-- DO WERYFIKACJI PRZEZ PRAWNIKA --}}
                <div class="mx-auto max-w-4xl space-y-2">
                    <p class="font-semibold text-ink-900">{{ __('app.brand') }}</p>
                    @include('partials.legal-links', ['withSoon' => true])
                    <p class="text-sm">{{ __('dashboard.footer.payments_note') }}</p>
                </div>
            </footer>
        </div>

        {{-- Telefon: dolny pasek menu, nie zasłania treści (padding strony) i uwzględnia safe-area --}}
        @unless ($minimal)
            <nav class="no-print fixed inset-x-0 bottom-0 z-30 border-t border-line bg-paper pb-[env(safe-area-inset-bottom)] md:hidden" aria-label="{{ __('dashboard.nav.main_label') }}">
                <ul class="flex">
                    @foreach ($items as $item)
                        <li class="min-w-0 flex-1">
                            <x-nav-item :mobile="true" :label="$item['label']" :icon="$item['icon']" :href="$item['href'] ?? null" :active="$item['active'] ?? false" />
                        </li>
                    @endforeach
                </ul>
            </nav>
        @endunless
    </div>

    @stack('scripts')
</body>
</html>
