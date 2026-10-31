<?php

namespace Database\Factories;

use App\Domain\Specialists\Enums\CertificateType;
use App\Domain\Specialists\Enums\VerificationStatus;
use App\Domain\Specialists\Models\ArchivedCertificate;
use App\Domain\Specialists\Models\SpecialistCertificate;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<ArchivedCertificate>
 */
class ArchivedCertificateFactory extends Factory
{
    protected $model = ArchivedCertificate::class;

    /**
     * Dane SYNTETYCZNE. Fabryka NIE tworzy pliku na dysku.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'ulid' => SpecialistCertificate::newUlid(),
            'former_user_id' => null,
            'holder_label' => fake()->company(),
            'type' => CertificateType::BuildingPermit,
            'title' => 'Uprawnienia budowlane',
            'number' => fake()->numerify('MAP/BO/####/##'),
            'issuer' => null,
            'valid_until' => null,
            'verification_status_at_archive' => VerificationStatus::Pending,
            'verified_at' => null,
            'file_path' => 'specialist-certificates-archive/'.Str::lower(Str::random(40)).'.pdf',
            'file_mime' => 'application/pdf',
            'file_size' => 1024,
            'file_sha256' => hash('sha256', Str::random(32)),
            'archived_at' => now(),
            'delete_after' => now()->addMonths(12)->toDateString(),
        ];
    }

    /** Wpis po terminie usunięcia. */
    public function overdue(int $days = 1): static
    {
        return $this->state(fn (): array => ['delete_after' => now()->subDays($days)->toDateString()]);
    }
}
