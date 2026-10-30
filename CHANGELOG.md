# Historia zmian

Format: [Keep a Changelog](https://keepachangelog.com/pl/1.1.0/), wersje: SemVer.

## [Nieopublikowane]
### Dodano
- Instrukcje dla zespołu agentów (CLAUDE.md, .claude/agents/), specyfikacja i plan etapów.
- Szkielet Laravel 12, strona startowa, testy Pest, Pint, Larastan (zadanie 001).
- Konta i uwierzytelnianie (zadanie 002): rejestracja właściciela i fachowca (NIP z sumą kontrolną),
  logowanie, reset hasła, weryfikacja e-mail, zmiana hasła i e-maila, 2FA (kod z aplikacji, kody awaryjne,
  blokada po 5 błędach), role (właściciel, fachowiec, pracownik, administrator), pulpity z pustymi stanami,
  strony prawne (szkice do weryfikacji przez prawnika), strony błędów, polskie teksty w `lang/pl`.
- Bezpieczeństwo: Cloudflare Turnstile, nagłówki (CSP z nonce, HSTS), limity prób, dziennik aktywności,
  zgody RODO z datami, strażnik konfiguracji produkcyjnej, komendy `admin:create`, `user:reset-2fa`,
  `accounts:prune-unverified`, `activity:prune`.
- Pliki `.env.example` i `.env.production.example` (bez sekretów).

### Znane ograniczenia (do domknięcia przed produkcją)
- Brak eksportu i usunięcia konta, wycofania zgody marketingowej oraz banera cookies (RODO, patrz `docs/RODO_REJESTR.md`).
  Turnstile ładuje się przed wyborem użytkownika.
- Teksty prawne zawierają pola `[do uzupełnienia]` i są szkicami do weryfikacji przez prawnika.
- Wymagane sprawdzenie na stagingu: adres IP widziany za Cloudflare i home.pl (`TRUSTED_PROXIES`),
  przekierowanie `www` na domenę główną, `CONTACT_EMAIL`, `QUEUE_CONNECTION=database` z cronem.
- Niskie ustalenia audytu (V2, V3, N1, N3, N4, N6–N11) opisane w `docs/zadania/002-konta-i-uwierzytelnianie.md`.
