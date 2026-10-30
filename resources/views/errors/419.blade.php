@extends('layouts.auth')

@section('title', __('auth.errors.419_title'))

@section('content')
    <x-error-page code="419" :title="__('auth.errors.419_title')" :text="__('auth.errors.419_text', ['minutes' => config('session.lifetime')])" :home="false" :back="true" :auth="true" />
@endsection
