{{-- 2FA włączone: stan, ostrzeżenie o małej liczbie kodów, kody awaryjne, wyłączenie (tylko role, które go nie wymagają). --}}
@php
    $since = $user->two_factor_confirmed_at?->timezone('Europe/Warsaw')->format('d.m.Y');
    $remaining = (int) $codesRemaining;
@endphp

<x-card>
    <p class="flex items-center gap-2 font-semibold text-ink-900">
        <x-icon name="check" class="size-6 text-ok" />
        {{ __('auth.twofa.enabled_since', ['date' => $since]) }}
    </p>

    @if ($codesLow)
        <x-alert variant="warn" class="mt-4">
            {{ $remaining === 0 ? __('auth.twofa.codes_none') : trans_choice('auth.twofa.codes_low', $remaining) }}
        </x-alert>
    @else
        <p class="mt-3 text-ink-600">{{ __('ui.codes_remaining', ['count' => $remaining]) }}</p>
    @endif

    <div class="mt-5 space-y-4">
        <div>
            <x-button :href="route('two-factor.codes.show')" variant="secondary">{{ __('auth.twofa.show_codes') }}</x-button>
        </div>

        {{-- Nowe kody: najpierw ostrzeżenie "Dotychczasowe kody przestaną działać", potem potwierdzenie --}}
        <div x-data="disclosure">
            <button type="button" x-on:click="toggle" x-bind:aria-expanded="expanded" aria-controls="regenerate-panel" class="inline-flex min-h-12 items-center justify-center rounded-md border border-line bg-paper px-5 font-semibold text-ink-900 hover:bg-paper-2">
                {{ __('auth.twofa.regenerate') }}
            </button>

            <div id="regenerate-panel" x-cloak x-show="open" class="mt-3 space-y-3">
                <x-alert variant="warn" :title="__('auth.twofa.step3_important')">{{ __('auth.twofa.regenerate_warning') }}</x-alert>
                <div class="flex flex-wrap gap-3">
                    <x-form :action="route('two-factor.codes.regenerate')">
                        <x-submit-button :busy="__('auth.security.submitting')" :full="false">{{ __('auth.twofa.regenerate') }}</x-submit-button>
                    </x-form>
                    <button type="button" x-on:click="close" class="inline-flex min-h-12 items-center justify-center rounded-md px-5 font-semibold text-brand-700 underline underline-offset-4">{{ __('auth.twofa.regenerate_cancel') }}</button>
                </div>
            </div>
        </div>

        <div class="border-t border-line pt-4">
            @if ($user->requiresTwoFactor())
                <p class="text-ink-600">{{ __('auth.twofa.mandatory_note') }}</p>
            @else
                <x-form :action="route('two-factor.disable')" method="DELETE">
                    <x-submit-button variant="danger" :busy="__('auth.security.submitting')" :full="false">{{ __('auth.twofa.disable') }}</x-submit-button>
                </x-form>
            @endif
        </div>
    </div>
</x-card>
