@extends('layouts.auth')

@section('title', __('auth.errors.503_title'))

@section('content')
    <x-error-page code="503" :title="__('auth.errors.503_title')" :text="__('auth.errors.503_text')"  />
@endsection
