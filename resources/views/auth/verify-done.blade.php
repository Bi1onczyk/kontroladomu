@extends('layouts.auth')

@php
    use App\Domain\Accounts\Actions\VerifyEmailAddress;

    $loggedIn = auth()->check();
    $isSpecialist = $verifiedRole === 'specialist';
    // Zalogowany fachowiec/administrator bez 2FA trafia do kreatora, reszta na pulpit.
    $needsTwoFactor = $loggedIn && auth()->user()->requiresTwoFactor() && ! auth()->user()->hasConfirmedTwoFactor();
    $heading = match ($status) {
        VerifyEmailAddress::VERIFIED => __('auth.verify.done_title'),
        VerifyEmailAddress::ALREADY => __('auth.verify.already'),
        default => __('auth.verify.invalid_title'),
    };
@endphp

@section('title', $heading)

@section('content')
    <h1 tabindex="-1" x-data="focusOnLoad" class="text-2xl font-semibold text-ink-900">{{ $heading }}</h1>

    <div class="mt-4 space-y-3">
        @if ($status === VerifyEmailAddress::VERIFIED)
            @if ($loggedIn)
                <p>{{ __('auth.verify.done_text') }}</p>
                @if ($needsTwoFactor)
                    <p>{{ __('auth.verify.done_specialist') }}</p>
                @endif
            @else
                <p>{{ __('auth.verify.done_guest') }}</p>
                {{-- Link weryfikacyjny nie loguje: fachowiec dowiaduje się o 2FA, ale przycisk kreatora pojawi się po zalogowaniu --}}
                @if ($isSpecialist)
                    <p>{{ __('auth.verify.done_guest_specialist') }}</p>
                @endif
            @endif
        @elseif ($status === VerifyEmailAddress::ALREADY)
            @unless ($loggedIn)
                <p>{{ __('auth.verify.already_guest') }}</p>
            @endunless
        @else
            @unless ($loggedIn)
                <p>{{ __('auth.verify.invalid_guest') }}</p>
            @endunless
        @endif
    </div>

    <div class="mt-6">
        @if ($loggedIn)
            @if ($status === VerifyEmailAddress::INVALID || ! in_array($status, [VerifyEmailAddress::VERIFIED, VerifyEmailAddress::ALREADY], true))
                <x-form :action="route('verification.send')">
                    <x-submit-button :busy="__('auth.forgot.submitting')">{{ __('auth.verify.send_new') }}</x-submit-button>
                </x-form>
            @elseif ($needsTwoFactor)
                <x-button :href="route('account.security')" :full="true">{{ __('auth.verify.go_2fa') }}</x-button>
            @else
                <x-button :href="route('dashboard')" :full="true">{{ __('auth.verify.go_dashboard') }}</x-button>
            @endif
        @else
            <x-button :href="route('login')" :full="true">
                {{ in_array($status, [VerifyEmailAddress::VERIFIED, VerifyEmailAddress::ALREADY], true) ? __('auth.verify.go_login') : __('auth.verify.login_to_send') }}
            </x-button>
        @endif
    </div>
@endsection
