@props([
    'name',
    'value',
    'checked' => false,
    'hint' => null,
    'idPrefix' => 'field',
])

@php
    $id = $idPrefix.'-'.$name.'-'.$value;
@endphp

{{-- Pole wyboru jednokrotnego (w <fieldset>): cały wiersz klikalny, cel dotyku min. 44 px, opcjonalna podpowiedź pod etykietą. --}}
<div {{ $attributes->only('class')->merge(['class' => 'flex items-start gap-3']) }}>
    <input
        id="{{ $id }}"
        name="{{ $name }}"
        type="radio"
        value="{{ $value }}"
        class="mt-1 size-6 shrink-0 accent-brand-700"
        @checked($checked)
        @if ($hint) aria-describedby="{{ $id }}-hint" @endif
        {{ $attributes->except('class') }}
    >
    <label for="{{ $id }}" class="min-h-11 flex-1 cursor-pointer py-0.5 text-base text-ink-900">
        {{ $slot }}
        @if ($hint)
            <span id="{{ $id }}-hint" class="block text-sm text-ink-600">{{ $hint }}</span>
        @endif
    </label>
</div>
