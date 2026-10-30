@extends('layouts.auth')

@section('title', __('auth.login.title'))

@section('content')
    <h1 class="text-2xl font-semibold text-ink-900">{{ __('auth.login.title') }}</h1>

    {{-- Komunikaty z sesji: po resecie hasła, po wygaśnięciu sesji lub wyzwania 2FA --}}
    <x-flash variant="info" class="mt-4" />

    <div class="mt-6">
        {{-- Błąd danych logowania: jedna ramka nad formularzem (ten sam tekst dla złego e-maila i złego hasła) --}}
        @if ($errors->has('email'))
            <x-alert variant="error" class="mb-5">{{ $errors->first('email') }}</x-alert>
        @endif

        <x-form :action="route('login.store')" class="space-y-5">
            <x-form-field name="email" type="email" :label="__('auth.login.email')" autocomplete="username" inputmode="email" :required="true" :hide-error="true" autofocus />

            <x-password-field :label="__('auth.login.password')" autocomplete="current-password" />

            <div class="flex flex-col gap-1 sm:flex-row sm:items-start sm:justify-between sm:gap-4">
                <div>
                    <x-checkbox-field name="remember">{{ __('auth.login.remember') }}</x-checkbox-field>
                    <p class="ml-9 text-sm text-ink-600">{{ __('auth.login.remember_hint') }}</p>
                </div>
                <a class="prose-link inline-flex min-h-11 items-center" href="{{ route('password.request') }}">{{ __('auth.login.forgot') }}</a>
            </div>

            @if ($requiresTurnstile ?? false)
                <x-turnstile />
            @endif

            <x-submit-button :busy="__('auth.login.submitting')">{{ __('auth.login.submit') }}</x-submit-button>
        </x-form>
    </div>
@endsection

@section('below')
    <p class="border-t border-line pt-5 text-base">
        {{ __('auth.login.no_account') }}
        <a class="prose-link inline-flex min-h-11 items-center font-semibold" href="{{ route('register') }}">{{ __('auth.login.register_link') }}</a>
    </p>
@endsection
