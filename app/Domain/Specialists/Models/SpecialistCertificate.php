<?php

namespace App\Domain\Specialists\Models;

use App\Domain\Specialists\Enums\CertificateType;
use App\Domain\Specialists\Policies\SpecialistCertificatePolicy;
use Database\Factories\SpecialistCertificateFactory;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Attributes\UsePolicy;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

/**
 * Zaświadczenie o uprawnieniach fachowca: metadane + plik na dysku `local` (ADR-019).
 *
 * W adresach występuje wyłącznie `ulid`. Pola pliku (`file_*`) i `ulid` zapisują tylko akcje przez `forceFill`,
 * dlatego nie ma ich w `$fillable`. Numer, wystawca i plik nie są nigdy publiczne.
 *
 * `updated_at` ma znaczenie biznesowe (S6): strona publiczna pokazuje zaświadczenie tylko wtedy, gdy
 * `updated_at <= verified_at` profilu.
 *
 * @property int $id
 * @property string $ulid
 * @property int $specialist_profile_id
 * @property CertificateType $type
 * @property string $title
 * @property string $number
 * @property string|null $issuer
 * @property Carbon|null $valid_until
 * @property string $file_path
 * @property string $file_original_name
 * @property string $file_mime
 * @property int $file_size
 * @property string $file_sha256
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read SpecialistProfile $profile
 */
#[UseFactory(SpecialistCertificateFactory::class)]
#[UsePolicy(SpecialistCertificatePolicy::class)]
class SpecialistCertificate extends Model
{
    /** @use HasFactory<SpecialistCertificateFactory> */
    use HasFactory;

    /** Rozszerzenia plików wg MIME (rozszerzenie nigdy nie pochodzi od klienta). */
    public const EXTENSIONS = [
        'application/pdf' => 'pdf',
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
    ];

    /** @var list<string> */
    protected $fillable = [
        'type',
        'title',
        'number',
        'issuer',
        'valid_until',
    ];

    /**
     * Przy tworzeniu nadaje `ulid`, jeśli kod go nie podał.
     */
    protected static function booted(): void
    {
        static::creating(function (self $certificate): void {
            if (blank($certificate->ulid)) {
                $certificate->ulid = self::newUlid();
            }
        });
    }

    /**
     * Nowy identyfikator ULID (małe litery, 26 znaków).
     */
    public static function newUlid(): string
    {
        return strtolower((string) Str::ulid());
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'type' => CertificateType::class,
            'valid_until' => 'date',
            'file_size' => 'integer',
        ];
    }

    /**
     * Klucz w adresach tras to ULID, nigdy numeryczne ID.
     */
    public function getRouteKeyName(): string
    {
        return 'ulid';
    }

    /**
     * Profil, do którego należy zaświadczenie.
     *
     * @return BelongsTo<SpecialistProfile, $this>
     */
    public function profile(): BelongsTo
    {
        return $this->belongsTo(SpecialistProfile::class, 'specialist_profile_id');
    }

    /**
     * Rozszerzenie pliku wynikające z zapisanego MIME.
     */
    public function fileExtension(): string
    {
        return self::EXTENSIONS[$this->file_mime] ?? 'bin';
    }

    /**
     * Liczba dni do końca ważności (ujemna po terminie); null dla zaświadczeń bezterminowych.
     */
    public function daysUntilExpiry(): ?int
    {
        if ($this->valid_until === null) {
            return null;
        }

        return (int) today()->diffInDays($this->valid_until, false);
    }
}
