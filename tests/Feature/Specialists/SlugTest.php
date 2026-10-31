<?php

/*
| Adres profilu (slug): format, zastrzeżone słowa, zajętość, zmiana przed i po publikacji, limit 30 dni, przekierowania 301.
*/

use App\Domain\Specialists\Models\SpecialistProfile;
use App\Domain\Specialists\Models\SpecialistSlugRedirect;
use Spatie\Activitylog\Models\Activity;

/** Dane formularza zmiany adresu (po publikacji wymagana zgoda). */
function slugPayload(string $slug, bool $confirmed = true): array
{
    return ['slug' => $slug] + ($confirmed ? ['slug_confirm' => '1'] : []);
}

it('przed pierwszą publikacją zmienia adres dowolnie, bez przekierowań i bez limitu', function () {
    $profile = specialistProfile(['slug' => 'stary-adres']);

    $this->actingAs($profile->user)->put(route('specialist.profile.slug'), slugPayload('pierwszy', false))
        ->assertRedirect(route('specialist.profile.edit').'#sekcja-adres');
    $this->actingAs($profile->user)->put(route('specialist.profile.slug'), slugPayload('drugi', false))
        ->assertSessionHasNoErrors();

    $profile->refresh();
    expect($profile->slug)->toBe('drugi')
        ->and($profile->slug_changed_at)->toBeNull()
        ->and(SpecialistSlugRedirect::query()->count())->toBe(0);
});

it('normalizuje wpisany adres: małe litery, polskie znaki i myślniki', function () {
    $profile = specialistProfile();

    $this->actingAs($profile->user)->put(route('specialist.profile.slug'), slugPayload('Kominiarz Łódź Żółć', false))->assertSessionHasNoErrors();

    expect($profile->refresh()->slug)->toBe('kominiarz-lodz-zolc');
});

it('odrzuca zły format, za krótki i za długi adres oraz słowa zastrzeżone', function (string $slug, string $message) {
    $profile = specialistProfile(['slug' => 'moj-adres']);

    $this->actingAs($profile->user)->put(route('specialist.profile.slug'), slugPayload($slug, false))
        ->assertSessionHasErrors(['slug' => __($message)]);

    expect($profile->refresh()->slug)->toBe('moj-adres');
})->with([
    'za krótki' => ['ab', 'specialist.validation.slug_format'],
    'za długi' => [str_repeat('a', 61), 'specialist.validation.slug_format'],
    'pusty' => ['', 'specialist.validation.slug_format'],
    'tylko znaki specjalne' => ['!!!', 'specialist.validation.slug_format'],
    'zastrzeżony panel' => ['panel', 'specialist.validation.slug_reserved'],
    'zastrzeżony admin' => ['admin', 'specialist.validation.slug_reserved'],
]);

it('odrzuca adres zajęty przez inny profil, także ukrytego fachowca', function () {
    specialistProfile(['slug' => 'zajety-adres', 'is_published' => false]);
    $profile = specialistProfile(['slug' => 'moj-adres']);

    $this->actingAs($profile->user)->put(route('specialist.profile.slug'), slugPayload('zajety-adres', false))
        ->assertSessionHasErrors('slug');
});

it('po publikacji wymaga zaznaczenia zgody na ograniczenie zmian', function () {
    $profile = completeSpecialistProfile(['slug' => 'moj-adres', 'is_published' => true, 'first_published_at' => now()->subDays(40)]);

    $this->actingAs($profile->user)->put(route('specialist.profile.slug'), slugPayload('nowy-adres', false))
        ->assertSessionHasErrors('slug_confirm');

    expect($profile->refresh()->slug)->toBe('moj-adres');
});

it('po publikacji zapisuje stary adres jako przekierowanie 301 i blokuje kolejną zmianę na 30 dni', function () {
    $profile = completeSpecialistProfile(['slug' => 'moj-adres', 'is_published' => true, 'first_published_at' => now()->subDays(40)]);

    $this->actingAs($profile->user)->put(route('specialist.profile.slug'), slugPayload('nowy-adres'))
        ->assertSessionHasNoErrors();

    $profile->refresh();
    expect($profile->slug)->toBe('nowy-adres')
        ->and($profile->slug_changed_at)->not->toBeNull()
        ->and(SpecialistSlugRedirect::query()->where('old_slug', 'moj-adres')->where('specialist_profile_id', $profile->id)->exists())->toBeTrue();

    // Stary adres przekierowuje 301 na nowy, nowy działa.
    $this->get('/fachowiec/moj-adres')->assertStatus(301)->assertRedirect(route('specialists.show', 'nowy-adres'));
    $this->get('/fachowiec/nowy-adres')->assertOk();

    // Druga zmiana w ciągu 30 dni jest odrzucona z datą najbliższej możliwej zmiany.
    $next = $profile->slug_changed_at->copy()->addDays(30)->timezone('Europe/Warsaw')->format('d.m.Y');
    $this->actingAs($profile->user)->put(route('specialist.profile.slug'), slugPayload('jeszcze-inny'))
        ->assertSessionHasErrors(['slug' => __('specialist.validation.slug_limit', ['date' => $next])]);
    expect($profile->refresh()->slug)->toBe('nowy-adres');

    // Po 31 dniach zmiana jest możliwa.
    $this->travel(31)->days();
    $this->actingAs($profile->user)->put(route('specialist.profile.slug'), slugPayload('jeszcze-inny'))
        ->assertSessionHasNoErrors();
    expect($profile->refresh()->slug)->toBe('jeszcze-inny')
        ->and(SpecialistSlugRedirect::query()->where('specialist_profile_id', $profile->id)->count())->toBe(2);
});

it('stary adres jest zarezerwowany: nikt inny go nie zajmie, a właściciel może wrócić', function () {
    $profile = completeSpecialistProfile(['slug' => 'moj-adres', 'is_published' => true, 'first_published_at' => now()->subDays(60)]);
    $this->actingAs($profile->user)->put(route('specialist.profile.slug'), slugPayload('nowy-adres'))->assertSessionHasNoErrors();

    $other = specialistProfile(['slug' => 'inny-fachowiec']);
    $this->actingAs($other->user)->put(route('specialist.profile.slug'), slugPayload('moj-adres', false))
        ->assertSessionHasErrors('slug');

    // Powrót właściciela do starego adresu (po upływie limitu) usuwa wpis przekierowania tego adresu.
    $this->travel(31)->days();
    $this->actingAs($profile->user)->put(route('specialist.profile.slug'), slugPayload('moj-adres'))->assertSessionHasNoErrors();

    expect($profile->refresh()->slug)->toBe('moj-adres')
        ->and(SpecialistSlugRedirect::query()->where('old_slug', 'moj-adres')->exists())->toBeFalse()
        ->and(SpecialistSlugRedirect::query()->where('old_slug', 'nowy-adres')->exists())->toBeTrue();
});

it('zmiana adresu trafia do dziennika ze starym i nowym adresem', function () {
    $profile = specialistProfile(['slug' => 'moj-adres']);

    $this->actingAs($profile->user)->put(route('specialist.profile.slug'), slugPayload('nowy-adres', false))->assertSessionHasNoErrors();

    $entry = Activity::query()->where('event', 'slug_changed')->firstOrFail();
    expect($entry->properties['old_slug'])->toBe('moj-adres')
        ->and($entry->properties['new_slug'])->toBe('nowy-adres');
});

it('usunięcie profilu usuwa jego przekierowania', function () {
    $profile = specialistProfile();
    SpecialistSlugRedirect::query()->create(['specialist_profile_id' => $profile->id, 'old_slug' => 'dawny']);

    $profile->delete();

    expect(SpecialistSlugRedirect::query()->count())->toBe(0)
        ->and(SpecialistProfile::query()->count())->toBe(0);
});
