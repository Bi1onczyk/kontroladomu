@props(['variant' => 'success'])

{{-- Komunikat z sesji ("status"). role=status, można zamknąć. Nie znika sam (WCAG 2.2.1). --}}
@if (session('status'))
    <div x-data="dismissible" x-show="open" {{ $attributes }}>
        <x-alert :variant="$variant" role="status">
            <div class="flex items-start justify-between gap-3">
                <p>{{ session('status') }}</p>
                <button type="button" x-cloak x-on:click="close" class="no-print -m-2 inline-flex min-h-11 min-w-11 shrink-0 items-center justify-center rounded-md text-ink-900 hover:bg-paper/60" aria-label="{{ __('dashboard.flash_close') }}">
                    <x-icon name="x" />
                </button>
            </div>
        </x-alert>
    </div>
@endif
