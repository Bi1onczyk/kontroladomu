@extends('layouts.auth')

@section('title', __('auth.forgot.title'))

@section('content')
    <h1 class="text-2xl font-semibold text-ink-900">{{ __('auth.forgot.title') }}</h1>

    @if (session('status'))
        {{-- Sukces: zawsze ten sam komunikat, także gdy konta nie ma (neutralna odpowiedź). Fokus na komunikat. --}}
        <div class="mt-6" tabindex="-1" x-data="focusOnLoad">
            <x-alert variant="success" role="status">{{ session('status') }}</x-alert>
        </div>

        <div class="mt-5 space-y-3" x-data="resendCountdown" data-seconds="60" data-template="{{ __('auth.forgot.resend_in', ['seconds' => ':seconds']) }}" data-ready-text="{{ __('auth.forgot.resend_ready') }}">
            <p class="text-sm text-ink-600" aria-live="off" x-show="waiting" x-text="text">{{ __('auth.forgot.resend_in', ['seconds' => 60]) }}</p>
            <p class="sr-only" role="status" x-text="announcement"></p>
            <div x-cloak x-show="ready">
                <x-button :href="route('password.request')" variant="secondary" :full="true">{{ __('auth.forgot.resend') }}</x-button>
            </div>
        </div>
    @else
        <p class="mt-2 text-ink-600">{{ __('auth.forgot.intro') }}</p>

        <div class="mt-6">
            <x-error-summary />

            <x-form :action="route('password.email')" class="space-y-5">
                <x-form-field name="email" type="email" :label="__('auth.forgot.email')" autocomplete="email" inputmode="email" :required="true" autofocus />

                <x-turnstile />

                <x-submit-button :busy="__('auth.forgot.submitting')">{{ __('auth.forgot.submit') }}</x-submit-button>
            </x-form>
        </div>
    @endif
@endsection

@section('below')
    <p class="border-t border-line pt-5">
        <a class="prose-link inline-flex min-h-11 items-center" href="{{ route('login') }}">{{ __('auth.forgot.back') }}</a>
    </p>
@endsection
