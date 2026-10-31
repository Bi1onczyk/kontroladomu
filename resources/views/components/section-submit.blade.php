@props(['busy' => null])

{{-- Dół formularza sekcji: przycisk (pełna szerokość na telefonie) i znacznik "Niezapisane zmiany" (Alpine formLock, tylko sygnał). --}}
<div class="flex flex-col gap-3 pt-2 sm:flex-row sm:items-center">
    <x-submit-button :busy="$busy ?? __('specialist.page.saving')" :full="false" class="w-full sm:w-auto">{{ $slot }}</x-submit-button>
    <p x-cloak x-show="showDirty" role="status" class="flex items-center gap-2 text-base text-ink-900">
        <x-icon name="info" class="size-5 text-ink-600" />
        {{ __('specialist.page.unsaved') }}
    </p>
</div>
