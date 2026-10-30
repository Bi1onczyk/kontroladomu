<?php

namespace App\Support\Security;

/**
 * Sprawdza, czy konfiguracja produkcyjna nie zawiera wartości "deweloperskich", które po cichu
 * osłabiają zabezpieczenia (np. klucze testowe Turnstile, kolejka sync, mailer log, APP_DEBUG).
 *
 * Aplikacja na produkcji nie startuje, gdy lista naruszeń nie jest pusta (AppServiceProvider).
 */
class ProductionConfigGuard
{
    /** Prefiksy kluczy TESTOWYCH Cloudflare Turnstile (zawsze przechodzą lub zawsze odrzucają). */
    private const TURNSTILE_TEST_PREFIXES = ['1x0000', '2x0000', '3x0000'];

    /** Minimalna długość hasła szyfrującego archiwa kopii zapasowych. */
    private const MIN_BACKUP_PASSWORD_LENGTH = 20;

    /**
     * Lista naruszeń (po polsku, do komunikatu wyjątku). Pusta = konfiguracja poprawna.
     *
     * @return list<string>
     */
    public function violations(): array
    {
        $violations = [];

        if (config('app.debug')) {
            $violations[] = 'APP_DEBUG musi być false.';
        }

        if (! config('services.turnstile.enabled')) {
            $violations[] = 'TURNSTILE_ENABLED nie może być wyłączone.';
        }

        foreach (['site_key' => 'TURNSTILE_SITE_KEY', 'secret' => 'TURNSTILE_SECRET_KEY'] as $key => $env) {
            $value = (string) config('services.turnstile.'.$key);

            if ($value === '' || $this->isTurnstileTestKey($value)) {
                $violations[] = "{$env} jest pusty albo to klucz testowy Cloudflare (ochrona pozorna).";
            }
        }

        if (config('queue.default') === 'sync') {
            $violations[] = 'QUEUE_CONNECTION=sync jest niedozwolone (maile mają iść przez kolejkę, używaj database).';
        }

        if (in_array(config('mail.default'), ['log', 'array'], true)) {
            $violations[] = 'MAIL_MAILER=log/array jest niedozwolone (linki resetu hasła trafiałyby do logów).';
        }

        if (config('session.secure') !== true) {
            $violations[] = 'SESSION_SECURE_COOKIE musi być true.';
        }

        $host = parse_url((string) config('app.url'), PHP_URL_HOST);
        if (! str_starts_with((string) config('app.url'), 'https://') || ! is_string($host) || $host === '') {
            $violations[] = 'APP_URL musi zaczynać się od https:// i zawierać nazwę hosta.';
        }

        $proxies = trim((string) config('security.trusted_proxies'));
        if ($proxies === '' || $proxies === '*') {
            $violations[] = 'TRUSTED_PROXIES musi zawierać zakresy IP Cloudflare (nie może być puste ani "*").';
        }

        if (! config('auth.check_pwned')) {
            $violations[] = 'AUTH_CHECK_PWNED nie może być false.';
        }

        // Kopia plików zawiera .env (APP_KEY): bez szyfrowania archiwum jej wyciek ujawniłby sekrety (ADR-014).
        // Adresy e-mail alarmów o kopiach (BACKUP_NOTIFICATION_EMAIL) i kontaktu dla Nominatim (NOMINATIM_CONTACT_EMAIL)
        // NIE są wymagane: właściciel jeszcze ich nie ustalił. Brak = brak alarmów mailowych / brak zapytań do Nominatim.
        if (strlen((string) config('backup.backup.password')) < self::MIN_BACKUP_PASSWORD_LENGTH) {
            $violations[] = 'BACKUP_ARCHIVE_PASSWORD musi mieć co najmniej '.self::MIN_BACKUP_PASSWORD_LENGTH.' znaków (archiwa kopii są szyfrowane).';
        }

        return $violations;
    }

    private function isTurnstileTestKey(string $value): bool
    {
        foreach (self::TURNSTILE_TEST_PREFIXES as $prefix) {
            if (str_starts_with($value, $prefix)) {
                return true;
            }
        }

        return false;
    }
}
