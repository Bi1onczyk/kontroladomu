<?php

/*
| Wydawanie plików zaświadczeń: tylko właściciel profilu i administrator, nagłówki bezpieczeństwa,
| dziennik pobrań administratora, brak różnicy między „cudzym” a „nieistniejącym” plikiem.
*/

use App\Domain\Specialists\Models\SpecialistCertificate;
use App\Domain\Specialists\Models\SpecialistProfile;
use App\Models\User;
use Illuminate\Support\Facades\Storage;
use Spatie\Activitylog\Models\Activity;

beforeEach(function () {
    Storage::fake('local');
});

/**
 * Zaświadczenie z plikiem PDF na (sfałszowanym) dysku.
 */
function certificateWithFile(?SpecialistProfile $profile = null): SpecialistCertificate
{
    $profile ??= specialistProfile();
    $certificate = SpecialistCertificate::factory()->create([
        'specialist_profile_id' => $profile->id,
        'file_original_name' => 'moj-PESEL-skan.pdf',
    ]);
    Storage::disk('local')->put($certificate->file_path, "%PDF-1.4\nTAJNA-TRESC\n%%EOF");

    return $certificate;
}

it('właściciel pobiera plik jako załącznik z własną nazwą i nagłówkami bezpieczeństwa', function () {
    $certificate = certificateWithFile();

    $response = $this->actingAs($certificate->profile->user)->get(route('specialist.certificates.file', $certificate));

    $response->assertOk();
    expect($response->headers->get('Content-Type'))->toStartWith('application/pdf')
        ->and($response->headers->get('Content-Disposition'))->toContain('attachment')
        ->toContain('zaswiadczenie-'.$certificate->ulid.'.pdf')
        ->not->toContain('PESEL')
        ->and($response->headers->get('X-Content-Type-Options'))->toBe('nosniff')
        ->and($response->headers->get('Cache-Control'))->toContain('no-store')->toContain('private')
        ->and($response->streamedContent())->toContain('TAJNA-TRESC');

    // Pobranie przez właściciela nie trafia do dziennika działań administratora.
    expect(Activity::query()->where('event', 'certificate_file_viewed')->count())->toBe(0);
});

it('adres pliku używa ULID, a nie numerycznego ID', function () {
    $certificate = certificateWithFile();

    expect(route('specialist.certificates.file', $certificate))->toContain($certificate->ulid)
        ->and(route('specialist.certificates.file', $certificate))->not->toContain('/zaswiadczenia/'.$certificate->id.'/');

    $this->actingAs($certificate->profile->user)->get('/panel/zaswiadczenia/'.$certificate->id.'/plik')->assertNotFound();
});

it('administrator pobiera plik, a pobranie trafia do dziennika działań admina bez zawartości', function () {
    $certificate = certificateWithFile();
    $admin = User::factory()->admin()->withTwoFactor()->create();

    $this->actingAs($admin)->get(route('specialist.certificates.file', $certificate))->assertOk();

    $entry = Activity::query()->where('event', 'certificate_file_viewed')->firstOrFail();
    expect($entry->log_name)->toBe('security_admin')
        ->and($entry->causer_id)->toBe($admin->id)
        ->and($entry->properties['ulid'])->toBe($certificate->ulid)
        ->and($entry->properties)->toHaveKey('ip')
        ->and(json_encode($entry->properties))->not->toContain('TAJNA-TRESC')->not->toContain($certificate->number);
});

it('inni użytkownicy dostają 404, takie samo jak dla nieistniejącego pliku', function () {
    $certificate = certificateWithFile();
    $users = [
        'inny fachowiec' => specialistProfile()->user,
        'właściciel nieruchomości' => User::factory()->owner()->create(),
        'pracownik' => User::factory()->technician()->withTwoFactor()->create(),
    ];

    foreach ($users as $label => $user) {
        $this->actingAs($user)->get(route('specialist.certificates.file', $certificate))->assertNotFound();
    }

    $this->actingAs($certificate->profile->user)->get('/panel/zaswiadczenia/01aaaaaaaaaaaaaaaaaaaaaaaa/plik')->assertNotFound();
    expect(Activity::query()->count())->toBe(0);
});

it('gość jest odsyłany na logowanie', function () {
    $certificate = certificateWithFile();

    $this->get(route('specialist.certificates.file', $certificate))->assertRedirect(route('login'));
});

it('brak pliku na dysku daje 404', function () {
    $certificate = certificateWithFile();
    Storage::disk('local')->delete($certificate->file_path);

    $this->actingAs($certificate->profile->user)->get(route('specialist.certificates.file', $certificate))->assertNotFound();
});

it('limit pobierania: 60 żądań na minutę', function () {
    $certificate = certificateWithFile();
    $this->actingAs($certificate->profile->user);

    for ($i = 0; $i < 60; $i++) {
        $this->get(route('specialist.certificates.file', $certificate))->assertOk();
    }

    $this->get(route('specialist.certificates.file', $certificate))->assertStatus(429);
});

it('dysk local nie serwuje plików przez adresy tymczasowe', function () {
    expect(config('filesystems.disks.local.serve'))->toBeFalse()
        ->and(config('filesystems.disks.local.root'))->toBe(storage_path('app/private'));
});
