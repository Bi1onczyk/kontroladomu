@props([
    'busy',
    'variant' => 'primary',
    'full' => true,
])

{{-- Przycisk wysyłania: podpis zmienia się na wersję "...trwa" (formLock), kolejne kliknięcia są ignorowane. --}}
<x-button type="submit" :variant="$variant" :full="$full" x-bind:aria-disabled="ariaDisabled" {{ $attributes }}>
    <span x-show="idle">{{ $slot }}</span>
    <span x-cloak x-show="busy" role="status">{{ $busy }}</span>
</x-button>
