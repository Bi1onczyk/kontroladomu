<!-- DO WERYFIKACJI PRZEZ PRAWNIKA -->
@extends('layouts.public')

@section('title', __('pages.verification.title'))
@section('meta_description', __('pages.verification.description'))

@section('content')
    @php
        $email = config('app.contact_email');
        $sections = __('pages.verification.sections');
    @endphp

    <div class="mx-auto max-w-180 px-5 py-10 sm:py-14">
        <h1 class="text-3xl font-semibold tracking-tight text-ink-900">{{ __('pages.verification.title') }}</h1>

        <x-alert variant="info" class="mt-5">{{ __('pages.draft_notice') }}</x-alert>

        <p class="mt-6 text-lg text-ink-900">{{ __('pages.verification.intro') }}</p>

        <div class="mt-8 space-y-8">
            @foreach ($sections as $index => $section)
                <section aria-labelledby="sekcja-{{ $index }}">
                    <h2 id="sekcja-{{ $index }}" class="text-xl font-semibold text-ink-900">{{ $section['title'] }}</h2>
                    @isset($section['text'])
                        <p class="mt-2 text-ink-900">{{ str_replace(':email', $email, $section['text']) }}</p>
                    @endisset
                    @isset($section['items'])
                        <ul class="mt-2 list-disc space-y-2 pl-6">
                            @foreach ($section['items'] as $item)
                                <li>{{ $item }}</li>
                            @endforeach
                        </ul>
                    @endisset
                </section>
            @endforeach
        </div>
    </div>
@endsection
