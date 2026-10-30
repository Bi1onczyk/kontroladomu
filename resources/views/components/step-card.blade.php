@props(['current', 'total', 'title', 'id' => 'step-title'])

{{-- Krok kreatora: tekst "Krok X z Y" (nie tylko pasek postępu) + tytuł + treść. --}}
<section aria-labelledby="{{ $id }}" {{ $attributes->merge(['class' => 'rounded-lg border border-line bg-paper p-5 sm:p-6']) }}>
    <p class="text-sm font-semibold text-brand-700">{{ __('auth.twofa.step_of', ['current' => $current, 'total' => $total]) }}</p>
    <h2 id="{{ $id }}" class="mt-1 text-xl font-semibold text-ink-900">{{ $title }}</h2>
    <div class="mt-4 space-y-4">{{ $slot }}</div>
</section>
