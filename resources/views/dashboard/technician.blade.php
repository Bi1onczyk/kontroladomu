@extends('layouts.app')

@section('title', __('dashboard.technician.my_day'))

@section('content')
    <h1 class="text-2xl font-semibold text-ink-900 md:text-3xl">{{ __('dashboard.greeting', ['name' => $user->name]) }}</h1>

    <div class="mt-6">
        <x-dashboard-notices :user="$user" />
    </div>

    <section aria-labelledby="my-day-title">
        <h2 id="my-day-title" class="mb-3 text-xl font-semibold">{{ __('dashboard.technician.my_day') }}</h2>
        <x-empty-state>{{ __('dashboard.technician.my_day_empty') }}</x-empty-state>
    </section>
@endsection
