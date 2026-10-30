{{-- Krok 1 z 3: informacja o aplikacji. "Dalej" tworzy (niepotwierdzony) sekret: POST two-factor.enable. --}}
<x-step-card :current="1" :total="3" :title="__('auth.twofa.step1_title')" id="step-1-title">
    <p>{{ __('auth.twofa.step1_text') }}</p>

    <details class="rounded-md border border-line p-3">
        <summary class="min-h-11 cursor-pointer py-2 font-semibold text-brand-700">{{ __('auth.twofa.step1_no_phone') }}</summary>
        <p class="mt-2 text-ink-600">{{ __('auth.twofa.step1_no_phone_text') }}</p>
    </details>

    <x-form :action="route('two-factor.enable')">
        <x-submit-button :busy="__('auth.security.submitting')" :full="false">{{ __('auth.twofa.step1_next') }}</x-submit-button>
    </x-form>
</x-step-card>
