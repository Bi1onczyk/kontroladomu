@props(['title' => null])

{{-- Pusty stan: konkretne zdanie, co zrobić dalej, i opcjonalny przycisk (slot "action"). --}}
<div {{ $attributes->merge(['class' => 'rounded-md border border-dashed border-line bg-paper-2 p-5']) }}>
    @if ($title)
        <p class="font-semibold text-ink-900">{{ $title }}</p>
    @endif
    <p class="@if ($title) mt-1 @endif text-ink-600">{{ $slot }}</p>
    @isset($action)
        <div class="mt-4">{{ $action }}</div>
    @endisset
</div>
