{{-- Krok 3 z 3: kody awaryjne. Pole "Zapisałem(-am)" jest tylko przypomnieniem (2FA jest już włączone), "Zakończ" jest zawsze aktywny. --}}
<x-step-card :current="3" :total="3" :title="__('auth.twofa.step3_title')" id="step-3-title">
    <x-alert variant="warn" :title="__('auth.twofa.step3_important')">
        <p>{{ __('auth.twofa.step3_text') }}</p>
    </x-alert>

    <ul class="grid gap-x-8 gap-y-1 sm:grid-cols-2" aria-label="{{ __('ui.recovery_codes_list_label') }}">
        @foreach ($recoveryCodes as $code)
            <li class="py-1.5 font-mono text-lg text-ink-900">{{ $code }}</li>
        @endforeach
    </ul>

    <div class="no-print flex flex-wrap gap-3">
        <x-button :href="route('two-factor.codes.download')" variant="secondary" download>{{ __('auth.twofa.download_codes') }}</x-button>

        <span x-data="printPage" class="contents">
            <button type="button" x-cloak x-show="supported" x-on:click="print" class="inline-flex min-h-12 items-center justify-center rounded-md border border-line bg-paper px-5 font-semibold text-ink-900 hover:bg-paper-2">{{ __('auth.twofa.print_codes') }}</button>
        </span>

        <span x-data="copyToClipboard" data-copy-text="{{ implode("\n", $recoveryCodes) }}" data-copied-text="{{ __('auth.twofa.copied') }}" class="contents">
            <button type="button" x-cloak x-show="supported" x-on:click="copy" class="inline-flex min-h-12 items-center justify-center rounded-md border border-line bg-paper px-5 font-semibold text-ink-900 hover:bg-paper-2">{{ __('auth.twofa.copy_codes') }}</button>
            <span class="self-center text-sm text-ink-600" role="status" x-text="message"></span>
        </span>
    </div>

    <div class="no-print space-y-4 border-t border-line pt-5">
        <div class="flex items-start gap-3">
            <input id="codes-saved" type="checkbox" class="mt-1 size-6 shrink-0 rounded border-ink-600 accent-brand-700">
            <label for="codes-saved" class="min-h-11 cursor-pointer py-0.5">{{ __('auth.twofa.codes_saved') }}</label>
        </div>

        <x-button :href="route('dashboard')" :full="true">{{ __('auth.twofa.finish') }}</x-button>
    </div>
</x-step-card>
