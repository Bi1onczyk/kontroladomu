@props([
    'name',
    'value' => '1',
    'required' => false,
    'optional' => false,
    'checked' => false,
])

@php
    $id = 'field-'.$name;
    $error = $errors->first($name);
@endphp

{{-- Pole wyboru z etykietą klikalną w całości (cel dotyku min. 44 px). Stan "wymagane"/"nieobowiązkowe" jako tekst, nie gwiazdka. --}}
<div {{ $attributes->only('class')->merge(['class' => 'space-y-1.5']) }}>
    <div class="flex items-start gap-3">
        <input
            id="{{ $id }}"
            name="{{ $name }}"
            type="checkbox"
            value="{{ $value }}"
            class="mt-1 size-6 shrink-0 rounded border-ink-600 accent-brand-700"
            @checked(old($name, $checked))
            @if ($required) aria-required="true" @endif
            @if ($error) aria-invalid="true" aria-describedby="{{ $id }}-error" @endif
        >
        <label for="{{ $id }}" class="min-h-11 cursor-pointer py-0.5 text-base text-ink-900">
            {{ $slot }}
            @if ($required)
                <span class="text-ink-600">({{ __('auth.required') }})</span>
            @elseif ($optional)
                <span class="text-ink-600">({{ __('auth.optional') }})</span>
            @endif
        </label>
    </div>

    <x-field-error :id="$id.'-error'" :message="$error" />
</div>
