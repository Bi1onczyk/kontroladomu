@props([
    'href' => null,
    'label',
    'icon',
    'active' => false,
    'mobile' => false,
])

@php
    $soon = __('dashboard.soon');
    if ($mobile) {
        $base = 'flex min-h-14 flex-col items-center justify-center gap-0.5 border-t-[3px] px-1 py-1.5 text-center text-xs leading-tight';
        $state = $active ? 'border-brand-700 bg-brand-100 font-semibold text-brand-700' : 'border-transparent text-ink-900';
    } else {
        $base = 'flex min-h-11 items-center gap-3 rounded-md border-l-[3px] px-3 py-2 text-base';
        $state = $active ? 'border-brand-700 bg-brand-100 font-semibold text-brand-700' : 'border-transparent text-ink-900 hover:bg-paper-2';
    }
@endphp

{{-- Pozycja menu: aktywna (tło + pasek + pogrubienie, nie tylko kolor) albo nieaktywna "Wkrótce" bez linku (żeby nie prowadziła na 404). --}}
@if ($href)
    <a href="{{ $href }}" class="{{ $base }} {{ $state }}" @if ($active) aria-current="page" @endif>
        <x-icon :name="$icon" />
        <span>{{ $label }}</span>
    </a>
@else
    <span class="{{ $base }} cursor-not-allowed border-transparent text-ink-600" aria-disabled="true">
        <x-icon :name="$icon" />
        <span>{{ $label }} <span class="{{ $mobile ? 'block text-xs' : 'text-sm' }} font-normal">({{ $soon }})</span></span>
    </span>
@endif
