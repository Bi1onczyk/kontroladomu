<?php

namespace App\Domain\Accounts\Actions;

use App\Domain\Accounts\Data\RegisterAccountData;
use App\Domain\Accounts\Enums\UserRole;
use App\Domain\Accounts\Support\TakenEmailNotifier;
use App\Models\User;
use App\Support\AuditLogger;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;

/**
 * Rejestracja konta właściciela lub fachowca.
 *
 * Odpowiedź dla użytkownika jest zawsze taka sama, niezależnie od tego, czy e-mail był wolny
 * (brak ujawniania istniejących kont). Gdy e-mail jest zajęty, nic nie tworzymy, a posiadacz
 * konta dostaje wiadomość "ktoś próbował założyć konto" (max 1 na godzinę).
 * Użytkownik NIE jest logowany po rejestracji (ADR-006). Zajęty NIP nie blokuje rejestracji.
 */
class RegisterAccount
{
    public function __construct(private readonly TakenEmailNotifier $takenEmail) {}

    /**
     * Wykonuje rejestrację.
     *
     * @return bool true, gdy utworzono nowe konto; false, gdy e-mail był zajęty (informacja tylko dla testów i logów, nie dla użytkownika).
     */
    public function handle(RegisterAccountData $data): bool
    {
        $existing = User::query()->where('email', $data->email)->first();

        if ($existing !== null) {
            $this->handleTakenEmail($existing);

            return false;
        }

        try {
            $user = DB::transaction(function () use ($data): User {
                UserRole::ensureExist();

                $now = now();

                $user = User::create([
                    'name' => $data->name,
                    'email' => $data->email,
                    'password' => $data->password,
                    'nip' => $data->role === UserRole::Specialist ? $data->nip : null,
                    'company_name' => $data->role === UserRole::Specialist ? $data->companyName : null,
                    'terms_accepted_at' => $now,
                    'terms_version' => config('legal.terms_version'),
                    'privacy_accepted_at' => $now,
                    'privacy_version' => config('legal.privacy_version'),
                    'marketing_consent_at' => $data->marketingConsent ? $now : null,
                    'marketing_consent_version' => $data->marketingConsent ? config('legal.marketing_version') : null,
                ]);

                $user->assignRole($data->role->value);

                return $user;
            });
        } catch (UniqueConstraintViolationException) {
            // Wyścig: ktoś zajął adres między sprawdzeniem a zapisem. Traktujemy jak zajęty e-mail.
            $existing = User::query()->where('email', $data->email)->first();
            if ($existing !== null) {
                $this->handleTakenEmail($existing);
            }

            return false;
        }

        AuditLogger::record('registered', causer: $user, subject: $user, properties: [
            'role' => $data->role->value,
            'marketing_consent' => $data->marketingConsent,
        ]);

        // Jedna wiadomość weryfikacyjna (przez kolejkę). Zdarzenia Registered celowo nie wysyłamy:
        // jego domyślny listener wysłałby drugi, identyczny mail.
        $user->sendEmailVerificationNotification();

        return true;
    }

    /**
     * Zajęty e-mail: wiadomość do właściciela konta (z limitem) i wyrównanie czasu odpowiedzi.
     */
    private function handleTakenEmail(User $existing): void
    {
        $this->takenEmail->notify($existing);
    }
}
