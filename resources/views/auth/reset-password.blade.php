@extends('layouts.auth')

@section('title', $errors->has('email') ? __('auth.reset.invalid_title') : __('auth.reset.title'))

@section('content')
    @if ($errors->has('email'))
        {{-- Link nieważny, wygasły lub już użyty (Fortify zwraca błąd na polu email): osobny stan zamiast formularza --}}
        <h1 tabindex="-1" x-data="focusOnLoad" class="text-2xl font-semibold text-ink-900">{{ __('auth.reset.invalid_title') }}</h1>
        <p class="mt-3 text-ink-600" role="alert">{{ __('auth.reset.invalid_text') }}</p>
        <div class="mt-6">
            <x-button :href="route('password.request')" :full="true">{{ __('auth.reset.request_new') }}</x-button>
        </div>
    @else
        <h1 class="text-2xl font-semibold text-ink-900">{{ __('auth.reset.title') }}</h1>

        <div class="mt-6">
            <x-error-summary />

            <x-form :action="route('password.store')" class="space-y-5">
                <input type="hidden" name="token" value="{{ $token }}">

                {{-- E-mail widoczny i tylko do odczytu: osoba widzi, którego konta dotyczy zmiana --}}
                <x-form-field name="email" type="email" :label="__('auth.reset.email')" :value="$email" autocomplete="username" :readonly="true" />

                <x-password-field :label="__('auth.reset.password')" :hint="__('auth.password_hint')" />

                <x-submit-button :busy="__('auth.reset.submitting')">{{ __('auth.reset.submit') }}</x-submit-button>
            </x-form>
        </div>
    @endif
@endsection

@section('below')
    <p class="border-t border-line pt-5">
        <a class="prose-link inline-flex min-h-11 items-center" href="{{ route('login') }}">{{ __('auth.forgot.back') }}</a>
    </p>
@endsection
