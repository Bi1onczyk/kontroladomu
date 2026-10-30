@props([
    'code',
    'title',
    'text',
    'home' => true,
    'auth' => false,
    'back' => false,
])

@php
    // Sprawdzenie logowania w osłonie: strona błędu nie może sama wywołać kolejnego błędu (np. gdy baza sesji nie działa).
    $loggedIn = false;
    if ($auth) {
        try {
            $loggedIn = auth()->check();
        } catch (\Throwable) {
            $loggedIn = false;
        }
    }
@endphp

{{-- Strona błędu: H1 z fokusem, jedno zdanie wyjaśnienia, jeden przycisk główny, kod błędu małym drukiem (bez emoji i ilustracji). --}}
<h1 tabindex="-1" x-data="focusOnLoad" class="text-2xl font-semibold text-ink-900">{{ $title }}</h1>
<p class="mt-3 text-ink-900" role="alert">{{ $text }}</p>

<div class="mt-6 space-y-3">
    @if ($loggedIn)
        <x-button :href="route('dashboard')" :full="true">{{ __('auth.errors.dashboard') }}</x-button>
    @elseif ($auth)
        <x-button :href="route('login')" :full="true">{{ __('auth.errors.login') }}</x-button>
    @elseif ($home)
        <x-button :href="route('home')" :full="true">{{ __('auth.errors.home') }}</x-button>
    @endif

    @if ($back)
        <x-button :href="url()->previous()" variant="secondary" :full="true">{{ __('auth.errors.previous') }}</x-button>
    @endif

    {{ $slot }}
</div>

<p class="mt-6 text-sm text-ink-600">{{ __('auth.errors.code', ['code' => $code]) }}</p>
