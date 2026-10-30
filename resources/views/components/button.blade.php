@props([
    'href' => null,
    'type' => 'button',
    'variant' => 'primary',
    'full' => false,
])

@php
    $baseClasses = 'inline-flex min-h-12 items-center justify-center gap-2 rounded-md px-5 py-2.5 text-base font-semibold transition-colors';
    $variantClasses = [
        'primary' => 'bg-brand-700 text-white hover:bg-brand-600',
        'secondary' => 'border border-line bg-paper text-ink-900 hover:bg-paper-2',
        'danger' => 'border border-danger bg-paper text-danger hover:bg-danger-100',
        'link' => 'min-h-11 px-1 text-brand-700 underline underline-offset-4 hover:text-brand-600',
    ];
    $classes = $baseClasses.' '.($variantClasses[$variant] ?? $variantClasses['secondary']).($full ? ' w-full' : '');
@endphp

@if ($href)
    <a href="{{ $href }}" {{ $attributes->merge(['class' => $classes]) }}>{{ $slot }}</a>
@else
    <button type="{{ $type }}" {{ $attributes->merge(['class' => $classes]) }}>{{ $slot }}</button>
@endif
