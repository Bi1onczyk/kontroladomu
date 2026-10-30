@extends('layouts.public')

@section('content')
    <section class="bg-paper-2">
        <div class="mx-auto grid max-w-6xl gap-10 px-5 py-16 sm:py-24 lg:grid-cols-[minmax(0,1.3fr)_minmax(18rem,0.7fr)] lg:items-center">
            <div class="max-w-3xl">
                <p class="mb-4 text-sm font-semibold uppercase tracking-wide text-brand-700">
                    {{ __('app.home.eyebrow') }}
                </p>
                <h1 class="max-w-2xl text-4xl font-semibold leading-tight tracking-tight text-ink-900 sm:text-5xl">
                    {{ __('app.home.title') }}
                </h1>
                <p class="mt-6 max-w-2xl text-lg leading-8 text-ink-600">
                    {{ __('app.home.intro') }}
                </p>
                <x-button href="#dla-wlascicieli" class="mt-8">
                    {{ __('app.navigation.owners') }}
                </x-button>
            </div>

            <aside class="border-l-4 border-brand-600 bg-paper px-6 py-6 sm:px-8" aria-labelledby="status-serwisu">
                <h2 id="status-serwisu" class="text-xl font-semibold text-ink-900">
                    {{ __('app.home.status_title') }}
                </h2>
                <p class="mt-3 text-ink-600">
                    {{ __('app.home.status_text') }}
                </p>
            </aside>
        </div>
    </section>

    <section class="mx-auto grid max-w-6xl gap-10 px-5 py-14 md:grid-cols-2 md:gap-16">
        <article id="dla-wlascicieli" class="scroll-mt-8">
            <h2 class="text-2xl font-semibold tracking-tight text-ink-900">
                {{ __('app.home.owners_title') }}
            </h2>
            <p class="mt-4 max-w-xl text-ink-600">
                {{ __('app.home.owners_text') }}
            </p>
        </article>
        <article id="dla-fachowcow" class="scroll-mt-8 border-t border-line pt-8 md:border-l md:border-t-0 md:pl-12 md:pt-0">
            <h2 class="text-2xl font-semibold tracking-tight text-ink-900">
                {{ __('app.home.specialists_title') }}
            </h2>
            <p class="mt-4 max-w-xl text-ink-600">
                {{ __('app.home.specialists_text') }}
            </p>
        </article>
    </section>
@endsection
