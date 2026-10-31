@props(['verified' => false, 'date' => null])

{{-- Status weryfikacji: ikona + napis + zdanie wyjaśnienia (stan nigdy samym kolorem). --}}
@if ($verified)
    <x-alert variant="success" :title="__('specialist.public.verified')" {{ $attributes }}>
        <p>{{ __('specialist.public.verified_text', ['date' => $date]) }}</p>
        <p><a class="prose-link" href="{{ route('pages.verification') }}">{{ __('specialist.public.how_we_verify') }}</a></p>
    </x-alert>
@else
    <x-alert variant="warn" :title="__('specialist.public.pending')" {{ $attributes }}>
        <p>{{ __('specialist.public.pending_text') }}</p>
        <p><a class="prose-link" href="{{ route('pages.verification') }}">{{ __('specialist.public.how_we_verify') }}</a></p>
    </x-alert>
@endif
