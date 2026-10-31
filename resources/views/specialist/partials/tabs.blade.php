@php
    /** @var string $current profile|certificates */
    $tabs = [
        'profile' => [__('specialist.page.tab_profile'), route('specialist.profile.edit')],
        'certificates' => [__('specialist.page.tab_certificates'), route('specialist.certificates.index')],
    ];
@endphp

{{-- Pasek "Profil | Zaświadczenia": dwa linki, bieżący oznaczony aria-current i pogrubieniem (nie samym kolorem). --}}
<nav class="mt-5 grid grid-cols-2 gap-2 sm:inline-grid sm:grid-cols-[auto_auto]" aria-label="{{ __('specialist.page.tabs_label') }}">
    @foreach ($tabs as $key => [$label, $url])
        <a
            href="{{ $url }}"
            @if ($current === $key) aria-current="page" @endif
            class="inline-flex min-h-12 items-center justify-center rounded-md border px-6 text-base {{ $current === $key ? 'border-brand-700 bg-brand-100 font-semibold text-brand-700' : 'border-line bg-paper text-ink-900 hover:bg-paper-2' }}"
        >{{ $label }}</a>
    @endforeach
</nav>
