@extends('layouts.app')

@section('title', __('specialist.page.title'))
{{-- Komunikat "Zapisano" zostaje widoczny po przeskoku do kotwicy sekcji (patrz layouts/app). --}}
@section('flash_sticky', '1')

@section('content')
    <h1 class="text-2xl font-semibold text-ink-900 md:text-3xl">{{ __('specialist.page.heading') }}</h1>
    <p class="mt-2 max-w-prose text-ink-600">{{ __('specialist.page.intro') }}</p>

    @include('specialist.partials.tabs', ['current' => 'profile'])

    <div class="mt-6">
        <x-error-summary />
    </div>

    <div class="space-y-6">
        @include('specialist.profile.partials.publication')
        @include('specialist.profile.partials.data')
        @include('specialist.profile.partials.services')
        @include('specialist.profile.partials.area')
        @include('specialist.profile.partials.slug')
    </div>
@endsection
