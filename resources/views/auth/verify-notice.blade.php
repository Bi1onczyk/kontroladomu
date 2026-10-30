@extends('layouts.auth')

@section('title', __('auth.verify.title'))

@section('content')
    @php($justResent = (bool) session('status'))

    <h1 tabindex="-1" x-data="focusOnLoad" class="text-2xl font-semibold text-ink-900">{{ __('auth.verify.title') }}</h1>

    <x-flash class="mt-4" />

    <p class="mt-4">
        <x-emphasize key="auth.verify.sent_to" :value="$displayEmail" />
    </p>

    <div class="mt-6">
        <h2 class="font-semibold text-ink-900">{{ __('auth.verify.no_mail_title') }}</h2>
        <ul class="mt-2 list-disc space-y-1 pl-6 text-ink-600">
            <li>{{ __('auth.verify.no_mail_1') }}</li>
            <li>{{ __('auth.verify.no_mail_2') }}</li>
            <li>{{ __('auth.verify.no_mail_3') }}</li>
        </ul>
    </div>

    {{-- Ponowne wysyłanie co 60 s: po właśnie wysłanej wiadomości przycisk czeka na koniec odliczania --}}
    <div class="mt-6 space-y-3"
         @if ($justResent) x-data="resendCountdown" data-seconds="60" data-template="{{ __('auth.verify.resend_in', ['seconds' => ':seconds']) }}" data-ready-text="{{ __('auth.forgot.resend_ready') }}" @endif>
        @if ($justResent)
            <p class="text-sm text-ink-600" aria-live="off" x-show="waiting" x-text="text">{{ __('auth.verify.resend_in', ['seconds' => 60]) }}</p>
            <p class="sr-only" role="status" x-text="announcement"></p>
        @endif

        <div @if ($justResent) x-cloak x-show="ready" @endif>
            <x-form :action="route('verification.send')">
                <x-submit-button variant="secondary" :busy="__('auth.forgot.submitting')">{{ __('auth.verify.resend') }}</x-submit-button>
            </x-form>
        </div>
    </div>

    <p class="mt-5">
        <a class="prose-link inline-flex min-h-11 items-center" href="{{ route('email.change') }}">{{ __('auth.verify.change_email') }}</a>
    </p>

    <form method="POST" action="{{ route('logout') }}" class="mt-2 border-t border-line pt-4">
        @csrf
        <button type="submit" class="inline-flex min-h-11 items-center gap-2 rounded-md font-semibold text-brand-700 underline underline-offset-4">
            <x-icon name="logout" />{{ __('auth.verify.logout') }}
        </button>
    </form>
@endsection
