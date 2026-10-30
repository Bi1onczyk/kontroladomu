@extends('layouts.auth')

@section('title', __('auth.errors.500_title'))

@section('content')
    <x-error-page code="500" :title="__('auth.errors.500_title')" :text="__('auth.errors.500_text', ['email' => config('app.contact_email')])"  />
@endsection
