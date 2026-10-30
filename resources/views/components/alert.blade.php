@props([
    'variant' => 'info',
    'title' => null,
    'role' => null,
])

@php
    $styles = [
        'info' => ['box' => 'border-brand-600 bg-brand-100', 'icon' => 'info', 'iconClass' => 'text-brand-700'],
        'success' => ['box' => 'border-brand-600 bg-brand-100', 'icon' => 'check', 'iconClass' => 'text-brand-700'],
        'warn' => ['box' => 'border-warn bg-warn-100', 'icon' => 'alert', 'iconClass' => 'text-warn'],
        'error' => ['box' => 'border-danger bg-danger-100', 'icon' => 'alert', 'iconClass' => 'text-danger'],
    ];
    $style = $styles[$variant] ?? $styles['info'];
    $liveRole = $role ?? ($variant === 'error' ? 'alert' : null);
@endphp

{{-- Ramka komunikatu. Tekst zawsze ink-900 (kontrast), kolor tylko na obramowaniu i ikonie, a znaczenie podaje tytuł/tekst. --}}
<div {{ $attributes->merge(['class' => 'flex gap-3 rounded-md border border-l-4 p-4 text-ink-900 '.$style['box']]) }} @if ($liveRole) role="{{ $liveRole }}" @endif>
    <x-icon :name="$style['icon']" class="mt-0.5 size-6 {{ $style['iconClass'] }}" />
    <div class="min-w-0 flex-1 space-y-1">
        @if ($title)
            <p class="font-semibold">{{ $title }}</p>
        @endif
        <div class="space-y-2">{{ $slot }}</div>
    </div>
</div>
