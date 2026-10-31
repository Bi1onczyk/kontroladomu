@php
    /** @var \App\Domain\Specialists\Data\ProfilePanelData $panel */
    /** @var array<string, int> $limits */
    $profile = $panel->profile;
    $locked = $panel->slugNextChangeAt !== null;
    $slugError = $errors->first('slug');
    $slugRule = __('specialist.slug.rules');
    $describedBy = 'field-slug-rules'.($slugError ? ' field-slug-error' : '');
@endphp

<x-profile-section anchor="adres" number="4" :title="__('specialist.slug.title')">
    <div class="space-y-5">
        <div class="space-y-1.5">
            <p class="font-semibold text-ink-900">{{ __('specialist.slug.current') }}</p>
            <div class="flex flex-wrap items-center gap-x-4 gap-y-1">
                <p class="min-w-0 break-all text-base text-ink-900">{{ __('specialist.slug.prefix') }}<strong>{{ $profile->slug }}</strong></p>
                <span x-data="copyToClipboard" data-copy-text="{{ $panel->publicUrl }}" data-copied-text="{{ __('specialist.slug.copied') }}" class="contents">
                    <button type="button" x-cloak x-show="supported" x-on:click="copy" class="inline-flex min-h-11 items-center rounded-md border border-line bg-paper px-4 text-base font-semibold text-ink-900 hover:bg-paper-2">{{ __('specialist.slug.copy') }}</button>
                    <span class="text-sm text-ink-600" role="status" x-text="message"></span>
                </span>
            </div>
        </div>

        @if ($locked)
            <x-alert variant="info">
                <p>{{ __('specialist.slug.locked', [
                    'date' => $panel->slugChangedAt?->timezone('Europe/Warsaw')->format('d.m.Y'),
                    'next_date' => $panel->slugNextChangeAt->timezone('Europe/Warsaw')->format('d.m.Y'),
                ]) }}</p>
            </x-alert>
        @else
            <x-form :action="route('specialist.profile.slug')" method="PUT" class="space-y-5">
                @if ($panel->slugRequiresConfirmation)
                    <x-alert variant="warn" :title="__('specialist.slug.warning_title')">
                        <p>{{ __('specialist.slug.warning_text') }}</p>
                    </x-alert>
                @else
                    <p class="text-ink-900">{{ __('specialist.slug.free_before_publish') }}</p>
                @endif

                <div class="space-y-1.5">
                    <label for="field-slug" class="block font-semibold text-ink-900">
                        {{ __('specialist.slug.label') }}
                        <span class="font-normal text-ink-600">({{ __('auth.required') }})</span>
                    </label>
                    <p id="field-slug-rules" class="text-sm text-ink-600">{{ $slugRule }}</p>
                    <div class="flex flex-col gap-1 sm:flex-row sm:items-center sm:gap-2">
                        <span class="text-base text-ink-900" aria-hidden="true">{{ __('specialist.slug.prefix') }}</span>
                        <input
                            id="field-slug"
                            name="slug"
                            type="text"
                            value="{{ old('slug', $profile->slug) }}"
                            minlength="{{ $limits['slug_min_length'] }}"
                            maxlength="{{ $limits['slug_max_length'] }}"
                            autocomplete="off"
                            autocapitalize="off"
                            spellcheck="false"
                            aria-required="true"
                            aria-describedby="{{ $describedBy }}"
                            @if ($slugError) aria-invalid="true" @endif
                            class="block min-h-12 w-full rounded-md border bg-paper px-3 py-2 text-base text-ink-900 sm:max-w-md {{ $slugError ? 'border-danger' : 'border-ink-600' }}"
                        >
                    </div>
                    <x-field-error id="field-slug-error" :message="$slugError" />
                </div>

                @if ($panel->slugRequiresConfirmation)
                    <x-checkbox-field name="slug_confirm" :required="true">{{ __('specialist.slug.confirm') }}</x-checkbox-field>
                @endif

                <x-section-submit>{{ $panel->slugRequiresConfirmation ? __('specialist.slug.change') : __('specialist.page.save_address') }}</x-section-submit>
            </x-form>
        @endif
    </div>
</x-profile-section>
