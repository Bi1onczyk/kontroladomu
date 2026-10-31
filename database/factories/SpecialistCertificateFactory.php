<?php

namespace Database\Factories;

use App\Domain\Specialists\Enums\CertificateType;
use App\Domain\Specialists\Models\SpecialistCertificate;
use App\Domain\Specialists\Models\SpecialistProfile;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<SpecialistCertificate>
 */
class SpecialistCertificateFactory extends Factory
{
    protected $model = SpecialistCertificate::class;

    /**
     * Dane SYNTETYCZNE. Fabryka NIE tworzy pliku na dysku; plik zapisz osobno, gdy test go potrzebuje.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $content = Str::random(32);

        return [
            'specialist_profile_id' => SpecialistProfile::factory(),
            'type' => CertificateType::BuildingPermit,
            'title' => 'Specjalność instalacyjna w zakresie sieci elektrycznych',
            'number' => fake()->numerify('MAP/BO/####/##'),
            'issuer' => 'Wielkopolska Okręgowa Izba Inżynierów Budownictwa',
            'valid_until' => now()->addYears(3)->toDateString(),
            'file_path' => 'specialist-certificates/'.Str::lower(Str::random(40)).'.pdf',
            'file_original_name' => 'skan.pdf',
            'file_mime' => 'application/pdf',
            'file_size' => 1024,
            'file_sha256' => hash('sha256', $content),
        ];
    }
}
