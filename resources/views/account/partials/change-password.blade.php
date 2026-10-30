{{-- Zmiana hasła zalogowanego użytkownika. Brak pola powtórzenia (spójnie z rejestracją). --}}
<x-card :title="__('auth.security.password_title')" id="password-title">
    <x-error-summary />

    <x-form :action="route('password.change')" class="space-y-5">
        <x-password-field name="current_password" :label="__('auth.security.current_password')" autocomplete="current-password" />
        <x-password-field name="password" :label="__('auth.security.new_password')" :hint="__('auth.password_hint')" />

        <p class="text-sm text-ink-600">{{ __('auth.security.password_note') }}</p>

        <x-submit-button :busy="__('auth.security.submitting')" :full="false">{{ __('auth.security.submit') }}</x-submit-button>
    </x-form>
</x-card>
