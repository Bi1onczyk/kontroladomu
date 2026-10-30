@extends('layouts.auth')

@section('title', __('auth.twofa.challenge_title'))

@section('content')
    <h1 class="text-2xl font-semibold text-ink-900">{{ __('auth.twofa.challenge_title') }}</h1>

    {{-- Przełącznik: kod z aplikacji / kod awaryjny. Bez JS widać oba pola (serwer przyjmie którykolwiek). --}}
    <div x-data="challengeMode" data-initial="{{ $errors->has('recovery_code') ? 'recovery' : 'app' }}" class="mt-4">
        <x-form :action="route('two-factor.login.store')" class="space-y-5">
            <div x-show="isApp" class="space-y-2">
                <p class="text-ink-600">{{ __('auth.twofa.challenge_text') }}</p>
                <x-code-field name="code" :label="__('auth.twofa.code_label')" mode="totp" :autofocus="! $errors->has('recovery_code')" x-ref="code" />
            </div>

            <div x-cloak x-show="isRecovery" class="space-y-2">
                <x-code-field name="recovery_code" :label="__('auth.twofa.recovery_label')" :hint="__('auth.twofa.recovery_hint')" mode="recovery" x-ref="recovery" />
            </div>

            <x-submit-button :busy="__('auth.login.submitting')">{{ __('auth.twofa.challenge_submit') }}</x-submit-button>
        </x-form>

        <div class="mt-4">
            <button type="button" x-show="isApp" x-on:click="showRecovery" class="prose-link inline-flex min-h-11 items-center text-left">
                {{ __('auth.twofa.use_recovery') }}
            </button>
            <button type="button" x-cloak x-show="isRecovery" x-on:click="showApp" class="prose-link inline-flex min-h-11 items-center text-left">
                {{ __('auth.twofa.use_app_code') }}
            </button>
        </div>
    </div>

    <x-alert variant="info" class="mt-6">{{ __('auth.twofa.lost_everything', ['email' => config('app.contact_email')]) }}</x-alert>
@endsection

@section('below')
    <p class="border-t border-line pt-5">
        <a class="prose-link inline-flex min-h-11 items-center" href="{{ route('login') }}">{{ __('auth.twofa.locked_back') }}</a>
    </p>
@endsection
