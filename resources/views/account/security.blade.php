@extends('layouts.app')

@section('title', $forced ? __('auth.twofa.required_title') : __('auth.security.title'))

{{-- Tryb wymuszony (fachowiec / administrator bez 2FA): menu ukryte, dostępne tylko wylogowanie --}}
@if ($forced)
    @section('minimal', 'yes')
@endif

@section('content')
    @php
        $showPasswordSection = ! $forced && in_array($step, ['intro', 'manage'], true);
    @endphp

    <div class="mx-auto max-w-2xl space-y-6">
        @if ($forced)
            <div>
                <h1 class="text-2xl font-semibold text-ink-900">{{ __('auth.twofa.required_title') }}</h1>
                <p class="mt-2 text-ink-600">{{ __('auth.twofa.required_text') }}</p>
            </div>
        @else
            <h1 class="text-2xl font-semibold text-ink-900">{{ __('auth.security.title') }}</h1>
        @endif

        @if ($showPasswordSection)
            @include('account.partials.change-password')
        @endif

        <section id="weryfikacja-dwuetapowa" class="space-y-4" aria-labelledby="twofa-title">
            @if (! $forced)
                <div>
                    <h2 id="twofa-title" class="text-xl font-semibold text-ink-900">{{ __('auth.twofa.section_title') }}</h2>
                    @if ($step === 'intro')
                        <p class="mt-1 text-ink-600">{{ __('auth.twofa.section_text') }}</p>
                    @endif
                </div>
            @else
                <h2 id="twofa-title" class="sr-only">{{ __('auth.twofa.section_title') }}</h2>
            @endif

            @switch($step)
                @case('intro')
                    @include('account.partials.two-factor-intro')
                    @break
                @case('scan')
                    @include('account.partials.two-factor-scan')
                    @break
                @case('codes')
                    @include('account.partials.two-factor-codes')
                    @break
                @default
                    @include('account.partials.two-factor-manage')
            @endswitch
        </section>
    </div>
@endsection
