@extends('layouts.app')

@section('title', __('certificates.page.title'))
@section('flash_sticky', '1')

@section('content')
    @php
        /** @var \App\Domain\Specialists\Models\SpecialistProfile $profile */
        /** @var \Illuminate\Support\Collection<int, \App\Domain\Specialists\Models\SpecialistCertificate> $certificates */
        // Błędy walidacji nie mają przypisanego formularza, dlatego każdy formularz wysyła ukryte pole "_form"
        // ("add" albo "edit-{ulid}") i błędy pokazujemy tylko w tym, który został wysłany. Brak pola = błąd serwera przy uploadzie.
        $submittedForm = old('_form');
        $addActive = $submittedForm === null || $submittedForm === 'add';
        $verified = $profile->verification_status->value === 'verified';
        $formatSize = fn (int $bytes): string => $bytes >= 1048576
            ? number_format($bytes / 1048576, 1, ',', '').' MB'
            : max(1, (int) round($bytes / 1024)).' KB';
        $addValues = [
            'type' => (string) ($addActive ? old('type', '') : ''),
            'title' => (string) ($addActive ? old('title', '') : ''),
            'number' => (string) ($addActive ? old('number', '') : ''),
            'issuer' => (string) ($addActive ? old('issuer', '') : ''),
            'valid_until' => (string) ($addActive ? old('valid_until', '') : ''),
            'no_expiry' => $addActive && (bool) old('no_expiry'),
        ];
        $addOpen = $certificates->isEmpty() || ($errors->any() && $addActive);
    @endphp

    <h1 class="text-2xl font-semibold text-ink-900 md:text-3xl">{{ __('certificates.page.title') }}</h1>

    @include('specialist.partials.tabs', ['current' => 'certificates'])

    <div class="mt-5 max-w-prose space-y-2 text-ink-900">
        <p>{{ __('certificates.page.intro_why') }}</p>
        <p>{{ __('certificates.page.intro_who') }}</p>
        <p>{{ __('certificates.page.intro_pesel') }}</p>
    </div>

    <div class="mt-6">
        <x-error-summary />
    </div>

    <section id="lista" aria-labelledby="lista-title" class="mt-6 scroll-mt-20 space-y-4">
        <div class="flex flex-wrap items-baseline justify-between gap-x-4">
            <h2 id="lista-title" tabindex="-1" class="text-xl font-semibold text-ink-900">{{ __('certificates.list.heading') }}</h2>
            <p class="text-sm text-ink-600">{{ __('certificates.list.count', ['count' => $certificates->count(), 'max' => $limits['max_count']]) }}</p>
        </div>

        @if ($certificates->isEmpty())
            <x-empty-state :title="__('certificates.list.empty_title')">
                {{ __('certificates.list.empty_text') }}
                <x-slot:action>
                    <x-button href="#dodaj">{{ __('certificates.list.empty_cta') }}</x-button>
                </x-slot:action>
            </x-empty-state>
        @else
            <ul class="space-y-4">
                @foreach ($certificates as $certificate)
                    @php
                        $days = $certificate->daysUntilExpiry();
                        $editPrefix = 'edit-'.$certificate->ulid;
                        $editActive = $submittedForm === $editPrefix;
                        $addedAfterVerification = $verified && $profile->verified_at !== null && $certificate->updated_at !== null && $certificate->updated_at->gt($profile->verified_at);
                        $editValues = [
                            'type' => (string) ($editActive ? old('type') : $certificate->type->value),
                            'title' => (string) ($editActive ? old('title') : $certificate->title),
                            'number' => (string) ($editActive ? old('number') : $certificate->number),
                            'issuer' => (string) ($editActive ? old('issuer') : $certificate->issuer),
                            'valid_until' => (string) ($editActive ? old('valid_until') : $certificate->valid_until?->format('Y-m-d')),
                            'no_expiry' => $editActive ? (bool) old('no_expiry') : $certificate->valid_until === null,
                        ];
                    @endphp
                    <li>
                        <article class="rounded-lg border border-line bg-paper p-5" aria-labelledby="cert-{{ $certificate->ulid }}-title">
                            <h3 id="cert-{{ $certificate->ulid }}-title" class="text-lg font-semibold text-ink-900">{{ $certificate->type->label() }}</h3>
                            <p class="text-ink-900">{{ $certificate->title }}</p>

                            <dl class="mt-3 grid gap-x-6 gap-y-2 sm:grid-cols-2">
                                <div>
                                    <dt class="text-sm text-ink-600">{{ __('certificates.list.number') }}</dt>
                                    <dd class="break-words text-ink-900">{{ $certificate->number }}</dd>
                                </div>
                                @if ($certificate->issuer)
                                    <div>
                                        <dt class="text-sm text-ink-600">{{ __('certificates.list.issuer') }}</dt>
                                        <dd class="break-words text-ink-900">{{ $certificate->issuer }}</dd>
                                    </div>
                                @endif
                                <div>
                                    <dt class="text-sm text-ink-600">{{ __('certificates.form.valid_until') }}</dt>
                                    <dd class="text-ink-900">
                                        @if ($days === null)
                                            {{ __('certificates.list.indefinite') }}
                                        @elseif ($days < 0)
                                            <span class="inline-flex items-center gap-1.5 rounded-md border border-danger bg-danger-100 px-2 py-0.5 font-semibold">
                                                <x-icon name="alert" class="size-4 text-danger" />{{ __('certificates.list.expired', ['date' => $certificate->valid_until->format('d.m.Y')]) }}
                                            </span>
                                        @elseif ($days <= $expiryWarningDays)
                                            <span class="inline-flex items-center gap-1.5 rounded-md border border-warn bg-warn-100 px-2 py-0.5 font-semibold">
                                                <x-icon name="alert" class="size-4 text-warn" />{{ __('certificates.list.expires_soon', ['days' => $days]) }}
                                            </span>
                                        @else
                                            {{ __('certificates.list.valid_until', ['date' => $certificate->valid_until->format('d.m.Y')]) }}
                                        @endif
                                    </dd>
                                </div>
                                <div>
                                    <dt class="text-sm text-ink-600">{{ __('certificates.list.file') }}</dt>
                                    <dd class="break-words text-ink-900">
                                        {{ $certificate->file_original_name }}
                                        <span class="text-ink-600">({{ strtoupper($certificate->fileExtension()) }}, {{ $formatSize((int) $certificate->file_size) }})</span>
                                    </dd>
                                </div>
                                @if ($addedAfterVerification || ! $verified)
                                    <div class="sm:col-span-2">
                                        <dt class="text-sm text-ink-600">{{ __('certificates.list.status') }}</dt>
                                        <dd class="inline-flex items-center gap-1.5 text-ink-900">
                                            <x-icon name="clock" class="size-5 text-ink-600" />
                                            {{ $addedAfterVerification ? __('certificates.list.added_after_verification') : __('certificates.list.status_waiting') }}
                                        </dd>
                                    </div>
                                @endif
                            </dl>

                            <div class="mt-4 flex flex-wrap items-start gap-2">
                                <x-button :href="route('specialist.certificates.file', $certificate)" variant="secondary">{{ __('certificates.list.download') }}</x-button>

                                {{-- Edycja metadanych: <details> działa bez JS. Plik zmienia się wyłącznie przez usunięcie i dodanie nowego. --}}
                                <details class="open:basis-full" @if ($editActive) open @endif>
                                    <summary class="inline-flex min-h-12 cursor-pointer list-none items-center justify-center rounded-md border border-line bg-paper px-5 text-base font-semibold text-ink-900 hover:bg-paper-2 [&::-webkit-details-marker]:hidden">{{ __('certificates.list.edit') }}</summary>
                                    <div class="mt-3 rounded-md border border-line bg-paper-2 p-4">
                                        <h4 class="font-semibold text-ink-900">{{ __('certificates.list.edit_title') }}</h4>
                                        <p class="mb-4 text-sm text-ink-600">{{ __('certificates.list.edit_note') }}</p>
                                        <x-form :action="route('specialist.certificates.update', $certificate)" method="PUT" class="space-y-5">
                                            <input type="hidden" name="_form" value="{{ $editPrefix }}">
                                            @include('specialist.certificates.partials.fields', [
                                                'prefix' => $editPrefix,
                                                'values' => $editValues,
                                                'showErrors' => $editActive,
                                                'withFile' => false,
                                            ])
                                            <x-submit-button :busy="__('specialist.page.saving')" :full="false" class="w-full sm:w-auto">{{ __('certificates.list.save') }}</x-submit-button>
                                        </x-form>
                                    </div>
                                </details>

                                {{-- Usuwanie: potwierdzenie w treści strony (bez okna modalnego). Anuluj dostaje fokus po rozwinięciu. --}}
                                <details x-data="confirmDelete" x-on:toggle="onToggle" class="open:basis-full">
                                    <summary x-ref="summary" class="inline-flex min-h-12 cursor-pointer list-none items-center justify-center rounded-md border border-danger bg-paper px-5 text-base font-semibold text-danger hover:bg-danger-100 [&::-webkit-details-marker]:hidden">{{ __('certificates.list.delete') }}</summary>
                                    <div class="mt-3 rounded-md border border-l-4 border-danger bg-danger-100 p-4 text-ink-900">
                                        <p class="flex items-start gap-2 font-semibold">
                                            <x-icon name="alert" class="mt-0.5 size-6 text-danger" />
                                            {{ __('certificates.list.delete_title', ['title' => $certificate->title]) }}
                                        </p>
                                        <p class="mt-1">{{ __('certificates.list.delete_text') }}</p>
                                        <div class="mt-4 flex flex-wrap gap-3">
                                            <x-form :action="route('specialist.certificates.destroy', $certificate)" method="DELETE">
                                                <x-submit-button variant="danger" :busy="__('certificates.list.deleting')" :full="false" class="w-full sm:w-auto">{{ __('certificates.list.delete_confirm') }}</x-submit-button>
                                            </x-form>
                                            <button type="button" x-ref="cancel" x-cloak x-show="ready" x-on:click="cancel" class="inline-flex min-h-12 items-center justify-center rounded-md border border-line bg-paper px-5 text-base font-semibold text-ink-900 hover:bg-paper-2">{{ __('certificates.list.cancel') }}</button>
                                        </div>
                                    </div>
                                </details>
                            </div>
                        </article>
                    </li>
                @endforeach
            </ul>
        @endif
    </section>

    <section id="dodaj" aria-labelledby="dodaj-title" class="mt-8 scroll-mt-20">
        <details class="rounded-lg border border-line bg-paper" @if ($addOpen) open @endif>
            <summary class="flex min-h-14 cursor-pointer list-none items-center px-5 text-xl font-semibold text-ink-900 [&::-webkit-details-marker]:hidden">
                <span id="dodaj-title">{{ __('certificates.form.title') }}</span>
            </summary>
            <div class="border-t border-line p-5 sm:p-6">
                <x-form :action="route('specialist.certificates.store')" enctype="multipart/form-data" class="space-y-5">
                    <input type="hidden" name="_form" value="add">
                    @include('specialist.certificates.partials.fields', [
                        'prefix' => 'field',
                        'values' => $addValues,
                        'showErrors' => $addActive,
                        'withFile' => true,
                    ])
                    <div class="space-y-2">
                        <x-submit-button :busy="__('certificates.form.uploading')" :full="false" class="w-full sm:w-auto">{{ __('certificates.form.submit') }}</x-submit-button>
                        <p x-cloak x-show="busy" role="status" class="text-sm text-ink-900">{{ __('certificates.form.uploading_note') }}</p>
                    </div>
                </x-form>
            </div>
        </details>
    </section>

    {{-- DO WERYFIKACJI PRZEZ PRAWNIKA (okres i podstawa przechowywania skanów po usunięciu konta; ADR-021). --}}
    <aside class="mt-8 rounded-md border border-line bg-paper-2 p-4 text-sm text-ink-900" aria-label="{{ __('certificates.page.retention_label') }}">
        <p>{{ __('certificates.page.retention_note') }}</p>
    </aside>
@endsection
