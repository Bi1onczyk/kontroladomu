@php
    use App\Domain\Specialists\Enums\LocationStatus;

    /** @var \App\Domain\Specialists\Data\ProfilePanelData $panel */
    $profile = $panel->profile;
    $status = $profile->location_status;
    $hasLocality = $profile->locality_simc !== null;
    $simc = (string) old('locality_simc', $profile->locality_simc);
    $label = (string) old('locality_label', $panel->localityLabel);
    $localityError = $errors->first('locality_simc');
    // Teksty podpowiedzi przekazujemy do komponentu Alpine jako JSON w atrybucie (escapowany przez {{ }}).
    $comboMessages = [
        'searching' => __('specialist.area.combo.searching'),
        'found' => __('specialist.area.combo.found'),
        'none' => __('specialist.area.combo.none'),
        'error' => __('specialist.area.combo.error'),
        'rate_limited' => __('specialist.area.combo.rate_limited'),
        'invalid' => __('specialist.area.combo.invalid'),
        'selected' => __('specialist.area.combo.selected'),
        'detail' => __('specialist.area.combo.detail'),
    ];
@endphp

<x-profile-section anchor="obszar" number="3" :title="__('specialist.area.title')">
    <div class="space-y-6">
        {{-- Stan ustalania położenia (location_status). Bez automatycznego odświeżania: link "Sprawdź ponownie" to zwykłe przeładowanie. --}}
        @if (! $hasLocality)
            <p class="text-ink-900">{{ __('specialist.area.status.empty') }}</p>
        @elseif ($status === LocationStatus::Pending)
            <div role="status" class="flex gap-3 rounded-md border border-l-4 border-ink-600 bg-paper-2 p-4 text-ink-900">
                <x-icon name="clock" class="mt-0.5 size-6 text-ink-600" />
                <div class="min-w-0 flex-1 space-y-2">
                    <p class="font-semibold">{{ __('specialist.area.status.pending_title') }}</p>
                    <p>{{ __('specialist.area.status.pending_text') }}</p>
                    <p><a class="prose-link inline-flex min-h-11 items-center" href="{{ route('specialist.profile.edit') }}#sekcja-obszar">{{ __('specialist.area.status.pending_check') }}</a></p>
                </div>
            </div>
        @elseif ($status === LocationStatus::Resolved)
            <x-alert variant="success" role="status" :title="__('specialist.area.status.resolved_title', ['name' => $panel->localityName])">
                <p>{{ __('specialist.area.status.resolved_text', ['km' => $profile->service_radius_km]) }}</p>
            </x-alert>
        @else
            <x-alert variant="error" :title="__('specialist.area.status.failed_title', ['name' => $panel->localityName])">
                <p>{{ __('specialist.area.status.failed_text') }}</p>
                <p><a class="prose-link" href="{{ route('contact') }}">{{ __('dashboard.footer.contact') }}</a></p>
                <x-form :action="route('specialist.profile.area.retry')">
                    <x-submit-button variant="secondary" :busy="__('specialist.page.saving')" :full="false" class="w-full sm:w-auto">{{ __('specialist.area.status.retry') }}</x-submit-button>
                </x-form>
            </x-alert>
        @endif

        <x-form :action="route('specialist.profile.area')" method="PUT" class="space-y-6">
            <div
                x-data="localityCombobox"
                data-url="{{ route('localities.search') }}"
                data-simc="{{ $simc }}"
                data-label="{{ $label }}"
                data-has-error="{{ $localityError ? '1' : '0' }}"
                data-messages="{{ json_encode($comboMessages, JSON_UNESCAPED_UNICODE) }}"
                class="space-y-3"
            >
                @if ($panel->localityLabel)
                    <div class="flex flex-wrap items-center justify-between gap-3">
                        <p class="font-semibold text-ink-900">{{ __('specialist.area.current', ['label' => $panel->localityLabel]) }}</p>
                        <button type="button" x-cloak x-show="showChange" x-on:click="startEditing" class="inline-flex min-h-11 items-center rounded-md border border-line bg-paper px-4 text-base font-semibold text-ink-900 hover:bg-paper-2">
                            {{ __('specialist.area.change') }}
                        </button>
                    </div>
                @endif

                <div x-show="editing" class="relative space-y-1.5">
                    <label for="field-locality_simc" class="block font-semibold text-ink-900">
                        {{ __('specialist.area.locality') }}
                        <span class="font-normal text-ink-600">({{ __('auth.required') }})</span>
                    </label>
                    <p id="field-locality-hint" class="text-sm text-ink-600">{{ __('specialist.area.locality_hint') }}</p>
                    <noscript><p class="text-sm font-semibold text-ink-900">{{ __('specialist.area.no_js') }}</p></noscript>

                    <input type="hidden" name="locality_simc" value="{{ $simc }}" x-bind:value="simc">
                    <input
                        id="field-locality_simc"
                        name="locality_label"
                        type="text"
                        value="{{ $label }}"
                        maxlength="150"
                        autocomplete="off"
                        autocapitalize="off"
                        spellcheck="false"
                        role="combobox"
                        aria-autocomplete="list"
                        aria-expanded="false"
                        aria-controls="locality-listbox"
                        aria-required="true"
                        aria-describedby="field-locality-hint{{ $localityError ? ' field-locality_simc-error' : '' }}"
                        @if ($localityError) aria-invalid="true" @endif
                        x-ref="input"
                        x-model="query"
                        x-bind:aria-expanded="expanded"
                        x-bind:aria-activedescendant="activeId"
                        x-on:input="onInput"
                        x-on:keydown.down="next"
                        x-on:keydown.up="previous"
                        x-on:keydown.enter="onEnter"
                        x-on:keydown.escape="close"
                        x-on:click.outside="close"
                        class="block min-h-12 w-full rounded-md border bg-paper px-3 py-2 text-base text-ink-900 {{ $localityError ? 'border-danger' : 'border-ink-600' }}"
                    >

                    <ul
                        id="locality-listbox"
                        role="listbox"
                        aria-label="{{ __('specialist.area.combo.list_label') }}"
                        x-cloak
                        x-show="open"
                        class="absolute z-20 mt-1 max-h-72 w-full overflow-auto rounded-md border border-line bg-paper"
                    >
                        <template x-for="item in items" x-bind:key="item.simc">
                            <li
                                role="option"
                                x-bind:id="item.optionId"
                                x-bind:aria-selected="item.selected"
                                x-bind:data-index="item.index"
                                x-bind:class="item.cls"
                                x-on:click="choose"
                                x-on:mousedown.prevent
                                class="min-h-12 cursor-pointer px-3 py-2 text-base text-ink-900 hover:bg-brand-100"
                            >
                                <span class="font-semibold" x-text="item.name"></span>
                                <span class="text-ink-600" x-text="item.kind"></span>
                                <span class="block text-sm text-ink-600" x-text="item.detail"></span>
                            </li>
                        </template>
                    </ul>

                    <p role="status" class="min-h-6 text-sm text-ink-900" x-text="status"></p>
                    <x-field-error id="field-locality_simc-error" :message="$localityError" />

                    <div class="flex items-start gap-3">
                        <input id="field-include_parts" type="checkbox" value="1" x-ref="parts" x-on:change="onPartsChange" class="mt-1 size-6 shrink-0 rounded border-ink-600 accent-brand-700">
                        <label for="field-include_parts" class="min-h-11 cursor-pointer py-0.5 text-base text-ink-900">{{ __('specialist.area.include_parts') }}</label>
                    </div>
                </div>
            </div>

            <x-form-field
                name="service_radius_km"
                :label="__('specialist.area.radius').' ('.__('specialist.area.radius_suffix').')'"
                :value="$profile->service_radius_km"
                :hint="__('specialist.area.radius_hint')"
                inputmode="numeric"
                maxlength="3"
                class="max-w-xs"
                :required="true"
            />

            <fieldset class="space-y-5 rounded-md border border-line p-4">
                <legend class="px-1 font-semibold text-ink-900">{{ __('specialist.area.travel_title') }}</legend>
                <x-form-field
                    name="travel_rate_per_km"
                    :label="__('specialist.area.travel_rate')"
                    :value="$panel->travelRateInput"
                    :hint="__('specialist.area.travel_rate_hint')"
                    inputmode="decimal"
                    maxlength="8"
                    class="max-w-xs"
                />
                <x-form-field
                    name="travel_free_km"
                    :label="__('specialist.area.travel_free_km')"
                    :value="$profile->travel_free_km"
                    :hint="__('specialist.area.travel_free_km_hint')"
                    inputmode="numeric"
                    maxlength="3"
                    class="max-w-xs"
                />
            </fieldset>

            <x-section-submit>{{ __('specialist.area.save') }}</x-section-submit>
        </x-form>
    </div>
</x-profile-section>
