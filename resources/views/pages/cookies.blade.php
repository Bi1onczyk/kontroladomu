<!-- DO WERYFIKACJI PRZEZ PRAWNIKA -->
@extends('layouts.public')

@section('title', __('pages.cookies.title'))
@section('meta_description', __('pages.cookies.description'))

@section('content')
    @include('pages.partials.document', ['page' => 'cookies', 'versionKey' => 'privacy_version'])
@endsection
