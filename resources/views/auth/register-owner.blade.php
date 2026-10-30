@extends('layouts.auth')

@section('title', __('auth.register.title'))

@section('content')
    <p class="text-sm font-semibold text-brand-700">{{ __('auth.register.step', ['current' => 2, 'total' => 2]) }}</p>
    <h1 class="mt-1 text-2xl font-semibold text-ink-900">{{ __('auth.register.type_owner') }}</h1>

    <div class="mt-6">
        <x-error-summary />

        <x-form :action="route('register.owner.store')" class="space-y-5">
            <x-form-field name="name" :label="__('auth.register.name')" autocomplete="name" :required="true" />
            <x-form-field name="email" type="email" :label="__('auth.register.email')" autocomplete="email" inputmode="email" :required="true" />
            <x-password-field :label="__('auth.register.password')" :hint="__('auth.password_hint')" />

            <div class="space-y-3">
                <x-checkbox-field name="terms" :required="true">{{ __('auth.register.consent_terms') }}</x-checkbox-field>
                <p class="ml-9 flex flex-wrap gap-x-4 text-sm">
                    <a class="prose-link" href="{{ route('terms') }}" target="_blank" rel="noopener">{{ __('auth.register.terms_link') }} {{ __('auth.opens_new_tab') }}</a>
                    <a class="prose-link" href="{{ route('privacy') }}" target="_blank" rel="noopener">{{ __('auth.register.privacy_link') }} {{ __('auth.opens_new_tab') }}</a>
                </p>
                <x-checkbox-field name="marketing" :optional="true">{{ __('auth.register.consent_marketing') }}</x-checkbox-field>
            </div>

            <x-turnstile />

            <x-submit-button :busy="__('auth.register.submitting')">{{ __('auth.register.submit_owner') }}</x-submit-button>

            <p class="text-sm text-ink-600">{{ __('auth.register.gdpr_note') }}</p>
        </x-form>
    </div>
@endsection

@section('below')
    <p class="border-t border-line pt-5 text-base">
        <a class="prose-link inline-flex min-h-11 items-center" href="{{ route('register') }}">{{ __('auth.register.back') }}</a>
        <span class="mx-2 text-ink-600" aria-hidden="true">|</span>
        {{ __('auth.register.have_account') }}
        <a class="prose-link inline-flex min-h-11 items-center font-semibold" href="{{ route('login') }}">{{ __('auth.register.login_link') }}</a>
    </p>
@endsection
