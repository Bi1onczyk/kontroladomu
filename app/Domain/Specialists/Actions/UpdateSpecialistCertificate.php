<?php

namespace App\Domain\Specialists\Actions;

use App\Domain\Specialists\Models\SpecialistCertificate;
use App\Models\User;
use App\Support\AuditLogger;
use Illuminate\Support\Facades\DB;

/**
 * Zmiana metadanych zaświadczenia (rodzaj, zakres, numer, wystawca, ważność). Plik zmienia się wyłącznie
 * przez usunięcie i dodanie nowego. Zmiana odświeża `updated_at`, co ma znaczenie dla strony publicznej:
 * zaświadczenie zmienione po weryfikacji nie jest pokazywane jako sprawdzone (S6).
 */
class UpdateSpecialistCertificate
{
    /**
     * @param  array{type: string, title: string, number: string, issuer?: string|null, valid_until?: string|null}  $data  Dane po walidacji.
     */
    public function handle(SpecialistCertificate $certificate, array $data, User $actor): SpecialistCertificate
    {
        return DB::transaction(function () use ($certificate, $data, $actor): SpecialistCertificate {
            $certificate->fill([
                'type' => $data['type'],
                'title' => trim($data['title']),
                'number' => trim($data['number']),
                'issuer' => filled($data['issuer'] ?? null) ? trim((string) $data['issuer']) : null,
                'valid_until' => $data['valid_until'] ?? null,
            ]);

            if ($certificate->isDirty()) {
                $certificate->save();

                // Bez numeru i zakresu: tylko ULID i nazwy zmienionych pól.
                AuditLogger::record('certificate_updated', $actor, $certificate, [
                    'ulid' => $certificate->ulid,
                    'changed' => array_values(array_diff(array_keys($certificate->getChanges()), ['updated_at'])),
                ], AuditLogger::LOG_SPECIALIST);
            }

            return $certificate;
        });
    }
}
