<?php

/*
| Profil fachowca: zakładanie, zapis sekcji "Dane", cennik, kompletność, publikacja, uprawnienia ról.
*/

use App\Domain\Specialists\Actions\BuildProfilePanelData;
use App\Domain\Specialists\Actions\EnsureSpecialistProfile;
use App\Domain\Specialists\Enums\PublicationState;
use App\Domain\Specialists\Enums\VerificationStatus;
use App\Domain\Specialists\Models\SpecialistProfile;
use App\Domain\Specialists\Models\SpecialistService;
use App\Domain\Specialists\Support\ProfileCompleteness;
use App\Models\User;
use Spatie\Activitylog\Models\Activity;

it('zakłada pusty profil z nazwą firmy i slugiem, idempotentnie', function () {
    $user = User::factory()->specialist()->create(['company_name' => 'Kominiarz Łukasz Żółć']);

    $first = app(EnsureSpecialistProfile::class)->handle($user);
    $second = app(EnsureSpecialistProfile::class)->handle($user);

    expect($first->id)->toBe($second->id)
        ->and($first->display_name)->toBe('Kominiarz Łukasz Żółć')
        ->and($first->slug)->toBe('kominiarz-lukasz-zolc')
        ->and($first->is_published)->toBeFalse()
        ->and($first->verification_status)->toBe(VerificationStatus::Pending)
        ->and($first->service_radius_km)->toBe(25)
        ->and(SpecialistProfile::query()->count())->toBe(1);
});

it('nie zakłada profilu kontom bez roli fachowca', function () {
    foreach ([User::factory()->owner()->create(), User::factory()->technician()->create(), User::factory()->admin()->create()] as $user) {
        expect(app(EnsureSpecialistProfile::class)->handle($user))->toBeNull();
    }

    expect(SpecialistProfile::query()->count())->toBe(0);
});

it('dopisuje numer do sluga, gdy nazwa jest zajęta, i omija słowa zastrzeżone', function () {
    $a = User::factory()->specialist()->create(['company_name' => 'Kominiarz Jan']);
    $b = User::factory()->specialist()->create(['company_name' => 'Kominiarz Jan']);
    $c = User::factory()->specialist()->create(['company_name' => 'Panel']);

    $slugA = app(EnsureSpecialistProfile::class)->handle($a)->slug;
    $slugB = app(EnsureSpecialistProfile::class)->handle($b)->slug;
    $slugC = app(EnsureSpecialistProfile::class)->handle($c)->slug;

    expect($slugA)->toBe('kominiarz-jan')
        ->and($slugB)->toBe('kominiarz-jan-2')
        ->and($slugC)->not->toBe('panel');
});

it('pierwsze wejście na stronę profilu zakłada profil i pokazuje trzy wymagane kroki', function () {
    $user = User::factory()->specialist()->withTwoFactor()->create();

    $response = $this->actingAs($user)->get(route('specialist.profile.edit'));

    $response->assertOk()
        ->assertSee(__('specialist.steps.progress', ['done' => 0, 'total' => 3]))
        ->assertViewHas('panel', fn ($panel) => $panel->publicationState === PublicationState::Incomplete
            && $panel->completeness->missing() === ['data', 'services', 'area']);

    expect(SpecialistProfile::query()->where('user_id', $user->id)->count())->toBe(1);
});

it('zapisuje sekcję Dane: telefon w formacie +48, zgoda z dowodem i wycofanie', function () {
    $profile = specialistProfile();
    $payload = [
        'display_name' => 'Kominiarz Jan Kowalski',
        'account_type' => 'company',
        'description' => "Pierwszy wiersz\r\nDrugi wiersz",
        'phone' => '600 123 456',
        'phone_is_public' => '1',
    ];

    $this->actingAs($profile->user)->put(route('specialist.profile.update'), $payload)
        ->assertRedirect(route('specialist.profile.edit').'#sekcja-dane')
        ->assertSessionHas('status', __('specialist.data.saved'));

    $profile->refresh();
    expect($profile->phone)->toBe('+48600123456')
        ->and($profile->phone_is_public)->toBeTrue()
        ->and($profile->phone_public_consent_at)->not->toBeNull()
        ->and($profile->description)->toBe("Pierwszy wiersz\nDrugi wiersz")
        ->and($profile->account_type->value)->toBe('company');

    // Wycofanie zgody zeruje dowód zgody od razu.
    $this->actingAs($profile->user)->put(route('specialist.profile.update'), array_merge($payload, ['phone_is_public' => '0']))->assertRedirect();

    $profile->refresh();
    expect($profile->phone_is_public)->toBeFalse()
        ->and($profile->phone_public_consent_at)->toBeNull()
        ->and($profile->phone)->toBe('+48600123456');
});

it('zgoda na telefon publiczny wymaga wpisanego telefonu', function () {
    $profile = specialistProfile();

    $this->actingAs($profile->user)->put(route('specialist.profile.update'), [
        'display_name' => 'Jan', 'account_type' => 'sole_proprietor', 'phone' => '', 'phone_is_public' => '1',
    ])->assertSessionHasErrors('phone_is_public');

    expect($profile->refresh()->phone_is_public)->toBeFalse();
});

it('waliduje sekcję Dane: nazwa, telefon, opis, znaki niedozwolone', function (array $override, string $errorKey) {
    $profile = specialistProfile();
    $payload = array_merge(['display_name' => 'Jan', 'account_type' => 'sole_proprietor'], $override);

    $this->actingAs($profile->user)->put(route('specialist.profile.update'), $payload)->assertSessionHasErrors($errorKey);
})->with([
    'pusta nazwa' => [['display_name' => ''], 'display_name'],
    'nazwa ze znacznikiem' => [['display_name' => 'Jan <b>'], 'display_name'],
    'nazwa ze znakiem kierunku tekstu' => [['display_name' => "Jan \u{202E}nimda"], 'display_name'],
    'nazwa za długa' => [['display_name' => str_repeat('a', 121)], 'display_name'],
    'zły telefon' => [['phone' => '12345'], 'phone'],
    'zły rodzaj działalności' => [['account_type' => 'inny'], 'account_type'],
    'opis za długi' => [['description' => str_repeat('a', 1501)], 'description'],
]);

it('nie pozwala ustawić pól stanu przez formularz (mass assignment)', function () {
    $profile = specialistProfile();
    $otherUser = User::factory()->specialist()->create();

    $this->actingAs($profile->user)->put(route('specialist.profile.update'), [
        'display_name' => 'Jan Kowalski',
        'account_type' => 'sole_proprietor',
        'verification_status' => 'verified',
        'verified_at' => now()->toDateTimeString(),
        'is_published' => '1',
        'slug' => 'przejety-adres',
        'user_id' => $otherUser->id,
        'start_lat' => '52.1',
        'service_radius_km' => '150',
        'phone_public_consent_at' => now()->toDateTimeString(),
    ])->assertRedirect();

    $profile->refresh();
    expect($profile->verification_status)->toBe(VerificationStatus::Pending)
        ->and($profile->verified_at)->toBeNull()
        ->and($profile->is_published)->toBeFalse()
        ->and($profile->slug)->not->toBe('przejety-adres')
        ->and($profile->user_id)->not->toBe($otherUser->id)
        ->and($profile->start_lat)->toBeNull()
        ->and($profile->service_radius_km)->toBe(25)
        ->and($profile->phone_public_consent_at)->toBeNull();
});

it('model ma jawne fillable bez pól stanu', function () {
    $fillable = (new SpecialistProfile)->getFillable();

    expect($fillable)->not->toBeEmpty()
        ->and($fillable)->not->toContain('slug', 'verification_status', 'verified_at', 'is_published', 'user_id', 'locality_simc', 'start_lat', 'phone_public_consent_at', 'first_published_at');
});

it('zapisuje cennik: ceny w groszach, wycena indywidualna i usuwanie odznaczonych', function () {
    $profile = specialistProfile();

    $this->actingAs($profile->user)->put(route('specialist.profile.services'), [
        'services' => [
            'chimney' => [
                'single_family_house' => ['enabled' => '1', 'price' => '180,50'],
                'apartment' => ['enabled' => '1', 'individual' => '1', 'price' => ''],
                'commercial_unit' => ['price' => '99'],
            ],
            'gas' => [
                'single_family_house' => ['enabled' => '1', 'price' => '1 200'],
            ],
        ],
    ])->assertRedirect(route('specialist.profile.edit').'#sekcja-uslugi');

    $rows = SpecialistService::query()->where('specialist_profile_id', $profile->id)->get()
        ->mapWithKeys(fn ($s) => [$s->inspection_type->value.'.'.$s->property_type->value => $s->price_grosze])
        ->sortKeys();

    expect($rows->all())->toBe([
        'chimney.apartment' => null,
        'chimney.single_family_house' => 18050,
        'gas.single_family_house' => 120000,
    ]);

    // Drugi zapis: zostaje tylko jedna pozycja ze zmienioną ceną (reszta usunięta, bez duplikatów).
    $this->actingAs($profile->user)->put(route('specialist.profile.services'), [
        'services' => ['gas' => ['single_family_house' => ['enabled' => '1', 'price' => '150']]],
    ])->assertRedirect();

    expect(SpecialistService::query()->where('specialist_profile_id', $profile->id)->count())->toBe(1)
        ->and(SpecialistService::query()->where('specialist_profile_id', $profile->id)->value('price_grosze'))->toBe(15000);
});

it('waliduje cennik: brak ceny, zła cena, zakres, nieznane klucze i pusty zestaw', function (array $services, string $errorKey) {
    $profile = specialistProfile();

    $this->actingAs($profile->user)->put(route('specialist.profile.services'), ['services' => $services])
        ->assertSessionHasErrors($errorKey);

    expect(SpecialistService::query()->count())->toBe(0);
})->with([
    'brak ceny i wyceny indywidualnej' => [['chimney' => ['apartment' => ['enabled' => '1', 'price' => '']]], 'services.chimney.apartment.price'],
    'cena tekstowa' => [['chimney' => ['apartment' => ['enabled' => '1', 'price' => 'tanio']]], 'services.chimney.apartment.price'],
    'cena poniżej 1 zł' => [['chimney' => ['apartment' => ['enabled' => '1', 'price' => '0,50']]], 'services.chimney.apartment.price'],
    'cena powyżej 50 000 zł' => [['chimney' => ['apartment' => ['enabled' => '1', 'price' => '50000,01']]], 'services.chimney.apartment.price'],
    'nieznany rodzaj przeglądu' => [['hacking' => ['apartment' => ['enabled' => '1', 'price' => '100']]], 'services'],
    'nieznany typ nieruchomości' => [['chimney' => ['pałac' => ['enabled' => '1', 'price' => '100']]], 'services'],
    'nic nie zaznaczono' => [['chimney' => ['apartment' => ['price' => '100']]], 'services'],
]);

it('liczy kompletność: trzy kroki wymagane, dwa zalecane, zaświadczenie nie blokuje', function () {
    $profile = specialistProfile();

    $empty = ProfileCompleteness::for($profile);
    expect($empty->isComplete())->toBeFalse()
        ->and($empty->missing())->toBe(['data', 'services', 'area'])
        ->and($empty->requiredDone())->toBe(0)
        ->and($empty->requiredTotal())->toBe(3)
        ->and($empty->recommended())->toBe(['description' => false, 'certificates' => false]);

    $complete = completeSpecialistProfile();
    $result = ProfileCompleteness::for($complete);

    expect($result->isComplete())->toBeTrue()
        ->and($result->missing())->toBe([])
        ->and($result->recommended()['certificates'])->toBeFalse();
});

it('wymóg telefonu do publikacji można wyłączyć konfiguracją', function () {
    $profile = completeSpecialistProfile();
    $profile->forceFill(['phone' => null])->save();

    expect(ProfileCompleteness::for($profile)->missing())->toBe(['data']);

    config(['specialists.phone_required_to_publish' => false]);

    expect(ProfileCompleteness::for($profile->refresh())->isComplete())->toBeTrue();
});

it('publikuje kompletny profil bez zaświadczenia i ustawia first_published_at raz', function () {
    $profile = completeSpecialistProfile();

    $this->actingAs($profile->user)->post(route('specialist.profile.publish'))
        ->assertRedirect(route('specialist.profile.edit').'#publikacja');

    $profile->refresh();
    $firstPublished = $profile->first_published_at;
    expect($profile->is_published)->toBeTrue()->and($firstPublished)->not->toBeNull();

    $this->actingAs($profile->user)->delete(route('specialist.profile.unpublish'))->assertRedirect();
    expect($profile->refresh()->is_published)->toBeFalse();

    $this->actingAs($profile->user)->post(route('specialist.profile.publish'))->assertRedirect();
    expect($profile->refresh()->first_published_at->equalTo($firstPublished))->toBeTrue();
});

it('niekompletny profil nie przejdzie publikacji: błąd walidacji z listą braków', function () {
    $profile = specialistProfile();

    $this->actingAs($profile->user)->post(route('specialist.profile.publish'))
        ->assertSessionHasErrors(['publish.data', 'publish.services', 'publish.area']);

    expect($profile->refresh()->is_published)->toBeFalse();
});

it('profil z odrzuconą weryfikacją nie może być opublikowany', function () {
    $profile = completeSpecialistProfile(['verification_status' => VerificationStatus::Rejected]);

    $this->actingAs($profile->user)->post(route('specialist.profile.publish'))->assertForbidden();

    expect($profile->refresh()->is_published)->toBeFalse();
});

it('zapisuje zmiany profilu w dzienniku bez telefonu i opisu', function () {
    $profile = specialistProfile();

    $this->actingAs($profile->user)->put(route('specialist.profile.update'), [
        'display_name' => 'Jan', 'account_type' => 'sole_proprietor', 'phone' => '600123456', 'description' => 'tajny opis',
    ])->assertRedirect();

    $entry = Activity::query()->where('log_name', 'specialist')->where('event', 'profile_updated')->first();

    expect($entry)->not->toBeNull()
        ->and(json_encode($entry->properties))->not->toContain('600123456')
        ->not->toContain('tajny opis')
        ->and($entry->causer_id)->toBe($profile->user_id);
});

it('stan karty publikacji odpowiada sześciu stanom UX', function () {
    $builder = app(BuildProfilePanelData::class);

    expect($builder->handle(specialistProfile())->publicationState)->toBe(PublicationState::Incomplete)
        ->and($builder->handle(completeSpecialistProfile())->publicationState)->toBe(PublicationState::Ready)
        ->and($builder->handle(completeSpecialistProfile()->forceFill(['is_published' => true, 'first_published_at' => now()]))->publicationState)->toBe(PublicationState::LivePending)
        ->and($builder->handle(completeSpecialistProfile()->forceFill(['is_published' => true, 'first_published_at' => now(), 'verification_status' => VerificationStatus::Verified]))->publicationState)->toBe(PublicationState::LiveVerified)
        ->and($builder->handle(completeSpecialistProfile()->forceFill(['is_published' => false, 'first_published_at' => now()]))->publicationState)->toBe(PublicationState::Hidden)
        ->and($builder->handle(completeSpecialistProfile()->forceFill(['verification_status' => VerificationStatus::Revoked]))->publicationState)->toBe(PublicationState::Blocked);
});

it('trasy panelu dostępne tylko dla fachowca (rola) i po zalogowaniu', function () {
    $routes = [
        ['get', route('specialist.profile.edit')],
        ['put', route('specialist.profile.update')],
        ['put', route('specialist.profile.services')],
        ['put', route('specialist.profile.area')],
        ['put', route('specialist.profile.slug')],
        ['post', route('specialist.profile.publish')],
        ['delete', route('specialist.profile.unpublish')],
        ['get', route('specialist.certificates.index')],
        ['post', route('specialist.certificates.store')],
    ];

    // Goście: przekierowanie na logowanie (pętla osobno, bo actingAs zostaje aktywne do końca testu).
    foreach ($routes as [$method, $url]) {
        $this->$method($url)->assertRedirect(route('login'));
    }

    foreach ($routes as [$method, $url]) {
        foreach ([User::factory()->owner()->create(), User::factory()->technician()->withTwoFactor()->create(), User::factory()->admin()->withTwoFactor()->create()] as $user) {
            $this->actingAs($user)->$method($url)->assertForbidden();
        }
    }
});

it('fachowiec bez 2FA jest odsyłany do konfiguracji 2FA', function () {
    $user = User::factory()->specialist()->create();

    $this->actingAs($user)->get(route('specialist.profile.edit'))->assertRedirect(route('account.security'));
});

it('fachowiec widzi tylko własny profil (profil z konta, nie z adresu)', function () {
    $a = specialistProfile(['display_name' => 'Firma A']);
    $b = specialistProfile(['display_name' => 'Firma B']);

    $this->actingAs($a->user)->put(route('specialist.profile.update'), ['display_name' => 'Zmiana A', 'account_type' => 'sole_proprietor'])->assertRedirect();

    expect($a->refresh()->display_name)->toBe('Zmiana A')
        ->and($b->refresh()->display_name)->toBe('Firma B');
});

it('pulpit fachowca dostaje kompletność profilu', function () {
    $user = User::factory()->specialist()->withTwoFactor()->create();

    $this->actingAs($user)->get(route('dashboard'))
        ->assertOk()
        ->assertViewHas('completeness', fn (ProfileCompleteness $c) => $c->requiredTotal() === 3);
});
