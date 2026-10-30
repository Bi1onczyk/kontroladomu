@extends('layouts.auth')

@section('title', __('auth.errors.403_title'))

@section('content')
    <x-error-page code="403" :title="__('auth.errors.403_title')" :text="__('auth.errors.403_text', ['email' => config('app.contact_email')])" :auth="true" />
@endsection
