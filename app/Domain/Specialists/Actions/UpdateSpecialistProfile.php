<?php

namespace App\Domain\Specialists\Actions;

use App\Domain\Specialists\Models\SpecialistProfile;
use App\Domain\Specialists\Support\PhoneNumber;
use App\Models\User;
use App\Support\AuditLogger;
use Illuminate\Support\Facades\DB;

/**
 * Zapis sekcji "Dane firmy i opis": nazwa publiczna, rodzaj działalności, opis, telefon i zgoda na jego publikację.
 *
 * Telefon zapisujemy w formacie +48XXXXXXXXX. Zgoda na telefon publiczny (art. 7 ust. 1 RODO) ma dowód w
 * `phone_public_consent_at`: ustawiany przy zmianie false -> true, zerowany przy wycofaniu (działa od razu).
 * Opis jest czystym tekstem: nie usuwamy z niego znaków `<` i `>` (widok escapuje wszystko), usuwamy tylko
 * znaki sterujące. Pola stanu (slug, publikacja, weryfikacja) nie są tu zmieniane.
 */
class UpdateSpecialistProfile
{
    /**
     * @param  array{display_name: string, account_type: string, description?: string|null, phone?: string|null, phone_is_public?: bool}  $data  Dane po walidacji.
     */
    public function handle(SpecialistProfile $profile, array $data, User $actor): SpecialistProfile
    {
        return DB::transaction(function () use ($profile, $data, $actor): SpecialistProfile {
            $phone = filled($data['phone'] ?? null) ? PhoneNumber::normalize((string) $data['phone']) : null;
            $wantsPublic = (bool) ($data['phone_is_public'] ?? false) && $phone !== null;
            $wasPublic = $profile->phone_is_public;

            // Zgoda dotyczyła poprzedniego numeru: po zmianie numeru wygasa i trzeba ją udzielić ponownie
            // (audyt 004, art. 7 RODO). Zaznaczone pole w tym samym formularzu nie jest ponowną zgodą na nowy numer.
            if ($wasPublic && $phone !== $profile->phone) {
                $wantsPublic = false;
            }

            $profile->fill([
                'display_name' => trim((string) $data['display_name']),
                'account_type' => $data['account_type'],
                'description' => $this->cleanDescription($data['description'] ?? null),
                'phone' => $phone,
                'phone_is_public' => $wantsPublic,
            ]);

            if ($wantsPublic && ! $wasPublic) {
                $profile->phone_public_consent_at = now();
            } elseif (! $wantsPublic) {
                $profile->phone_public_consent_at = null;
            }

            $profile->save();

            // Bez wartości telefonu, opisu i nazwy: tylko informacja, co zmieniono i stan zgody.
            AuditLogger::record('profile_updated', $actor, $profile, [
                'changed' => array_keys($profile->getChanges()),
                'phone_public' => $profile->phone_is_public,
            ], AuditLogger::LOG_SPECIALIST);

            return $profile;
        });
    }

    /**
     * Czysty tekst: ujednolicone znaki nowej linii, bez znaków sterujących, bez nadmiarowych pustych linii na końcach.
     */
    private function cleanDescription(?string $description): ?string
    {
        if ($description === null) {
            return null;
        }

        $text = str_replace(["\r\n", "\r"], "\n", $description);
        $text = (string) preg_replace('/[^\P{C}\n]+/u', '', $text);
        $text = trim($text);

        return $text === '' ? null : $text;
    }
}
