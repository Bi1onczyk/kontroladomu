@php
    /**
     * Wspólne pola formularza zaświadczenia (dodawanie i edycja metadanych).
     *
     * @var string $prefix Przedrostek identyfikatorów pól (unikalny na stronie): "field" dla dodawania, "edit-{ulid}" dla edycji.
     * @var array{type: string, title: string, number: string, issuer: string, valid_until: string, no_expiry: bool} $values
     * @var bool $showErrors Czy pokazywać błędy walidacji w tym formularzu (błędy nie mają przypisanego formularza).
     * @var bool $withFile Czy dodać pole pliku i oświadczenie (tylko dodawanie).
     * @var array<int, \App\Domain\Specialists\Enums\CertificateType> $certificateTypes
     */
    $err = fn (string $key): ?string => $showErrors ? $errors->first($key) : null;
    $typeError = $err('type');
    $titleError = $err('title');
    $numberError = $err('number');
    $issuerError = $err('issuer');
    $validError = $err('valid_until');
    $fileError = $withFile ? $err('file') : null;
    $declarationError = $withFile ? $err('declaration') : null;
    $inputClass = 'block min-h-12 w-full rounded-md border bg-paper px-3 py-2 text-base text-ink-900 disabled:bg-paper-2 disabled:text-ink-600 ';
@endphp

<div
    x-data="certificateUpload"
    data-chosen="{{ __('certificates.form.file_chosen') }}"
    class="space-y-5"
>
    <fieldset id="{{ $prefix }}-type" tabindex="-1" class="space-y-2">
        <legend class="font-semibold text-ink-900">
            {{ __('certificates.form.type') }}
            <span class="font-normal text-ink-600">({{ __('auth.required') }})</span>
        </legend>
        @foreach ($certificateTypes as $type)
            <x-radio-option name="type" :id-prefix="$prefix" :value="$type->value" :checked="$values['type'] === $type->value">{{ $type->label() }}</x-radio-option>
        @endforeach
        <x-field-error :id="$prefix.'-type-error'" :message="$typeError" />
    </fieldset>

    <div class="space-y-1.5">
        <label for="{{ $prefix }}-title" class="block font-semibold text-ink-900">
            {{ __('certificates.form.scope') }} <span class="font-normal text-ink-600">({{ __('auth.required') }})</span>
        </label>
        <p id="{{ $prefix }}-title-hint" class="text-sm text-ink-600">{{ __('certificates.form.scope_hint') }}</p>
        <input id="{{ $prefix }}-title" name="title" type="text" maxlength="150" value="{{ $values['title'] }}" aria-required="true"
            aria-describedby="{{ $prefix }}-title-hint{{ $titleError ? ' '.$prefix.'-title-error' : '' }}" @if ($titleError) aria-invalid="true" @endif
            class="{{ $inputClass }}{{ $titleError ? 'border-danger' : 'border-ink-600' }}">
        <x-field-error :id="$prefix.'-title-error'" :message="$titleError" />
    </div>

    <div class="space-y-1.5">
        <label for="{{ $prefix }}-number" class="block font-semibold text-ink-900">
            {{ __('certificates.form.number') }} <span class="font-normal text-ink-600">({{ __('auth.required') }})</span>
        </label>
        <p id="{{ $prefix }}-number-hint" class="text-sm text-ink-600">{{ __('certificates.form.number_hint') }}</p>
        <input id="{{ $prefix }}-number" name="number" type="text" maxlength="80" autocomplete="off" value="{{ $values['number'] }}" aria-required="true"
            aria-describedby="{{ $prefix }}-number-hint{{ $numberError ? ' '.$prefix.'-number-error' : '' }}" @if ($numberError) aria-invalid="true" @endif
            class="{{ $inputClass }}{{ $numberError ? 'border-danger' : 'border-ink-600' }}">
        <x-field-error :id="$prefix.'-number-error'" :message="$numberError" />
    </div>

    <div class="space-y-1.5">
        <label for="{{ $prefix }}-issuer" class="block font-semibold text-ink-900">
            {{ __('certificates.form.issuer') }} <span class="font-normal text-ink-600">({{ __('auth.optional') }})</span>
        </label>
        <input id="{{ $prefix }}-issuer" name="issuer" type="text" maxlength="150" autocomplete="off" value="{{ $values['issuer'] }}"
            @if ($issuerError) aria-invalid="true" aria-describedby="{{ $prefix }}-issuer-error" @endif
            class="{{ $inputClass }}{{ $issuerError ? 'border-danger' : 'border-ink-600' }}">
        <x-field-error :id="$prefix.'-issuer-error'" :message="$issuerError" />
    </div>

    <div class="space-y-1.5">
        <label for="{{ $prefix }}-valid_until" class="block font-semibold text-ink-900">
            {{ __('certificates.form.valid_until') }} <span class="font-normal text-ink-600">({{ __('auth.required') }})</span>
        </label>
        <input id="{{ $prefix }}-valid_until" name="valid_until" type="date" value="{{ $values['valid_until'] }}"
            x-bind:disabled="dateDisabled"
            @if ($validError) aria-invalid="true" aria-describedby="{{ $prefix }}-valid_until-error" @endif
            class="{{ $inputClass }}sm:max-w-xs {{ $validError ? 'border-danger' : 'border-ink-600' }}">
        <div class="flex items-start gap-3">
            <input id="{{ $prefix }}-no_expiry" name="no_expiry" type="checkbox" value="1" x-ref="noExpiry" x-on:change="onNoExpiry"
                class="mt-1 size-6 shrink-0 rounded border-ink-600 accent-brand-700" @checked($values['no_expiry'])>
            <label for="{{ $prefix }}-no_expiry" class="min-h-11 cursor-pointer py-0.5 text-base text-ink-900">{{ __('certificates.form.no_expiry') }}</label>
        </div>
        <x-field-error :id="$prefix.'-valid_until-error'" :message="$validError" />
    </div>

    @if ($withFile)
        <div class="space-y-1.5">
            <label for="{{ $prefix }}-file" class="block font-semibold text-ink-900">
                {{ __('certificates.form.file') }} <span class="font-normal text-ink-600">({{ __('auth.required') }})</span>
            </label>
            <p id="{{ $prefix }}-file-hint" class="text-sm text-ink-600">{{ __('certificates.form.file_hint') }}</p>
            <input id="{{ $prefix }}-file" name="file" type="file" accept=".pdf,.jpg,.jpeg,.png,application/pdf,image/*"
                x-ref="file" x-on:change="onFile" aria-required="true"
                aria-describedby="{{ $prefix }}-file-hint{{ $fileError ? ' '.$prefix.'-file-error' : '' }}" @if ($fileError) aria-invalid="true" @endif
                class="block min-h-12 w-full rounded-md border bg-paper px-3 py-2 text-base text-ink-900 file:mr-3 file:min-h-10 file:rounded-md file:border-0 file:bg-brand-100 file:px-4 file:font-semibold file:text-brand-700 {{ $fileError ? 'border-danger' : 'border-ink-600' }}">
            <p class="text-sm text-ink-900" role="status" x-text="fileInfo"></p>
            <x-field-error :id="$prefix.'-file-error'" :message="$fileError" />
        </div>

        <div class="space-y-1.5">
            <div class="flex items-start gap-3">
                <input id="{{ $prefix }}-declaration" name="declaration" type="checkbox" value="1"
                    class="mt-1 size-6 shrink-0 rounded border-ink-600 accent-brand-700" aria-required="true"
                    @checked(old('declaration') && $showErrors)
                    @if ($declarationError) aria-invalid="true" aria-describedby="{{ $prefix }}-declaration-error" @endif>
                <label for="{{ $prefix }}-declaration" class="min-h-11 cursor-pointer py-0.5 text-base text-ink-900">
                    {{ __('certificates.form.declaration') }} <span class="text-ink-600">({{ __('auth.required') }})</span>
                </label>
            </div>
            <x-field-error :id="$prefix.'-declaration-error'" :message="$declarationError" />
        </div>
    @endif
</div>
