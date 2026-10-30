@extends('layouts.auth')

@section('title', __('auth.errors.404_title'))

@section('content')
    <x-error-page code="404" :title="__('auth.errors.404_title')" :text="__('auth.errors.404_text')">
        @auth
            <x-button :href="route('dashboard')" variant="secondary" :full="true">{{ __('auth.errors.dashboard') }}</x-button>
        @endauth
    </x-error-page>
@endsection
