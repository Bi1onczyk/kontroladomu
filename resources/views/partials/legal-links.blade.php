{{-- Linki prawne w stopkach. Pozycje bez strony ("Zgłoś nielegalną treść", "Ustawienia cookies") są nieaktywne: bez linku, z dopiskiem "Wkrótce". --}}
<ul class="flex flex-wrap gap-x-5 gap-y-1" aria-label="{{ __('ui.legal_nav') }}">
    <li><a class="prose-link inline-flex min-h-11 items-center" href="{{ route('terms') }}">{{ __('dashboard.footer.terms') }}</a></li>
    <li><a class="prose-link inline-flex min-h-11 items-center" href="{{ route('privacy') }}">{{ __('dashboard.footer.privacy') }}</a></li>
    <li><a class="prose-link inline-flex min-h-11 items-center" href="{{ route('cookies') }}">{{ __('dashboard.footer.cookies') }}</a></li>
    <li><a class="prose-link inline-flex min-h-11 items-center" href="{{ route('contact') }}">{{ __('dashboard.footer.contact') }}</a></li>
    @if ($withSoon ?? false)
        <li><span class="inline-flex min-h-11 items-center text-ink-600" aria-disabled="true">{{ __('dashboard.footer.report') }} ({{ __('dashboard.soon') }})</span></li>
        <li><span class="inline-flex min-h-11 items-center text-ink-600" aria-disabled="true">{{ __('dashboard.footer.cookie_settings') }} ({{ __('dashboard.soon') }})</span></li>
    @endif
</ul>
