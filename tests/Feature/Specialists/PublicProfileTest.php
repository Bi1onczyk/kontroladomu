<?php

/*
| Publiczna strona profilu: widoczność (404/podgląd), telefon tylko za zgodą, dane niepubliczne nigdy w HTML,
| zaświadczenia widoczne dopiero po weryfikacji (S6), przekierowania, liczba zapytań.
*/

use App\Domain\Specialists\Actions\BuildPublicSpecialistProfile;
use App\Domain\Specialists\Data\PublicSpecialistData;
use App\Domain\Specialists\Enums\VerificationStatus;
use App\Domain\Specialists\Models\SpecialistCertificate;
use App\Domain\Specialists\Models\SpecialistProfile;
use App\Domain\Specialists\Models\SpecialistService;
use App\Domain\Specialists\Models\SpecialistSlugRedirect;
use App\Models\User;
use Illuminate\Support\Facades\Gate;

/**
 * Opublikowany profil z cennikiem w miejscowości ze współrzędnymi.
 *
 * @param  array<string, mixed>  $attributes
 */
function publishedProfile(array $attributes = []): SpecialistProfile
{
    return completeSpecialistProfile(array_merge([
        'slug' => 'kominiarz-jan',
        'display_name' => 'Kominiarz Jan',
        'is_published' => true,
        'first_published_at' => now()->subDays(5),
    ], $attributes));
}

it('gość widzi opublikowany profil oczekujący na weryfikację bez wgranego zaświadczenia', function () {
    $profile = publishedProfile();

    $this->get(route('specialists.show', $profile->slug))
        ->assertOk()
        ->assertSee('Kominiarz Jan')
        ->assertSee(__('specialist.public.pending'))
        ->assertSee(__('specialist.public.certificates_pending'))
        ->assertDontSee(__('specialist.public.preview_bar'))
        ->assertViewHas('profile', fn ($data) => $data instanceof PublicSpecialistData)
        ->assertViewHas('isPreview', false);
});

it('widok dostaje obiekt PublicSpecialistData, a nie model', function () {
    $profile = publishedProfile();

    $response = $this->get(route('specialists.show', $profile->slug));

    expect($response->viewData('profile'))->toBeInstanceOf(PublicSpecialistData::class);
});

it('telefon: bez zgody numeru nie ma nigdzie w HTML, ze zgodą jest linkiem tel:', function () {
    $profile = publishedProfile();

    $hidden = $this->get(route('specialists.show', $profile->slug))->assertOk()->getContent();
    expect($hidden)->not->toContain('600123456')->not->toContain('600 123 456')->not->toContain('+48')->not->toContain('tel:')
        ->and($hidden)->toContain(__('specialist.public.phone_none'));

    $profile->forceFill(['phone_is_public' => true, 'phone_public_consent_at' => now()])->save();

    $shown = $this->get(route('specialists.show', $profile->slug))->assertOk()->getContent();
    expect($shown)->toContain('tel:+48600123456')->toContain('600 123 456');

    // Wycofanie zgody działa od razu.
    $profile->forceFill(['phone_is_public' => false, 'phone_public_consent_at' => null])->save();
    expect($this->get(route('specialists.show', $profile->slug))->getContent())->not->toContain('600123456');
});

it('dane niepubliczne nigdy nie trafiają do HTML ani do obiektu widoku', function () {
    $profile = publishedProfile(['description' => 'Opis publiczny']);
    $profile->user->forceFill(['nip' => '6931171824', 'email' => 'prywatny.adres@example.pl'])->save();
    $certificate = SpecialistCertificate::factory()->create([
        'specialist_profile_id' => $profile->id,
        'title' => 'Zakres publiczny',
        'number' => 'NUMER-PRYWATNY-777',
        'issuer' => 'Wystawca-Prywatny',
        'file_original_name' => 'skan-prywatny.pdf',
    ]);

    $html = $this->get(route('specialists.show', $profile->slug))->assertOk()->getContent();
    $data = json_encode(app(BuildPublicSpecialistProfile::class)->handle($profile->fresh()));

    foreach ([$html, $data] as $output) {
        expect($output)->not->toContain('6931171824')
            ->not->toContain('prywatny.adres@example.pl')
            ->not->toContain('NUMER-PRYWATNY-777')
            ->not->toContain('Wystawca-Prywatny')
            ->not->toContain('skan-prywatny')
            ->not->toContain($certificate->ulid)
            ->not->toContain($certificate->file_sha256)
            ->not->toContain('start_lat')
            ->not->toContain((string) $profile->start_lat);
    }

    expect($html)->toContain('Opis publiczny');
});

it('nieopublikowany profil: 404 dla gościa, innego fachowca i właściciela nieruchomości', function () {
    $profile = publishedProfile(['is_published' => false]);

    $this->get(route('specialists.show', $profile->slug))->assertNotFound();
    $this->actingAs(specialistProfile()->user)->get(route('specialists.show', $profile->slug))->assertNotFound();
    $this->actingAs(User::factory()->owner()->create())->get(route('specialists.show', $profile->slug))->assertNotFound();
});

it('nieopublikowany profil: właściciel i admin widzą podgląd z nagłówkami noindex i no-store', function () {
    $profile = publishedProfile(['is_published' => false]);

    $owner = $this->actingAs($profile->user)->get(route('specialists.show', $profile->slug));
    $owner->assertOk()->assertSee(__('specialist.public.preview_bar'))->assertViewHas('previewAudience', 'owner');
    expect($owner->headers->get('X-Robots-Tag'))->toContain('noindex')
        ->and($owner->headers->get('Cache-Control'))->toContain('no-store');

    $admin = $this->actingAs(User::factory()->admin()->withTwoFactor()->create())->get(route('specialists.show', $profile->slug));
    $admin->assertOk()->assertSee(__('specialist.public.preview_bar'))->assertViewHas('previewAudience', 'admin');
});

it('profil odrzucony lub cofnięty: 404 dla gościa nawet gdy jest opublikowany, podgląd dla właściciela', function (VerificationStatus $status) {
    $profile = publishedProfile(['verification_status' => $status]);

    $this->get(route('specialists.show', $profile->slug))->assertNotFound();
    $this->actingAs($profile->user)->get(route('specialists.show', $profile->slug))->assertOk()->assertSee(__('specialist.public.preview_bar'));
})->with([VerificationStatus::Rejected, VerificationStatus::Revoked]);

it('konto zablokowane: profil niewidoczny dla gości', function () {
    $profile = publishedProfile();
    $profile->user->forceFill(['banned_at' => now()])->save();

    $this->get(route('specialists.show', $profile->slug))->assertNotFound();
});

it('nieznany adres daje 404, a wielkość liter w adresie nie ma znaczenia', function () {
    $profile = publishedProfile();

    $this->get('/fachowiec/nie-istnieje')->assertNotFound();
    $this->get('/fachowiec/KOMINIARZ-JAN')->assertOk();
    $this->get('/fachowiec/'.$profile->slug.'%00')->assertNotFound();
});

it('stary adres przekierowuje 301 na bieżący, ale nie ujawnia ukrytego profilu', function () {
    $profile = publishedProfile(['slug' => 'nowy-adres']);
    SpecialistSlugRedirect::query()->create(['specialist_profile_id' => $profile->id, 'old_slug' => 'stary-adres']);

    $this->get('/fachowiec/stary-adres')->assertStatus(301)->assertRedirect(route('specialists.show', 'nowy-adres'));

    $profile->forceFill(['is_published' => false])->save();

    $this->get('/fachowiec/stary-adres')->assertNotFound();
});

it('zaświadczenia widać dopiero po weryfikacji i tylko niezmienione po niej (S6)', function () {
    $profile = publishedProfile(['verification_status' => VerificationStatus::Pending]);
    $before = SpecialistCertificate::factory()->create(['specialist_profile_id' => $profile->id, 'title' => 'Zakres sprawdzony']);
    $after = SpecialistCertificate::factory()->create(['specialist_profile_id' => $profile->id, 'title' => 'Zakres dodany później']);

    // Pending: żadnych zaświadczeń, tylko informacja o oczekiwaniu (bez ujawniania, że skan jest dodany).
    $pending = $this->get(route('specialists.show', $profile->slug))->assertOk();
    $pending->assertDontSee('Zakres sprawdzony')->assertSee(__('specialist.public.certificates_pending'));

    $verifiedAt = now()->addHour();
    $profile->forceFill(['verification_status' => VerificationStatus::Verified, 'verified_at' => $verifiedAt])->save();
    $after->forceFill(['updated_at' => $verifiedAt->copy()->addMinute()])->saveQuietly();
    SpecialistCertificate::query()->whereKey($before->id)->update(['updated_at' => $verifiedAt->copy()->subMinute()]);

    $this->get(route('specialists.show', $profile->slug))->assertOk()
        ->assertSee('Zakres sprawdzony')
        ->assertDontSee('Zakres dodany później')
        ->assertSee(__('specialist.public.verified'))
        ->assertDontSee(__('specialist.public.certificates_pending'));
});

it('zweryfikowany profil bez żadnego sprawdzonego zaświadczenia pomija sekcję uprawnień', function () {
    $profile = publishedProfile(['verification_status' => VerificationStatus::Verified, 'verified_at' => now()->subDay()]);
    SpecialistCertificate::factory()->create(['specialist_profile_id' => $profile->id, 'title' => 'Dodane po weryfikacji']);

    $data = app(BuildPublicSpecialistProfile::class)->handle($profile->fresh());

    expect($data->isVerified)->toBeTrue()
        ->and($data->certificates)->toBe([])
        ->and($data->certificatesPending)->toBeFalse();
});

it('cennik: grupy w kolejności enumów, wycena indywidualna, ceny w złotych z groszami', function () {
    $profile = publishedProfile();
    SpecialistService::query()->where('specialist_profile_id', $profile->id)->delete();
    SpecialistService::factory()->create(['specialist_profile_id' => $profile->id, 'inspection_type' => 'gas', 'property_type' => 'apartment', 'price_grosze' => 9990]);
    SpecialistService::factory()->create(['specialist_profile_id' => $profile->id, 'inspection_type' => 'chimney', 'property_type' => 'apartment', 'price_grosze' => null]);
    SpecialistService::factory()->create(['specialist_profile_id' => $profile->id, 'inspection_type' => 'chimney', 'property_type' => 'single_family_house', 'price_grosze' => 15000]);

    $data = app(BuildPublicSpecialistProfile::class)->handle($profile->fresh());

    expect(array_map(fn ($group) => $group->inspectionType, $data->serviceGroups))->toBe(['chimney', 'gas'])
        ->and(array_map(fn ($price) => $price->propertyType, $data->serviceGroups[0]->prices))->toBe(['single_family_house', 'apartment'])
        ->and($data->serviceGroups[0]->prices[0]->priceFormatted)->toBe("150\u{00A0}zł")
        ->and($data->serviceGroups[0]->prices[1]->priceFormatted)->toBeNull()
        ->and($data->serviceGroups[1]->prices[0]->priceFormatted)->toBe("99,90\u{00A0}zł");

    $this->get(route('specialists.show', $profile->slug))->assertSee(__('specialist.public.price_individual'));
});

it('dojazd: przy stawce 0 profil nic o nim nie mówi, przy stawce podaje kwotę i km gratis', function () {
    $profile = publishedProfile();
    $data = app(BuildPublicSpecialistProfile::class)->handle($profile->fresh());

    expect($data->travelRateFormatted)->toBeNull()->and($data->travelFreeKm)->toBeNull();

    $profile->forceFill(['travel_rate_per_km_grosze' => 150, 'travel_free_km' => 10])->save();
    $data = app(BuildPublicSpecialistProfile::class)->handle($profile->fresh());

    expect($data->travelRateFormatted)->toBe("1,50\u{00A0}zł")->and($data->travelFreeKm)->toBe(10);
});

it('obszar i tytuł strony zawierają miejscowość, gminę i powiat bez współrzędnych', function () {
    $profile = publishedProfile();
    $locality = $profile->locality()->first();
    $municipality = $locality->municipality;

    $data = app(BuildPublicSpecialistProfile::class)->handle($profile->fresh());

    expect($data->areaLabel)->toBe($locality->name.', gm. '.$municipality->name.', pow. '.$municipality->county_name)
        ->and($data->metaTitle)->toContain('Kominiarz Jan')->toContain($locality->name)
        ->and($data->radiusKm)->toBe(25);

    $this->get(route('specialists.show', $profile->slug))->assertSee($locality->name);
});

it('opis jest escapowany (znaczniki HTML nie wykonują się)', function () {
    $profile = publishedProfile(['description' => '<script>alert(1)</script><b>pogrubienie</b>']);

    $html = $this->get(route('specialists.show', $profile->slug))->getContent();

    expect($html)->not->toContain('<script>alert(1)</script>')->not->toContain('<b>pogrubienie</b>')
        ->and($html)->toContain('&lt;script&gt;');
});

it('strona publiczna wykonuje stałą, małą liczbę zapytań niezależnie od rozmiaru cennika i zaświadczeń', function () {
    $small = publishedProfile(['slug' => 'maly']);
    $big = publishedProfile(['slug' => 'duzy', 'verification_status' => VerificationStatus::Verified, 'verified_at' => now()->addYear()]);
    foreach (['chimney', 'electrical', 'gas'] as $inspection) {
        foreach (['apartment', 'commercial_unit', 'multi_family_building'] as $property) {
            SpecialistService::factory()->create(['specialist_profile_id' => $big->id, 'inspection_type' => $inspection, 'property_type' => $property]);
        }
    }
    SpecialistCertificate::factory()->count(5)->create(['specialist_profile_id' => $big->id]);

    $smallQueries = countQueries(fn () => $this->get(route('specialists.show', 'maly'))->assertOk());
    $bigQueries = countQueries(fn () => $this->get(route('specialists.show', 'duzy'))->assertOk());

    expect($smallQueries)->toBeLessThanOrEqual(6)->and($bigQueries)->toBe($smallQueries);
});

it('limit żądań strony publicznej: 120 na minutę z adresu IP', function () {
    for ($i = 0; $i < 120; $i++) {
        $this->get('/fachowiec/nie-istnieje')->assertNotFound();
    }

    $this->get('/fachowiec/nie-istnieje')->assertStatus(429);
});

it('statyczna strona „Jak weryfikujemy fachowców” jest publiczna', function () {
    $this->get(route('pages.verification'))->assertOk()->assertSee(__('specialist.public.how_we_verify'));
});

it('polityka widoczności: właściciel i admin tak, inni tylko dla profilu publicznego', function () {
    $hidden = publishedProfile(['is_published' => false]);
    $public = publishedProfile(['slug' => 'publiczny', 'is_published' => true]);
    $admin = User::factory()->admin()->create();

    expect(Gate::forUser($hidden->user)->allows('view', $hidden))->toBeTrue()
        ->and(Gate::forUser($admin)->allows('view', $hidden))->toBeTrue()
        ->and(Gate::forUser(User::factory()->owner()->create())->allows('view', $hidden))->toBeFalse()
        ->and(Gate::allows('view', $hidden))->toBeFalse()
        ->and(Gate::allows('view', $public))->toBeTrue();
});
