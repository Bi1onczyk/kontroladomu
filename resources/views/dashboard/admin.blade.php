@extends('layouts.app')

@section('title', __('dashboard.nav.dashboard'))

@section('content')
    <p class="mb-4 rounded-md bg-brand-100 px-4 py-2 font-semibold text-ink-900">{{ __('dashboard.admin_bar') }}</p>

    <h1 class="text-2xl font-semibold text-ink-900 md:text-3xl">{{ __('dashboard.greeting', ['name' => $user->name]) }}</h1>

    <div class="mt-6">
        <x-dashboard-notices :user="$user" />
    </div>

    <div class="grid gap-8 lg:grid-cols-3">
        <section class="lg:col-span-2" aria-labelledby="to-verify-title">
            <h2 id="to-verify-title" class="mb-3 text-xl font-semibold">{{ __('dashboard.admin.to_verify_title') }}</h2>
            <x-empty-state>{{ __('dashboard.admin.to_verify_empty') }}</x-empty-state>
        </section>

        <section aria-labelledby="reports-title">
            <h2 id="reports-title" class="mb-3 text-xl font-semibold">{{ __('dashboard.admin.reports_title') }}</h2>
            <x-empty-state>{{ __('dashboard.admin.reports_empty') }}</x-empty-state>
        </section>
    </div>
@endsection
