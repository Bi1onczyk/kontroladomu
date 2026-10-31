@php
    /** @var \App\Domain\Specialists\Data\ProfilePanelData $panel */
    // Po błędzie walidacji pokazujemy to, co wpisano (old), inaczej zapisany cennik.
    $oldServices = old('services');
    $fromOld = is_array($oldServices);
    $stateOf = function (string $inspection, string $property) use ($panel, $oldServices, $fromOld): array {
        if ($fromOld) {
            return [
                'enabled' => (bool) data_get($oldServices, "{$inspection}.{$property}.enabled"),
                'price' => (string) data_get($oldServices, "{$inspection}.{$property}.price", ''),
                'individual' => (bool) data_get($oldServices, "{$inspection}.{$property}.individual"),
            ];
        }

        return $panel->servicesMatrix[$inspection][$property] ?? ['enabled' => false, 'price' => '', 'individual' => false];
    };
    $servicesError = $errors->first('services');
@endphp

<x-profile-section anchor="uslugi" number="2" :title="__('specialist.services.title')">
    <x-form :action="route('specialist.profile.services')" method="PUT" class="space-y-6">
        <div x-data="servicesMatrix" x-on:change="onChange" class="space-y-6">
            @if ($panel->servicesPositionCount > 0)
                <p class="font-semibold text-ink-900" role="status">
                    {{ __('specialist.services.summary', ['inspections' => $panel->servicesInspectionCount, 'positions' => $panel->servicesPositionCount]) }}
                </p>
            @else
                <x-empty-state>{{ __('specialist.services.empty') }}</x-empty-state>
            @endif

            @if ($servicesError)
                <div id="field-services"><x-field-error id="field-services-error" :message="$servicesError" /></div>
            @endif

            {{-- Pytanie 1: jakie przeglądy. Pola bez nazwy: tylko sterują widocznością bloków (bez JS wszystkie bloki są widoczne). --}}
            <fieldset class="space-y-1">
                <legend class="font-semibold text-ink-900">{{ __('specialist.services.intro_types') }}</legend>
                <p class="text-sm text-ink-600">{{ __('specialist.services.select_inspection_hint') }}</p>
                @foreach ($inspectionTypes as $inspection)
                    <div class="flex items-start gap-3">
                        <input
                            id="inspection-toggle-{{ $inspection->value }}"
                            type="checkbox"
                            data-inspection-toggle="{{ $inspection->value }}"
                            class="mt-1 size-6 shrink-0 rounded border-ink-600 accent-brand-700"
                            @checked(collect($propertyTypes)->contains(fn ($property) => $stateOf($inspection->value, $property->value)['enabled']))
                            aria-describedby="inspection-toggle-{{ $inspection->value }}-hint"
                        >
                        <label for="inspection-toggle-{{ $inspection->value }}" class="min-h-11 flex-1 cursor-pointer py-0.5 text-base text-ink-900">
                            <span class="font-semibold">{{ $inspection->label() }}</span>
                            <span id="inspection-toggle-{{ $inspection->value }}-hint" class="block text-sm text-ink-600">{{ $inspection->hint() }}</span>
                        </label>
                    </div>
                @endforeach
            </fieldset>

            <p class="text-ink-900">{{ __('specialist.services.intro_matrix') }}</p>

            {{-- Pytanie 2: typy nieruchomości i ceny dla każdego przeglądu. --}}
            @foreach ($inspectionTypes as $inspection)
                <fieldset data-inspection="{{ $inspection->value }}" class="rounded-md border border-line p-4">
                    <legend class="px-1 font-semibold text-ink-900">{{ __('specialist.services.types_legend', ['inspection' => $inspection->label()]) }}</legend>

                    <button type="button" x-cloak x-on:click="selectAll" class="mb-2 inline-flex min-h-11 items-center text-base font-semibold text-brand-700 underline underline-offset-4 hover:text-brand-600">
                        {{ __('specialist.services.select_all_types') }}
                    </button>

                    <div class="divide-y divide-line">
                        @foreach ($propertyTypes as $property)
                            @php
                                $row = $stateOf($inspection->value, $property->value);
                                $base = $inspection->value.'-'.$property->value;
                                $priceKey = "services.{$inspection->value}.{$property->value}.price";
                                $priceError = $errors->first($priceKey);
                                $fieldName = "services[{$inspection->value}][{$property->value}]";
                            @endphp
                            <div data-row class="grid gap-3 py-3 sm:grid-cols-[minmax(0,1fr)_13rem_auto] sm:items-start">
                                <div class="flex items-start gap-3">
                                    <input
                                        id="svc-{{ $base }}-enabled"
                                        name="{{ $fieldName }}[enabled]"
                                        type="checkbox"
                                        value="1"
                                        data-enabled
                                        class="mt-1 size-6 shrink-0 rounded border-ink-600 accent-brand-700"
                                        @checked($row['enabled'])
                                    >
                                    <label for="svc-{{ $base }}-enabled" class="min-h-11 flex-1 cursor-pointer py-0.5 text-base text-ink-900">
                                        <span class="sr-only">{{ __('specialist.services.performs') }}: </span>{{ $property->label() }}
                                        @if ($property->hint())
                                            <span class="block text-sm text-ink-600">{{ $property->hint() }}</span>
                                        @endif
                                    </label>
                                </div>

                                <div class="space-y-1">
                                    <label for="{{ 'field-'.$priceKey }}" class="block text-sm font-semibold text-ink-900 sm:sr-only">{{ __('specialist.services.price') }}</label>
                                    <div class="flex items-center gap-2">
                                        <input
                                            id="{{ 'field-'.$priceKey }}"
                                            name="{{ $fieldName }}[price]"
                                            type="text"
                                            inputmode="decimal"
                                            autocomplete="off"
                                            maxlength="10"
                                            data-price
                                            value="{{ $row['price'] }}"
                                            class="block min-h-12 w-full min-w-0 rounded-md border px-3 py-2 text-base text-ink-900 disabled:bg-paper-2 disabled:text-ink-600 {{ $priceError ? 'border-danger' : 'border-ink-600' }} bg-paper"
                                            @if ($priceError) aria-invalid="true" aria-describedby="{{ 'field-'.$priceKey }}-error" @endif
                                        >
                                        <span class="shrink-0 text-base text-ink-900">{{ __('specialist.services.price_suffix') }}</span>
                                    </div>
                                    <p data-individual-note @unless ($row['individual'] && $row['enabled']) hidden @endunless class="text-sm text-ink-600">{{ __('specialist.services.individual_note') }}</p>
                                    <x-field-error :id="'field-'.$priceKey.'-error'" :message="$priceError" />
                                </div>

                                <div class="flex items-start gap-3">
                                    <input
                                        id="svc-{{ $base }}-individual"
                                        name="{{ $fieldName }}[individual]"
                                        type="checkbox"
                                        value="1"
                                        data-individual
                                        class="mt-1 size-6 shrink-0 rounded border-ink-600 accent-brand-700"
                                        @checked($row['individual'])
                                    >
                                    <label for="svc-{{ $base }}-individual" class="min-h-11 cursor-pointer py-0.5 text-base text-ink-900">{{ __('specialist.services.individual') }}</label>
                                </div>
                            </div>
                        @endforeach
                    </div>
                </fieldset>
            @endforeach

            <p class="text-sm text-ink-600">{{ __('specialist.services.price_note') }}</p>
        </div>

        <x-section-submit>{{ __('specialist.services.save') }}</x-section-submit>
    </x-form>
</x-profile-section>
