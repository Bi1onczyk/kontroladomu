<?php

namespace App\Domain\Specialists\Models;

use App\Domain\Specialists\Enums\CertificateType;
use App\Domain\Specialists\Enums\VerificationStatus;
use App\Domain\Specialists\Policies\ArchivedCertificatePolicy;
use Database\Factories\ArchivedCertificateFactory;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Attributes\UsePolicy;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * Zarchiwizowane zaświadczenie po usunięciu konta fachowca (ADR-021): skan + podstawowe metadane przez 12 miesięcy,
 * dostęp wyłącznie dla administratora, potem automatyczne usunięcie (`certificates:purge-archive`).
 *
 * Wiersz powstaje tylko w akcji `PurgeSpecialistData`. Brak kluczy obcych do kont i profili (są kasowane).
 *
 * @property int $id
 * @property string $ulid
 * @property int|null $former_user_id
 * @property string $holder_label
 * @property CertificateType $type
 * @property string $title
 * @property string $number
 * @property string|null $issuer
 * @property Carbon|null $valid_until
 * @property VerificationStatus $verification_status_at_archive
 * @property Carbon|null $verified_at
 * @property string $file_path
 * @property string $file_mime
 * @property int $file_size
 * @property string $file_sha256
 * @property Carbon $archived_at
 * @property Carbon $delete_after
 */
#[UseFactory(ArchivedCertificateFactory::class)]
#[UsePolicy(ArchivedCertificatePolicy::class)]
class ArchivedCertificate extends Model
{
    /** @use HasFactory<ArchivedCertificateFactory> */
    use HasFactory;

    /** @var list<string> */
    protected $fillable = [
        'ulid',
        'former_user_id',
        'holder_label',
        'type',
        'title',
        'number',
        'issuer',
        'valid_until',
        'verification_status_at_archive',
        'verified_at',
        'file_path',
        'file_mime',
        'file_size',
        'file_sha256',
        'archived_at',
        'delete_after',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'type' => CertificateType::class,
            'verification_status_at_archive' => VerificationStatus::class,
            'valid_until' => 'date',
            'verified_at' => 'datetime',
            'archived_at' => 'datetime',
            'delete_after' => 'date',
            'file_size' => 'integer',
        ];
    }

    /**
     * Klucz w adresach tras to ULID.
     */
    public function getRouteKeyName(): string
    {
        return 'ulid';
    }
}
