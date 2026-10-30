<?php

namespace App\Domain\Accounts\Support;

use App\Support\EmailNormalizer;
use Closure;
use Illuminate\Support\Facades\RateLimiter;

/**
 * Jedno miejsce limitujące maile kont per ADRES ODBIORCY (także adresy bez konta).
 *
 * Chroni przed mail-bombingiem cudzej skrzynki (np. przez zmianę adresu na adres ofiary z wielu kont).
 * Po przekroczeniu limitu wysyłka jest po cichu pomijana: użytkownik zawsze dostaje tę samą odpowiedź.
 */
class RecipientMailLimiter
{
    /** Domyślny limit maili jednego rodzaju do jednego adresu na godzinę. */
    public const PER_HOUR = 3;

    /**
     * Wykonuje wysyłkę, o ile limit dla adresu i rodzaju wiadomości nie został wyczerpany.
     *
     * @param  string  $email  Adres odbiorcy.
     * @param  string  $kind  Rodzaj wiadomości (`verify`, `reset`, `attempt`): każdy ma osobny licznik.
     * @param  Closure  $send  Funkcja wysyłająca wiadomość.
     * @param  int  $max  Limit na godzinę.
     * @return bool true, gdy wiadomość wysłano (lub zakolejkowano); false, gdy pominięto z powodu limitu.
     */
    public function send(string $email, string $kind, Closure $send, int $max = self::PER_HOUR): bool
    {
        $sent = RateLimiter::attempt(
            'recipient-mail:'.$kind.':'.sha1(EmailNormalizer::canonical($email)),
            $max,
            function () use ($send): bool {
                $send();

                return true;
            },
            3600,
        );

        return $sent === true;
    }
}
