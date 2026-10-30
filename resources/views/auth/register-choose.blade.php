@extends('layouts.auth')

@section('title', __('auth.register.title'))
@section('wide', 'yes')

@section('content')
    <p class="text-sm font-semibold text-brand-700">{{ __('auth.register.step', ['current' => 1, 'total' => 2]) }}</p>
    <h1 class="mt-1 text-2xl font-semibold text-ink-900">{{ __('auth.register.title') }}</h1>
    <p class="mt-2 text-ink-600">{{ __('auth.register.choose_intro') }}</p>

    {{-- Dwa bloki-linki, celowo nierówne (7/12 i 5/12): właściciele to większość kont. Osobne adresy, bez JS (K14). --}}
    <nav class="mt-6 grid gap-4 md:grid-cols-12" aria-label="{{ __('auth.register.type_legend') }}">
        <a href="{{ route('register.owner') }}" class="block min-h-22 rounded-lg border-2 border-line p-5 hover:border-brand-700 hover:bg-brand-100 md:col-span-7">
            <span class="flex items-center gap-2 text-lg font-semibold text-ink-900">
                <x-icon name="home" class="size-6 text-brand-700" />
                {{ __('auth.register.type_owner') }}
            </span>
            <span class="mt-2 block text-ink-600">{{ __('auth.register.type_owner_text') }}</span>
        </a>
        <a href="{{ route('register.specialist') }}" class="block min-h-22 rounded-lg border-2 border-line p-5 hover:border-brand-700 hover:bg-brand-100 md:col-span-5">
            <span class="flex items-center gap-2 text-lg font-semibold text-ink-900">
                <x-icon name="shield" class="size-6 text-brand-700" />
                {{ __('auth.register.type_specialist') }}
            </span>
            <span class="mt-2 block text-ink-600">{{ __('auth.register.type_specialist_text') }}</span>
        </a>
    </nav>
@endsection

@section('below')
    <p class="border-t border-line pt-5 text-base">
        {{ __('auth.register.have_account') }}
        <a class="prose-link inline-flex min-h-11 items-center font-semibold" href="{{ route('login') }}">{{ __('auth.register.login_link') }}</a>
    </p>
@endsection
