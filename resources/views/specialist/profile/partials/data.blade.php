@php
    /** @var \App\Domain\Specialists\Data\ProfilePanelData $panel */
    /** @var array<string, int> $limits */
    $profile = $panel->profile;
    // Po błędzie walidacji formularz wraca z wpisanymi wartościami (old); odznaczone pole wyboru nie wraca w old, więc sprawdzamy, czy formularz był wysłany.
    $submitted = session()->hasOldInput('display_name');
    $accountType = old('account_type', $profile->account_type->value);
    $phonePublic = $submitted ? (bool) old('phone_is_public') : $profile->phone_is_public;
    $description = (string) old('description', $profile->description);
    $accountError = $errors->first('account_type');
    $phoneError = $errors->first('phone_is_public');
@endphp

<x-profile-section anchor="dane" number="1" :title="__('specialist.data.title')">
    <x-form :action="route('specialist.profile.update')" method="PUT" class="space-y-6">
        <x-form-field
            name="display_name"
            :label="__('specialist.data.display_name')"
            :value="$profile->display_name"
            :hint="__('specialist.data.display_name_hint')"
            :maxlength="$limits['display_name_max_length']"
            autocomplete="organization"
            :required="true"
        />

        <fieldset id="field-account_type" tabindex="-1" class="space-y-2" @if ($accountError) aria-describedby="field-account_type-error" @endif>
            <legend class="font-semibold text-ink-900">
                {{ __('specialist.data.account_type') }}
                <span class="font-normal text-ink-600">({{ __('auth.required') }})</span>
            </legend>
            @foreach ($accountTypes as $type)
                <x-radio-option
                    name="account_type"
                    :value="$type->value"
                    :checked="$accountType === $type->value"
                    :hint="$type->value === 'company' ? __('specialist.data.account_type_company_hint') : null"
                >{{ __('specialist.data.account_type_'.$type->value) }}</x-radio-option>
            @endforeach
            <x-field-error id="field-account_type-error" :message="$accountError" />
        </fieldset>

        @if ($panel->nip)
            <div class="space-y-1.5">
                <p id="nip-label" class="font-semibold text-ink-900">{{ __('specialist.data.nip') }}</p>
                <p aria-labelledby="nip-label" class="min-h-12 rounded-md border border-line bg-paper-2 px-3 py-2.5 text-base text-ink-900">{{ $panel->nip }}</p>
                <p class="text-sm text-ink-600">
                    {{ __('specialist.data.nip_hint') }}
                    <a class="prose-link" href="{{ route('contact') }}">{{ __('dashboard.footer.contact') }}</a>
                </p>
            </div>
        @endif

        <x-form-field
            name="phone"
            type="tel"
            :label="__('specialist.data.phone')"
            :value="$profile->phone"
            :hint="__('specialist.data.phone_hint')"
            inputmode="tel"
            autocomplete="tel"
            maxlength="20"
            :required="true"
        />

        <div class="space-y-1.5">
            <div class="flex items-start gap-3">
                <input
                    id="field-phone_is_public"
                    name="phone_is_public"
                    type="checkbox"
                    value="1"
                    class="mt-1 size-6 shrink-0 rounded border-ink-600 accent-brand-700"
                    @checked($phonePublic)
                    aria-describedby="field-phone_is_public-help{{ $phoneError ? ' field-phone_is_public-error' : '' }}"
                    @if ($phoneError) aria-invalid="true" @endif
                >
                <label for="field-phone_is_public" class="min-h-11 cursor-pointer py-0.5 font-semibold text-ink-900">{{ __('specialist.data.phone_is_public') }}</label>
            </div>
            <p id="field-phone_is_public-help" class="pl-9 text-sm text-ink-600">{{ __('specialist.data.phone_public_help') }}</p>
            <div class="pl-9"><x-field-error id="field-phone_is_public-error" :message="$phoneError" /></div>
        </div>

        <div
            x-data="charCounter"
            data-max="{{ $limits['description_max_length'] }}"
            data-template="{{ __('specialist.data.description_counter') }}"
            class="space-y-1.5"
        >
            @php $descriptionError = $errors->first('description'); @endphp
            <label for="field-description" class="block font-semibold text-ink-900">{{ __('specialist.data.description') }}</label>
            <p id="field-description-hint" class="text-sm text-ink-600">{{ __('specialist.data.description_hint') }}</p>
            <textarea
                id="field-description"
                name="description"
                rows="6"
                x-ref="field"
                x-on:input="update"
                aria-describedby="field-description-hint field-description-counter{{ $descriptionError ? ' field-description-error' : '' }}"
                @if ($descriptionError) aria-invalid="true" @endif
                class="block min-h-40 w-full rounded-md border bg-paper px-3 py-2 text-base text-ink-900 {{ $descriptionError ? 'border-danger' : 'border-ink-600' }}"
            >{{ $description }}</textarea>
            <p id="field-description-counter" class="text-sm text-ink-600" x-text="text">{{ __('specialist.data.description_counter', ['count' => mb_strlen($description), 'max' => $limits['description_max_length']]) }}</p>
            <p class="sr-only" role="status" aria-live="polite" x-text="live"></p>
            <x-field-error id="field-description-error" :message="$descriptionError" />
        </div>

        <x-section-submit>{{ __('specialist.data.save') }}</x-section-submit>
    </x-form>
</x-profile-section>
