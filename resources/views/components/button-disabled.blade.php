{{-- Przycisk niedostępny (moduł jeszcze nie istnieje). Bez linku, żeby nie prowadził na 404. --}}
<span aria-disabled="true" {{ $attributes->merge(['class' => 'inline-flex min-h-12 cursor-not-allowed items-center justify-center gap-2 rounded-md border border-line bg-paper-2 px-5 py-2.5 text-base font-semibold text-ink-600']) }}>
    {{ $slot }}
    <span class="text-sm font-normal">({{ __('dashboard.soon') }})</span>
</span>
