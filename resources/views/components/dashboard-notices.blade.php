@props(['user'])

@php
    // Mało kodów awaryjnych: ostrzeżenie dla każdego konta z włączonym 2FA.
    $remaining = $user->hasConfirmedTwoFactor() ? count($user->recoveryCodes()) : null;
    $codesLow = $remaining !== null && $remaining < 3;

    // Opcjonalna zachęta do 2FA dla kont, które go nie wymagają (można odłożyć na 30 dni).
    $remindOwner = ! $user->hasConfirmedTwoFactor()
        && ! $user->requiresTwoFactor()
        && ($user->two_factor_reminder_until === null || $user->two_factor_reminder_until->isPast());
@endphp

{{-- Po zalogowaniu kodem awaryjnym: ile kodów zostało (flash ustawiany przez backend). --}}
@if (session()->has('recovery_codes_left'))
    @php($left = (int) session('recovery_codes_left'))
    <x-alert variant="warn" class="mb-6">
        <p>{{ $left === 0 ? __('auth.twofa.recovery_used_none') : trans_choice('auth.twofa.recovery_used', $left) }}</p>
    </x-alert>
@endif

@if ($codesLow)
    <x-alert variant="warn" class="mb-6">
        <p>{{ $remaining === 0 ? __('auth.twofa.codes_none') : trans_choice('auth.twofa.codes_low', $remaining) }}</p>
        <p><a class="prose-link" href="{{ route('account.security') }}">{{ __('dashboard.account_menu.security') }}</a></p>
    </x-alert>
@endif

@if ($remindOwner)
    <x-alert variant="info" class="mb-6">
        <p>{{ __('auth.twofa.owner_banner') }}</p>
        <div class="flex flex-wrap items-center gap-3">
            <x-button :href="route('account.security')">{{ __('auth.twofa.enable') }}</x-button>
            <x-form :action="route('two-factor.reminder.dismiss')">
                <x-submit-button variant="secondary" :full="false" :busy="__('auth.security.submitting')">{{ __('auth.twofa.remind_later') }}</x-submit-button>
            </x-form>
        </div>
    </x-alert>
@endif
