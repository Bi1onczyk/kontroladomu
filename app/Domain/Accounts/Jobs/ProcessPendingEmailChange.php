<?php

namespace App\Domain\Accounts\Jobs;

use App\Domain\Accounts\Notifications\VerifyPendingEmailNotification;
use App\Domain\Accounts\Support\RecipientMailLimiter;
use App\Domain\Accounts\Support\TakenEmailNotifier;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Notification;

/**
 * Obsługa prośby o zmianę adresu e-mail W KOLEJCE (cała "ciężka" i rozróżniająca praca poza żądaniem HTTP).
 *
 * Dopiero tutaj sprawdzamy, czy nowy adres ma konto:
 * - wolny adres: link potwierdzający na NOWY adres (limit maili na adres odbiorcy),
 * - zajęty adres: wiadomość "ktoś próbował użyć Twojego adresu" do właściciela konta (1/h).
 * Żądanie HTTP robi w obu przypadkach to samo (zapis `pending_email` i zakolejkowanie), więc czas
 * i treść odpowiedzi nie zdradzają, czy adres ma konto.
 */
class ProcessPendingEmailChange implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable;

    /**
     * @param  int  $userId  Konto z ustawionym `pending_email`.
     */
    public function __construct(public readonly int $userId) {}

    /**
     * Wykonuje rozróżnienie wolny/zajęty i wysyła odpowiednią wiadomość.
     */
    public function handle(RecipientMailLimiter $limiter, TakenEmailNotifier $takenEmail): void
    {
        $user = User::query()->find($this->userId);

        if ($user === null || $user->pending_email === null || $user->hasVerifiedEmail()) {
            return;
        }

        $pending = $user->pending_email;
        $holder = User::query()->where('email', $pending)->whereKeyNot($user->getKey())->first();

        if ($holder !== null) {
            $takenEmail->notify($holder);

            return;
        }

        $limiter->send(
            $pending,
            'verify',
            fn () => Notification::route('mail', $pending)->notify(new VerifyPendingEmailNotification($user->getKey(), $pending)),
        );
    }
}
