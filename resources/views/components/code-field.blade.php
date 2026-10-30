@props([
    'name' => 'code',
    'label',
    'mode' => 'totp',
    'hint' => null,
    'autofocus' => false,
])

@php
    $id = 'field-'.$name;
    $error = $errors->first($name);
    $describedBy = trim(($hint ? $id.'-hint ' : '').($error ? $id.'-error' : ''));
    $isTotp = $mode === 'totp';
    $borderClass = $error ? 'border-danger' : 'border-ink-600';
@endphp

{{-- Pole kodu: "totp" = 6 cyfr z aplikacji (klawiatura numeryczna, one-time-code), "recovery" = kod awaryjny (zwykły tekst, bez limitu długości). --}}
<div {{ $attributes->only('class')->merge(['class' => 'space-y-1.5']) }}>
    <label for="{{ $id }}" class="block font-semibold text-ink-900">{{ $label }}</label>

    @if ($hint)
        <p id="{{ $id }}-hint" class="text-sm text-ink-600">{{ $hint }}</p>
    @endif

    <input
        id="{{ $id }}"
        name="{{ $name }}"
        type="text"
        @if ($isTotp)
            inputmode="numeric" pattern="[0-9 ]*" maxlength="7" autocomplete="one-time-code"
            class="block min-h-14 w-full rounded-md border bg-paper px-3 py-2 font-mono text-2xl tracking-[0.35em] text-ink-900 {{ $borderClass }}"
        @else
            autocomplete="off" autocapitalize="none" spellcheck="false"
            class="block min-h-14 w-full rounded-md border bg-paper px-3 py-2 font-mono text-xl text-ink-900 {{ $borderClass }}"
        @endif
        @if ($autofocus) autofocus @endif
        @if ($describedBy !== '') aria-describedby="{{ $describedBy }}" @endif
        @if ($error) aria-invalid="true" @endif
        {{ $attributes->except('class') }}
    >

    <x-field-error :id="$id.'-error'" :message="$error" />
</div>
