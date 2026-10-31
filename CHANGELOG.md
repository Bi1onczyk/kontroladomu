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
- Baza miejscowości TERYT (zadanie 003): komenda `teryt:import` (pliki CSV z eTeryt, 3964 gminy i ok. 102 tys. miejscowości),
  endpoint autouzupełniania `GET /miejscowosci/szukaj` (bez wrażliwości na polskie znaki, limity zapytań).
- `GeocodingService` z Nominatim: limit 1 zapytanie/s, cache bez tekstu adresu, wyłącznik awarii; bez adresu kontaktowego
  (`NOMINATIM_CONTACT_EMAIL`) nie wysyła zapytań.
- Tabela `settings` z szyfrowanymi sekretami (`SettingsRepository`).
- Kopie zapasowe `spatie/laravel-backup`: baza codziennie, pliki w niedzielę, szyfrowanie AES-256, retencja 14 dni;
  strażnik produkcyjny wymaga hasła archiwum (min. 20 znaków).
- Skrypt `composer phpstan` (z limitem pamięci).
- Profil fachowca (zadanie 004): edycja w panelu (dane i opis, usługi i cennik w groszach, miejscowość startowa z TERYT, promień
  dojazdu, adres profilu), publikacja i ukrywanie z listą brakujących kroków, strona publiczna `/fachowiec/{slug}`
  (telefon tylko za zgodą fachowca, bez NIP i e-maila), przekierowania 301 po zmianie adresu (limit raz na 30 dni).
- Zaświadczenia o uprawnieniach: bezpieczne wgrywanie (typ po zawartości, przekodowanie obrazów bez EXIF, kontrola PDF),
  pobieranie tylko przez właściciela i administratora (z wpisem w dzienniku), archiwum skanów po usunięciu konta.
- Geokodowanie środka miejscowości w kolejce (`GeocodeLocalityJob`), poprawki z audytu 003: skrót HMAC w cache, brak przekierowań, https.
- Strona `/jak-weryfikujemy-fachowcow` (szkic do weryfikacji przez prawnika), menu panelu fachowca.

### Znane ograniczenia (do domknięcia przed produkcją)
- Brak eksportu i usunięcia konta, wycofania zgody marketingowej oraz banera cookies (RODO, patrz `docs/RODO_REJESTR.md`).
  Turnstile ładuje się przed wyborem użytkownika.
- Teksty prawne zawierają pola `[do uzupełnienia]` i są szkicami do weryfikacji przez prawnika.
- Wymagane sprawdzenie na stagingu: adres IP widziany za Cloudflare i home.pl (`TRUSTED_PROXIES`),
  przekierowanie `www` na domenę główną, `CONTACT_EMAIL`, `QUEUE_CONNECTION=database` z cronem.
- Niskie ustalenia audytu (V2, V3, N1, N3, N4, N6–N11) opisane w `docs/zadania/002-konta-i-uwierzytelnianie.md`.
- Zadanie 003: brak adresu `BACKUP_NOTIFICATION_EMAIL` (bez niego nie ma alarmu o nieudanej kopii) i `NOMINATIM_CONTACT_EMAIL`;
  licencja danych TERYT czeka na odpowiedź GUS (szkic: `docs/GUS_ZAPYTANIE_LICENCJA.md`); kopia poza serwerem tylko ręcznie;
  nie sprawdzono prawdziwego Nominatim ani parametrów home.pl (`mysqldump`, `ext-zip`, limity). Ustalenia audytu 2, 4, 5
  (cache geokodowania, `withoutRedirecting`, geokodowanie tylko w kolejce) to warunki przed zadaniem z nieruchomościami.
- Zadanie 004: brak zgłaszania nielegalnych treści (DSA art. 16; link „Zgłoś ten profil” prowadzi do `/kontakt`); brak usuwania i eksportu
  konta fachowca, więc archiwum skanów jeszcze nie działa w praktyce, a jego okres (12 miesięcy) i zakres ma ocenić prawnik
  (audytor sugeruje 6 miesięcy, tylko profile zweryfikowane, zasłonięty PESEL); bez JavaScriptu nie da się zmienić miejscowości;
  nie sprawdzono w prawdziwej przeglądarce CSP, błędów 422/429 i obsługi klawiaturą; na home.pl sprawdzić `gd`, `fileinfo`, `exif`,
  `memory_limit` i `upload_max_filesize`/`post_max_size` (min. 6 MB).
