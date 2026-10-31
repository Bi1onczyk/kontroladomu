<?php

namespace App\Domain\Specialists\Actions;

use App\Domain\Specialists\Models\SpecialistCertificate;
use App\Domain\Specialists\Models\SpecialistProfile;
use App\Domain\Specialists\Support\CertificateFileInspector;
use App\Domain\Specialists\Support\ImageReencoder;
use App\Models\User;
use App\Support\AuditLogger;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Throwable;

/**
 * Dodanie zaświadczenia o uprawnieniach (plik + metadane), ADR-019.
 *
 * Kolejność: limity liczby i rozmiaru -> niezależna kontrola pliku (MIME z zawartości, higiena PDF, limit pikseli) ->
 * obraz jest dekodowany i zapisywany ponownie jako JPEG (bez EXIF/GPS), PDF zapisywany bez zmian -> sprawdzenie
 * duplikatu po SHA-256 -> zapis pliku pod losową nazwą na dysku `local` (poza `public/`) -> zapis wiersza w transakcji.
 * Gdy zapis wiersza się nie uda, plik jest usuwany. Oryginalna nazwa pliku służy tylko do wyświetlania właścicielowi.
 */
class UploadSpecialistCertificate
{
    public function __construct(
        private readonly CertificateFileInspector $inspector,
        private readonly ImageReencoder $reencoder,
    ) {}

    /**
     * @param  array{type: string, title: string, number: string, issuer?: string|null, valid_until?: string|null}  $data  Metadane po walidacji.
     *
     * @throws ValidationException Gdy plik lub limity nie spełniają wymagań (komunikat przypisany do pola `file`).
     */
    public function handle(SpecialistProfile $profile, User $actor, UploadedFile $file, array $data): SpecialistCertificate
    {
        $this->assertLimits($profile);

        $mime = $this->inspector->inspect($file);
        $path = (string) $file->getRealPath();

        if ($mime === 'application/pdf') {
            $contents = (string) file_get_contents($path);
        } else {
            $contents = $this->reencoder->toJpeg($path, $mime);
            $mime = 'image/jpeg';
        }

        $hash = hash('sha256', $contents);
        $size = strlen($contents);

        $this->assertNewFile($profile, $hash, $size);

        $extension = SpecialistCertificate::EXTENSIONS[$mime];
        $storedPath = 'specialist-certificates/'.Str::lower(Str::random(40)).'.'.$extension;
        $disk = Storage::disk('local');

        if (! $disk->put($storedPath, $contents)) {
            throw ValidationException::withMessages(['file' => __('certificates.errors.server_limit')]);
        }

        try {
            return DB::transaction(function () use ($profile, $actor, $file, $data, $mime, $storedPath, $hash, $size): SpecialistCertificate {
                // Blokada profilu: dwa równoległe wysłania nie przekroczą limitu liczby.
                $locked = SpecialistProfile::query()->lockForUpdate()->findOrFail($profile->id);
                $this->assertLimits($locked, $size);

                $certificate = new SpecialistCertificate;
                $certificate->fill([
                    'type' => $data['type'],
                    'title' => trim($data['title']),
                    'number' => trim($data['number']),
                    'issuer' => filled($data['issuer'] ?? null) ? trim((string) $data['issuer']) : null,
                    'valid_until' => $data['valid_until'] ?? null,
                ]);
                $certificate->forceFill([
                    'specialist_profile_id' => $locked->id,
                    'file_path' => $storedPath,
                    'file_original_name' => $this->safeOriginalName($file),
                    'file_mime' => $mime,
                    'file_size' => $size,
                    'file_sha256' => $hash,
                ])->save();

                // Bez numeru dokumentu i nazwy pliku; zapisujemy fakt złożenia oświadczenia o prawie do dokumentu.
                AuditLogger::record('certificate_added', $actor, $certificate, [
                    'ulid' => $certificate->ulid,
                    'type' => $certificate->type->value,
                    'declaration_accepted' => true,
                ], AuditLogger::LOG_SPECIALIST);

                return $certificate;
            });
        } catch (Throwable $e) {
            $disk->delete($storedPath);

            throw $e;
        }
    }

    /**
     * Limity: liczba zaświadczeń i łączny rozmiar plików w profilu.
     *
     * @param  int  $incomingSize  Rozmiar nowego pliku (bajty); 0 przy samym sprawdzeniu liczby.
     *
     * @throws ValidationException
     */
    private function assertLimits(SpecialistProfile $profile, int $incomingSize = 0): void
    {
        if ($profile->certificates()->count() >= (int) config('specialists.certificate_max_count')) {
            throw ValidationException::withMessages(['file' => __('certificates.errors.limit_count')]);
        }

        if ((int) $profile->certificates()->sum('file_size') + $incomingSize > (int) config('specialists.certificate_max_total_kb') * 1024) {
            throw ValidationException::withMessages(['file' => __('certificates.errors.limit_total')]);
        }
    }

    /**
     * Ten sam plik nie może być dodany dwa razy do jednego profilu; plik po przekodowaniu nie może przekraczać limitu.
     *
     * @throws ValidationException
     */
    private function assertNewFile(SpecialistProfile $profile, string $hash, int $size): void
    {
        if ($size > (int) config('specialists.certificate_max_kb') * 1024) {
            throw ValidationException::withMessages(['file' => __('certificates.errors.file_size')]);
        }

        if ($profile->certificates()->where('file_sha256', $hash)->exists()) {
            throw ValidationException::withMessages(['file' => __('certificates.errors.file_duplicate')]);
        }

        $this->assertLimits($profile, $size);
    }

    /**
     * Nazwa oryginalna oczyszczona do wyświetlania: bez ścieżki i znaków sterujących, najwyżej 150 znaków.
     */
    private function safeOriginalName(UploadedFile $file): string
    {
        $name = basename(str_replace('\\', '/', (string) $file->getClientOriginalName()));
        $name = (string) preg_replace('/\p{C}+/u', '', $name);
        $name = mb_substr($name, 0, 150);

        return $name === '' ? 'dokument' : $name;
    }
}
