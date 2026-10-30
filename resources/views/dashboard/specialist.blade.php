@extends('layouts.app')

@section('title', __('dashboard.nav.today'))

@section('content')
    @php
        // Lista kroków do ukończenia profilu. Dwa pierwsze są zawsze gotowe (bramki: e-mail potwierdzony, 2FA włączone),
        // reszta czeka na moduły z kolejnych zadań ("Wkrótce").
        $checklist = [
            ['label' => __('dashboard.specialist.check_email'), 'done' => $user->hasVerifiedEmail()],
            ['label' => __('dashboard.specialist.check_2fa'), 'done' => $user->hasConfirmedTwoFactor()],
            ['label' => __('dashboard.specialist.check_certs'), 'done' => false, 'soon' => true],
            ['label' => __('dashboard.specialist.check_profile'), 'done' => false, 'soon' => true],
            ['label' => __('dashboard.specialist.check_area'), 'done' => false, 'soon' => true],
        ];
    @endphp

    <h1 class="text-2xl font-semibold text-ink-900 md:text-3xl">{{ __('dashboard.greeting', ['name' => $user->name]) }}</h1>

    <div class="mt-6">
        <x-dashboard-notices :user="$user" />
    </div>

    {{-- Stan weryfikacji: ramka z tekstem i ikoną (nie sam kolor) + lista do uzupełnienia --}}
    <x-alert variant="warn" :title="__('dashboard.specialist.pending_title')">
        <p>{{ __('dashboard.specialist.pending_text') }}</p>

        <h2 class="pt-2 font-semibold">{{ __('dashboard.specialist.checklist_title') }}</h2>
        <ul class="space-y-1">
            @foreach ($checklist as $item)
                <li class="flex min-h-8 items-center gap-2">
                    @if ($item['done'])
                        <x-icon name="check" class="size-5 text-ok" />
                        <span>{{ $item['label'] }}: <strong>{{ __('dashboard.specialist.done') }}</strong></span>
                    @else
                        <x-icon name="clock" class="size-5 text-ink-600" />
                        <span>{{ $item['label'] }}: <strong>{{ ($item['soon'] ?? false) ? __('dashboard.soon') : __('dashboard.specialist.todo') }}</strong></span>
                    @endif
                </li>
            @endforeach
        </ul>
    </x-alert>

    <div class="mt-8 grid gap-8 lg:grid-cols-3">
        <section class="lg:col-span-2" aria-labelledby="today-title">
            <h2 id="today-title" class="mb-3 text-xl font-semibold">{{ __('dashboard.specialist.today_title') }}</h2>
            <x-empty-state>{{ __('dashboard.specialist.today_empty') }}</x-empty-state>
        </section>

        <section aria-labelledby="requests-title">
            <h2 id="requests-title" class="mb-3 text-xl font-semibold">{{ __('dashboard.specialist.requests_title') }}</h2>
            <x-empty-state>{{ __('dashboard.specialist.requests_empty') }}</x-empty-state>
        </section>
    </div>
@endsection
