<?php

/*
| Zaświadczenia: wgrywanie (MIME po zawartości, przekodowanie obrazów, limity, duplikaty), edycja, usuwanie.
| Pliki trafiają na sfałszowany dysk `local` (Storage::fake), nigdy do prawdziwego storage/.
*/

use App\Domain\Specialists\Models\ArchivedCertificate;
use App\Domain\Specialists\Models\SpecialistCertificate;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Spatie\Activitylog\Models\Activity;

beforeEach(function () {
    Storage::fake('local');
});

/** Najprostszy poprawny PDF (nagłówek, jeden obiekt, EOF) z możliwością dopisania treści. */
function tinyPdf(string $extra = ''): string
{
    return "%PDF-1.4\n1 0 obj\n<< /Type /Catalog >>\nendobj\n{$extra}\ntrailer\n<< /Root 1 0 R >>\n%%EOF\n";
}

/**
 * Poprawne dane formularza dodawania zaświadczenia.
 *
 * @param  array<string, mixed>  $override
 * @return array<string, mixed>
 */
function certificatePayload(UploadedFile $file, array $override = []): array
{
    return array_merge([
        'type' => 'building_permit',
        'title' => 'Specjalność instalacyjna w zakresie sieci elektrycznych',
        'number' => 'MAP/BO/1234/20',
        'issuer' => 'WOIIB',
        'valid_until' => now()->addYear()->toDateString(),
        'declaration' => '1',
        'file' => $file,
    ], $override);
}

it('dodaje PDF: losowa nazwa na dysku local, metadane, SHA-256, wpis w dzienniku bez numeru', function () {
    $profile = specialistProfile();
    $pdf = tinyPdf();

    $this->actingAs($profile->user)
        ->post(route('specialist.certificates.store'), certificatePayload(UploadedFile::fake()->createWithContent('Mój skan ../../x.pdf', $pdf)))
        ->assertRedirect(route('specialist.certificates.index').'#lista')
        ->assertSessionHas('status', __('certificates.form.added'));

    $certificate = SpecialistCertificate::query()->firstOrFail();

    expect($certificate->specialist_profile_id)->toBe($profile->id)
        ->and($certificate->ulid)->toMatch('/^[0-9a-z]{26}$/')
        ->and($certificate->file_mime)->toBe('application/pdf')
        ->and($certificate->file_sha256)->toBe(hash('sha256', $pdf))
        ->and($certificate->file_size)->toBe(strlen($pdf))
        ->and($certificate->file_path)->toMatch('#^specialist-certificates/[A-Za-z0-9]{40}\.pdf$#')
        ->and($certificate->file_original_name)->not->toContain('/')
        ->and($certificate->file_path)->not->toContain('skan');

    Storage::disk('local')->assertExists($certificate->file_path);
    expect(Storage::disk('local')->get($certificate->file_path))->toBe($pdf);

    $entry = Activity::query()->where('event', 'certificate_added')->firstOrFail();
    expect(json_encode($entry->properties))->not->toContain('MAP/BO/1234/20')
        ->and($entry->properties['declaration_accepted'])->toBeTrue();
});

it('zdjęcie jest przekodowane do JPEG bez EXIF/GPS, a pomniejszone do 2400 px', function () {
    // Przekodowanie sprawdza wolną pamięć PHP; pełny zestaw testów zużywa jej dużo, więc podnosimy limit na czas testu.
    $previousLimit = ini_get('memory_limit');
    ini_set('memory_limit', '1G');
    $profile = specialistProfile();
    $jpeg = UploadedFile::fake()->image('zdjecie.jpg', 3000, 1500)->get();

    // Doklejamy segment APP1 (EXIF) z "tajnym" znacznikiem lokalizacji.
    $secret = 'GPS-SECRET-52.2297N-21.0122E';
    $payload = "Exif\0\0".$secret;
    $withExif = substr($jpeg, 0, 2)."\xFF\xE1".pack('n', strlen($payload) + 2).$payload.substr($jpeg, 2);
    expect(str_contains($withExif, $secret))->toBeTrue();

    $this->actingAs($profile->user)
        ->post(route('specialist.certificates.store'), certificatePayload(UploadedFile::fake()->createWithContent('foto.jpg', $withExif)))
        ->assertSessionHasNoErrors();

    $certificate = SpecialistCertificate::query()->firstOrFail();
    $stored = Storage::disk('local')->get($certificate->file_path);
    [$width, $height] = getimagesizefromstring($stored);
    ini_set('memory_limit', (string) $previousLimit);

    expect($certificate->file_mime)->toBe('image/jpeg')
        ->and($certificate->file_path)->toEndWith('.jpg')
        ->and(str_contains($stored, $secret))->toBeFalse()
        ->and($width)->toBe(2400)
        ->and($height)->toBe(1200)
        ->and($certificate->file_sha256)->toBe(hash('sha256', $stored))
        ->and($certificate->file_size)->toBe(strlen($stored));
});

it('PNG jest zapisany jako JPEG', function () {
    $profile = specialistProfile();

    $this->actingAs($profile->user)
        ->post(route('specialist.certificates.store'), certificatePayload(UploadedFile::fake()->image('skan.png', 200, 100)))
        ->assertSessionHasNoErrors();

    $certificate = SpecialistCertificate::query()->firstOrFail();

    expect($certificate->file_mime)->toBe('image/jpeg')
        ->and(getimagesizefromstring(Storage::disk('local')->get($certificate->file_path))['mime'])->toBe('image/jpeg');
});

it('odrzuca pliki niedozwolone, także gdy mają „dobre” rozszerzenie', function (string $name, string $content) {
    $profile = specialistProfile();

    $this->actingAs($profile->user)
        ->post(route('specialist.certificates.store'), certificatePayload(UploadedFile::fake()->createWithContent($name, $content)))
        ->assertSessionHasErrors('file');

    expect(SpecialistCertificate::query()->count())->toBe(0)
        ->and(Storage::disk('local')->allFiles())->toBe([]);
})->with([
    'plik wykonywalny jako pdf' => ['skan.pdf', "MZ\x90\x00\x03\x00\x00\x00 to nie jest pdf"],
    'skrypt PHP jako jpg' => ['skan.jpg', '<?php // to nie jest obraz'],
    'tekst jako png' => ['skan.png', 'zwykły tekst'],
    'html jako pdf' => ['skan.pdf', '<html><script>alert(1)</script></html>'],
    'svg jako jpg' => ['skan.jpg', '<svg xmlns="http://www.w3.org/2000/svg"><script>alert(1)</script></svg>'],
    'plik pusty' => ['skan.pdf', ''],
]);

it('odrzuca PDF z aktywną treścią (JavaScript, OpenAction, Launch)', function (string $token) {
    $profile = specialistProfile();

    $this->actingAs($profile->user)
        ->post(route('specialist.certificates.store'), certificatePayload(UploadedFile::fake()->createWithContent('skan.pdf', tinyPdf("2 0 obj\n<< {$token} (x) >>\nendobj"))))
        ->assertSessionHasErrors(['file' => __('certificates.errors.file_pdf_content')]);

    expect(Storage::disk('local')->allFiles())->toBe([]);
})->with(['/JavaScript', '/JS', '/OpenAction', '/Launch', '/EmbeddedFile', '/AA']);

it('PDF z nazwą zaczynającą się jak znacznik (np. /JSON) nie jest odrzucany', function () {
    $profile = specialistProfile();

    $this->actingAs($profile->user)
        ->post(route('specialist.certificates.store'), certificatePayload(UploadedFile::fake()->createWithContent('skan.pdf', tinyPdf("2 0 obj\n<< /JSONData (x) /Title (AAbb) >>\nendobj"))))
        ->assertSessionHasNoErrors();

    expect(SpecialistCertificate::query()->count())->toBe(1);
});

it('odrzuca obraz o zbyt dużej rozdzielczości (przed dekodowaniem)', function () {
    $profile = specialistProfile();

    // Minimalny PNG, którego nagłówek deklaruje 5000 x 5000 px (25 MP > 16 MP); dekodowanie nie powinno się zacząć.
    $ihdr = pack('N', 5000).pack('N', 5000)."\x08\x02\x00\x00\x00";
    $png = "\x89PNG\r\n\x1a\n".pack('N', 13).'IHDR'.$ihdr.pack('N', crc32('IHDR'.$ihdr)).pack('N', 0).'IEND'.pack('N', crc32('IEND'));

    $this->actingAs($profile->user)
        ->post(route('specialist.certificates.store'), certificatePayload(UploadedFile::fake()->createWithContent('duzy.png', $png)))
        ->assertSessionHasErrors(['file' => __('certificates.errors.file_pixels')]);

    expect(SpecialistCertificate::query()->count())->toBe(0);
});

it('odrzuca plik większy niż 5 MB', function () {
    $profile = specialistProfile();
    $big = tinyPdf(str_repeat('a', 5 * 1024 * 1024));

    $this->actingAs($profile->user)
        ->post(route('specialist.certificates.store'), certificatePayload(UploadedFile::fake()->createWithContent('duzy.pdf', $big)))
        ->assertSessionHasErrors(['file' => __('certificates.errors.file_size')]);
});

it('odrzuca ten sam plik dodany drugi raz do profilu, ale inny profil może go dodać', function () {
    $profile = specialistProfile();
    $other = specialistProfile();
    $pdf = tinyPdf();

    $this->actingAs($profile->user)->post(route('specialist.certificates.store'), certificatePayload(UploadedFile::fake()->createWithContent('a.pdf', $pdf)))->assertSessionHasNoErrors();
    $this->actingAs($profile->user)->post(route('specialist.certificates.store'), certificatePayload(UploadedFile::fake()->createWithContent('b.pdf', $pdf)))
        ->assertSessionHasErrors(['file' => __('certificates.errors.file_duplicate')]);
    $this->actingAs($other->user)->post(route('specialist.certificates.store'), certificatePayload(UploadedFile::fake()->createWithContent('c.pdf', $pdf)))->assertSessionHasNoErrors();

    expect(SpecialistCertificate::query()->count())->toBe(2)
        ->and(count(Storage::disk('local')->allFiles()))->toBe(2);
});

it('nie pozwala dodać 11. zaświadczenia', function () {
    $profile = specialistProfile();
    SpecialistCertificate::factory()->count(10)->create(['specialist_profile_id' => $profile->id]);

    $this->actingAs($profile->user)
        ->post(route('specialist.certificates.store'), certificatePayload(UploadedFile::fake()->createWithContent('a.pdf', tinyPdf())))
        ->assertSessionHasErrors(['file' => __('certificates.errors.limit_count')]);

    expect(SpecialistCertificate::query()->count())->toBe(10);
});

it('pilnuje łącznego rozmiaru plików w profilu', function () {
    config(['specialists.certificate_max_total_kb' => 1]);
    $profile = specialistProfile();

    $this->actingAs($profile->user)
        ->post(route('specialist.certificates.store'), certificatePayload(UploadedFile::fake()->createWithContent('a.pdf', tinyPdf(str_repeat('a', 2000)))))
        ->assertSessionHasErrors(['file' => __('certificates.errors.limit_total')]);
});

it('waliduje metadane: rodzaj, zakres, numer, ważność, oświadczenie', function (array $override, string $errorKey) {
    $profile = specialistProfile();

    $this->actingAs($profile->user)
        ->post(route('specialist.certificates.store'), certificatePayload(UploadedFile::fake()->createWithContent('a.pdf', tinyPdf()), $override))
        ->assertSessionHasErrors($errorKey);

    expect(SpecialistCertificate::query()->count())->toBe(0)->and(Storage::disk('local')->allFiles())->toBe([]);
})->with([
    'zły rodzaj' => [['type' => 'wymyslony'], 'type'],
    'zbyt krótki zakres' => [['title' => 'ab'], 'title'],
    'brak numeru' => [['number' => ''], 'number'],
    'data z przeszłości' => [['valid_until' => '2020-01-01'], 'valid_until'],
    'brak daty i brak „bezterminowo”' => [['valid_until' => ''], 'valid_until'],
    'brak oświadczenia' => [['declaration' => ''], 'declaration'],
]);

it('„bezterminowo” zapisuje brak daty ważności', function () {
    $profile = specialistProfile();

    $this->actingAs($profile->user)
        ->post(route('specialist.certificates.store'), certificatePayload(UploadedFile::fake()->createWithContent('a.pdf', tinyPdf()), ['valid_until' => '', 'no_expiry' => '1']))
        ->assertSessionHasNoErrors();

    expect(SpecialistCertificate::query()->firstOrFail()->valid_until)->toBeNull();
});

it('brak pliku w żądaniu daje komunikat „wybierz plik”', function () {
    $profile = specialistProfile();
    $payload = certificatePayload(UploadedFile::fake()->createWithContent('a.pdf', tinyPdf()));
    unset($payload['file']);

    $this->actingAs($profile->user)->post(route('specialist.certificates.store'), $payload)
        ->assertSessionHasErrors(['file' => __('certificates.errors.file_required')]);
});

it('limit wysyłania: 10 żądań na godzinę na użytkownika', function () {
    $profile = specialistProfile();

    for ($i = 0; $i < 10; $i++) {
        $this->actingAs($profile->user)->post(route('specialist.certificates.store'), [])->assertSessionHasErrors();
    }

    $this->actingAs($profile->user)->post(route('specialist.certificates.store'), [])->assertStatus(429);
});

it('edytuje metadane zaświadczenia i odświeża updated_at, plik zostaje', function () {
    $profile = specialistProfile();
    $certificate = SpecialistCertificate::factory()->create(['specialist_profile_id' => $profile->id]);
    $this->travel(2)->days();

    $this->actingAs($profile->user)->put(route('specialist.certificates.update', $certificate), [
        'type' => 'master_chimney_sweep', 'title' => 'Mistrz kominiarski', 'number' => 'KOM/55/2020', 'no_expiry' => '1',
    ])->assertRedirect(route('specialist.certificates.index').'#lista');

    $fresh = $certificate->fresh();
    expect($fresh->type->value)->toBe('master_chimney_sweep')
        ->and($fresh->valid_until)->toBeNull()
        ->and($fresh->issuer)->toBeNull()
        ->and($fresh->file_path)->toBe($certificate->file_path)
        ->and($fresh->updated_at->gt($certificate->updated_at))->toBeTrue();
});

it('edycja nie przyjmuje zmiany pliku ani pól technicznych', function () {
    $profile = specialistProfile();
    $certificate = SpecialistCertificate::factory()->create(['specialist_profile_id' => $profile->id]);
    $other = specialistProfile();

    $this->actingAs($profile->user)->put(route('specialist.certificates.update', $certificate), [
        'type' => 'other', 'title' => 'Inne uprawnienie', 'number' => 'XY/1/2', 'no_expiry' => '1',
        'file_path' => 'specialist-certificates/podmieniony.pdf', 'specialist_profile_id' => $other->id, 'ulid' => 'x', 'file_sha256' => 'x',
    ])->assertSessionHasNoErrors();

    $fresh = $certificate->fresh();
    expect($fresh->file_path)->toBe($certificate->file_path)
        ->and($fresh->specialist_profile_id)->toBe($profile->id)
        ->and($fresh->ulid)->toBe($certificate->ulid)
        ->and($fresh->file_sha256)->toBe($certificate->file_sha256);
});

it('usuwa zaświadczenie razem z plikiem od razu, bez archiwum', function () {
    $profile = specialistProfile();
    $certificate = SpecialistCertificate::factory()->create(['specialist_profile_id' => $profile->id]);
    Storage::disk('local')->put($certificate->file_path, tinyPdf());

    $this->actingAs($profile->user)->delete(route('specialist.certificates.destroy', $certificate))
        ->assertRedirect(route('specialist.certificates.index').'#lista')
        ->assertSessionHas('status', __('certificates.list.deleted'));

    Storage::disk('local')->assertMissing($certificate->file_path);
    expect(SpecialistCertificate::query()->count())->toBe(0)
        ->and(ArchivedCertificate::query()->count())->toBe(0);
});

it('cudze zaświadczenie nie może być edytowane ani usunięte', function () {
    $owner = specialistProfile();
    $intruder = specialistProfile();
    $certificate = SpecialistCertificate::factory()->create(['specialist_profile_id' => $owner->id]);
    Storage::disk('local')->put($certificate->file_path, tinyPdf());

    $this->actingAs($intruder->user)->put(route('specialist.certificates.update', $certificate), [
        'type' => 'other', 'title' => 'Podmiana', 'number' => 'XY/1/2', 'no_expiry' => '1',
    ])->assertForbidden();
    $this->actingAs($intruder->user)->delete(route('specialist.certificates.destroy', $certificate))->assertForbidden();

    expect($certificate->fresh()->title)->not->toBe('Podmiana');
    Storage::disk('local')->assertExists($certificate->file_path);
});

it('lista zaświadczeń zawiera tylko zaświadczenia zalogowanego fachowca', function () {
    $profile = specialistProfile();
    $other = specialistProfile();
    SpecialistCertificate::factory()->create(['specialist_profile_id' => $profile->id, 'title' => 'Moje uprawnienie', 'number' => 'MOJ/1/2026']);
    SpecialistCertificate::factory()->create(['specialist_profile_id' => $other->id, 'title' => 'Cudze uprawnienie', 'number' => 'CUD/9/2026']);

    // Właściciel widzi na liście własny numer (widok z zadania 004.7), ale nigdy cudzy.
    $this->actingAs($profile->user)->get(route('specialist.certificates.index'))
        ->assertOk()
        ->assertSee('Moje uprawnienie')
        ->assertSee('MOJ/1/2026')
        ->assertDontSee('Cudze uprawnienie')
        ->assertDontSee('CUD/9/2026');
});
