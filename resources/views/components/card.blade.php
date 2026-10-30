@props(['title' => null, 'id' => null, 'level' => 2])

{{-- Ramka sekcji: obramowanie 1 px, bez cienia, promień 8 px. --}}
<section {{ $attributes->merge(['class' => 'rounded-lg border border-line bg-paper p-5 sm:p-6']) }} @if ($id && $title) aria-labelledby="{{ $id }}" @endif>
    @if ($title)
        <h{{ $level }} @if ($id) id="{{ $id }}" @endif class="mb-3 text-xl font-semibold text-ink-900">{{ $title }}</h{{ $level }}>
    @endif
    {{ $slot }}
</section>
