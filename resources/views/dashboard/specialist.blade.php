@extends('layouts.app')

@section('title', __('dashboard.nav.today'))

@section('content')
    @php
        /** @var \App\Domain\Specialists\Models\SpecialistProfile|null $profile */
        /** @var \App\Domain\Specialists\Support\ProfileCompleteness|null $completeness */
        $verified = $profile?->verification_status->value === 'verified';
        $profileUrl = route('specialist.profile.edit');
        // Lista do ukończenia profilu: dwa pierwsze kroki to bramki konta, potem trzy wymagane do publikacji
        // i dwa zalecane (opis, zaświadczenia) z ProfileCompleteness. Każdy niegotowy krok prowadzi do swojej sekcji.
        $checklist = [
            ['label' => __('dashboard.specialist.check_email'), 'done' => $user->hasVerifiedEmail()],
            ['label' => __('dashboard.specialist.check_2fa'), 'done' => $user->hasConfirmedTwoFactor()],
        ];
        $links = [
            'data' => $profileUrl.'#sekcja-dane',
            'services' => $profileUrl.'#sekcja-uslugi',
            'area' => $profileUrl.'#sekcja-obszar',
            'description' => $profileUrl.'#sekcja-dane',
            'certificates' => route('specialist.certificates.index'),
        ];
        foreach ($completeness?->required() ?? [] as $key => $done) {
            $checklist[] = ['label' => __('specialist.steps.'.$key), 'done' => $done, 'link' => $links[$key]];
        }
        foreach ($completeness?->recommended() ?? [] as $key => $done) {
            $checklist[] = ['label' => __('specialist.steps.'.$key), 'done' => $done, 'link' => $links[$key], 'recommended' => true];
        }
    @endphp

    <h1 class="text-2xl font-semibold text-ink-900 md:text-3xl">{{ __('dashboard.greeting', ['name' => $user->name]) }}</h1>

    <div class="mt-6">
        <x-dashboard-notices :user="$user" />
    </div>

    {{-- Stan weryfikacji: ramka z tekstem i ikoną (nie sam kolor) + lista do uzupełnienia --}}
    @if ($verified)
        <x-alert variant="success" :title="__('specialist.publish.verified_title')">
            <p>{{ __('specialist.publish.verified_text', ['date' => $profile->verified_at?->timezone('Europe/Warsaw')->format('d.m.Y')]) }}</p>
        </x-alert>
    @endif

    <x-alert :variant="$verified ? 'info' : 'warn'" :title="$verified ? null : __('dashboard.specialist.pending_title')" class="{{ $verified ? 'mt-4' : '' }}">
        @unless ($verified)
            <p>{{ __('dashboard.specialist.pending_text') }}</p>
        @endunless

        <h2 class="pt-2 font-semibold">{{ __('dashboard.specialist.checklist_title') }}</h2>
        <ul class="space-y-1">
            @foreach ($checklist as $item)
                <li class="flex min-h-8 flex-wrap items-center gap-x-2">
                    @if ($item['done'])
                        <x-icon name="check" class="size-5 text-ok" />
                        <span>{{ $item['label'] }}: <strong>{{ __('dashboard.specialist.done') }}</strong></span>
                    @else
                        <x-icon name="clock" class="size-5 text-ink-600" />
                        <span>{{ $item['label'] }}: <strong>{{ ($item['recommended'] ?? false) ? __('dashboard.specialist.recommended') : __('dashboard.specialist.todo') }}</strong></span>
                        @isset($item['link'])
                            <a class="prose-link inline-flex min-h-11 items-center" href="{{ $item['link'] }}" aria-label="{{ __('dashboard.specialist.go_profile') }}: {{ $item['label'] }}">{{ __('dashboard.specialist.go_profile') }}</a>
                        @endisset
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
