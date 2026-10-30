@props([
    'action',
    'method' => 'POST',
])

{{-- Formularz z @csrf, spoofingiem metody i blokadą podwójnego wysłania (Alpine: formLock). novalidate: komunikaty pokazuje serwer. --}}
<form method="{{ strtoupper($method) === 'GET' ? 'GET' : 'POST' }}" action="{{ $action }}" novalidate x-data="formLock" @submit="onSubmit" {{ $attributes }}>
    @if (strtoupper($method) !== 'GET')
        @csrf
    @endif
    @if (! in_array(strtoupper($method), ['GET', 'POST'], true))
        @method($method)
    @endif
    {{ $slot }}
</form>
