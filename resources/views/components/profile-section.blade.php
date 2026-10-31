@props(['anchor', 'title', 'number' => null])

{{-- Sekcja strony "Profil": własny przycisk "Zapisz", kotwica #sekcja-{anchor}, nagłówek jako cel fokusu po zapisie. --}}
<section id="sekcja-{{ $anchor }}" aria-labelledby="sekcja-{{ $anchor }}-title" {{ $attributes->merge(['class' => 'scroll-mt-20 rounded-lg border border-line bg-paper p-5 sm:p-6']) }}>
    <h2 id="sekcja-{{ $anchor }}-title" tabindex="-1" class="mb-4 text-xl font-semibold text-ink-900">
        @if ($number){{ $number }}. @endif{{ $title }}
    </h2>
    {{ $slot }}
</section>
