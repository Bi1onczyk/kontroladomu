# Rejestr decyzji architektonicznych (ADR)

Każda istotna decyzja techniczna: dlaczego tak, a nie inaczej. Najnowsze na dole.

## ADR-001: Laravel 12 + Blade/Livewire zamiast osobnego SPA
- **Kontekst:** właściciel zna podstawy PHP; hosting współdzielony home.pl; planowana PWA.
- **Decyzja:** monolit Laravel z Blade, Livewire 3 i Alpine.js. Logika w klasach Actions, żeby
  w przyszłości wystawić API bez przepisywania.
- **Alternatywy:** Laravel API + Vue/React (więcej kodu, dwa projekty, trudniejsze utrzymanie).
- **Konsekwencje:** prostsze wdrożenie i utrzymanie; interaktywność ograniczona do możliwości Livewire.
- **Data:** 2026-10-29

## ADR-002: OpenStreetMap + Leaflet + Nominatim z cache
- **Kontekst:** darmowe mapy, obsługa wsi.
- **Decyzja:** kafelki OSM, geokodowanie Nominatim (limit 1 zapytanie/s, cache w bazie),
  baza miejscowości TERYT do autouzupełniania. Trasa dnia — link do Map Google bez klucza API.
- **Alternatywy:** Google Maps API (płatne powyżej limitu), Mapbox (płatne).
- **Konsekwencje:** przy dużym ruchu może być potrzebny własny serwer geokodowania lub płatny dostawca.
- **Data:** 2026-10-29

## ADR-003: Kolejki na bazie danych + cron
- **Kontekst:** home.pl nie pozwala na stałe procesy ani Redis.
- **Decyzja:** `QUEUE_CONNECTION=database`, cron co minutę uruchamia `schedule:run`,
  który odpala `queue:work --stop-when-empty`.
- **Konsekwencje:** maile mogą wychodzić z opóźnieniem do ~1 minuty — akceptowalne.
- **Data:** 2026-10-29

## ADR-004: PHP 8.3 obok XAMPP bez zmiany jego PHP
- **Kontekst:** XAMPP ma PHP 8.2, projekt wymaga PHP 8.3, a właściciel zatwierdził doinstalowanie
  PHP 8.3 bez modyfikowania istniejącej instalacji XAMPP.
- **Decyzja:** uruchamiać lokalny Laravel przez PHP 8.3 CLI (`php artisan serve`) i używać XAMPP
  wyłącznie jako lokalnego serwera MySQL/MariaDB. Wybór `php.exe` dla Composer i Artisan ma być
  jawny; nie podmieniać modułu PHP Apache ani plików XAMPP.
- **Alternatywy:** przełączyć Apache XAMPP na moduł PHP 8.3 (narusza ograniczenie niemodyfikowania
  XAMPP) albo testować na PHP 8.2 (niezgodne z docelowym środowiskiem projektu).
- **Konsekwencje:** lokalny adres deweloperski będzie adresem i portem serwera Artisan, a nie
  `kontroladomu.test` na Apache XAMPP; przed wdrożeniem trzeba potwierdzić PHP 8.3 i rozszerzenia
  na home.pl. Decyzja dotyczy środowiska developerskiego, nie produkcji.
- **Data:** 2026-10-29

## ADR-005: Paczki dla kont i uwierzytelniania (zadanie 002)
- **Kontekst:** potrzebne logowanie, reset hasła, 2FA TOTP, role i dziennik aktywności. Stos z CLAUDE.md §3
  wskazuje Fortify, spatie/laravel-permission i spatie/laravel-activitylog. Produkcja i lokalnie: PHP 8.3.
  Wersje sprawdzone w Packagist 29.10.2026.
- **Decyzja:** dodać do `composer.json` (instalacja: backend, przez `composer83.cmd`):

  | Paczka | Wersja (constraint) | Najnowsze wydanie | Licencja | Wymagania | Uzasadnienie |
  |---|---|---|---|---|---|
  | `laravel/fortify` | `^1.41` | 1.41.0 (06.10.2026) | MIT | PHP ^8.2, Laravel 11-13 | Backend logowania/2FA/resetu bez widoków narzuconych przez paczkę; oficjalna paczka Laravela; QR i TOTP dostarcza przez zależności (`bacon/qr-code`, `pragmarx/google2fa`, obie permisywne licencje). |
  | `spatie/laravel-permission` | `^8.3` | 8.3.2 (19.10.2026) | MIT | PHP ^8.3, Laravel 12-13 | Role `owner/specialist/technician/admin` z enuma; gotowe `hasRole`, cache uprawnień. Własna tabela ról byłaby powielaniem. |
  | `spatie/laravel-activitylog` | `^4.12` (NIE 5.x) | 4.12.3 (data wydania: potwierdzić `composer show` po instalacji) | MIT | PHP ^8.1, Laravel 8-13 | Dziennik aktywności wymagany w CLAUDE.md §8. Linia 5.x (5.1.3 z 19.10.2026) wymaga PHP ^8.4, a projekt jest na PHP 8.3 (ADR-004, home.pl), więc zostajemy na 4.x. |
  | `livewire/livewire` | `^3.8` (3.8.11 z 21.10.2026, MIT) | — | MIT | Laravel 10-13 | Zostaje w stosie, ale NIE jest instalowany w zadaniu 002 (patrz ADR-007). Nie przechodzić na 4.x bez nowego ADR. |
  | `tailwindcss` / `@tailwindcss/vite` | `^4.0.0` (już w `package.json`) | — | MIT | — | Bez zmian. |
  | `@alpinejs/csp` (npm) | `^3` (dokładną wersję zapisać po `npm view`) | — | MIT | — | Alpine w wersji zgodnej z CSP (bez `eval`); patrz ADR-007. Jedyna nowa paczka npm. |

  Bez nowych paczek: Turnstile (klient HTTP Laravela, ADR-008), walidacja NIP (własna klasa), kody QR (z Fortify).

  **Korekta po instalacji (backend, 29.10.2026, `composer show`):** zainstalowano `laravel/fortify` v1.41.0 (wydana **28.09.2026**, nie 06.10),
  `spatie/laravel-permission` 8.3.2 (19.10.2026), `spatie/laravel-activitylog` 4.12.3 (**24.03.2026**; PHP ^8.1, działa na 8.3). Fortify 1.41 ma nową,
  **obowiązkową zależność `laravel/passkeys` ^0.2 (MIT, v0.2.1 z 18.05.2026)**, która ciągnie `web-auth/webauthn-lib`. Nie używamy kluczy dostępu (passkeys):
  funkcja jest wyłączona w `config/fortify.php`, a opublikowaną migrację `create_passkeys_table` usunięto (tabela nie jest potrzebna). Zależność tranzytywna
  zostaje do czasu, aż Fortify uczyni ją opcjonalną; do rozważenia osobno, jeśli kiedyś zechcemy logowanie kluczem dostępu.
- **Alternatywy:** Laravel Breeze/Jetstream (narzucają widoki i strukturę, trudniejsze do dopasowania do UX i polskich adresów);
  własna implementacja 2FA (ryzykowne kryptograficznie); activitylog 5.x (wymaga PHP 8.4); `owen-it/laravel-auditing` (inne API, mniejsza społeczność).
- **Konsekwencje:** aktualizacja activitylog do 5.x będzie możliwa po podniesieniu PHP na hostingu (osobny ADR);
  `spatie/laravel-permission` 8 wymaga PHP 8.3, czyli dolna granica jest zgodna z ADR-004. Fortify szyfruje sekret 2FA kluczem `APP_KEY`:
  zmiana `APP_KEY` unieważni wszystkie sekrety 2FA (opisać w rozdziale "Wdrożenie").
- **Data:** 2026-10-29

## ADR-006: Fortify bez jego tras; polskie adresy; rejestracja własna, bez auto-logowania
- **Kontekst:** UX wymaga polskich adresów (`/logowanie`, `/rejestracja`), rejestracji z dwiema rolami, neutralnej odpowiedzi
  przy zajętym e-mailu (brak ujawniania kont) oraz weryfikacji e-mail działającej bez sesji. Domyślne trasy Fortify mają angielskie adresy,
  rejestracja Fortify loguje użytkownika od razu, a reset hasła zwraca błąd "nie znaleziono użytkownika" (ujawnia konta).
- **Decyzja:** `Fortify::ignoreRoutes()`; własny `routes/auth.php` wskazuje na kontrolery Fortify (logowanie, reset, wyzwanie 2FA,
  potwierdzenie hasła) z zachowaniem standardowych nazw tras (`login`, `password.reset`, `verification.verify`, `two-factor.login` itd.).
  Rejestracja i weryfikacja e-mail to własne kontrolery + akcje (`RegisterAccount`). Rejestracja nie loguje użytkownika; zawsze pokazuje
  ten sam ekran "Sprawdź skrzynkę". Odpowiedzi Fortify na reset hasła (`FailedPasswordResetLinkRequestResponse`, throttled) zastąpione neutralnymi.
  Link weryfikacyjny jest podpisany i działa bez sesji (nie loguje).
- **Alternatywy:** domyślne trasy angielskie (prostsze, ale sprzeczne z UX i z neutralnym komunikatem); Breeze.
- **Konsekwencje:** przy aktualizacji Fortify trzeba porównać `vendor/laravel/fortify/routes/routes.php` z `routes/auth.php` (test regresji
  sprawdza nazwy tras). Maile (weryfikacja, reset, próba rejestracji) idą przez kolejkę, żeby czas odpowiedzi nie zdradzał istnienia konta.
- **Data:** 2026-10-29

## ADR-007: CSP bez 'unsafe-eval' → Alpine CSP build; Livewire odroczony
- **Kontekst:** CLAUDE.md §8 wymaga CSP. Standardowy Alpine i wbudowany w Livewire 3 Alpine używają `new Function` (wymaga `'unsafe-eval'`).
  Ekrany logowania/rejestracji/2FA to najbardziej wrażliwa część serwisu.
- **Decyzja:** w zadaniu 002 ekrany to Blade + `@alpinejs/csp` (komponenty rejestrowane w JS przez `Alpine.data()`, bez wyrażeń inline).
  Livewire 3 nie jest instalowany do czasu pierwszego ekranu, który go naprawdę potrzebuje (kalendarz/panel); wtedy osobny ADR rozstrzygnie,
  czy polityka CSP dla panelu dopuszcza `'unsafe-eval'`, czy używamy wersji Livewire obsługującej CSP. Skrypty ładowane z nonce (`Vite::useCspNonce()`);
  `style-src 'self'` (zakaz atrybutów `style=""`, tylko klasy Tailwind).
- **Alternatywy:** `'unsafe-eval'` globalnie (osłabia CSP na stronach z hasłami); Livewire od razu (jw.).
- **Konsekwencje:** komponenty UI na ekranach auth są prostsze (jeden plik JS), ale `x-data` może wskazywać tylko zarejestrowaną nazwę,
  a nie obiekt wpisany w atrybucie. Kreator 2FA to zwykłe formularze POST (każdy krok = odświeżenie strony).
- **Data:** 2026-10-29

## ADR-008: Turnstile przez serwis i middleware, bez paczki
- **Kontekst:** Turnstile ma chronić rejestrację, reset hasła (zawsze) i logowanie (po 3 nieudanych próbach). Kontrolery resetu i logowania są Fortify,
  więc reguła walidacji w Form Requeście nie wystarczy.
- **Decyzja:** `App\Services\Turnstile\TurnstileVerifier` (wywołanie `siteverify` przez `Http::asForm()`, timeout 5 s, przekazanie IP) +
  middleware `turnstile:always|after-failures` rzucający `ValidationException` na polu `cf-turnstile-response`. Brak odpowiedzi Cloudflare = odmowa
  (fail closed) i wpis do logu. Klucze w `.env` (`TURNSTILE_SITE_KEY`, `TURNSTILE_SECRET_KEY`, `TURNSTILE_ENABLED`); lokalnie klucze testowe Cloudflare;
  w testach `TURNSTILE_ENABLED=false` lub `Http::fake()`.
- **Alternatywy:** paczki trzecie (dodatkowa zależność dla 30 linii kodu); reCAPTCHA (śledzenie, wbrew CLAUDE.md §3).
- **Konsekwencje:** skrypt `api.js` ładowany z `challenges.cloudflare.com` (nie da się go hostować lokalnie; brak SRI) → wpis w polityce prywatności i
  `docs/RODO_REJESTR.md`; CSP musi zawierać domenę Cloudflare. Awaria Cloudflare blokuje rejestrację i reset (akceptowalne).
- **Data:** 2026-10-29

## ADR-009: Egzekwowanie 2FA i blokada po błędnych kodach
- **Kontekst:** 2FA obowiązkowe dla `admin` i `specialist`. Domyślnie Fortify tylko pozwala włączyć 2FA i limituje 5 żądań/min.
- **Decyzja:** (1) bramki w kolejności `auth` → `verified` → `two-factor.required` (własne middleware; ekran konfiguracji 2FA jest poza trzecią bramką);
  (2) wymóg liczony z ról (`UserRole::requiresTwoFactor()`), jeśli konto ma choć jedną taką rolę, wymóg dotyczy całego konta; (3) wyłączenie 2FA dla takich kont
  blokuje Policy po stronie serwera, nie tylko ukryty przycisk; (4) "Zapamiętaj mnie" nie działa dla kont z wymogiem 2FA (middleware wylogowuje sesję odtworzoną z ciasteczka);
  (5) własny licznik BŁĘDNYCH kodów 2FA (5 → blokada 15 min, klucz = id użytkownika, nie IP) zasilany zdarzeniami Fortify;
  (6) reset 2FA wyłącznie komendą `user:reset-2fa` z wpisem w dzienniku aktywności.
- **Alternatywy:** wymuszenie tylko w widokach (omijalne); limiter po IP (łatwo obejść zmianą IP).
- **Konsekwencje:** osoba znająca hasło może zablokować 2FA ofiary na 15 minut (akceptowalne; hasło + blokada nie daje wejścia). Zmiana `APP_KEY` = reset wszystkich 2FA.
- **Data:** 2026-10-29

## ADR-010: Dane rejestracyjne w `users`, struktura `app/Domain/Accounts`
- **Kontekst:** NIP, nazwa firmy i zgody muszą być zapisane przy rejestracji; profile fachowców powstaną później.
- **Decyzja:** kolumny `nip`, `company_name`, zgody z timestampami i wersjami tekstów w `users` (NIP indeksowany, NIE unikalny: spec §10 dopuszcza kolejne konta z tym NIP-em).
  Model `User` zostaje w `app/Models` (konwencja Laravela, `config/auth.php`), a logika w `app/Domain/Accounts/{Actions,Data,Enums,Listeners,Notifications,Policies,Rules,Support}`.
  Kontrolery/Form Requesty/middleware w standardowych katalogach `app/Http`. Wersje tekstów prawnych w `config/legal.php`.
- **Alternatywy:** tabela `user_consents` (lepszy dowód zgód i historia wycofań, ale więcej kodu; do rozważenia przy panelu "Moje dane i prywatność"); NIP w tabeli profilu fachowca (zadanie profili może go tam przenieść).
- **Konsekwencje:** wycofanie zgody marketingowej zeruje `marketing_consent_at`, a historię zachowuje dziennik aktywności. Okres próbny po NIP (przyszłe zadanie) będzie potrzebował osobnej tabeli
  trwałej po usunięciu konta (RODO: usunięcie konta nie może kasować informacji o wykorzystanym okresie bez podstawy; do uzgodnienia z prawnikiem).
- **Data:** 2026-10-29

## ADR-011: Paczki npm frontendu (Alpine CSP, czcionka lokalna)
- **Kontekst:** ADR-007 wymaga Alpine w wersji zgodnej z CSP; CLAUDE.md wymaga czcionek hostowanych lokalnie (RODO).
- **Decyzja:** `@alpinejs/csp` 3.17.5 (MIT, wydanie 20.10.2026) oraz `@fontsource-variable/source-sans-3` 5.3.1 (licencja OFL-1.1, wydanie 11.10.2026).
  Z czcionki używamy tylko podzbiorów `latin` i `latin-ext` (polskie znaki), własne `@font-face` w `resources/css/app.css`; pliki trafiają do `public/build` przez Vite.
  Axios nie jest już importowany w `app.js` (nieużywany; `resources/js/bootstrap.js` zostaje nieużywany).
- **Alternatywy:** ręczne pobranie plików woff2 (bez aktualizacji, bez śladu licencji); Alpine standardowy (wymaga `unsafe-eval`).
- **Konsekwencje:** w `x-data` wolno wskazywać tylko zarejestrowane nazwy; w metodach komponentu dane z `data-*` czytamy przez `this.$root` (nie `$el`).
- **Data:** 2026-10-29

## ADR-012: Baza miejscowości TERYT (SIMC + TERC), import z plików, wyszukiwanie po kolumnie `name_search`
- **Kontekst:** autouzupełnianie miejscowości (także wsi, z gminą i powiatem) wymaga lokalnej bazy. Źródło: rejestr TERYT GUS (pliki pełne SIMC i TERC, 101 865 miejscowości od 01.01.2026).
  SIMC nie zawiera współrzędnych ani nazw gmin/powiatów. Licencji TERYT nie udało się potwierdzić (strony eTeryt jej nie podają). Struktura plików niezweryfikowana na prawdziwym pliku.
- **Decyzja:** tabele `teryt_municipalities` (z denormalizowanymi nazwami powiatu i województwa) i `teryt_localities` (klucz biznesowy `simc` char(7), `slug`, `lat/lng` NULL, `is_active`).
  Import komendą `teryt:import` z ręcznie wgranych plików (strumieniowo, porcje 1000, `upsert`), z planem awaryjnym: import lokalny + zrzut tabel przez phpMyAdmin. Wyszukiwanie przez własną kolumnę
  `name_search` (małe litery, bez polskich znaków, bez interpunkcji), dopasowanie prefiksowe `LIKE 'q%'` po indeksie. Współrzędne uzupełniane leniwie przez `GeocodingService`. Modele w `app/Domain/Geo/Models`
  (modele domenowe w katalogach domen; `User` zostaje w `app/Models`, ADR-010). Dane TERYT nie są commitowane i nie są udostępniane jako zbiór do czasu wyjaśnienia licencji.
- **Alternatywy:** collation `utf8mb4_polish_ci`/`0900_ai_ci` (różne zachowanie MariaDB i MySQL, brak w SQLite testów); FULLTEXT (słabe dla prefiksów i polskiej fleksji); osobne tabele powiatów i województw (więcej złączeń bez korzyści);
  pobieranie TERYT przez API SOAP (wymaga konta, kruche); hurtowe współrzędne z PRG/Geoportalu (licencja i format niezweryfikowane, opcja na przyszłość); geokodowanie hurtowe w Nominatim (niezgodne z polityką OSMF).
- **Konsekwencje:** dodatkowa kolumna do utrzymania spójności z helperem `SearchText`; brak współrzędnych dla niewybieranych miejscowości; aktualizacja danych jest ręczna (kilka razy w roku). Brak nowych paczek.
- **Data:** 2026-10-30

## ADR-013: GeocodingService (Nominatim): cache skrótowy, throttle w bazie, wyłącznik awarii, bez autouzupełniania
- **Kontekst:** polityka OSMF Nominatim (sprawdzona 30.10.2026): 1 zapytanie/s dla całej aplikacji, własny User-Agent, zalecane cache, zakaz autouzupełniania po stronie klienta na publicznym API, atrybucja ODbL. Hosting bez Redisa.
- **Decyzja:** interfejs `GeocodingProvider` + `NominatimProvider` + `GeocodingService`. Autouzupełnianie wyłącznie z lokalnego TERYT. Cache w `geocode_cache`: skrót znormalizowanego adresu (od ADR-020: HMAC-SHA256 z `APP_KEY`; pseudonimizacja, nie anonimizacja) + współrzędne, bez tekstu adresu i bez `display_name` (RODO, minimalizacja);
  TTL 180 dni dla trafień, 7 dni dla „nie znaleziono”, błędy sieci nie są cache’owane. Limit 1/s przez `Cache::lock` (driver `database`, tabela `cache_locks`) i zapis czasu ostatniego zapytania. Wyłącznik `geocoding:down` (5 min po awarii, 30 min po 429/403).
  Serwis nie rzuca wyjątków do UI; wywołujący używa środka miejscowości i kolejki `GeocodeProperty`. W zapytaniu tylko adres (ulica, numer, kod, miejscowość, kraj). Bazowy URL i kontakt w `.env`.
- **Alternatywy:** geokodery zewnętrzne (płatne lub dodatkowa zależność); własny Nominatim (koszt serwera); cache pełnego adresu (utrata minimalizacji danych); throttle plikowy `flock` (gorzej przenośny niż lock w bazie);
  Google Geocoding (płatne, ograniczenia cache w licencji).
- **Konsekwencje:** przy dużym ruchu publiczny Nominatim przestanie wystarczać (ADR-002); interfejs pozwala podmienić dostawcę. Odbiorca danych (OSMF) trafia do rejestru RODO i polityki prywatności. Brak nowych paczek (klient HTTP Laravela).
- **Data:** 2026-10-30

## ADR-014: Paczka spatie/laravel-backup ^10.3 i zasady kopii
- **Kontekst:** CLAUDE.md §3, §5.6: baza codziennie, pliki co tydzień, retencja 14 dni, kopia poza serwerem. Packagist (30.10.2026): `spatie/laravel-backup` 10.3.3 z 14.09.2026, licencja MIT, `php ^8.3`, `illuminate/* ^12.40|^13.0`, `ext-zip`.
  Wydania 10.0.0–10.1.1 wymagały PHP ^8.4 (README to powtarza), od 10.2.0 znów ^8.3. Zależności: `spatie/db-dumper ^4.0`, `spatie/laravel-signal-aware-command ^2.1`, `spatie/temporary-directory ^2.3` (Spatie, licencje do potwierdzenia przez `composer show` po instalacji).
- **Decyzja:** `composer require spatie/laravel-backup:^10.3`. Archiwa szyfrowane AES-256 (`BACKUP_ARCHIVE_PASSWORD`), `.env` w kopii plików (utrata `APP_KEY` = utrata 2FA, ADR-005). Dysk `backups` lokalny poza `public/`; kopia poza serwerem jako dodatkowy dysk w `BACKUP_DISKS`
  po decyzji właściciela (SFTP: `league/flysystem-sftp-v3`, S3-zgodny: `league/flysystem-aws-s3-v3`; każdy wymaga osobnego wpisu przed instalacją). Harmonogram (UTC): baza 02:00, pliki niedziela 02:30, clean 02:50, monitor 04:00. Retencja `keep_all_backups_for_days=14`.
  Alert mailowy przy błędzie lub braku świeżej kopii.
- **Alternatywy:** `spatie/laravel-backup` ^9.4 (też działa na PHP 8.3, starsza); własna komenda `mysqldump` (brak retencji, powiadomień, szyfrowania); wyłącznie kopie panelu home.pl (poza kontrolą aplikacji, nieznana retencja);
  `ifsnop/mysqldump-php` jako plan B, gdy na hostingu brak `mysqldump`/`proc_open` (niepotwierdzone, osobny ADR przy użyciu).
- **Konsekwencje:** wymaga `mysqldump`, `proc_open` i `ext-zip` na hostingu (do weryfikacji przed wdrożeniem); długi `backup:run` w `schedule:run` w nocy zajmuje ten proces crona; hasło archiwum trzeba trzymać poza serwerem.
- **Data:** 2026-10-30

## ADR-015: Tabela `settings` z osobną kolumną na sekrety
- **Kontekst:** CLAUDE.md wymaga ustawień SMTP edytowanych przez admina w tabeli `settings` z szyfrowaniem (`encrypted` cast). Tabela nie istniała (stan po zadaniu 002).
- **Decyzja:** `settings(group, key UNIQUE, value, secret_value, updated_by)`; `secret_value` z castem `encrypted`; dostęp tylko przez `SettingsRepository` z cache całej tabeli, unieważnianym przy zapisie. Sekrety nie trafiają do dziennika aktywności.
  W zadaniu 003 używana tylko do dat stanu importu TERYT; ładowanie SMTP do `config('mail')` w ServiceProviderze to osobne zadanie (panel admina).
- **Alternatywy:** jedna kolumna `value` + flaga `is_encrypted` (cast Eloquenta nie działa warunkowo, trzeba ręcznie `Crypt`); paczka `spatie/laravel-settings` (dodatkowa zależność, nadmiarowa).
- **Konsekwencje:** zmiana `APP_KEY` unieważnia odczyt sekretów; kopia `.env` poza serwerem jest obowiązkowa.
- **Data:** 2026-10-30

## ADR-016: Decyzje właściciela z 30.10.2026 do zadania 003 i odstępstwa implementacji od projektu architekta
- **Kontekst:** po projekcie architekta (ADR-012…015) właściciel rozstrzygnął pytania otwarte, a implementacja na prawdziwych plikach GUS ujawniła kilka szczegółów.
- **Decyzja (właściciel):** (a) adresy e-mail alarmów o kopiach (`BACKUP_NOTIFICATION_EMAIL`) i kontaktu dla Nominatim (`NOMINATIM_CONTACT_EMAIL`) są jeszcze nieznane: dwie osobne zmienne, domyślnie puste.
  Pusty `BACKUP_NOTIFICATION_EMAIL` = kopie działają, alarmy mailowe wyłączone. Pusty `NOMINATIM_CONTACT_EMAIL` = serwis nie wysyła zapytań do publicznego Nominatim (cache działa; własny serwer Nominatim pod innym hostem nie wymaga kontaktu).
  `ProductionConfigGuard` NIE wymaga tych adresów (aplikacja startuje), ale wymaga `BACKUP_ARCHIVE_PASSWORD` (min. 20 znaków), bo kopia plików zawiera `.env`. (b) Kopia poza serwerem: na razie ręczne pobieranie; bez paczek SFTP/S3.
  (c) Licencja TERYT: właściciel wysyła zapytanie do GUS (`docs/GUS_ZAPYTANIE_LICENCJA.md`); do odpowiedzi dane oznaczone źródłem „GUS, rejestr TERYT” (ustawienie `teryt.source`). (d) Pozostałe pytania: domyślne odpowiedzi architekta.
- **Odstępstwa implementacji:** (1) importer czyta tylko CSV (UTF-8 z BOM, średnik, CRLF; sprawdzone na prawdziwych plikach z 30.10.2026), nie XML; wiersze dzieli po średniku zamiast `fgetcsv`, bo w danych są niezamknięte cudzysłowy (`Mazewo Dworskie"B"`).
  (2) Dodatkowa kolumna `teryt_municipalities.county_name_search` (zapytanie „dabrowka poznanski”). (3) Gmina w SIMC odwołuje się do wiersza TERC o kodzie WOJ+POW+GMI+RODZ, w tym RODZ 4 i 5 (miasto/obszar wiejski w gminie miejsko-wiejskiej), więc importujemy wszystkie wiersze gmin (3964).
  (4) Trasa autouzupełniania działa bez sesji i ciasteczek (`withoutMiddleware`), bo odpowiedź ma `Cache-Control: public`. (5) Daty w `upsert` jako tekst, nie obiekty Carbon (obiekty powodowały rosnące zużycie pamięci przy imporcie). (6) Zmienna alarmów kopii nazywa się `BACKUP_NOTIFICATION_EMAIL` (nie `BACKUP_NOTIFY_EMAIL`).
- **Alternatywy:** wymaganie adresów w strażniku produkcyjnym (aplikacja nie wystartowałaby przed ich ustaleniem); wspólna zmienna dla obu adresów (różne role, mogą być różne skrzynki).
- **Konsekwencje:** do czasu podania adresów nowe adresy nieruchomości nie są geokodowane (UX: ręczna pinezka lub środek miejscowości), a awarii kopii nikt nie zgłasza mailem (sprawdzanie ręczne: `php artisan backup:list`). Paczek nowych: tylko `spatie/laravel-backup` 10.3.3 (zależności MIT: `spatie/db-dumper` 4.1.1, `spatie/laravel-signal-aware-command` 2.1.2, `spatie/temporary-directory` 2.4.0).
- **Data:** 2026-10-30

## ADR-017: Model profilu fachowca (zadanie 004): Enumy jako klucze tekstowe, macierz usług z cenami w jednej tabeli
- **Kontekst:** profil ma rodzaje przeglądów, typy nieruchomości i cennik (rodzaj x typ). Spec §9 przewiduje edycję słowników przez admina, ale logika (terminy, protokoły) zależy od stałych kluczy.
- **Decyzja:** `InspectionType` (`app/Domain/Inspections/Enums`) i `PropertyType` (`app/Domain/Properties/Enums`) są źródłem prawdy o kluczach. Tabele profilu przechowują wartość enuma jako `varchar` (bez klucza obcego). Jedna tabela `specialist_services` (profil, rodzaj, typ, `price_grosze` NULL = wycena indywidualna) zastępuje trzy osobne tabele (rodzaje, typy, ceny). Tabela słownikowa z etykietami i flagą aktywności powstanie przy zadaniu słowników admina i nie zmieni tabel profilu. Stan weryfikacji to tylko `verification_status` + `verified_at` w `specialist_profiles` (logika i historia: zadanie 005). Pola `plan`, `trial_ends_at`, `featured_until` NIE są dodawane w tym zadaniu (okres próbny po NIP wymaga osobnej, trwałej tabeli, patrz ADR-010).
- **Alternatywy:** tabele słownikowe z FK od razu (więcej złączeń, kod i tak zależy od stałych kluczy); osobne tabele rodzajów, typów i cen (trudniejsze zapytanie wyszukiwarki, zbędna normalizacja); kolumny JSON (brak indeksu pod wyszukiwarkę).
- **Konsekwencje:** dodanie rodzaju przeglądu = nowy case enuma + wpis w tłumaczeniach, bez migracji. Wyszukiwarka dostaje indeks `(inspection_type, property_type, specialist_profile_id)`. Dopuszczamy wszystkie kombinacje rodzaj x typ (nie ustalamy w kodzie, co jest "sensowne", bo nie ma tego w `docs/PRZEPISY.md`).
- **Data:** 2026-10-31

## ADR-018: Adres publiczny profilu (slug), przekierowania ze starych adresów
- **Kontekst:** `/fachowiec/{slug}` ma być czytelny, trwały dla SEO i odporny na przejęcie cudzego adresu.
- **Decyzja:** slug generowany z nazwy publicznej (`Str::slug`, polskie znaki transliterowane), 3-60 znaków, `^[a-z0-9]+(-[a-z0-9]+)*$`, unikalny globalnie (indeks UNIQUE; przy kolizji dopisek `-2`, `-3`). Lista zastrzeżonych słów w `config/specialists.php`. Do pierwszej publikacji (`first_published_at` NULL) slug można zmieniać dowolnie i bez przekierowań. Po publikacji: zmiana najwyżej raz na 30 dni, stary slug trafia do `specialist_slug_redirects` (UNIQUE) i daje przekierowanie 301 na bieżący adres; stary slug jest zarezerwowany dla tego profilu na stałe (inni go nie zajmą), a właściciel może do niego wrócić (wpis przekierowania jest wtedy usuwany). Usunięcie profilu usuwa przekierowania (kaskada). Wyszukiwanie sluga w kontrolerze (nie implicit binding), bo trzeba obsłużyć przekierowania.
- **Alternatywy:** slug z ID (`/fachowiec/123-jan-kowalski`: odporny na zmiany, ale brzydki i ujawnia numerację); brak zmiany sluga (sztywne, męczące przy literówce); wolne zmiany bez limitu (łańcuchy przekierowań, podszywanie się).
- **Konsekwencje:** tabela przekierowań rośnie najwyżej o ok. 12 wierszy rocznie na profil. Nazwy typu `jan-kowalski` mogą być zajęte przez inną osobę o tym imieniu: pomaga dopisek miejscowości w propozycji.
- **Data:** 2026-10-31

## ADR-019: Zaświadczenia fachowców: dysk `local`, przekodowanie obrazów, PDF tylko do pobrania, ULID w adresie
- **Kontekst:** skany uprawnień (często z PESEL i datą urodzenia) to dane wrażliwe z punktu widzenia ryzyka. Hosting współdzielony: bez antywirusa, limit pamięci PHP ok. 128 MB, nieznane `upload_max_filesize`.
- **Decyzja:** pliki w `storage/app/private/specialist-certificates/` (dysk `local`, `serve` ustawiony na `false`), nazwa losowa, rozszerzenie z wykrytego MIME. Dozwolone: PDF, JPEG, PNG; 5 MB; max 10 zaświadczeń na profil. Obrazy są dekodowane i zapisywane ponownie przez GD jako JPEG (usuwa EXIF z GPS i ewentualne dopiski; limit 16 megapikseli z powodu pamięci). PDF nie da się przekodować bez dodatkowych narzędzi, więc jest tylko sprawdzany (nagłówek `%PDF-`, MIME z `finfo`, odrzucenie znaczników aktywnej treści) i wydawany wyłącznie jako załącznik (`Content-Disposition: attachment`, `nosniff`, `no-store`). Wydawanie przez kontroler z Policy; klucz w adresie to `ulid` (brak enumeracji, brak różnicy 403/404 dla cudzych plików). Każde otwarcie pliku przez admina trafia do dziennika aktywności. Bez nowych paczek (wymagane rozszerzenia `gd` i `fileinfo`).
- **Alternatywy:** `intervention/image` (nowa zależność dla 20 linii); ClamAV (niedostępny na home.pl); wydawanie przez podpisane URL (`temporaryUrl` wymaga `serve=true`); numeryczne ID w adresie (enumeracja).
- **Konsekwencje:** złośliwy PDF nie jest wykrywany w pełni (ryzyko szczątkowe: admin otwiera pliki lokalnie w czytniku PDF, polecenie w UX); użytkownicy z bardzo dużymi zdjęciami dostaną komunikat, by zmniejszyć zdjęcie lub wgrać PDF. Usuwanie wierszy kaskadą nie usuwa plików, więc komenda `certificates:prune-orphans` (cotygodniowo) sprząta pliki bez wiersza.
- **Data:** 2026-10-31

## ADR-020: Punkt startowy fachowca = środek miejscowości z TERYT, geokodowanie w kolejce; poprawki geokodowania z audytu 003
- **Kontekst:** spec §7 mówi o pinezce, ale zbieranie dokładnego adresu domowego fachowca (często JDG pod adresem zamieszkania) jest niepotrzebne do wyszukiwania i zwiększa ryzyko. Audyt 003 (#2, #4, #5) wymaga: geokodowania tylko w kolejce, HMAC w cache, `withoutRedirecting()`, https dla Nominatim, krótszego TTL dla adresów z ulicą.
- **Decyzja:** profil zapisuje `locality_simc` (FK do `teryt_localities.simc`) i kopiuje współrzędne środka miejscowości do `start_lat/start_lng` (`start_source = locality_center`, wynik oznaczony jako przybliżony). Brak ulicy, brak ręcznej pinezki w tym zadaniu. Gdy miejscowość nie ma współrzędnych, `location_status = pending` i job `GeocodeLocalityJob` (`ShouldQueue`, `ShouldBeUnique` po `simc`, `uniqueFor` 300 s, 3 próby z opóźnieniem) wywołuje istniejące `ResolveLocalityCoordinates`, a potem uzupełnia wszystkie profile z tym `simc`. Gdy dostawca nie jest skonfigurowany (pusty `NOMINATIM_CONTACT_EMAIL`), job kończy się bez błędu, profil zostaje `pending`, a godzinna komenda `specialists:retry-geocoding` (limit 20 miejscowości na uruchomienie) wraca do niego później. Po wyczerpaniu prób: `failed` i przycisk "Spróbuj ponownie" (3 na godzinę). Poprawki geokodowania (podzadanie 004.1): `AddressQuery::hash()` jako `hash_hmac('sha256', ..., config('app.key'))`; `->withoutRedirecting()` i limit rozmiaru odpowiedzi (64 KB) w `NominatimProvider`; `ProductionConfigGuard` wymaga `https` dla `NOMINATIM_URL`; TTL trafienia dla zapytania z ulicą 30 dni (`ttl_found_days_street`); poprawka komentarzy i ADR-013 ("pseudonimizacja", nie "anonimizacja"). Mapa z okręgiem (Leaflet, kafelki OSM, zmiana CSP `img-src`, wpis RODO o kafelkach) NIE jest częścią 004: wchodzi razem z wyszukiwarką (006); profil publiczny w 004 pokazuje miejscowość, gminę i promień tekstem.
- **Alternatywy:** adres siedziby i geokodowanie ulicy (więcej danych osobowych, więcej zapytań do OSMF, wyższy koszt RODO); synchroniczne geokodowanie przy zapisie (blokuje proces PHP do 12 s, patrz audyt 003 #5); hurtowe współrzędne z PRG (licencja i format niezweryfikowane, ADR-012); ręczna pinezka (wymaga mapy i CSP, odroczone).
- **Konsekwencje:** do czasu ustawienia `NOMINATIM_CONTACT_EMAIL` (lub zasilenia współrzędnych miejscowości innym sposobem) profile w miejscowościach bez współrzędnych nie trafią do wyszukiwania promieniowego (006). To zależność startowa serwisu, do wpisania na listę kontrolną wdrożenia. Odległość od środka miejscowości jest przybliżeniem (błąd rzędu kilku km dla dużych miast), co trzeba zaznaczyć w UI.
- **Data:** 2026-10-31

## ADR-021: Rewizja modelu profilu po UX (zadanie 004): publikacja bez zaświadczenia, zgoda na telefon publiczny, archiwum skanów 12 miesięcy po usunięciu konta, brak zdjęcia
- **Kontekst:** po sekcji UX zadania 004 właściciel zdecydował (31.10.2026): (1) telefon publiczny tylko za zgodą, (2) profil można opublikować bez zaświadczenia, (3) skany zaświadczeń przechowujemy 12 miesięcy po usunięciu konta (podstawa prawna do weryfikacji prawnika), (4) zdjęcie/logo poza zadaniem. ADR-017 i ADR-019 zakładały kasowanie plików razem z kontem, wymóg zaświadczenia do publikacji i kolumny `photo_*`.
- **Decyzja:**
  1. `ProfileCompleteness` ma 3 kroki wymagane (`data` = nazwa + prywatny telefon, `services`, `area`) i 2 zalecane (`description`, `certificates`). Zaświadczenie nigdy nie blokuje publikacji. Wymóg prywatnego telefonu jest przełączalny w `config('specialists.phone_required_to_publish')`.
  2. Telefon publiczny: `phone_is_public` (domyślnie false) + `phone_public_consent_at` jako dowód zgody (art. 7 ust. 1 RODO); numeru nie ma w HTML bez zgody (DTO `PublicSpecialistData` go nie zawiera).
  3. Archiwum: tabela `archived_certificates` (bez FK do `users`/`specialist_profiles`) i katalog `storage/app/private/specialist-certificates-archive/`. Action `PurgeSpecialistData` (wywoływana przez przyszłe zadanie usuwania konta PRZED skasowaniem użytkownika) przenosi pliki, kopiuje metadane (rodzaj, zakres, numer, wydawca, ważność, nazwa publiczna, status weryfikacji) i kasuje profil. Codzienna komenda `certificates:purge-archive` usuwa wpisy z `delete_after <= dziś`; okres w `config('specialists.archive_retention_months')` = 12. Dostęp wyłącznie `admin` (Policy), wcześniejsze usunięcie przez admina (sprzeciw, art. 21) przez `DeleteArchivedCertificate`. Pojedyncze usunięcie zaświadczenia przy żywym koncie kasuje plik od razu. Podstawa prawna (hipoteza do weryfikacji przez prawnika): art. 6 ust. 1 lit. f RODO w związku z art. 17 ust. 3 lit. e; do oceny także zgodność okresu z DSA art. 30 i test równowagi interesów.
  4. Zdjęcie/logo wypada: brak `photo_*`, tras i Actions zdjęcia; `ImageReencoder` zostaje dla skanów.
  5. Publicznie widoczne zaświadczenia tylko dla profilu `verified` i tylko te z `updated_at <= verified_at` (zaświadczenie dodane lub zmienione po weryfikacji nie wygląda na sprawdzone); docelowe `reviewed_at` w zadaniu 005.
  6. Pola promienia i dojazdu zapisuje wyłącznie `PUT /panel/profil/obszar`; przy stawce 0 profil nic nie mówi o dojeździe (brak flagi "dojazd wliczony" do decyzji właściciela).
  7. Statyczna strona `/jak-weryfikujemy-fachowcow` (`Route::view`) wchodzi do 004.8; treść jako szkic do weryfikacji prawnika.
- **Alternatywy:** zostawić zaświadczenia w tabeli `specialist_certificates` z soft-delete po usunięciu profilu (miesza dane żywe z archiwalnymi, profil i jego dane osobowe musiałyby zostać); archiwizować cały profil (zbyt dużo danych osobowych: telefon, opis); archiwizować same pliki bez metadanych (admin nie wie, czyj to skan); okres hardkodowany w komendzie (zmiana po opinii prawnika wymaga wdrożenia kodu); nowa paczka do retencji danych (zbędna: wystarcza komenda i harmonogram).
- **Konsekwencje:** jedna dodatkowa tabela i dwie komendy (`purge-archive` dzienna, `prune-orphans` rozszerzone o drugi katalog). Skan z PESEL żyje do 12 miesięcy + do 14 dni w kopiach po usunięciu konta, co trzeba opisać w polityce prywatności i rejestrze RODO. Przed uruchomieniem produkcyjnym wymagana opinia prawnika (podstawa, zakres, okres). Więcej profili `pending` publicznie widocznych zwiększa ryzyko podszywania się: łagodzą to znaczek, brak widocznych uprawnień przed weryfikacją i priorytetowa lista dla admina w 005.
- **Data:** 2026-10-31
