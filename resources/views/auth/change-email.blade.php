@extends('layouts.auth')

@section('title', __('auth.email_change.title'))

@section('content')
    <h1 class="text-2xl font-semibold text-ink-900">{{ __('auth.email_change.title') }}</h1>
    <p class="mt-2 text-ink-600">{{ __('auth.email_change.intro') }}</p>
    <p class="mt-3">
        <x-emphasize key="auth.email_change.current" :value="$displayEmail" />
    </p>

    <div class="mt-6">
        <x-form :action="route('email.change.store')" class="space-y-5">
            <x-form-field name="email" type="email" :label="__('auth.email_change.new_email')" autocomplete="email" inputmode="email" :required="true" autofocus />

            <x-submit-button :busy="__('auth.email_change.submitting')">{{ __('auth.email_change.submit') }}</x-submit-button>
        </x-form>
    </div>

    <p class="mt-5">
        <a class="prose-link inline-flex min-h-11 items-center" href="{{ route('verification.notice') }}">{{ __('auth.email_change.back') }}</a>
    </p>

    <form method="POST" action="{{ route('logout') }}" class="mt-2 border-t border-line pt-4">
        @csrf
        <button type="submit" class="inline-flex min-h-11 items-center gap-2 rounded-md font-semibold text-brand-700 underline underline-offset-4">
            <x-icon name="logout" />{{ __('auth.verify.logout') }}
        </button>
    </form>
@endsection
