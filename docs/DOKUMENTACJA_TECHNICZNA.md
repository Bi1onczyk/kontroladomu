# Dokumentacja techniczna — kontroladomu.pl

## 1. Przegląd systemu

Kontroladomu.pl jest polską aplikacją webową do organizowania przeglądów nieruchomości.
Powstaje od zera w Laravel 12, z widokami Blade i zasobami budowanymi przez Vite.
Etap 0 jest w trakcie realizacji; aktualnie aplikacja udostępnia publiczną stronę startową.
Logowanie, role, profile fachowców, wyszukiwanie i rezerwacje nie są jeszcze wdrożone.

Główne ustalenia produktu i kolejność prac opisują [specyfikacja](SPECYFIKACJA.md),
[plan etapów](PLAN_ETAPOW.md) i [zadanie fundamentu](zadania/001-fundament.md).

## 2. Uruchomienie lokalne (Windows)

### Przygotowane środowisko

- Projekt: `C:\xampp\htdocs\kontroladomu`
- PHP CLI 8.3.36: instalacja WinGet obok XAMPP; PHP XAMPP 8.2 pozostaje bez zmian.
- Composer 2.11.0: `%LOCALAPPDATA%\Programs\Composer-2.11\composer83.cmd`
- Node.js 24 i npm 11.
- MariaDB 10.11.19: lokalny serwer na `127.0.0.1:3307`; konfiguracja i dane są poza
  repozytorium.
- Lokalny plik `.env` zawiera ustawienia połączenia i sekrety; nie kopiować go do Git.

PHP 8.3 można wskazać w PowerShell tak:

```powershell
$php83 = "$env:LOCALAPPDATA\Microsoft\WinGet\Packages\PHP.PHP.NTS.8.3_Microsoft.Winget.Source_8wekyb3d8bbwe\php.exe"
```

W nowej kopii projektu:

```powershell
Copy-Item .env.example .env
& "$env:LOCALAPPDATA\Programs\Composer-2.11\composer83.cmd" install
npm install
```

Przed migracjami uzupełnij `.env` o lokalne dane dostępowe do MariaDB. Następnie:

```powershell
& $php83 artisan key:generate
& $php83 artisan migrate
npm run build
```

Na przygotowanym komputerze lokalna baza i `.env` są już skonfigurowane. Serwer MariaDB
uruchomiono jako proces użytkownika, nie jako usługę Windows; po jego zatrzymaniu należy
uruchomić go ponownie z lokalnym plikiem `my.ini` znajdującym się poza repozytorium.

### Uruchomienie aplikacji

W pierwszym terminalu:

```powershell
& $php83 artisan serve --host=127.0.0.1 --port=8000
```

W drugim terminalu:

```powershell
npm run dev
```

Następnie otwórz `http://127.0.0.1:8000`. Do podglądu bez serwera Vite można wykonać
`npm run build`; skompilowane zasoby są dostępne w `public/build/`.

### Testy i kontrola jakości

Uruchamiaj wszystkie polecenia PHP z PHP 8.3. Narzędzia `vendor\bin\*.bat` wybierają
PHP z `PATH`, więc przed ich użyciem ustaw:

```powershell
$php83Dir = Split-Path $php83
$env:PATH = "$php83Dir;$env:PATH"
```

Następnie:

```powershell
& $php83 artisan test
& .\vendor\bin\pint.bat --test
composer phpstan   # skrypt z composer.json: phpstan analyse --no-progress --memory-limit=1G
npm run build
```

Testy używają SQLite in-memory, aby nie wykonywać migracji testowych na lokalnej bazie MariaDB.

## 3. Struktura katalogów

- `app/` — kod aplikacji Laravel; docelowe moduły domenowe i akcje będą dodawane wraz z etapami.
- `bootstrap/`, `config/`, `routes/` — konfiguracja frameworka i tras.
- `database/migrations/`, `database/factories/`, `database/seeders/` — schemat i dane testowe.
- `resources/views/` — widoki Blade i layout publiczny.
- `resources/css/`, `resources/js/` — źródła zasobów Vite/Tailwind.
- `lang/pl/` — polskie teksty interfejsu.
- `tests/Feature/`, `tests/Unit/` — testy Pest.
- `docs/` — specyfikacja, decyzje, plan i dokumentacja techniczna.

## 4. Model danych

Laravelowy szkielet zawiera domyślne migracje użytkowników, resetu haseł i kolejek. Tabele
ustawień, ról, fachowców, nieruchomości i rezerwacji nie zostały jeszcze zaimplementowane.
Założenia i reguły biznesowe przedstawią kolejne zadania przed dodaniem migracji.

Planowany model kont (zadanie 002, jeszcze niezaimplementowany; szczegóły w `docs/zadania/002-konta-i-uwierzytelnianie.md`):

```mermaid
erDiagram
    users ||--o{ model_has_roles : "ma"
    roles ||--o{ model_has_roles : "przypisana"
    users ||--o{ sessions : "sesje"
    users ||--o{ activity_log : "causer"
    users {
        bigint id PK
        string name
        string email UK
        timestamp email_verified_at
        string password
        char nip "index, nie unikalny"
        string company_name
        timestamp terms_accepted_at
        string terms_version
        timestamp privacy_accepted_at
        string privacy_version
        timestamp marketing_consent_at
        string marketing_consent_version
        timestamp banned_at
        text two_factor_secret
        text two_factor_recovery_codes
        timestamp two_factor_confirmed_at
        timestamp two_factor_reminder_until
    }
    roles { bigint id PK string name string guard_name }
    model_has_roles { bigint role_id FK string model_type bigint model_id }
    activity_log { bigint id PK string log_name string description string event bigint causer_id json properties }
    sessions { string id PK bigint user_id FK string ip_address }
    password_reset_tokens { string email PK string token }
```

Planowane dane geograficzne i ustawienia (zadanie 003, jeszcze niezaimplementowane; szczegóły w `docs/zadania/003-teryt-geokodowanie-kopie.md`):

```mermaid
erDiagram
    teryt_municipalities ||--o{ teryt_localities : "gmina"
    users ||--o{ settings : "updated_by"
    teryt_municipalities {
        bigint id PK
        char code UK "7 znaków, WWPPGGR"
        string name
        char type_code
        char county_code
        string county_name
        char voivodeship_code
        string voivodeship_name
        string name_search
    }
    teryt_localities {
        bigint id PK
        char simc UK "7 znaków"
        char parent_simc "null dla samodzielnych"
        bigint municipality_id FK "restrict"
        string name
        string name_search "index z is_part"
        char kind_code
        string kind
        boolean is_part
        string slug UK
        decimal lat "10,7 null"
        decimal lng "10,7 null"
        string coordinates_source
        timestamp geocoded_at
        boolean is_active
    }
    geocode_cache {
        bigint id PK
        char query_hash UK "sha256, bez tekstu adresu"
        string status "found / not_found"
        decimal lat "null"
        decimal lng "null"
        string precision
        string provider
        timestamp expires_at "index"
    }
    settings {
        bigint id PK
        string group
        string key UK
        text value
        text secret_value "cast encrypted"
        bigint updated_by FK "nullOnDelete"
    }
```

Planowany model profilu fachowca (zadanie 004, jeszcze niezaimplementowany, bez zdjęcia/logo; szczegóły w `docs/zadania/004-profil-fachowca.md`).
Rodzaje przeglądów i typy nieruchomości to klucze tekstowe z Enumów (`InspectionType`, `PropertyType`), nie klucze obce (ADR-017):

```mermaid
erDiagram
    users ||--o| specialist_profiles : "właściciel konta"
    teryt_localities ||--o{ specialist_profiles : "punkt startowy (simc)"
    specialist_profiles ||--o{ specialist_services : "rodzaj x typ + cena"
    specialist_profiles ||--o{ specialist_slug_redirects : "stare adresy"
    specialist_profiles ||--o{ specialist_certificates : "zaświadczenia"
    specialist_profiles {
        bigint id PK
        bigint user_id FK "UK, cascade"
        string slug UK "3-60, a-z0-9 i myślnik"
        timestamp slug_changed_at
        string display_name
        string account_type "sole_proprietor / company"
        text description "tekst, bez HTML"
        string phone "prywatny"
        boolean phone_is_public "zgoda, domyślnie false"
        timestamp phone_public_consent_at "dowód zgody"
        char locality_simc FK "restrict, null do wyboru"
        smallint service_radius_km
        int travel_rate_per_km_grosze
        smallint travel_free_km
        decimal start_lat "10,7 null"
        decimal start_lng "10,7 null"
        string start_precision
        string start_source
        string location_status "pending / resolved / failed"
        timestamp location_resolved_at
        string verification_status "pending / verified / rejected / revoked"
        timestamp verified_at
        boolean is_published
        timestamp first_published_at
    }
    specialist_services {
        bigint id PK
        bigint specialist_profile_id FK "cascade"
        string inspection_type "Enum InspectionType"
        string property_type "Enum PropertyType"
        int price_grosze "null = wycena indywidualna"
    }
    specialist_slug_redirects {
        bigint id PK
        bigint specialist_profile_id FK "cascade"
        string old_slug UK
    }
    specialist_certificates {
        bigint id PK
        char ulid UK "klucz w URL"
        bigint specialist_profile_id FK "cascade"
        string type "Enum CertificateType"
        string title
        string number
        string issuer
        date valid_until "null = bezterminowo"
        string file_path "dysk local, losowa nazwa"
        string file_original_name
        string file_mime
        int file_size
        char file_sha256
    }
    archived_certificates {
        bigint id PK
        char ulid UK
        bigint former_user_id "bez FK"
        string holder_label "nazwa publiczna z chwili usunięcia"
        string type
        string title
        string number
        string issuer
        date valid_until
        string verification_status_at_archive
        timestamp verified_at
        string file_path "katalog archiwum, dysk local"
        string file_mime
        int file_size
        char file_sha256
        timestamp archived_at
        date delete_after "archived_at + 12 mies., INDEX"
    }
```

Archiwum skanów (`archived_certificates`, ADR-021) powstaje przy usunięciu konta fachowca (Action `PurgeSpecialistData`), nie ma kluczy obcych do `users` ani `specialist_profiles`, jest dostępne tylko dla administratora, a komenda `certificates:purge-archive` usuwa wpisy po `delete_after` (12 miesięcy; okres w `config/specialists.php`, podstawa prawna do weryfikacji przez prawnika).

## 5. Kluczowe procesy

Procesy logowania, weryfikacji fachowca, rezerwacji, przypomnień i protokołów pozostają
w zakresie kolejnych etapów; nie są jeszcze dostępne w aplikacji.

## 6. Uprawnienia

Nie wdrożono jeszcze kont ani ról. Docelowe zasady weryfikacji, widoczności i anulowania
rezerwacji opisuje [specyfikacja produktu](SPECYFIKACJA.md).

## 7. Harmonogram i kolejki

Kolejka i harmonogram zadań nie są jeszcze skonfigurowane do procesów produktu.
Etap 0 ma przygotować lokalne mechanizmy oraz instrukcję zgodną z ograniczeniami hostingu.

## 8. Bezpieczeństwo

- Sekrety lokalne przechowuj wyłącznie w `.env`, który jest ignorowany przez Git.
- Nie zapisuj dokumentów fachowców w publicznym katalogu; obsługa dokumentów nie została
  jeszcze zaimplementowana.
- Używaj PHP 8.3 dla Artisan i narzędzi projektu; PHP XAMPP nie jest modyfikowane.
- Testy korzystają z odizolowanej bazy SQLite in-memory.
- 2FA, role, limity, nagłówki i dziennik aktywności są zaplanowane, ale nie są jeszcze wdrożone.

## 9. Wdrożenie na home.pl

Wdrożenie produkcyjne nie jest jeszcze przygotowane. Przed etapem wdrożenia należy potwierdzić
wersję PHP, bazę danych, cron, uprawnienia katalogów i możliwość przechowywania kopii poza
serwerem.

## 10. Kopie zapasowe i przywracanie

Procedura kopii i przywracania zostanie opisana po wdrożeniu rozwiązania kopii w Etapie 0
i potwierdzeniu ograniczeń hostingu.

## 11. Znane ograniczenia i dług techniczny

- Aplikacja zawiera obecnie szkielet Laravel i podstawową stronę publiczną.
- Brak zdalnego repozytorium Git oraz skonfigurowanej tożsamości Git; publikowanie zmian
  wymaga wcześniejszego uzgodnienia z właścicielem.
- Przed użyciem triala powiązanego z NIP-em trzeba ustalić podstawę prawną i okres
  przechowywania identyfikatora, również po usunięciu konta.
- Warunki TERYT, Nominatim i hostingu home.pl należy zweryfikować przed integracją i wdrożeniem.

## Uwierzytelnianie: uwagi wdrożeniowe (zadanie 002, backend)

- Komunikaty `validation.custom.name.*` i `validation.custom.email.*` w `lang/pl/validation.php` są globalne (dotyczą każdego pola o tej nazwie).
  Przy kolejnych formularzach z polem `name` (np. nazwa nieruchomości) trzeba nadać polu inną nazwę albo dopisać komunikat per formularz.
- PHPStan wymaga większej pamięci niż domyślne 128 MB. Uruchamiaj go skryptem Composera: `composer phpstan` (to samo co `phpstan analyse --no-progress --memory-limit=1G`).
- `APP_KEY` szyfruje sekrety 2FA; jego zmiana unieważnia 2FA wszystkich użytkowników (kopia `.env` poza serwerem).
- Za Cloudflare ustaw `TRUSTED_PROXIES`, inaczej limity żądań „na IP" liczą adres proxy zamiast klienta.
- Utrata telefonu i kodów awaryjnych: `php artisan user:reset-2fa adres@example.pl --reason="..."` (wpis w dzienniku `security_admin`).

## Wdrożenie: checklista bezpieczeństwa (po audycie zadania 002)

1. Skopiuj `.env.production.example` na serwer jako `.env` i uzupełnij puste wartości (klucz aplikacji: `php artisan key:generate`). Aplikacja **nie wystartuje** na produkcji, gdy:
   `APP_DEBUG=true`, Turnstile wyłączony lub z kluczami testowymi Cloudflare (`1x0000…`, `2x0000…`, `3x0000…`), `QUEUE_CONNECTION=sync`, `MAIL_MAILER=log|array`,
   `SESSION_SECURE_COOKIE` różne od `true` albo `AUTH_CHECK_PWNED=false` (`App\Support\Security\ProductionConfigGuard`). Komendy `package:discover`, `key:generate`, `config:cache` i podobne mają wyjątek, żeby `composer install` działał przed uzupełnieniem `.env`.
2. **Zaufane proxy:** `TRUSTED_PROXIES` = zakresy IP Cloudflare po przecinku (aktualna lista: https://www.cloudflare.com/ips/; przykład w `.env.example`), nie `*`. Aplikacja ufa tylko `X-Forwarded-For/-Port/-Proto`, nigdy `X-Forwarded-Host`.
   Jeśli adres origin home.pl jest osiągalny z pominięciem Cloudflare, ogranicz ruch do zakresów Cloudflare (reguła w `.htaccess` lub zapora, o ile home.pl pozwala), bo inaczej napastnik może podszyć IP w `X-Forwarded-For`.
   Kontrola: `curl -H "X-Forwarded-For: 1.2.3.4" https://<adres-origin>/` nie może zmieniać adresu IP widzianego przez aplikację (podgląd w dzienniku aktywności po logowaniu).
3. **Hosty:** aplikacja akceptuje wyłącznie host z `APP_URL` (bez subdomen); obcy nagłówek `Host` dostaje 400. Na produkcji adresy URL są budowane z `APP_URL` po https.
4. **Sesje:** `AuthenticateSession` kończy sesje po zmianie/resecie hasła niezależnie od sterownika; zalecany `SESSION_DRIVER=database` (dodatkowo usuwa wiersze `sessions`).
5. **Limity:** rejestracja i reset hasła są ograniczone tylko po IP (10/h i 5/h); liczbę maili resetu ogranicza się po adresie (3/h, bez błędu dla użytkownika). Logowanie: po 3 błędach z IP albo 5 błędach na konto (z dowolnych IP) wymagany jest Turnstile; nie ma blokady 429 na konto, żeby nie dało się zablokować cudzego logowania.
6. Zmiana adresu e-mail na koncie niepotwierdzonym nie ujawnia, czy nowy adres ma konto (jak rejestracja).

### Wdrożenie: uzupełnienia po re-audycie

- **Strażnik konfiguracji** działa w każdym środowisku poza `local` i `testing` (także `staging`) i dodatkowo wymaga: `APP_URL` z `https://` i hostem oraz niepustego `TRUSTED_PROXIES` różnego od `*`.
- **Hosty i `www.`:** akceptowany jest wyłącznie host z `APP_URL` (bez subdomen). Jeśli `APP_URL` to `https://kontroladomu.pl`, adres `www.kontroladomu.pl` dostanie 400, więc w Cloudflare ustaw przekierowanie `www` na domenę główną (Redirect Rule) przed uruchomieniem. Gdy `APP_URL` nie ma hosta, żaden host nie jest zaufany (fail-closed).
- **Sesje:** wersja sesji konta (`users.session_version`) kończy sesje po resecie 2FA oraz zmianie/resecie hasła niezależnie od sterownika. Zalecany nadal `SESSION_DRIVER=database` (wiersze `sessions` są wtedy usuwane); `file` działa, ale nie jest sprawdzany przez strażnika.
- **Kontrola IP za Cloudflare/home.pl (R6, obowiązkowa na stagingu):** zanim ruch trafi na produkcję, sprawdź `request()->ip()` (np. tymczasową trasą chronioną hasłem lub wpisem w logu) i potwierdź, że (1) pokazuje adres klienta, nie adres Cloudflare/home.pl, (2) nagłówek `X-Forwarded-For` wysłany spoza Cloudflare nie zmienia adresu. Jeśli między Cloudflare a PHP stoi jeszcze proxy home.pl, jego adres też musi być w `TRUSTED_PROXIES`, inaczej wszystkie żądania mają jedno IP (limity 10 rejestracji i 5 resetów/h działają globalnie). Listę zakresów Cloudflare przeglądaj raz na kwartał.
- **Zmiana adresu e-mail:** `users.email` zmienia się dopiero po kliknięciu w link wysłany na NOWY adres (kolumna `pending_email`). Wolny i zajęty adres są obsługiwane identycznie przez żądanie HTTP; rozróżnia je dopiero zadanie w kolejce, więc kolejka musi działać (cron `schedule:run`). Limit: 3 maile weryfikacyjne i 3 maile resetu na godzinę na adres odbiorcy (także bez konta).

## Wdrożenie: uzupełnienia po audycie zadania 003

- Ustalić `BACKUP_NOTIFICATION_EMAIL` i `NOMINATIM_CONTACT_EMAIL` (adresy w domenie kontroladomu.pl). Do czasu ustawienia pierwszego raz w tygodniu wykonywać `php artisan backup:list` i sprawdzać, że istnieje kopia plików z ostatnich 7 dni.
- Ustawić `BACKUP_ARCHIVE_PASSWORD` (min. 20 znaków) i zapisać je w menedżerze haseł poza serwerem. Bez hasła i `APP_KEY` kopii nie da się odczytać.
- Na home.pl sprawdzić: `mysqldump`, `proc_open`, rozszerzenie zip, limity czasu i pamięci, wolne miejsce (limit kopii 3000 MB).
- Po awarii kopii sprawdzić i wyczyścić `storage/app/backup-temp` (może zawierać niezaszyfrowany zrzut bazy).
- Import TERYT zrobić lokalnie i przenieść tabele `teryt_*` przez phpMyAdmin, jeśli hosting nie daje SSH.
- Cloudflare: reguła ograniczenia zapytań dla `/miejscowosci/*` oraz cache tej ścieżki.
- Przed odpowiedzią GUS w sprawie licencji nie reklamować serwisu publicznie.

## Wdrożenie: uzupełnienia po audycie zadania 004

- Rozszerzenia PHP: `gd`, `fileinfo`, najlepiej też `exif` (obrót zdjęć z telefonu). Sprawdzić `memory_limit` (zdjęcia powyżej ok. 12 MP mogą być odrzucone przy 128 MB), `upload_max_filesize` i `post_max_size` (min. 6 MB).
- Cron co minutę musi działać: geokodowanie miejscowości (`GeocodeLocalityJob`), komendy `specialists:retry-geocoding` (co godzinę), `certificates:purge-archive` (03:30) i `certificates:prune-orphans` (niedziela).
- Dopóki `NOMINATIM_CONTACT_EMAIL` jest pusty, miejscowości bez współrzędnych zostają ze statusem `pending` i fachowcy nie będą znajdowani po odległości.
- Przed uruchomieniem publicznym: mechanizm zgłaszania treści (DSA art. 16), decyzja prawnika o archiwum skanów, test w prawdziwej przeglądarce (CSP, telefon).
- Po wdrożeniu nowych migracji: `php artisan backup:run --only-db`, potem `php artisan migrate`.

