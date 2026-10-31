<?php

/*
| Obszar działania i dojazd oraz geokodowanie punktu startowego w kolejce (ADR-020):
| zero ruchu sieciowego w żądaniu webowym, GeocodeLocalityJob (unikalny), komenda ponowień, limit ponowień.
*/

use App\Domain\Geo\Models\TerytLocality;
use App\Domain\Specialists\Enums\LocationStatus;
use App\Domain\Specialists\Jobs\GeocodeLocalityJob;
use App\Domain\Specialists\Models\SpecialistProfile;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;

/**
 * Poprawne dane formularza obszaru.
 *
 * @param  array<string, mixed>  $override
 * @return array<string, mixed>
 */
function areaPayload(string $simc, array $override = []): array
{
    return array_merge([
        'locality_simc' => $simc,
        'service_radius_km' => '30',
        'travel_rate_per_km' => '1,50',
        'travel_free_km' => '10',
    ], $override);
}

beforeEach(function () {
    Cache::flush();
});

it('miejscowość ze znanymi współrzędnymi: punkt startowy od razu, bez kolejki i bez sieci', function () {
    Queue::fake();
    Http::fake();
    $locality = TerytLocality::factory()->withCoordinates(52.3, 16.6)->create();
    $profile = specialistProfile();

    $this->actingAs($profile->user)->put(route('specialist.profile.area'), areaPayload($locality->simc))
        ->assertRedirect(route('specialist.profile.edit').'#sekcja-obszar');

    $profile->refresh();
    expect($profile->locality_simc)->toBe($locality->simc)
        ->and($profile->location_status)->toBe(LocationStatus::Resolved)
        ->and((float) $profile->start_lat)->toBe(52.3)
        ->and($profile->start_source)->toBe('locality_center')
        ->and($profile->service_radius_km)->toBe(30)
        ->and($profile->travel_rate_per_km_grosze)->toBe(150)
        ->and($profile->travel_free_km)->toBe(10);

    Queue::assertNothingPushed();
    Http::assertNothingSent();
});

it('miejscowość bez współrzędnych: stan pending i zadanie w kolejce, bez ruchu sieciowego w żądaniu', function () {
    Queue::fake();
    Http::fake();
    $locality = TerytLocality::factory()->create();
    $profile = specialistProfile();

    $this->actingAs($profile->user)->put(route('specialist.profile.area'), areaPayload($locality->simc))->assertSessionHasNoErrors();

    $profile->refresh();
    expect($profile->location_status)->toBe(LocationStatus::Pending)
        ->and($profile->start_lat)->toBeNull();

    Queue::assertPushed(GeocodeLocalityJob::class, 1);
    Queue::assertPushed(GeocodeLocalityJob::class, fn (GeocodeLocalityJob $job) => $job->simc === $locality->simc);
    Http::assertNothingSent();
});

it('zmiana miejscowości zeruje stary punkt startowy; zmiana samego promienia go nie rusza', function () {
    Queue::fake();
    $old = TerytLocality::factory()->withCoordinates(52.3, 16.6)->create();
    $new = TerytLocality::factory()->create();
    $profile = specialistProfile();

    $this->actingAs($profile->user)->put(route('specialist.profile.area'), areaPayload($old->simc))->assertSessionHasNoErrors();
    $this->actingAs($profile->user)->put(route('specialist.profile.area'), areaPayload($old->simc, ['service_radius_km' => '60']))->assertSessionHasNoErrors();

    $profile->refresh();
    expect($profile->location_status)->toBe(LocationStatus::Resolved)
        ->and((float) $profile->start_lat)->toBe(52.3)
        ->and($profile->service_radius_km)->toBe(60);

    $this->actingAs($profile->user)->put(route('specialist.profile.area'), areaPayload($new->simc))->assertSessionHasNoErrors();

    $profile->refresh();
    expect($profile->location_status)->toBe(LocationStatus::Pending)
        ->and($profile->start_lat)->toBeNull()
        ->and($profile->start_source)->toBeNull();
});

it('przy stawce 0 kilometry bezpłatne są zerowane', function () {
    Queue::fake();
    $locality = TerytLocality::factory()->withCoordinates()->create();
    $profile = specialistProfile();

    $this->actingAs($profile->user)->put(route('specialist.profile.area'), areaPayload($locality->simc, ['travel_rate_per_km' => '0', 'travel_free_km' => '15']))
        ->assertSessionHasNoErrors();

    $profile->refresh();
    expect($profile->travel_rate_per_km_grosze)->toBe(0)->and($profile->travel_free_km)->toBe(0);
});

it('puste pola dojazdu oznaczają zero', function () {
    Queue::fake();
    $locality = TerytLocality::factory()->withCoordinates()->create();
    $profile = specialistProfile();

    $this->actingAs($profile->user)->put(route('specialist.profile.area'), areaPayload($locality->simc, ['travel_rate_per_km' => '', 'travel_free_km' => '']))
        ->assertSessionHasNoErrors();

    expect($profile->refresh()->travel_rate_per_km_grosze)->toBe(0);
});

it('waliduje obszar: miejscowość, promień, stawka i km gratis', function (array $override, string $errorKey) {
    Queue::fake();
    $locality = TerytLocality::factory()->withCoordinates()->create();
    $profile = specialistProfile();

    $this->actingAs($profile->user)->put(route('specialist.profile.area'), areaPayload($locality->simc, $override))
        ->assertSessionHasErrors($errorKey);

    expect($profile->refresh()->locality_simc)->toBeNull();
})->with([
    'brak miejscowości' => [['locality_simc' => ''], 'locality_simc'],
    'nieistniejąca miejscowość' => [['locality_simc' => '0000000'], 'locality_simc'],
    'promień za mały' => [['service_radius_km' => '4'], 'service_radius_km'],
    'promień za duży' => [['service_radius_km' => '151'], 'service_radius_km'],
    'promień nie liczba' => [['service_radius_km' => 'abc'], 'service_radius_km'],
    'km gratis większe niż promień' => [['service_radius_km' => '20', 'travel_free_km' => '21'], 'travel_free_km'],
    'ujemne km gratis' => [['travel_free_km' => '-1'], 'travel_free_km'],
    'stawka za duża' => [['travel_rate_per_km' => '50,01'], 'travel_rate_per_km'],
    'stawka tekstowa' => [['travel_rate_per_km' => 'drogo'], 'travel_rate_per_km'],
]);

it('odrzuca miejscowość wyłączoną (is_active = false)', function () {
    $locality = TerytLocality::factory()->create(['is_active' => false]);
    $profile = specialistProfile();

    $this->actingAs($profile->user)->put(route('specialist.profile.area'), areaPayload($locality->simc))->assertSessionHasErrors('locality_simc');
});

it('zadanie geokodowania jest unikalne po kodzie SIMC i ma opóźnienia między próbami', function () {
    $job = new GeocodeLocalityJob('0900001');

    expect($job)->toBeInstanceOf(ShouldBeUnique::class)
        ->and($job->uniqueId())->toBe('0900001')
        ->and($job->uniqueFor)->toBe(3600)
        ->and($job->tries)->toBe(3)
        ->and($job->backoff())->toBe([120, 600, 1800]);
});

it('zadanie bez skonfigurowanego dostawcy kończy się cicho, a profil zostaje pending', function () {
    Http::fake();
    config(['geocoding.contact' => '', 'geocoding.base_url' => 'https://nominatim.openstreetmap.org']);
    $locality = TerytLocality::factory()->create();
    $profile = specialistProfile(['locality_simc' => $locality->simc]);

    GeocodeLocalityJob::dispatchSync($locality->simc);

    expect($profile->refresh()->location_status)->toBe(LocationStatus::Pending);
    Http::assertNothingSent();
});

it('zadanie ustala pozycję i uzupełnia wszystkie profile z tą miejscowością', function () {
    config(['geocoding.contact' => 'kontakt@example.pl']);
    Http::fake(['*' => Http::response([['lat' => '52.41', 'lon' => '16.93', 'addresstype' => 'village']], 200)]);
    $locality = TerytLocality::factory()->create();
    $first = specialistProfile(['locality_simc' => $locality->simc]);
    $second = specialistProfile(['locality_simc' => $locality->simc, 'location_status' => LocationStatus::Failed]);
    $elsewhere = specialistProfile();

    GeocodeLocalityJob::dispatchSync($locality->simc);

    expect($first->refresh()->location_status)->toBe(LocationStatus::Resolved)
        ->and((float) $first->start_lat)->toBe(52.41)
        ->and($second->refresh()->location_status)->toBe(LocationStatus::Resolved)
        ->and($elsewhere->refresh()->location_status)->toBe(LocationStatus::Pending)
        ->and($locality->refresh()->coordinates_source)->toBe('nominatim');
});

it('gdy nie znaleziono pozycji, zadanie zgłasza wyjątek, a po wyczerpaniu prób profile są failed', function () {
    config(['geocoding.contact' => 'kontakt@example.pl']);
    Http::fake(['*' => Http::response([], 200)]);
    $locality = TerytLocality::factory()->create();
    $profile = specialistProfile(['locality_simc' => $locality->simc]);
    $job = new GeocodeLocalityJob($locality->simc);

    expect(fn () => app()->call([$job, 'handle']))->toThrow(RuntimeException::class);
    expect($profile->refresh()->location_status)->toBe(LocationStatus::Pending);

    $job->failed(new RuntimeException('test'));
    expect($profile->refresh()->location_status)->toBe(LocationStatus::Failed);
});

it('ponowienie ręczne: failed wraca do pending i zleca zadanie; limit 3 na godzinę', function () {
    Queue::fake();
    $locality = TerytLocality::factory()->create();
    $profile = specialistProfile(['locality_simc' => $locality->simc, 'location_status' => LocationStatus::Failed]);

    $this->actingAs($profile->user)->post(route('specialist.profile.area.retry'))
        ->assertRedirect(route('specialist.profile.edit').'#sekcja-obszar')
        ->assertSessionHas('status', __('specialist.area.status.retry_done'));

    expect($profile->refresh()->location_status)->toBe(LocationStatus::Pending);
    Queue::assertPushed(GeocodeLocalityJob::class, 1);

    $this->actingAs($profile->user)->post(route('specialist.profile.area.retry'))->assertRedirect();
    $this->actingAs($profile->user)->post(route('specialist.profile.area.retry'))->assertRedirect();
    $this->actingAs($profile->user)->post(route('specialist.profile.area.retry'))->assertStatus(429);
});

it('komenda ponowień nic nie robi bez skonfigurowanego dostawcy', function () {
    Queue::fake();
    config(['geocoding.contact' => '']);
    $locality = TerytLocality::factory()->create();
    specialistProfile(['locality_simc' => $locality->simc]);
    SpecialistProfile::query()->update(['updated_at' => now()->subDay()]);

    $this->artisan('specialists:retry-geocoding')->assertSuccessful();

    Queue::assertNothingPushed();
});

it('komenda ponowień zleca zadania dla starych profili pending/failed, po jednym na miejscowość i w limicie partii', function () {
    Queue::fake();
    config(['geocoding.contact' => 'kontakt@example.pl', 'specialists.geocoding_retry_batch' => 2]);
    $shared = TerytLocality::factory()->create();
    $second = TerytLocality::factory()->create();
    $third = TerytLocality::factory()->create();
    specialistProfile(['locality_simc' => $shared->simc]);
    specialistProfile(['locality_simc' => $shared->simc, 'location_status' => LocationStatus::Failed]);
    specialistProfile(['locality_simc' => $second->simc]);
    specialistProfile(['locality_simc' => $third->simc]);
    $fresh = TerytLocality::factory()->create();
    $young = specialistProfile(['locality_simc' => $fresh->simc]);
    SpecialistProfile::query()->where('id', '!=', $young->id)->update(['updated_at' => now()->subHours(2)]);

    $this->artisan('specialists:retry-geocoding')->assertSuccessful();

    // Limit partii 2: tylko dwie różne miejscowości, nigdy profil młodszy niż godzina.
    Queue::assertPushed(GeocodeLocalityJob::class, 2);
    Queue::assertNotPushed(GeocodeLocalityJob::class, fn (GeocodeLocalityJob $job) => $job->simc === $fresh->simc);
});

it('komenda ponowień pomija profile już ustalone', function () {
    Queue::fake();
    config(['geocoding.contact' => 'kontakt@example.pl']);
    $locality = TerytLocality::factory()->withCoordinates()->create();
    specialistProfile(['locality_simc' => $locality->simc, 'location_status' => LocationStatus::Resolved]);
    SpecialistProfile::query()->update(['updated_at' => now()->subDay()]);

    $this->artisan('specialists:retry-geocoding')->assertSuccessful();

    Queue::assertNothingPushed();
});

it('harmonogram zawiera komendy profilu', function () {
    $commands = collect(app(Schedule::class)->events())->map(fn ($event) => $event->command)->implode("\n");

    expect($commands)->toContain('specialists:retry-geocoding')
        ->toContain('certificates:purge-archive')
        ->toContain('certificates:prune-orphans');
});
