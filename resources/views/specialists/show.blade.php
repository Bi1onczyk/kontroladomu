@extends('layouts.public')

{{-- Widok dostaje WYŁĄCZNIE PublicSpecialistData ($profile): nie ma tu modelu, więc nie da się wypisać pola prywatnego. --}}
@section('title', $profile->metaTitle)
@section('meta_description', $profile->metaDescription)
@section('canonical', $canonicalUrl)
@if ($noindex)
    @section('robots', 'noindex, nofollow')
@endif

@section('content')
    @php
        /** @var \App\Domain\Specialists\Data\PublicSpecialistData $profile */
        $verifiedDate = $profile->verifiedAt?->timezone('Europe/Warsaw')->format('d.m.Y');
    @endphp

    <div class="mx-auto max-w-6xl px-5 py-6 sm:py-10">
        @if ($isPreview)
            <x-alert variant="warn" role="note" class="mb-6">
                <p class="font-semibold">{{ __('specialist.public.preview_bar') }}</p>
                @if ($previewAudience === 'owner')
                    <p><a class="prose-link inline-flex min-h-11 items-center" href="{{ route('specialist.profile.edit') }}#publikacja">{{ __('specialist.public.preview_publish') }}</a></p>
                @elseif ($previewAudience === 'admin')
                    <p><a class="prose-link inline-flex min-h-11 items-center" href="{{ route('dashboard') }}">{{ __('specialist.public.preview_back_admin') }}</a></p>
                @endif
            </x-alert>
        @endif

        {{-- Nagłówek. Po lewej zostawiamy miejsce na zdjęcie/logo, gdy zostanie dodane (poza zakresem zadania 004). --}}
        <header class="max-w-3xl">
            <h1 class="text-3xl font-semibold tracking-tight text-ink-900 sm:text-4xl">{{ $profile->displayName }}</h1>
            <p class="mt-1 text-lg text-ink-600">{{ $profile->accountTypeLabel }}</p>
            <x-verification-badge class="mt-4" :verified="$profile->isVerified" :date="$verifiedDate" />
        </header>

        <div class="mt-8 grid gap-8 lg:grid-cols-3 lg:items-start">
            {{-- Kolejność w kodzie = kolejność na telefonie (kontakt, obszar, potem treść); na komputerze ta kolumna jest po prawej. --}}
            <aside class="space-y-6 lg:sticky lg:top-6 lg:col-start-3 lg:row-start-1" aria-label="{{ __('specialist.public.contact_title') }}">
                <section class="space-y-4 rounded-lg border border-line bg-paper-2 p-5" aria-labelledby="kontakt-title">
                    <h2 id="kontakt-title" class="text-xl font-semibold text-ink-900">{{ __('specialist.public.contact_title') }}</h2>

                    @if ($profile->phone && $profile->phoneLink)
                        <x-button :href="'tel:'.$profile->phoneLink" :full="true">{{ __('specialist.public.call', ['phone' => $profile->phone]) }}</x-button>
                    @else
                        <p class="text-ink-900">{{ __('specialist.public.phone_none') }}</p>
                    @endif

                    <div class="space-y-2">
                        <x-button-disabled class="w-full">{{ __('specialist.public.book') }}</x-button-disabled>
                        <p class="text-sm text-ink-600">{{ __('specialist.public.book_soon') }}</p>
                    </div>
                </section>

                @if ($profile->areaLabel)
                    <section class="space-y-1" aria-labelledby="obszar-title">
                        <h2 id="obszar-title" class="text-xl font-semibold text-ink-900">{{ __('specialist.public.area') }}</h2>
                        <p class="text-ink-900">{{ __('specialist.public.area_text', ['locality' => $profile->areaLabel, 'km' => $profile->radiusKm]) }}</p>
                        <p class="text-sm text-ink-600">{{ __('specialist.public.area_note') }}</p>
                    </section>
                @endif

                <p class="hidden lg:block">
                    <a class="prose-link inline-flex min-h-11 items-center text-base" href="{{ route('contact') }}">{{ __('specialist.public.report') }}</a>
                </p>
            </aside>

            <div class="space-y-8 lg:col-span-2 lg:row-start-1">
                @if ($profile->description)
                    <section aria-labelledby="opis-title">
                        <h2 id="opis-title" class="text-xl font-semibold text-ink-900">{{ __('specialist.public.about') }}</h2>
                        <p class="mt-2 max-w-prose whitespace-pre-line text-ink-900">{{ $profile->description }}</p>
                    </section>
                @endif

                @if ($profile->serviceGroups)
                    <section aria-labelledby="uslugi-title">
                        <h2 id="uslugi-title" class="text-xl font-semibold text-ink-900">{{ __('specialist.public.services') }}</h2>

                        <div class="mt-3 space-y-5">
                            @foreach ($profile->serviceGroups as $group)
                                <div>
                                    <h3 id="grupa-{{ $group->inspectionType }}" class="font-semibold text-ink-900">{{ $group->label }}</h3>
                                    <table class="mt-1 w-full border-collapse text-left" aria-labelledby="grupa-{{ $group->inspectionType }}">
                                        <tbody>
                                            @foreach ($group->prices as $price)
                                                <tr class="border-t border-line">
                                                    <th scope="row" class="py-2 pr-3 font-normal text-ink-900">{{ $price->propertyLabel }}</th>
                                                    <td class="whitespace-nowrap py-2 text-right font-semibold text-ink-900">{{ $price->priceFormatted ?? __('specialist.public.price_individual') }}</td>
                                                </tr>
                                            @endforeach
                                        </tbody>
                                    </table>
                                </div>
                            @endforeach
                        </div>

                        <p class="mt-3 text-sm text-ink-600">{{ __('specialist.public.price_note') }}</p>

                        @if ($profile->travelRateFormatted)
                            <p class="mt-3 text-ink-900">{{ $profile->travelFreeKm
                                ? __('specialist.public.travel', ['rate' => $profile->travelRateFormatted, 'free' => $profile->travelFreeKm])
                                : __('specialist.public.travel_no_free', ['rate' => $profile->travelRateFormatted]) }}</p>
                        @endif
                    </section>
                @endif

                @if ($profile->certificates || $profile->certificatesPending)
                    <section aria-labelledby="uprawnienia-title">
                        <h2 id="uprawnienia-title" class="text-xl font-semibold text-ink-900">{{ __('specialist.public.certificates') }}</h2>
                        @if ($profile->certificates)
                            <ul class="mt-2 space-y-2">
                                @foreach ($profile->certificates as $certificate)
                                    <li class="text-ink-900">
                                        <span class="font-semibold">{{ $certificate->typeLabel }}</span>: {{ $certificate->title }},
                                        {{ $certificate->validUntil ? __('specialist.public.valid_until', ['date' => $certificate->validUntil->format('d.m.Y')]) : __('specialist.public.valid_indefinitely') }}
                                    </li>
                                @endforeach
                            </ul>
                        @else
                            <p class="mt-2 text-ink-900">{{ __('specialist.public.certificates_pending') }}</p>
                        @endif
                    </section>
                @endif

                <p class="lg:hidden">
                    <a class="prose-link inline-flex min-h-11 items-center text-base" href="{{ route('contact') }}">{{ __('specialist.public.report') }}</a>
                </p>
            </div>
        </div>
    </div>
@endsection
