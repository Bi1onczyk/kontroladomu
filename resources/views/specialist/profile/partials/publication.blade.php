@php
    /** @var \App\Domain\Specialists\Data\ProfilePanelData $panel */
    use App\Domain\Specialists\Enums\PublicationState;

    $profile = $panel->profile;
    $completeness = $panel->completeness;
    $state = $panel->publicationState;
    $recommended = $completeness->recommended();
    $stepLinks = [
        'data' => '#sekcja-dane',
        'services' => '#sekcja-uslugi',
        'area' => '#sekcja-obszar',
        'description' => '#sekcja-dane',
        'certificates' => route('specialist.certificates.index'),
    ];
    // Szerokość paska postępu: gotowe klasy (Tailwind nie widzi klas składanych z liczb).
    $barWidth = match ($completeness->requiredDone()) {
        0 => 'w-0',
        1 => 'w-1/3',
        2 => 'w-2/3',
        default => 'w-full',
    };
    $publishErrors = collect(['data', 'services', 'area'])
        ->filter(fn (string $key): bool => $errors->has('publish.'.$key));
@endphp

{{-- Karta "Publikacja": najważniejszy element strony. Sześć stanów (PublicationState), jeden główny przycisk albo żaden. --}}
<section id="publikacja" aria-labelledby="publikacja-title" class="scroll-mt-20 rounded-lg border-2 border-brand-700 bg-paper p-5 sm:p-6">
    <h2 id="publikacja-title" tabindex="-1" class="text-2xl font-semibold text-ink-900">
        @switch($state)
            @case(PublicationState::Incomplete) {{ __('specialist.publish.incomplete_title') }} @break
            @case(PublicationState::Ready) {{ __('specialist.publish.ready_title') }} @break
            @case(PublicationState::LivePending) {{ __('specialist.publish.live_title') }} @break
            @case(PublicationState::LiveVerified) {{ __('specialist.publish.verified_title') }} @break
            @case(PublicationState::Hidden) {{ __('specialist.publish.hidden_title') }} @break
            @case(PublicationState::Blocked) {{ __('specialist.publish.rejected_title') }} @break
        @endswitch
    </h2>

    @if ($publishErrors->isNotEmpty())
        <x-alert variant="error" class="mt-4">
            <ul class="list-disc space-y-1 pl-5">
                @foreach ($publishErrors as $key)
                    <li id="field-publish.{{ $key }}">{{ $errors->first('publish.'.$key) }}</li>
                @endforeach
            </ul>
        </x-alert>
    @endif

    <div class="mt-3 space-y-4">
        @switch($state)
            @case(PublicationState::Incomplete)
                <p>{{ __('specialist.publish.incomplete_text') }}</p>
                <ul class="list-disc space-y-1 pl-6">
                    @foreach ($completeness->missing() as $key)
                        <li><a class="prose-link" href="{{ $stepLinks[$key] }}">{{ __('specialist.steps.'.$key) }}</a></li>
                    @endforeach
                </ul>
                @break

            @case(PublicationState::Ready)
                <p>{{ __('specialist.publish.ready_text') }}</p>
                <p class="font-semibold">{{ $profile->phone_is_public ? __('specialist.publish.phone_visible') : __('specialist.publish.phone_hidden') }}</p>
                <x-form :action="route('specialist.profile.publish')">
                    <x-submit-button :busy="__('specialist.page.saving')" :full="false" class="w-full sm:w-auto">{{ __('specialist.publish.action_publish') }}</x-submit-button>
                </x-form>
                @break

            @case(PublicationState::LivePending)
                <p>{{ __('specialist.publish.live_pending') }}</p>
                @unless ($recommended['certificates'] ?? false)
                    <x-alert variant="warn" :title="__('specialist.publish.live_no_certs_title')">
                        <p>{{ __('specialist.publish.live_no_certs') }}</p>
                        <x-button :href="route('specialist.certificates.index')" variant="secondary">{{ __('specialist.publish.live_no_certs_cta') }}</x-button>
                    </x-alert>
                @endunless
                <x-form :action="route('specialist.profile.unpublish')" method="DELETE">
                    <x-submit-button variant="secondary" :busy="__('specialist.page.saving')" :full="false" class="w-full sm:w-auto">{{ __('specialist.publish.action_unpublish') }}</x-submit-button>
                </x-form>
                @break

            @case(PublicationState::LiveVerified)
                <p>{{ __('specialist.publish.verified_text', ['date' => $profile->verified_at?->timezone('Europe/Warsaw')->format('d.m.Y')]) }}</p>
                <x-form :action="route('specialist.profile.unpublish')" method="DELETE">
                    <x-submit-button variant="secondary" :busy="__('specialist.page.saving')" :full="false" class="w-full sm:w-auto">{{ __('specialist.publish.action_unpublish') }}</x-submit-button>
                </x-form>
                @break

            @case(PublicationState::Hidden)
                <p>{{ __('specialist.publish.hidden_text') }}</p>
                <x-form :action="route('specialist.profile.publish')">
                    <x-submit-button :busy="__('specialist.page.saving')" :full="false" class="w-full sm:w-auto">{{ __('specialist.publish.action_republish') }}</x-submit-button>
                </x-form>
                @break

            @case(PublicationState::Blocked)
                <p>{{ __('specialist.publish.rejected_text') }}</p>
                @break
        @endswitch

        <p>
            <a class="prose-link inline-flex min-h-11 items-center" href="{{ $panel->publicUrl }}">{{ __('specialist.publish.preview') }}</a>
        </p>
    </div>

    {{-- Lista kroków = spis treści strony. Stan zawsze tekstem i ikoną. --}}
    <div class="mt-5 border-t border-line pt-5">
        <h3 class="font-semibold text-ink-900">{{ __('specialist.steps.title') }}</h3>
        <p class="mt-1 text-ink-900">{{ __('specialist.steps.progress', ['done' => $completeness->requiredDone(), 'total' => $completeness->requiredTotal()]) }}</p>
        <div class="mt-2 h-2 max-w-md rounded-full bg-line" aria-hidden="true">
            <div class="h-2 rounded-full bg-brand-700 {{ $barWidth }}"></div>
        </div>

        <ul class="mt-4 space-y-1">
            @foreach ($completeness->required() as $key => $done)
                <li class="flex flex-wrap items-center gap-x-3 gap-y-1">
                    <x-icon :name="$done ? 'check' : 'clock'" class="size-6 {{ $done ? 'text-ok' : 'text-ink-600' }}" />
                    <span class="min-w-0 flex-1">{{ __('specialist.steps.'.$key) }}: <strong>{{ $done ? __('specialist.steps.done') : __('specialist.steps.todo') }}</strong></span>
                    @unless ($done)
                        <a class="prose-link inline-flex min-h-11 items-center" href="{{ $stepLinks[$key] }}" aria-label="{{ __('specialist.steps.go') }}: {{ __('specialist.steps.'.$key) }}">{{ __('specialist.steps.go') }}</a>
                    @endunless
                </li>
            @endforeach
        </ul>

        <h4 class="mt-4 font-semibold text-ink-900">{{ __('specialist.steps.recommended_title') }}</h4>
        <ul class="mt-1 space-y-1">
            @foreach ($recommended as $key => $done)
                <li class="flex flex-wrap items-center gap-x-3 gap-y-1">
                    <x-icon :name="$done ? 'check' : 'clock'" class="size-6 {{ $done ? 'text-ok' : 'text-ink-600' }}" />
                    <span class="min-w-0 flex-1">{{ __('specialist.steps.'.$key) }}: <strong>{{ $done ? __('specialist.steps.done') : __('specialist.steps.recommended') }}</strong></span>
                    @unless ($done)
                        <a class="prose-link inline-flex min-h-11 items-center" href="{{ $stepLinks[$key] }}" aria-label="{{ __('specialist.steps.go') }}: {{ __('specialist.steps.'.$key) }}">{{ __('specialist.steps.go') }}</a>
                    @endunless
                </li>
            @endforeach
        </ul>
    </div>
</section>
