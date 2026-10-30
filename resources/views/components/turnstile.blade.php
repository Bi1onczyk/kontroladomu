@if (config('services.turnstile.enabled'))
    {{-- Cloudflare Turnstile (ADR-008). Skrypt ładowany tylko na stronach z tym komponentem, z nonce (CSP). --}}
    <div id="field-cf-turnstile-response" x-data="turnstileGuard" class="space-y-2">
        <div class="cf-turnstile min-h-[65px]" data-sitekey="{{ config('services.turnstile.site_key') }}" data-language="pl"></div>

        <div x-cloak x-show="failed">
            <x-alert variant="error">{{ __('auth.turnstile_failed') }}</x-alert>
        </div>

        <noscript>
            <x-alert variant="error">{{ __('auth.turnstile_failed') }}</x-alert>
        </noscript>

        <x-field-error id="field-cf-turnstile-response-error" :message="$errors->first('cf-turnstile-response')" />
    </div>

    @push('scripts')
        <script src="https://challenges.cloudflare.com/turnstile/v0/api.js" async defer nonce="{{ $cspNonce ?? '' }}"></script>
    @endpush
@endif
