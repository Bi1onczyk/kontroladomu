{{-- Krok 2 z 3: dodanie konta w aplikacji i potwierdzenie kodem. Na telefonie nie da się zeskanować QR z tego samego ekranu,
     więc kolejność jest odwrócona: najpierw "Otwórz w aplikacji" i klucz, kod QR zwinięty. --}}
<x-step-card :current="2" :total="3" :title="__('auth.twofa.step2_title')" id="step-2-title">
    <div class="grid gap-6 md:grid-cols-[13.5rem_minmax(0,1fr)]">
        {{-- Kod QR widoczny od razu tylko na szerokim ekranie (do zeskanowania telefonem) --}}
        <div class="hidden md:block">
            <img src="{{ $qrDataUri }}" width="216" height="216" alt="{{ __('auth.twofa.qr_alt') }}" class="size-54 rounded-md border border-line bg-white p-2">
        </div>

        <div class="space-y-4">
            <ol class="list-decimal space-y-1 pl-6">
                <li>{{ __('auth.twofa.step2_scan_1') }}</li>
                <li>{{ __('auth.twofa.step2_scan_2') }}</li>
                <li>{{ __('auth.twofa.step2_scan_3') }}</li>
            </ol>

            <div class="md:hidden">
                <x-button :href="$otpauthUrl" variant="secondary" :full="true">{{ __('auth.twofa.open_in_app') }}</x-button>
            </div>

            <div class="space-y-2">
                <p class="text-ink-600">{{ __('auth.twofa.manual_key') }}</p>
                <div class="flex flex-wrap items-center gap-3">
                    <code class="rounded-md bg-paper-2 px-3 py-2 font-mono text-lg tracking-wider text-ink-900" aria-label="{{ __('ui.scan_key_label') }}">{{ $secretKey }}</code>
                    <span x-data="copyToClipboard" data-copy-text="{{ str_replace(' ', '', $secretKey) }}" data-copied-text="{{ __('auth.twofa.copied') }}">
                        <button type="button" x-cloak x-show="supported" x-on:click="copy" class="min-h-11 rounded-md border border-line bg-paper px-4 font-semibold text-brand-700 hover:bg-paper-2">{{ __('auth.twofa.copy_key') }}</button>
                        <span class="ml-2 text-sm text-ink-600" role="status" x-text="message"></span>
                    </span>
                </div>
            </div>

            <details class="rounded-md border border-line p-3 md:hidden">
                <summary class="min-h-11 cursor-pointer py-2 font-semibold text-brand-700">{{ __('auth.twofa.qr_show') }}</summary>
                <img src="{{ $qrDataUri }}" width="216" height="216" alt="{{ __('auth.twofa.qr_alt') }}" loading="lazy" class="mx-auto mt-2 size-54 rounded-md border border-line bg-white p-2">
            </details>
        </div>
    </div>

    <x-error-summary />

    <x-form :action="route('two-factor.confirm')" class="space-y-4 border-t border-line pt-5">
        <x-code-field name="code" :label="__('auth.twofa.code_label')" mode="totp" />
        <x-submit-button :busy="__('auth.security.submitting')" :full="false">{{ __('auth.twofa.code_submit') }}</x-submit-button>
    </x-form>
</x-step-card>
