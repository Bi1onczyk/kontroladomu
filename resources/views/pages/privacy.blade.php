<!-- DO WERYFIKACJI PRZEZ PRAWNIKA -->
@extends('layouts.public')

@section('title', __('pages.privacy.title'))
@section('meta_description', __('pages.privacy.description'))

@section('content')
    @include('pages.partials.document', ['page' => 'privacy', 'versionKey' => 'privacy_version'])
@endsection
