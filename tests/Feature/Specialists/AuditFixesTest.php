<?php

/*
| Poprawki po audycie bezpieczeństwa zadania 004: higiena PDF, pamięć przy przekodowaniu zdjęć, dzienny limit zmian
| miejscowości, noindex/canonical, unikalność zadania geokodowania, wyścig prune-orphans, zgoda na telefon.
*/

use App\Domain\Geo\Models\TerytLocality;
use App\Domain\Specialists\Actions\PurgeSpecialistData;
use App\Domain\Specialists\Enums\VerificationStatus;
use App\Domain\Specialists\Jobs\GeocodeLocalityJob;
use App\Domain\Specialists\Models\SpecialistCertificate;
use App\Domain\Specialists\Support\ImageReencoder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Illuminate\Testing\TestResponse;
use Illuminate\Validation\ValidationException;

beforeEach(function () {
    Storage::fake('local');
    Cache::flush();
});

/**
 * Wysyła PDF z podanym dopiskiem jako nowe zaświadczenie i zwraca odpowiedź.
 */
function auditUploadPdf(object $test, string $extra, ?string $name = 'skan.pdf'): TestResponse
{
    $profile = specialistProfile();
    $pdf = "%PDF-1.4\n1 0 obj\n<< /Type /Catalog >>\nendobj\n{$extra}\ntrailer\n<< /Root 1 0 R >>\n%%EOF\n";

    return $test->actingAs($profile->user)->post(route('specialist.certificates.store'), [
        'type' => 'building_permit',
        'title' => 'Specjalność instalacyjna',
        'number' => 'MAP/BO/1234/20',
        'valid_until' => now()->addYear()->toDateString(),
        'declaration' => '1',
        'file' => UploadedFile::fake()->createWithContent((string) $name, $pdf),
    ]);
}

// --- #1 higiena PDF -------------------------------------------------------------------------------------------

it('PDF: odrzuca nazwy zapisane szesnastkowo (/J#61vaScript, /Open#41ction)', function (string $token) {
    auditUploadPdf($this, "2 0 obj\n<< {$token} (x) >>\nendobj")
        ->assertSessionHasErrors(['file' => __('certificates.errors.file_pdf_content')]);

    expect(Storage::disk('local')->allFiles())->toBe([]);
})->with(['/J#61vaScript', '/Open#41ction', '/#4A#53', '/La#75nch']);

it('PDF: odrzuca nowe znaczniki aktywnej treści i szyfrowanie', function (string $token) {
    auditUploadPdf($this, "2 0 obj\n<< {$token} true >>\nendobj")
        ->assertSessionHasErrors(['file' => __('certificates.errors.file_pdf_content')]);
})->with(['/RichMedia', '/XFA', '/SubmitForm', '/ImportData', '/GoToR', '/GoToE', '/Encrypt']);

it('PDF: znacznik po 2 MB (w całym limicie 5 MB) też jest wykrywany', function () {
    $padding = str_repeat('a', 3 * 1024 * 1024);

    auditUploadPdf($this, "% {$padding}\n2 0 obj\n<< /OpenAction 1 0 R >>\nendobj")
        ->assertSessionHasErrors(['file' => __('certificates.errors.file_pdf_content')]);
});

it('PDF: zwykły dokument, nazwa o podobnym początku i ogromny ciąg bez separatorów nie są odrzucane', function () {
    auditUploadPdf($this, "2 0 obj\n<< /JSONData (x) /AAbb 1 /Title (JavaScript w tytule) >>\nendobj\n% ".str_repeat('/a', 1000).str_repeat('b', 1024 * 1024))
        ->assertSessionHasNoErrors();

    expect(SpecialistCertificate::query()->count())->toBe(1);
});

// --- #2 pamięć przy przekodowaniu zdjęć -----------------------------------------------------------------------

it('zdjęcie: gdy brakuje pamięci, wraca czytelny błąd walidacji zamiast wyczerpania pamięci PHP', function () {
    $path = tempnam(sys_get_temp_dir(), 'img');
    file_put_contents($path, UploadedFile::fake()->image('a.jpg', 3000, 2000)->get());
    $previous = ini_get('memory_limit');

    try {
        // Limit tuż nad bieżącym zużyciem: po odjęciu zapasu nie zostaje miejsca na dekodowanie 6 Mpx.
        ini_set('memory_limit', (string) (memory_get_usage(true) + 8 * 1024 * 1024));

        expect(fn () => app(ImageReencoder::class)->toJpeg($path, 'image/jpeg'))
            ->toThrow(fn (ValidationException $e) => expect($e->errors()['file'][0])->toBe(__('certificates.errors.file_memory')));
    } finally {
        ini_set('memory_limit', (string) $previous);
        @unlink($path);
    }
});

it('zdjęcie: przy odpowiedniej pamięci jest zmniejszane do 2400 px i zapisywane jako JPEG', function () {
    $path = tempnam(sys_get_temp_dir(), 'img');
    file_put_contents($path, UploadedFile::fake()->image('a.jpg', 3000, 1500)->get());
    $previous = ini_get('memory_limit');

    try {
        ini_set('memory_limit', '1G');
        [$width, $height] = getimagesizefromstring(app(ImageReencoder::class)->toJpeg($path, 'image/jpeg'));
    } finally {
        ini_set('memory_limit', (string) $previous);
        @unlink($path);
    }

    expect($width)->toBe(2400)->and($height)->toBe(1200);
});

// --- #3 dzienny limit zmian miejscowości ----------------------------------------------------------------------

it('limit dobowy: po przekroczeniu zmiany miejscowości jest błąd pola, a profil się nie zmienia', function () {
    Queue::fake();
    config(['specialists.locality_changes_per_day' => 2]);
    $profile = specialistProfile();
    $payload = fn (TerytLocality $l): array => [
        'locality_simc' => $l->simc, 'service_radius_km' => '30', 'travel_rate_per_km' => '0', 'travel_free_km' => '0',
    ];
    [$a, $b, $c] = [TerytLocality::factory()->create(), TerytLocality::factory()->create(), TerytLocality::factory()->create()];

    $this->actingAs($profile->user)->put(route('specialist.profile.area'), $payload($a))->assertSessionHasNoErrors();
    $this->actingAs($profile->user)->put(route('specialist.profile.area'), $payload($b))->assertSessionHasNoErrors();
    $this->actingAs($profile->user)->put(route('specialist.profile.area'), $payload($c))
        ->assertSessionHasErrors(['locality_simc' => __('specialist.validation.locality_change_limited', ['max' => 2])]);

    expect($profile->refresh()->locality_simc)->toBe($b->simc);
    Queue::assertPushed(GeocodeLocalityJob::class, 2);
});

it('limit dobowy: zapis bez zmiany miejscowości (sam promień) nie zużywa limitu, a limit jest osobny dla każdego konta', function () {
    Queue::fake();
    config(['specialists.locality_changes_per_day' => 1]);
    $profile = specialistProfile();
    $other = specialistProfile();
    $locality = TerytLocality::factory()->create();
    $payload = ['locality_simc' => $locality->simc, 'service_radius_km' => '30', 'travel_rate_per_km' => '0', 'travel_free_km' => '0'];

    $this->actingAs($profile->user)->put(route('specialist.profile.area'), $payload)->assertSessionHasNoErrors();

    foreach ([40, 50, 60] as $radius) {
        $this->actingAs($profile->user)->put(route('specialist.profile.area'), array_merge($payload, ['service_radius_km' => (string) $radius]))->assertSessionHasNoErrors();
    }

    $this->actingAs($other->user)->put(route('specialist.profile.area'), $payload)->assertSessionHasNoErrors();

    expect($profile->refresh()->service_radius_km)->toBe(60)
        ->and($other->refresh()->locality_simc)->toBe($locality->simc);
});

it('limit dobowy: po upływie doby zmiana jest znowu możliwa', function () {
    Queue::fake();
    config(['specialists.locality_changes_per_day' => 1]);
    $profile = specialistProfile();
    $payload = fn (TerytLocality $l): array => ['locality_simc' => $l->simc, 'service_radius_km' => '30', 'travel_rate_per_km' => '0', 'travel_free_km' => '0'];
    [$a, $b] = [TerytLocality::factory()->create(), TerytLocality::factory()->create()];

    $this->actingAs($profile->user)->put(route('specialist.profile.area'), $payload($a))->assertSessionHasNoErrors();
    $this->actingAs($profile->user)->put(route('specialist.profile.area'), $payload($b))->assertSessionHasErrors('locality_simc');

    $this->travel(25)->hours();

    $this->actingAs($profile->user)->put(route('specialist.profile.area'), $payload($b))->assertSessionHasNoErrors();
    expect($profile->refresh()->locality_simc)->toBe($b->simc);
});

// --- #4 noindex i canonical -----------------------------------------------------------------------------------

it('opublikowany profil pending: X-Robots-Tag noindex, meta robots noindex i canonical na bieżący slug', function () {
    $profile = completeSpecialistProfile(['slug' => 'oczekujacy', 'display_name' => 'Oczekujący', 'is_published' => true, 'first_published_at' => now()->subDay()]);

    $response = $this->get(route('specialists.show', 'oczekujacy'))->assertOk();

    expect($response->headers->get('X-Robots-Tag'))->toContain('noindex')
        ->and($response->headers->get('Cache-Control'))->not->toContain('no-store');
    $response->assertSee('<meta name="robots" content="noindex, nofollow">', false)
        ->assertSee('<link rel="canonical" href="'.route('specialists.show', 'oczekujacy').'">', false);
});

it('profil verified: bez noindex (ani w nagłówku, ani w HTML), z canonical', function () {
    $profile = completeSpecialistProfile(['slug' => 'zweryfikowany', 'is_published' => true, 'first_published_at' => now()->subDay()]);
    $profile->forceFill(['verification_status' => VerificationStatus::Verified, 'verified_at' => now()->subDay()])->save();

    $response = $this->get(route('specialists.show', 'zweryfikowany'))->assertOk();

    expect($response->headers->get('X-Robots-Tag'))->toBeNull();
    $response->assertDontSee('name="robots"', false)
        ->assertSee('<link rel="canonical" href="'.route('specialists.show', 'zweryfikowany').'">', false);
});

it('canonical po starym adresie wskazuje nowy slug (301 prowadzi do adresu kanonicznego)', function () {
    $profile = completeSpecialistProfile(['slug' => 'nowy-slug', 'is_published' => true, 'first_published_at' => now()->subDay()]);
    DB::table('specialist_slug_redirects')->insert(['specialist_profile_id' => $profile->id, 'old_slug' => 'stary-slug', 'created_at' => now()]);

    $this->get('/fachowiec/stary-slug')->assertRedirect(route('specialists.show', 'nowy-slug'))->assertStatus(301);
});

it('podgląd nieopublikowanego profilu ma noindex także w meta robots', function () {
    $profile = completeSpecialistProfile(['slug' => 'ukryty-podglad', 'is_published' => false]);

    $this->actingAs($profile->user)->get(route('specialists.show', 'ukryty-podglad'))
        ->assertOk()
        ->assertHeader('X-Robots-Tag', 'noindex, nofollow')
        ->assertSee('<meta name="robots" content="noindex, nofollow">', false);
});

// --- uniqueFor >= suma backoff --------------------------------------------------------------------------------

it('GeocodeLocalityJob: blokada unikalności jest dłuższa niż suma opóźnień między próbami', function () {
    $job = new GeocodeLocalityJob('0000001');

    expect($job->uniqueFor)->toBeGreaterThanOrEqual(array_sum($job->backoff()));
});

// --- wyścig prune-orphans -------------------------------------------------------------------------------------

it('archiwizacja odświeża czas pliku, więc prune-orphans nie usuwa go w oknie przed zatwierdzeniem wiersza', function () {
    $profile = completeSpecialistProfile();
    $certificate = SpecialistCertificate::factory()->create(['specialist_profile_id' => $profile->id]);
    Storage::disk('local')->put($certificate->file_path, '%PDF-1.4 skan');
    touch(Storage::disk('local')->path($certificate->file_path), time() - 10 * 86400);

    app(PurgeSpecialistData::class)->handle($profile->user);

    $archivePath = Storage::disk('local')->files(PurgeSpecialistData::ARCHIVE_DIRECTORY)[0];
    clearstatcache();

    expect(filemtime(Storage::disk('local')->path($archivePath)))->toBeGreaterThan(time() - 3600);
});

// --- zgoda na telefon po zmianie numeru -----------------------------------------------------------------------

it('zmiana numeru przy publicznym telefonie cofa zgodę, zeruje dowód i mówi o tym użytkownikowi', function () {
    $profile = specialistProfile(['phone' => '+48600123456', 'phone_is_public' => true, 'phone_public_consent_at' => now()->subDay()]);

    $this->actingAs($profile->user)->put(route('specialist.profile.update'), [
        'display_name' => 'Jan', 'account_type' => 'sole_proprietor', 'phone' => '601 222 333', 'phone_is_public' => '1',
    ])->assertSessionHas('status', __('specialist.data.saved_phone_consent_reset'));

    $profile->refresh();
    expect($profile->phone)->toBe('+48601222333')
        ->and($profile->phone_is_public)->toBeFalse()
        ->and($profile->phone_public_consent_at)->toBeNull();
});

it('ten sam numer (w innym zapisie) zachowuje zgodę i jej datę', function () {
    $consent = now()->subDays(3)->startOfSecond();
    $profile = specialistProfile(['phone' => '+48600123456', 'phone_is_public' => true, 'phone_public_consent_at' => $consent]);

    $this->actingAs($profile->user)->put(route('specialist.profile.update'), [
        'display_name' => 'Jan', 'account_type' => 'sole_proprietor', 'phone' => '600 123 456', 'phone_is_public' => '1',
    ])->assertSessionHas('status', __('specialist.data.saved'));

    $profile->refresh();
    expect($profile->phone_is_public)->toBeTrue()
        ->and($profile->phone_public_consent_at->equalTo($consent))->toBeTrue();
});

it('zmiana numeru bez wcześniejszej zgody: zaznaczenie zgody w tym samym zapisie działa jak pierwsza zgoda', function () {
    $profile = specialistProfile(['phone' => '+48600123456', 'phone_is_public' => false]);

    $this->actingAs($profile->user)->put(route('specialist.profile.update'), [
        'display_name' => 'Jan', 'account_type' => 'sole_proprietor', 'phone' => '601 222 333', 'phone_is_public' => '1',
    ])->assertSessionHas('status', __('specialist.data.saved'));

    expect($profile->refresh()->phone_is_public)->toBeTrue()->and($profile->phone_public_consent_at)->not->toBeNull();
});
