@php
    /** @var string $page Klucz sekcji w lang/pl/pages.php (terms | privacy | cookies) */
    /** @var string $versionKey Klucz wersji w config/legal.php */
    $sections = __('pages.'.$page.'.sections');
    $email = config('app.contact_email');
    $fill = fn (string $text): string => str_replace(':email', $email, $text);
    $numbered = in_array($page, ['terms', 'privacy'], true);
@endphp

{{-- DO WERYFIKACJI PRZEZ PRAWNIKA --}}
<div class="mx-auto max-w-180 px-5 py-10 sm:py-14">
    <h1 class="text-3xl font-semibold tracking-tight text-ink-900">{{ __('pages.'.$page.'.title') }}</h1>

    <x-alert variant="info" class="mt-5">{{ __('pages.draft_notice') }}</x-alert>

    <div class="mt-8 space-y-8">
        @foreach ($sections as $index => $section)
            <section aria-labelledby="sekcja-{{ $index }}">
                <h2 id="sekcja-{{ $index }}" class="text-xl font-semibold text-ink-900">
                    @if ($numbered){{ $index + 1 }}. @endif{{ $section['title'] }}
                </h2>
                @isset($section['text'])
                    <p class="mt-2 text-ink-900">{{ $fill($section['text']) }}</p>
                @endisset
                @isset($section['items'])
                    <ul class="mt-2 list-disc space-y-2 pl-6">
                        @foreach ($section['items'] as $item)
                            <li>{{ $fill($item) }}</li>
                        @endforeach
                    </ul>
                @endisset
            </section>
        @endforeach
    </div>

    <p class="mt-10 border-t border-line pt-4 text-sm text-ink-600">
        {{ __('pages.version', ['date' => \Illuminate\Support\Carbon::parse(config('legal.'.$versionKey))->format('d.m.Y')]) }}
    </p>
</div>
