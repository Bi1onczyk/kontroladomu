<!-- DO WERYFIKACJI PRZEZ PRAWNIKA -->
@extends('layouts.public')

@section('title', __('pages.contact.title'))
@section('meta_description', __('pages.contact.description'))

@section('content')
    <div class="mx-auto max-w-180 px-5 py-10 sm:py-14">
        <h1 class="text-3xl font-semibold tracking-tight text-ink-900">{{ __('pages.contact.title') }}</h1>

        <x-alert variant="info" class="mt-5">{{ __('pages.draft_notice') }}</x-alert>

        <section class="mt-8" aria-labelledby="administrator">
            <h2 id="administrator" class="text-xl font-semibold text-ink-900">{{ __('pages.contact.admin_title') }}</h2>
            <address class="mt-2 not-italic">
                <p>{{ __('pages.administrator_name') }}</p>
                <p>{{ __('pages.administrator_address') }}</p>
                <p>{{ __('pages.administrator_nip') }}</p>
                <p class="mt-3">
                    {{ __('pages.contact.email_label') }}:
                    <a class="prose-link" href="mailto:{{ config('app.contact_email') }}">{{ config('app.contact_email') }}</a>
                </p>
            </address>
        </section>

        <p class="mt-6 text-ink-600">{{ __('pages.contact.form_note') }}</p>
        <p class="mt-2 text-ink-600">{{ __('pages.contact.report_note') }}</p>
    </div>
@endsection
