@extends('layouts.auth')

@section('title', __('auth.register_sent.title'))

@section('content')
    <h1 tabindex="-1" x-data="focusOnLoad" class="text-2xl font-semibold text-ink-900">{{ __('auth.register_sent.title') }}</h1>

    {{-- Tekst neutralny: prawdziwy zarówno dla wolnego, jak i zajętego adresu (nie ujawniamy, czy konto istnieje). --}}
    <p class="mt-4">
        @if ($email)
            <x-emphasize key="auth.register_sent.text" :value="$email" />
        @else
            {{ __('auth.register_sent.text_unknown') }}
        @endif
    </p>

    <div class="mt-6">
        <h2 class="font-semibold text-ink-900">{{ __('auth.register_sent.no_mail_title') }}</h2>
        <ul class="mt-2 list-disc space-y-1 pl-6 text-ink-600">
            <li>{{ __('auth.register_sent.no_mail_1') }}</li>
            <li>{{ __('auth.register_sent.no_mail_2') }}</li>
            <li><a class="prose-link" href="{{ route('register') }}">{{ __('auth.register_sent.back_to_register') }}</a></li>
        </ul>
    </div>

    {{-- Bez przycisku "Wyślij ponownie" (wymaga sesji): po zalogowaniu konto niepotwierdzone trafia na ekran z przyciskiem. --}}
    <div class="mt-6">
        <x-button :href="route('login')" variant="secondary" :full="true">{{ __('auth.register_sent.login_to_resend') }}</x-button>
    </div>
@endsection
