@props([
    'name',
    'label',
    'type' => 'text',
    'value' => null,
    'hint' => null,
    'autocomplete' => null,
    'inputmode' => null,
    'maxlength' => null,
    'required' => false,
    'readonly' => false,
    'autofocus' => false,
    'hideError' => false,
])

@php
    $id = 'field-'.$name;
    $error = $errors->first($name);
    $showError = $error && ! $hideError;
    $describedBy = trim(($hint ? $id.'-hint ' : '').($showError ? $id.'-error' : ''));
    $inputClasses = 'block min-h-12 w-full rounded-md border px-3 py-2 text-base text-ink-900 placeholder:text-ink-600 '
        .($error ? 'border-danger' : 'border-ink-600')
        .($readonly ? ' bg-paper-2' : ' bg-paper');
@endphp

{{-- Pole z etykietą nad polem, podpowiedzią i komunikatem błędu pod polem (tekst + ikona, nie sam kolor). --}}
<div {{ $attributes->only('class')->merge(['class' => 'space-y-1.5']) }}>
    <label for="{{ $id }}" class="block font-semibold text-ink-900">
        {{ $label }}
        @if ($required)
            <span class="font-normal text-ink-600">({{ __('auth.required') }})</span>
        @endif
    </label>

    @if ($hint)
        <p id="{{ $id }}-hint" class="text-sm text-ink-600">{{ $hint }}</p>
    @endif

    <input
        id="{{ $id }}"
        name="{{ $name }}"
        type="{{ $type }}"
        @if ($type !== 'password') value="{{ old($name, $value) }}" @endif
        @if ($autocomplete) autocomplete="{{ $autocomplete }}" @endif
        @if ($inputmode) inputmode="{{ $inputmode }}" @endif
        @if ($maxlength) maxlength="{{ $maxlength }}" @endif
        @if ($required) aria-required="true" @endif
        @if ($readonly) readonly @endif
        @if ($autofocus) autofocus @endif
        @if ($describedBy !== '') aria-describedby="{{ $describedBy }}" @endif
        @if ($error) aria-invalid="true" @endif
        {{ $attributes->except('class')->merge(['class' => $inputClasses]) }}
    >

    <x-field-error :id="$id.'-error'" :message="$showError ? $error : null" />
</div>
