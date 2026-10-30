@props(['id', 'message' => null])

@if ($message)
    <p id="{{ $id }}" class="flex items-start gap-1.5 text-sm font-semibold text-danger">
        <x-icon name="alert" class="mt-0.5 size-4" />
        <span><span class="sr-only">{{ __('ui.error_prefix') }}</span> {{ $message }}</span>
    </p>
@endif
