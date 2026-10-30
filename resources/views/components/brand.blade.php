@props(['href' => null])

{{-- Logo robocze: znak (dom z ptaszkiem) i napis "kontroladomu.pl". Do podmiany na docelowe. --}}
<a href="{{ $href ?? route('home') }}" {{ $attributes->merge(['class' => 'inline-flex min-h-11 items-center gap-2 text-xl font-semibold tracking-tight text-ink-900']) }} aria-label="{{ __('app.brand') }}">
    <svg class="size-7 text-brand-700" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false">
        <path d="M3 11.5 12 4l9 7.5M5.5 10v10h13V10"/><path d="m9 14.5 2.2 2.2L15.5 12"/>
    </svg>
    <span>kontrola<strong class="text-brand-700">domu</strong>.pl</span>
</a>
