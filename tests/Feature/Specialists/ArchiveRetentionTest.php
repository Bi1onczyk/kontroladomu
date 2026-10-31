<?php

/*
| Usunięcie danych fachowca z archiwizacją skanów na 12 miesięcy (ADR-021), komenda czyszcząca archiwum,
| sprzątanie osieroconych plików, uprawnienia do archiwum (tylko admin).
*/

use App\Domain\Specialists\Actions\DeleteArchivedCertificate;
use App\Domain\Specialists\Actions\PurgeSpecialistData;
use App\Domain\Specialists\Enums\VerificationStatus;
use App\Domain\Specialists\Models\ArchivedCertificate;
use App\Domain\Specialists\Models\SpecialistCertificate;
use App\Domain\Specialists\Models\SpecialistProfile;
use App\Domain\Specialists\Models\SpecialistService;
use App\Domain\Specialists\Models\SpecialistSlugRedirect;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Spatie\Activitylog\Models\Activity;

beforeEach(function () {
    Storage::fake('local');
});

afterEach(function () {
    Carbon::setTestNow();
});

/**
 * Profil z cennikiem, przekierowaniem i dwoma zaświadczeniami z plikami.
 */
function profileWithCertificates(): SpecialistProfile
{
    $profile = completeSpecialistProfile([
        'display_name' => 'Kominiarz Jan Kowalski',
        'description' => 'Prywatny opis do skasowania',
        'verification_status' => VerificationStatus::Verified,
        'verified_at' => now()->subDay(),
    ]);
    SpecialistSlugRedirect::query()->create(['specialist_profile_id' => $profile->id, 'old_slug' => 'dawny-adres']);

    foreach (['pierwszy', 'drugi'] as $name) {
        $certificate = SpecialistCertificate::factory()->create(['specialist_profile_id' => $profile->id, 'title' => "Zakres {$name}"]);
        Storage::disk('local')->put($certificate->file_path, "%PDF-1.4\n{$name}\n%%EOF");
    }

    return $profile->refresh();
}

it('przenosi skany do archiwum, zapisuje metadane i kasuje profil z całą resztą', function () {
    Carbon::setTestNow('2026-10-31 12:00:00');
    $profile = profileWithCertificates();
    $user = $profile->user;
    $originals = SpecialistCertificate::query()->pluck('file_path', 'ulid');

    $count = app(PurgeSpecialistData::class)->handle($user);

    expect($count)->toBe(2)
        ->and(SpecialistProfile::query()->count())->toBe(0)
        ->and(SpecialistService::query()->count())->toBe(0)
        ->and(SpecialistSlugRedirect::query()->count())->toBe(0)
        ->and(SpecialistCertificate::query()->count())->toBe(0)
        ->and(ArchivedCertificate::query()->count())->toBe(2);

    foreach (ArchivedCertificate::query()->get() as $archived) {
        expect($archived->file_path)->toStartWith('specialist-certificates-archive/')
            ->and($archived->holder_label)->toBe('Kominiarz Jan Kowalski')
            ->and($archived->former_user_id)->toBe($user->id)
            ->and($archived->verification_status_at_archive)->toBe(VerificationStatus::Verified)
            ->and($archived->archived_at->toDateString())->toBe('2026-10-31')
            ->and($archived->delete_after->toDateString())->toBe('2027-10-31');

        Storage::disk('local')->assertExists($archived->file_path);
        Storage::disk('local')->assertMissing($originals[$archived->ulid]);
    }

    // Konto użytkownika nie jest usuwane przez tę akcję (robi to przyszłe zadanie RODO po jej sukcesie).
    expect(User::query()->whereKey($user->id)->exists())->toBeTrue();
});

it('w archiwum nie ma telefonu, opisu, e-maila, NIP ani adresu profilu', function () {
    $profile = profileWithCertificates();
    $email = $profile->user->email;

    app(PurgeSpecialistData::class)->handle($profile->user);

    $dump = json_encode(ArchivedCertificate::query()->get()->toArray(), JSON_UNESCAPED_UNICODE);

    expect($dump)->not->toContain('600123456')
        ->not->toContain('Prywatny opis')
        ->not->toContain($email)
        ->not->toContain('6931171824')
        ->not->toContain('dawny-adres')
        ->not->toContain($profile->slug);

    $entry = Activity::query()->where('event', 'specialist_data_purged')->firstOrFail();
    expect($entry->properties['archived_certificates'])->toBe(2)
        ->and(json_encode($entry->properties))->not->toContain('MAP/BO');
});

it('okres archiwum bierze z konfiguracji', function () {
    config(['specialists.archive_retention_months' => 6]);
    Carbon::setTestNow('2026-10-31 12:00:00');
    $profile = profileWithCertificates();

    app(PurgeSpecialistData::class)->handle($profile->user);

    expect(ArchivedCertificate::query()->first()->delete_after->toDateString())->toBe('2027-04-30');
});

it('konto bez profilu lub profil bez zaświadczeń: nic do archiwizacji', function () {
    $noProfile = User::factory()->specialist()->create();
    $profile = specialistProfile();

    expect(app(PurgeSpecialistData::class)->handle($noProfile))->toBe(0)
        ->and(app(PurgeSpecialistData::class)->handle($profile->user))->toBe(0)
        ->and(SpecialistProfile::query()->count())->toBe(0)
        ->and(ArchivedCertificate::query()->count())->toBe(0);
});

it('błąd w środku cofa wszystko: profil zostaje, a pliki wracają na miejsce', function () {
    $profile = profileWithCertificates();
    $certificates = $profile->certificates()->orderBy('id')->get();
    $paths = $certificates->pluck('file_path')->all();

    // Drugie zaświadczenie koliduje z istniejącym wpisem archiwum (ten sam ULID): zapis wiersza rzuci wyjątek.
    ArchivedCertificate::factory()->create(['ulid' => $certificates[1]->ulid]);

    expect(fn () => app(PurgeSpecialistData::class)->handle($profile->user))->toThrow(Exception::class);

    expect(SpecialistProfile::query()->whereKey($profile->id)->exists())->toBeTrue()
        ->and(SpecialistCertificate::query()->count())->toBe(2)
        ->and(ArchivedCertificate::query()->count())->toBe(1);

    foreach ($paths as $path) {
        Storage::disk('local')->assertExists($path);
    }

    expect(Storage::disk('local')->allFiles('specialist-certificates-archive'))->toBe([]);
});

it('brak pliku na dysku: zaświadczenie jest pominięte w archiwum, a reszta archiwizowana', function () {
    $profile = profileWithCertificates();
    $first = $profile->certificates()->orderBy('id')->first();
    Storage::disk('local')->delete($first->file_path);
    Log::spy();

    $count = app(PurgeSpecialistData::class)->handle($profile->user);

    expect($count)->toBe(1)
        ->and(ArchivedCertificate::query()->count())->toBe(1)
        ->and(SpecialistProfile::query()->count())->toBe(0);
    Log::shouldHaveReceived('warning')->once();
});

it('komenda usuwa tylko wpisy po terminie: plik i wiersz, z wpisem w dzienniku (liczba i ULID)', function () {
    $overdue = ArchivedCertificate::factory()->overdue(1)->create();
    $today = ArchivedCertificate::factory()->create(['delete_after' => today()->toDateString()]);
    $future = ArchivedCertificate::factory()->create();
    foreach ([$overdue, $today, $future] as $archived) {
        Storage::disk('local')->put($archived->file_path, 'skan');
    }

    $this->artisan('certificates:purge-archive')->assertSuccessful();

    expect(ArchivedCertificate::query()->pluck('ulid')->all())->toBe([$future->ulid]);
    Storage::disk('local')->assertMissing($overdue->file_path);
    Storage::disk('local')->assertMissing($today->file_path);
    Storage::disk('local')->assertExists($future->file_path);

    $entry = Activity::query()->where('log_name', 'retention')->where('event', 'archive_purged')->firstOrFail();
    expect($entry->properties['count'])->toBe(2)
        ->and($entry->properties['ulids'])->toEqualCanonicalizing([$overdue->ulid, $today->ulid]);
});

it('komenda jest idempotentna i nie zapisuje pustego wpisu w dzienniku', function () {
    ArchivedCertificate::factory()->create();

    $this->artisan('certificates:purge-archive')->assertSuccessful();
    $this->artisan('certificates:purge-archive')->assertSuccessful();

    expect(ArchivedCertificate::query()->count())->toBe(1)
        ->and(Activity::query()->where('log_name', 'retention')->count())->toBe(0);
});

it('komenda usuwa wiersz także wtedy, gdy pliku już nie ma', function () {
    ArchivedCertificate::factory()->overdue()->create();

    $this->artisan('certificates:purge-archive')->assertSuccessful();

    expect(ArchivedCertificate::query()->count())->toBe(0);
});

it('wpis przeterminowany o ponad 7 dni zgłasza błąd (cron nie działał)', function () {
    ArchivedCertificate::factory()->overdue(10)->create();
    Log::spy();

    $this->artisan('certificates:purge-archive')->assertSuccessful();

    Log::shouldHaveReceived('error')->once();
});

it('wpis przeterminowany o kilka dni nie zgłasza błędu', function () {
    ArchivedCertificate::factory()->overdue(2)->create();
    Log::spy();

    $this->artisan('certificates:purge-archive')->assertSuccessful();

    Log::shouldNotHaveReceived('error');
    expect(ArchivedCertificate::query()->count())->toBe(0);
});

it('administrator może usunąć wpis archiwum wcześniej (sprzeciw), z wpisem w dzienniku admina', function () {
    $archived = ArchivedCertificate::factory()->create();
    Storage::disk('local')->put($archived->file_path, 'skan');
    $admin = User::factory()->admin()->withTwoFactor()->create();

    app(DeleteArchivedCertificate::class)->handle($archived, $admin, 'objection');

    Storage::disk('local')->assertMissing($archived->file_path);
    $entry = Activity::query()->where('event', 'archived_certificate_deleted')->firstOrFail();
    expect(ArchivedCertificate::query()->count())->toBe(0)
        ->and($entry->log_name)->toBe('security_admin')
        ->and($entry->properties['reason'])->toBe('objection')
        ->and($entry->causer_id)->toBe($admin->id);
});

it('do archiwum ma dostęp wyłącznie administrator', function () {
    $archived = ArchivedCertificate::factory()->create();
    $specialist = specialistProfile()->user;
    $admin = User::factory()->admin()->create();

    foreach (['viewAny' => ArchivedCertificate::class, 'view' => $archived, 'delete' => $archived] as $ability => $target) {
        expect(Gate::forUser($admin)->allows($ability, $target))->toBeTrue()
            ->and(Gate::forUser($specialist)->allows($ability, $target))->toBeFalse()
            ->and(Gate::forUser(User::factory()->owner()->create())->allows($ability, $target))->toBeFalse()
            ->and(Gate::forUser(User::factory()->technician()->create())->allows($ability, $target))->toBeFalse();
    }
});

it('nie ma żadnej trasy wydającej archiwum fachowcom', function () {
    $uris = collect(Route::getRoutes()->getRoutes())->map(fn ($route) => $route->uri())->implode("\n");

    expect($uris)->not->toContain('archiwum');
});

it('model archiwum ma jawne fillable', function () {
    $archived = new ArchivedCertificate;

    expect($archived->getFillable())->not->toBeEmpty()->not->toContain('id');
});

it('prune-orphans usuwa tylko stare pliki bez wiersza w obu katalogach', function () {
    $disk = Storage::disk('local');
    $kept = SpecialistCertificate::factory()->create();
    $keptArchive = ArchivedCertificate::factory()->create();
    $files = [
        $kept->file_path => true,
        $keptArchive->file_path => true,
        'specialist-certificates/osierocony-stary.pdf' => false,
        'specialist-certificates-archive/osierocony-stary.pdf' => false,
        'specialist-certificates/osierocony-swiezy.pdf' => true,
    ];

    foreach ($files as $path => $expectedToStay) {
        $disk->put($path, 'x');
        $age = str_contains($path, 'swiezy') ? time() : time() - 3 * 86400;
        touch($disk->path($path), $age);
    }

    $this->artisan('certificates:prune-orphans')->assertSuccessful();

    foreach ($files as $path => $expectedToStay) {
        $expectedToStay ? $disk->assertExists($path) : $disk->assertMissing($path);
    }
});
