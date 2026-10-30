@extends('layouts.auth')

@section('title', __('auth.twofa.locked_title'))

@php
    // Czas do końca blokady jako tekst z poprawną odmianą ("15 minut", "1 minutę", "mniej niż minutę").
    $wait = $secondsLeft < 60
        ? __('auth.wait.less_than_minute')
        : trans_choice('auth.wait.minutes', $minutesLeft);
@endphp

@section('content')
    <h1 tabindex="-1" x-data="focusOnLoad" class="text-2xl font-semibold text-ink-900">{{ __('auth.twofa.locked_title') }}</h1>

    <div class="mt-4 space-y-3" role="alert">
        <p>{{ __('auth.twofa.locked_text', ['wait' => $wait]) }}</p>
        <p class="text-ink-600">{{ __('auth.twofa.locked_hint') }}</p>
    </div>

    <x-alert variant="info" class="mt-6">{{ __('auth.twofa.lost_everything', ['email' => config('app.contact_email')]) }}</x-alert>

    <div class="mt-6">
        <x-button :href="route('login')" variant="secondary" :full="true">{{ __('auth.twofa.locked_back') }}</x-button>
    </div>
@endsection
