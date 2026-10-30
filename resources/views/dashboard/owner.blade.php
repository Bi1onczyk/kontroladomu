@extends('layouts.app')

@section('title', __('dashboard.nav.dashboard'))

@section('content')
    <h1 class="text-2xl font-semibold text-ink-900 md:text-3xl">{{ __('dashboard.greeting', ['name' => $user->name]) }}</h1>

    <div class="mt-6">
        <x-dashboard-notices :user="$user" />
    </div>

    {{-- Jeden dominujący element: co teraz zrobić. Moduł nieruchomości jeszcze nie istnieje, więc przycisk jest nieaktywny. --}}
    <x-card :title="__('dashboard.next_title')" id="next-title" class="border-brand-600">
        <p class="max-w-xl">{{ __('dashboard.owner.add_property') }}</p>
        <div class="mt-4">
            <x-button-disabled>{{ __('dashboard.owner.add_property_cta') }}</x-button-disabled>
        </div>
    </x-card>

    <div class="mt-8 grid gap-8 lg:grid-cols-3">
        <section class="lg:col-span-2" aria-labelledby="upcoming-title">
            <h2 id="upcoming-title" class="mb-3 text-xl font-semibold">{{ __('dashboard.owner.upcoming_title') }}</h2>
            <x-empty-state>
                {{ __('dashboard.owner.upcoming_empty') }}
                <x-slot:action>
                    <x-button-disabled>{{ __('dashboard.owner.find_cta') }}</x-button-disabled>
                </x-slot:action>
            </x-empty-state>
        </section>

        <section aria-labelledby="properties-title">
            <h2 id="properties-title" class="mb-3 text-xl font-semibold">{{ __('dashboard.owner.properties_title') }}</h2>
            <x-empty-state>{{ __('dashboard.owner.properties_empty') }}</x-empty-state>
        </section>
    </div>
@endsection
