@extends('layouts.auth')

@php
    // Czas do ponowienia z nagłówka Retry-After (jeśli jest); bez niego ogólnie "kilka minut".
    $retryAfter = (int) ($exception->getHeaders()['Retry-After'] ?? 0);
    if ($retryAfter <= 0) {
        $wait = __('auth.wait.few_minutes');
    } elseif ($retryAfter < 60) {
        $wait = trans_choice('auth.wait.seconds', $retryAfter);
    } else {
        $wait = trans_choice('auth.wait.minutes', (int) ceil($retryAfter / 60));
    }
@endphp

@section('title', __('auth.errors.429_title'))

@section('content')
    <x-error-page code="429" :title="__('auth.errors.429_title')" :text="__('auth.errors.429_text', ['wait' => $wait])" />
@endsection
