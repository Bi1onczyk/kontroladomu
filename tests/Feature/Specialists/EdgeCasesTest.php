<?php

/*
| Przypadki brzegowe i uprawnienia profilu fachowca (zadanie 004, tester): polyglot i nazwy plików, ceny graniczne,
| promień 5 i 150, limit 30 dni dla adresu, XSS w nazwie i zaświadczeniach, wyciek telefonu, IDOR między fachowcami,
| N+1 przy włączonym preventLazyLoading, unikalność zadania w prawdziwej kolejce, 29 lutego w archiwum.
*/

use App\Domain\Geo\Models\TerytLocality;
use App\Domain\Specialists\Actions\PurgeSpecialistData;
use App\Domain\Specialists\Enums\VerificationStatus;
use App\Domain\Specialists\Jobs\GeocodeLocalityJob;
use App\Domain\Specialists\Models\ArchivedCertificate;
use App\Domain\Specialists\Models\SpecialistCertificate;
use App\Domain\Specialists\Models\SpecialistProfile;
use App\Domain\Specialists\Models\SpecialistService;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    Storage::fake('local');
    Cache::flush();
});

afterEach(function () {
    Model::preventLazyLoading(false);
});

/**
 * Poprawne dane formularza zaświadczenia (lokalny odpowiednik, żeby plik działał samodzielnie).
 *
 * @param  array<string, mixed>  $override
 * @return array<string, mixed>
 */
function edgeCertificatePayload(UploadedFile $file, array $override = []): array
{
    return array_merge([
        'type' => 'building_permit',
        'title' => 'Specjalność instalacyjna',
        'number' => 'MAP/BO/1234/20',
        'issuer' => 'WOIIB',
        'valid_until' => now()->addYear()->toDateString(),
        'declaration' => '1',
        'file' => $file,
    ], $override);
}

/** Treść prawdziwego, małego JPEG-a. */
function edgeJpegBytes(): string
{
    $file = UploadedFile::fake()->image('x.jpg', 60, 40);

    return (string) file_get_contents($file->getPathname());
}

it('polyglot: JPEG z dopisanym kodem PHP po przekodowaniu nie zawiera kodu', function () {
    $profile = specialistProfile();
    $bytes = edgeJpegBytes()."\n<?php system(\$_GET['c']); ?>";

    $this->actingAs($profile->user)
        ->post(route('specialist.certificates.store'), edgeCertificatePayload(UploadedFile::fake()->createWithContent('skan.jpg', $bytes)))
        ->assertSessionHasNoErrors();

    $stored = Storage::disk('local')->get(SpecialistCertificate::query()->firstOrFail()->file_path);
    expect($stored)->not->toContain('<?php')->and($stored)->not->toContain('system(');
});

it('polyglot: PNG z kodem PHP za blokiem IEND po przekodowaniu nie zawiera kodu', function () {
    $profile = specialistProfile();
    $file = UploadedFile::fake()->image('x.png', 60, 40);
    $png = (string) file_get_contents($file->getPathname());

    $this->actingAs($profile->user)
        ->post(route('specialist.certificates.store'), edgeCertificatePayload(UploadedFile::fake()->createWithContent('skan.png', $png.'<?php echo 1; ?>')))
        ->assertSessionHasNoErrors();

    expect(Storage::disk('local')->get(SpecialistCertificate::query()->firstOrFail()->file_path))->not->toContain('<?php');
});

it('GIF i WebP pod rozszerzeniem jpg/png są odrzucone', function (string $name) {
    $profile = specialistProfile();
    $gif = base64_decode('R0lGODlhAQABAIAAAAAAAP///yH5BAEAAAAALAAAAAABAAEAAAIBRAA7');

    $this->actingAs($profile->user)
        ->post(route('specialist.certificates.store'), edgeCertificatePayload(UploadedFile::fake()->createWithContent($name, $gif)))
        ->assertSessionHasErrors('file');

    expect(SpecialistCertificate::query()->count())->toBe(0)->and(Storage::disk('local')->allFiles())->toBe([]);
})->with(['skan.jpg', 'skan.png', 'skan.pdf']);

it('PDF z nagłówkiem dalej niż w pierwszym kilobajcie jest odrzucony', function () {
    $profile = specialistProfile();
    $content = str_repeat('x', 2000)."\n%PDF-1.4\n%%EOF";

    $this->actingAs($profile->user)
        ->post(route('specialist.certificates.store'), edgeCertificatePayload(UploadedFile::fake()->createWithContent('skan.pdf', $content)))
        ->assertSessionHasErrors('file');
});

it('nazwa pliku z podwójnym rozszerzeniem lub ścieżką nie wpływa na nazwę na dysku', function (string $clientName) {
    $profile = specialistProfile();

    $this->actingAs($profile->user)
        ->post(route('specialist.certificates.store'), edgeCertificatePayload(UploadedFile::fake()->createWithContent($clientName, edgeJpegBytes())))
        ->assertSessionHasNoErrors();

    $certificate = SpecialistCertificate::query()->firstOrFail();
    expect($certificate->file_path)->toMatch('#^specialist-certificates/[A-Za-z0-9]{40}\.jpg$#')
        ->and($certificate->file_original_name)->not->toContain('/')->not->toContain('\\')->not->toContain('..');
})->with(['skan.php.jpg', '../../../public/evil.jpg', 'skan.jpg.php.jpg', "skan\0.php.jpg"]);

it('zaświadczenie: XSS w zakresie i numerze jest escapowany w panelu', function () {
    $profile = specialistProfile();
    SpecialistCertificate::factory()->create([
        'specialist_profile_id' => $profile->id,
        'title' => '<script>alert("x")</script>',
        'number' => '"><img src=x onerror=alert(1)>',
        'file_original_name' => '<b>skan</b>.pdf',
    ]);

    $html = $this->actingAs($profile->user)->get(route('specialist.certificates.index'))->assertOk()->getContent();

    expect($html)->not->toContain('<script>alert("x")</script>')
        ->and($html)->not->toContain('<img src=x onerror')
        ->and($html)->not->toContain('<b>skan</b>');
});

it('data ważności zaświadczenia: dziś i 29 lutego przechodzą, wczoraj nie', function () {
    $profile = specialistProfile();
    $size = 30;
    $post = function (string $date) use ($profile, &$size) {
        // Inny rozmiar obrazu przy każdym wywołaniu, żeby nie zadziałała blokada duplikatów.
        $file = UploadedFile::fake()->image('a'.$size.'.jpg', 40, $size++);

        return $this->actingAs($profile->user)->post(route('specialist.certificates.store'), edgeCertificatePayload($file, ['valid_until' => $date]));
    };

    $post(now()->toDateString())->assertSessionHasNoErrors();
    $post('2028-02-29')->assertSessionHasNoErrors();
    $post('2027-02-29')->assertSessionHasErrors('valid_until');
    $post(now()->subDay()->toDateString())->assertSessionHasErrors('valid_until');
});

it('zaświadczenie: podrzucone pola specialist_profile_id i file_path z formularza są ignorowane', function () {
    $mine = specialistProfile();
    $other = specialistProfile();

    $this->actingAs($mine->user)->post(route('specialist.certificates.store'), edgeCertificatePayload(
        UploadedFile::fake()->image('a.jpg', 40, 30),
        ['specialist_profile_id' => $other->id, 'file_path' => '../../.env', 'ulid' => '01AAAAAAAAAAAAAAAAAAAAAAAA', 'id' => 999],
    ))->assertSessionHasNoErrors();

    $certificate = SpecialistCertificate::query()->firstOrFail();
    expect($certificate->specialist_profile_id)->toBe($mine->id)
        ->and($certificate->file_path)->toStartWith('specialist-certificates/')
        ->and($certificate->ulid)->not->toBe('01AAAAAAAAAAAAAAAAAAAAAAAA');
});

it('IDOR: admin, pracownik, właściciel i obcy fachowiec nie edytują ani nie usuwają cudzego zaświadczenia', function () {
    $certificate = SpecialistCertificate::factory()->create(['specialist_profile_id' => specialistProfile()->id]);
    $payload = ['type' => 'other', 'title' => 'Zmienione', 'number' => 'ZMIANA-1', 'no_expiry' => '1'];

    $intruders = [
        'obcy fachowiec' => specialistProfile()->user,
        'admin' => User::factory()->admin()->withTwoFactor()->create(),
        'pracownik' => User::factory()->technician()->withTwoFactor()->create(),
        'właściciel' => User::factory()->owner()->create(),
    ];

    foreach ($intruders as $user) {
        $put = $this->actingAs($user)->put(route('specialist.certificates.update', $certificate), $payload)->getStatusCode();
        $delete = $this->actingAs($user)->delete(route('specialist.certificates.destroy', $certificate))->getStatusCode();
        expect([$put, $delete])->each->toBeIn([403, 404]);
    }

    expect($certificate->refresh()->title)->not->toBe('Zmienione')->and(SpecialistCertificate::query()->count())->toBe(1);
});

it('IDOR: gość nie wykona żadnej operacji na zaświadczeniu', function () {
    $certificate = SpecialistCertificate::factory()->create(['specialist_profile_id' => specialistProfile()->id]);

    $this->put(route('specialist.certificates.update', $certificate), [])->assertRedirect(route('login'));
    $this->delete(route('specialist.certificates.destroy', $certificate))->assertRedirect(route('login'));
    $this->get(route('specialist.certificates.file', $certificate))->assertRedirect(route('login'));
    expect(SpecialistCertificate::query()->count())->toBe(1);
});

it('ceny graniczne: 1 zł i 50 000 zł przechodzą, 0, 0,99, ujemne i zapis wykładniczy nie', function (string $price, ?int $grosze) {
    $profile = specialistProfile();

    $response = $this->actingAs($profile->user)->put(route('specialist.profile.services'), [
        'services' => ['chimney' => ['apartment' => ['enabled' => '1', 'price' => $price]]],
    ]);

    if ($grosze === null) {
        $response->assertSessionHasErrors('services.chimney.apartment.price');
        expect(SpecialistService::query()->count())->toBe(0);
    } else {
        $response->assertSessionHasNoErrors();
        expect(SpecialistService::query()->firstOrFail()->price_grosze)->toBe($grosze);
    }
})->with([
    '1 zł' => ['1', 100],
    '1,00 zł' => ['1,00', 100],
    '1.00 zł z kropką' => ['1.00', 100],
    '50 000 zł' => ['50000', 5_000_000],
    '0' => ['0', null],
    '0,99' => ['0,99', null],
    'ujemna' => ['-5', null],
    'wykładnicza' => ['1e3', null],
    'ogromna' => ['99999999999999999999', null],
    'tekst z emoji' => ['100 zł 😀', null],
]);

it('promień działania: 5 i 150 przechodzą, 0, 4, 151 i ujemny nie', function (string $radius, bool $valid) {
    Queue::fake();
    $locality = TerytLocality::factory()->withCoordinates()->create();
    $profile = specialistProfile();

    $response = $this->actingAs($profile->user)->put(route('specialist.profile.area'), [
        'locality_simc' => $locality->simc, 'service_radius_km' => $radius, 'travel_rate_per_km' => '0', 'travel_free_km' => '0',
    ]);

    if ($valid) {
        $response->assertSessionHasNoErrors();
        expect($profile->refresh()->service_radius_km)->toBe((int) $radius);
    } else {
        $response->assertSessionHasErrors('service_radius_km');
    }
})->with([['5', true], ['150', true], ['4', false], ['151', false], ['0', false], ['-10', false], ['30.5', false]]);

it('stawka za kilometr: 50 zł przechodzi, ujemna i powyżej 50 zł nie', function (string $rate, bool $valid) {
    Queue::fake();
    $locality = TerytLocality::factory()->withCoordinates()->create();
    $profile = specialistProfile();

    $response = $this->actingAs($profile->user)->put(route('specialist.profile.area'), [
        'locality_simc' => $locality->simc, 'service_radius_km' => '30', 'travel_rate_per_km' => $rate, 'travel_free_km' => '0',
    ]);

    $valid ? $response->assertSessionHasNoErrors() : $response->assertSessionHasErrors('travel_rate_per_km');
})->with([['50', true], ['0,01', true], ['-1', false], ['50,01', false], ['abc', false]]);

it('obszar: SQL injection i HTML w kodzie miejscowości nie powodują błędu 500', function (string $payload) {
    $profile = specialistProfile();

    $this->actingAs($profile->user)->put(route('specialist.profile.area'), [
        'locality_simc' => $payload, 'service_radius_km' => '30', 'travel_rate_per_km' => '0', 'travel_free_km' => '0',
    ])->assertSessionHasErrors('locality_simc');

    expect($profile->refresh()->locality_simc)->toBeNull();
})->with(["' OR 1=1 --", '<script>alert(1)</script>', "0000000'; DROP TABLE users; --", ['a', 'b']]);

it('adres profilu: granica 30 dni (29. dnia odmowa, 30. dnia zgoda)', function () {
    $profile = completeSpecialistProfile(['slug' => 'adres-a', 'is_published' => true, 'first_published_at' => now()->subYear()]);
    $change = fn (string $slug) => $this->actingAs($profile->user)->put(route('specialist.profile.slug'), ['slug' => $slug, 'slug_confirm' => '1']);

    $change('adres-b')->assertSessionHasNoErrors();
    $this->travel(29)->days();
    $change('adres-c')->assertSessionHasErrors('slug');
    $this->travel(1)->days();
    $change('adres-c')->assertSessionHasNoErrors();

    expect($profile->refresh()->slug)->toBe('adres-c');
});

it('adres profilu: duplikat różniący się wielkością liter lub zapisem z polskimi znakami jest odrzucony', function (string $input) {
    specialistProfile(['slug' => 'zolc-kominiarz']);
    $profile = specialistProfile(['slug' => 'moj-adres']);

    $this->actingAs($profile->user)->put(route('specialist.profile.slug'), ['slug' => $input])->assertSessionHasErrors('slug');
    expect($profile->refresh()->slug)->toBe('moj-adres');
})->with(['ZOLC-KOMINIARZ', 'Żółć-Kominiarz', ' zolc-kominiarz ']);

it('adres profilu: słowa zastrzeżone w różnych zapisach są odrzucone', function (string $input) {
    $profile = specialistProfile(['slug' => 'moj-adres']);

    $this->actingAs($profile->user)->put(route('specialist.profile.slug'), ['slug' => $input])->assertSessionHasErrors('slug');
})->with(['Admin', 'PANEL', 'szukaj', 'nowy']);

it('nazwa złożona z samych emoji lub jednej litery nadal daje poprawny adres profilu', function (string $name) {
    $user = User::factory()->specialist()->withTwoFactor()->create(['name' => $name, 'company_name' => null]);

    $this->actingAs($user)->get(route('specialist.profile.edit'))->assertOk();

    $slug = SpecialistProfile::query()->firstOrFail()->slug;
    expect($slug)->toMatch('/^[a-z0-9]+(-[a-z0-9]+)*$/')->and(strlen($slug))->toBeGreaterThanOrEqual(3)->toBeLessThanOrEqual(60);
})->with(['🔥🔥🔥', 'Ł', 'Jo', '---', str_repeat('Ż', 119)]);

it('strona publiczna: znaki specjalne w nazwie są escapowane w treści i w tytule', function () {
    $profile = completeSpecialistProfile([
        'slug' => 'xss-test', 'display_name' => '"><script>alert(1)</script> & Syn \'s', 'is_published' => true,
        'first_published_at' => now()->subDay(), 'description' => "<img src=x onerror=alert(1)>\nDrugi wiersz 😀 zażółć gęślą jaźń",
    ]);

    $html = $this->get(route('specialists.show', $profile->slug))->assertOk()->getContent();

    expect($html)->not->toContain('<script>alert(1)</script>')
        ->and($html)->not->toContain('<img src=x')
        ->and($html)->toContain('zażółć gęślą jaźń')
        ->and($html)->toContain('😀');
});

it('telefon bez zgody nie wycieka w HTML, w odpowiedzi JSON, w opisie meta ani w nagłówkach', function () {
    $profile = publishedProfileForEdge(['phone' => '+48601987654', 'phone_is_public' => false]);

    foreach ([$this->get(route('specialists.show', $profile->slug)), $this->getJson(route('specialists.show', $profile->slug))] as $response) {
        $raw = $response->getContent().json_encode($response->headers->all());
        expect($raw)->not->toContain('601987654')->and($raw)->not->toContain('601 987 654')->and($raw)->not->toContain('601-987-654');
    }
});

it('telefon po wycofaniu zgody znika z kolejnego żądania, a profil bez zgody nie ma dowodu zgody', function () {
    $profile = publishedProfileForEdge(['phone' => '+48601987654', 'phone_is_public' => true, 'phone_public_consent_at' => now()]);
    $this->get(route('specialists.show', $profile->slug))->assertSee('tel:+48601987654', false);

    $this->actingAs($profile->user)->put(route('specialist.profile.update'), [
        'display_name' => $profile->display_name, 'account_type' => 'sole_proprietor', 'phone' => '601987654',
    ])->assertSessionHasNoErrors();
    auth()->logout();

    expect($this->get(route('specialists.show', $profile->slug))->getContent())->not->toContain('601987654')
        ->and($profile->refresh()->phone_public_consent_at)->toBeNull();
});

it('NIP, e-mail konta i imię z konta nie trafiają do publicznego HTML', function () {
    $user = User::factory()->specialist()->withTwoFactor()->create(['nip' => '6931171824', 'email' => 'tajny.adres@example.pl', 'name' => 'Prywatne Nazwisko']);
    $profile = completeSpecialistProfile();
    $profile->forceFill(['user_id' => $user->id, 'slug' => 'bez-wyciekow', 'display_name' => 'Firma Publiczna', 'is_published' => true, 'first_published_at' => now()->subDay()])->save();

    $html = $this->get(route('specialists.show', 'bez-wyciekow'))->assertOk()->getContent();

    expect($html)->not->toContain('6931171824')->not->toContain('tajny.adres')->not->toContain('Prywatne Nazwisko');
});

it('profil oczekujący (niezweryfikowany) ma noindex także po publikacji, zweryfikowany nie ma, podgląd ukrytego ma', function () {
    $public = publishedProfileForEdge(['slug' => 'widoczny']);
    $public->forceFill(['verification_status' => VerificationStatus::Verified, 'verified_at' => now()->subDay()])->save();
    $hidden = publishedProfileForEdge(['slug' => 'ukryty', 'is_published' => false]);

    expect($this->get(route('specialists.show', 'widoczny'))->headers->get('X-Robots-Tag'))->toBeNull();
    $this->actingAs($hidden->user)->get(route('specialists.show', 'ukryty'))->assertOk();
    expect($this->actingAs($hidden->user)->get(route('specialists.show', 'ukryty'))->headers->get('X-Robots-Tag'))->toContain('noindex');
});

it('stary adres innego profilu, który został ukryty, daje 404 a nie 301', function () {
    $profile = publishedProfileForEdge(['slug' => 'nowy-adres', 'is_published' => false]);
    DB::table('specialist_slug_redirects')->insert(['specialist_profile_id' => $profile->id, 'old_slug' => 'stary-adres', 'created_at' => now()]);

    $this->get('/fachowiec/stary-adres')->assertNotFound();
});

it('N+1: panel profilu i zaświadczeń przy włączonym preventLazyLoading, stała liczba zapytań', function () {
    $small = completeSpecialistProfile();
    $big = completeSpecialistProfile();
    foreach (['chimney', 'electrical', 'gas'] as $inspection) {
        foreach (['apartment', 'commercial_unit', 'multi_family_building'] as $property) {
            SpecialistService::factory()->create(['specialist_profile_id' => $big->id, 'inspection_type' => $inspection, 'property_type' => $property]);
        }
    }
    SpecialistCertificate::factory()->count(8)->create(['specialist_profile_id' => $big->id]);

    Model::preventLazyLoading(true);

    $smallQueries = countQueries(function () use ($small) {
        $this->actingAs($small->user)->get(route('specialist.profile.edit'))->assertOk();
        $this->actingAs($small->user)->get(route('specialist.certificates.index'))->assertOk();
        $this->actingAs($small->user)->get(route('dashboard'))->assertOk();
    });
    $bigQueries = countQueries(function () use ($big) {
        $this->actingAs($big->user)->get(route('specialist.profile.edit'))->assertOk();
        $this->actingAs($big->user)->get(route('specialist.certificates.index'))->assertOk();
        $this->actingAs($big->user)->get(route('dashboard'))->assertOk();
    });

    expect($bigQueries)->toBeLessThanOrEqual($smallQueries + 1);
});

it('N+1: strona publiczna zweryfikowanego profilu przy włączonym preventLazyLoading', function () {
    $profile = publishedProfileForEdge(['verification_status' => VerificationStatus::Verified, 'verified_at' => now()->addDay()]);
    SpecialistCertificate::factory()->count(6)->create(['specialist_profile_id' => $profile->id, 'updated_at' => now()->subDay()]);
    Model::preventLazyLoading(true);

    $this->get(route('specialists.show', $profile->slug))->assertOk();
});

it('kolejka: dwa szybkie zapisy tej samej miejscowości dają jedno zadanie w prawdziwej kolejce (unikalność)', function () {
    config(['queue.default' => 'database']);
    $locality = TerytLocality::factory()->create(['lat' => null, 'lng' => null]);
    $profile = specialistProfile();
    $payload = ['locality_simc' => $locality->simc, 'service_radius_km' => '30', 'travel_rate_per_km' => '0', 'travel_free_km' => '0'];

    $this->actingAs($profile->user)->put(route('specialist.profile.area'), $payload)->assertSessionHasNoErrors();
    $other = specialistProfile();
    $this->actingAs($other->user)->put(route('specialist.profile.area'), $payload)->assertSessionHasNoErrors();

    expect(DB::table('jobs')->where('payload', 'like', '%'.class_basename(GeocodeLocalityJob::class).'%')->count())->toBe(1);
});

it('archiwum: konto usunięte 29 lutego dostaje termin 28 lutego w roku bez 29 lutego', function () {
    $this->travelTo(now()->setDate(2028, 2, 29)->setTime(10, 0));
    $profile = specialistProfile(['display_name' => 'Firma Przestępna']);
    $certificate = SpecialistCertificate::factory()->create(['specialist_profile_id' => $profile->id]);
    Storage::disk('local')->put($certificate->file_path, "%PDF-1.4\n%%EOF");

    app(PurgeSpecialistData::class)->handle($profile->user);

    $archived = ArchivedCertificate::query()->firstOrFail();
    expect($archived->delete_after->toDateString())->toBe('2029-02-28');
});

it('uprawnienia profilu: admin, pracownik i właściciel nieruchomości nie publikują cudzego profilu przez żadną trasę', function () {
    $profile = completeSpecialistProfile();

    foreach ([User::factory()->admin()->withTwoFactor()->create(), User::factory()->technician()->withTwoFactor()->create(), User::factory()->owner()->create()] as $user) {
        $this->actingAs($user)->post(route('specialist.profile.publish'))->assertForbidden();
    }

    expect($profile->refresh()->is_published)->toBeFalse();
});

it('formularz profilu: podrzucone user_id, slug i is_published z pól ukrytych są ignorowane', function () {
    $mine = specialistProfile(['slug' => 'moj-adres']);
    $other = specialistProfile(['slug' => 'cudzy']);

    foreach (['specialist.profile.update', 'specialist.profile.area', 'specialist.profile.services'] as $route) {
        $this->actingAs($mine->user)->put(route($route), [
            'display_name' => 'Nowa', 'account_type' => 'company', 'user_id' => $other->user_id, 'id' => $other->id,
            'slug' => 'przejete', 'is_published' => '1', 'verification_status' => 'verified', 'profile_id' => $other->id,
        ]);
    }

    expect($mine->refresh()->slug)->toBe('moj-adres')
        ->and($mine->user_id)->not->toBe($other->user_id)
        ->and($mine->is_published)->toBeFalse()
        ->and($mine->verification_status)->toBe(VerificationStatus::Pending)
        ->and($other->refresh()->slug)->toBe('cudzy')
        ->and($other->display_name)->not->toBe('Nowa');
});

/**
 * Opublikowany profil z telefonem i miejscowością (lokalny odpowiednik, żeby plik działał samodzielnie).
 *
 * @param  array<string, mixed>  $attributes
 */
function publishedProfileForEdge(array $attributes = []): SpecialistProfile
{
    $profile = completeSpecialistProfile(array_merge([
        'slug' => 'kominiarz-edge', 'display_name' => 'Kominiarz Edge', 'is_published' => true, 'first_published_at' => now()->subDays(5),
    ], $attributes));

    // Pomocnik completeSpecialistProfile() nadpisuje telefon, więc wartość z $attributes ustawiamy dopiero teraz.
    if (isset($attributes['phone'])) {
        $profile->forceFill(['phone' => $attributes['phone']])->save();
    }

    return $profile->refresh();
}
