<!-- DO WERYFIKACJI PRZEZ PRAWNIKA -->
@extends('layouts.public')

@section('title', __('pages.terms.title'))
@section('meta_description', __('pages.terms.description'))

@section('content')
    @include('pages.partials.document', ['page' => 'terms', 'versionKey' => 'terms_version'])
@endsection
