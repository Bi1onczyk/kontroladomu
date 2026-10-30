@extends('layouts.auth')

@section('title', __('auth.twofa.confirm_password_title'))

@section('content')
    <h1 class="text-2xl font-semibold text-ink-900">{{ __('auth.twofa.confirm_password_title') }}</h1>
    <p class="mt-2 text-ink-600">{{ __('auth.twofa.confirm_password_text') }}</p>

    <div class="mt-6">
        <x-form :action="route('password.confirm.store')" class="space-y-5">
            <x-password-field :label="__('auth.login.password')" autocomplete="current-password" />

            <x-submit-button :busy="__('auth.security.submitting')">{{ __('auth.twofa.confirm_password_submit') }}</x-submit-button>
        </x-form>
    </div>
@endsection

@section('below')
    <p class="border-t border-line pt-5">
        <a class="prose-link inline-flex min-h-11 items-center" href="{{ route('account.security') }}">{{ __('auth.twofa.regenerate_cancel') }}</a>
    </p>
@endsection
