@props([
    'name' => 'password',
    'label',
    'autocomplete' => 'new-password',
    'hint' => null,
    'required' => true,
])

@php
    $id = 'field-'.$name;
    $error = $errors->first($name);
    $describedBy = trim(($hint ? $id.'-hint ' : '').($error ? $id.'-error' : ''));
@endphp

{{-- Hasło z przyciskiem "Pokaż"/"Ukryj" (Alpine: passwordToggle). Wklejanie nie jest blokowane, wartość nigdy nie wraca z old(). --}}
<div {{ $attributes->only('class')->merge(['class' => 'space-y-1.5']) }}>
    <label for="{{ $id }}" class="block font-semibold text-ink-900">{{ $label }}</label>

    @if ($hint)
        <p id="{{ $id }}-hint" class="text-sm text-ink-600">{{ $hint }}</p>
    @endif

    <div class="flex items-stretch gap-2" x-data="passwordToggle" data-show-label="{{ __('auth.show_password') }}" data-hide-label="{{ __('auth.hide_password') }}">
        <input
            id="{{ $id }}"
            name="{{ $name }}"
            type="password"
            x-ref="input"
            autocomplete="{{ $autocomplete }}"
            autocapitalize="none"
            spellcheck="false"
            @if ($required) aria-required="true" @endif
            @if ($describedBy !== '') aria-describedby="{{ $describedBy }}" @endif
            @if ($error) aria-invalid="true" @endif
            class="block min-h-12 min-w-0 flex-1 rounded-md border bg-paper px-3 py-2 text-base text-ink-900 {{ $error ? 'border-danger' : 'border-ink-600' }}"
        >
        <button type="button" x-cloak x-show="ready" x-on:click="toggle" x-bind:aria-pressed="pressed" x-text="label"
                class="min-h-12 min-w-16 shrink-0 rounded-md border border-line bg-paper px-3 text-base font-semibold text-brand-700 hover:bg-paper-2">{{ __('auth.show_password') }}</button>
    </div>

    <x-field-error :id="$id.'-error'" :message="$error" />
</div>
