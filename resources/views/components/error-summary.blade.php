{{-- Podsumowanie błędów formularza (gdy jest ich więcej niż 1): role=alert, fokus po załadowaniu, każdy punkt to link do pola. --}}
@php
    $messages = collect($errors->messages())->map(fn ($list) => $list[0] ?? '')->filter();
@endphp

@if ($messages->count() > 1)
    <div tabindex="-1" role="alert" x-data="focusOnLoad" class="mb-6 rounded-md border border-l-4 border-danger bg-danger-100 p-4 text-ink-900" aria-labelledby="error-summary-title">
        <p id="error-summary-title" class="flex items-center gap-2 font-semibold">
            <x-icon name="alert" class="size-6 text-danger" />
            {{ __('auth.error_summary_title') }}
        </p>
        <p class="mt-1 text-sm text-ink-600">{{ __('auth.error_summary_hint') }}</p>
        <ul class="mt-2 list-disc space-y-1 pl-6">
            @foreach ($messages as $field => $message)
                <li><a class="prose-link" href="#field-{{ $field }}">{{ $message }}</a></li>
            @endforeach
        </ul>
    </div>
@endif
