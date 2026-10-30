# Zadanie 001: Fundament aplikacji

- **Status:** w toku — plan zaakceptowany, budowa Etapu 0
- **Etap:** Etap 0 — Fundament (v0.1.0)
- **Gałąź:** develop (repozytorium lokalne utworzone; brak commitów i remote)
- **Utworzono:** 2026-10-29

## Cel (koordynator)
Zbudować od zera fundament aplikacji kontroladomu.pl zgodnie z Etapem 0 z `docs/PLAN_ETAPOW.md`.
Plan podzadań, architektura i uzasadnienia zależności zostały przedstawione właścicielowi i
zaakceptowane przed rozpoczęciem instalacji Laravela.

## Zakres
- W zakresie: lokalne repozytorium, Laravel 12, konfiguracja lokalna, podstawowe testy/jakość,
  struktura i fundamenty opisane w Etapie 0.
- Poza zakresem: funkcje Etapów 1–8, konfiguracja produkcji i integracja płatności.
- Potwierdzone decyzje właściciela:
  - niezagnieżdżony folder `kontroladomu-agenci` jest głównym folderem dokumentacji;
  - cała aplikacja powstaje od zera;
  - fachowcy oczekujący na weryfikację są widoczni w wynikach wyszukiwania;
  - jeden NIP otrzymuje jeden okres próbny, trwający 6 miesięcy od pierwszej rejestracji z tym NIP-em;
  - administrator ręcznie weryfikuje profil na podstawie NIP-u i zaświadczeń dodanych przez fachowca;
  - klient może anulować do końca dnia poprzedzającego dzień przeglądu; fachowiec może anulować
    również w dniu przeglądu;
  - fachowiec może ustawiać godziny pracy, dni wolne i urlopy w kalendarzu.
- Stan sprawdzonych narzędzi: PHP 8.3.36 z rozszerzeniami Laravel (m.in. PDO MySQL, OpenSSL,
  Mbstring, Curl, ZIP) jest doinstalowane obok XAMPP. XAMPP nadal używa PHP 8.2.12; do pracy
  z projektem należy jawnie wskazywać PHP 8.3, nie podmieniać PHP Apache.
- Composer 2.9.2, Node.js 24.12.0, npm 11.6.2 i Git 2.52.0 są dostępne. Globalny Composer 2.9.2
  nie ma uprawnień do aktualizacji w `C:\ProgramData\ComposerSetup\bin`; obok zainstalowano
  Composer 2.11.0, którego diagnostyka z PHP 8.3 przechodzi bez ostrzeżeń.
- MariaDB 10.11.19 pobrano z oficjalnego archiwum i sprawdzono SHA-256 przed instalacją obok XAMPP.
  Baza danych: `%LOCALAPPDATA%\kontroladomu\mariadb-10.11-data`, port `3307`, nasłuch wyłącznie
  `127.0.0.1`; hasło lokalnego administratora znajduje się poza projektem w
  `%LOCALAPPDATA%\kontroladomu\mariadb-root-password.txt`. Serwer uruchomiono i sprawdzono zapytaniem
  zwracającym wersję `10.11.19`; nie instalowano usługi Windows ani nie zmieniano XAMPP.
- XAMPP zawiera MariaDB 10.4.32, poniżej wymaganego przez projekt MariaDB 10.6+; tej instalacji
  nie zmieniano. Git został zainicjowany na gałęzi `develop`; globalna tożsamość Git
  (`user.name`, `user.email`) nie jest skonfigurowana, a zdalne repozytorium nie zostało podane.

## Architekt

### Model danych i reguły na kolejne etapy

Poniższe elementy opisują docelowe kierunki modelu. Poza domyślnymi migracjami szkieletu
Laravela nie dodano jeszcze tabel produktu; szczegóły kolumn i indeksów należy zatwierdzać
w zadaniu przed implementacją danego obszaru:

| Obszar | Kierunek |
|---|---|
| Konta | Laravelowa tabela `users`; jedna osoba może mieć jednocześnie role `owner` i `specialist`. Role i uprawnienia przez tabele pakietu Spatie, bez własnego systemu ról. Administrator zakładany komendą Artisan. |
| Ustawienia | Tabela `settings` z unikalnym kluczem i wartością; sekrety SMTP przechowywane szyfrowane (`encrypted` cast), ładowane do konfiguracji przez Service Provider. |
| Miejscowości | Tabela `teryt_localities` z kodem SIMC przechowywanym jako tekst (zera wiodące), nazwą i kodami jednostek TERYT; indeks po kodzie i polach wyszukiwanych. Import oficjalnych danych TERYT komendą Artisan. |
| Geokodowanie | Cache w bazie dla odpowiedzi Nominatim; użyć laravelowego cache store `database`, bez osobnej tabeli, o ile testy nie wykażą potrzeby trwałego, własnego modelu cache. |
| Aktywność i kopie | Tabele dziennika aktywności oraz wymagane przez Laravel/wybrane pakiety tabele kolejki, cache i kopii. Nie utrwalać sekretów ani plików użytkowników w publicznym katalogu. |

Reguły właściciela, które muszą zostać zachowane w projektach kolejnych etapów:

- Fachowiec oczekujący na ręczną weryfikację jest widoczny w wynikach tak jak zweryfikowany,
  ale zawsze z czytelnym statusem; ban wyklucza z wyników. Admin weryfikuje NIP i zaświadczenia.
- Jeden NIP daje jeden okres próbny: 6 miesięcy od pierwszej rejestracji z tym NIP-em. Nie
  implementować płatności w Etapie 0; późniejszy model musi zapobiec ponownemu okresowi po
  utworzeniu kolejnego konta.
- Klient anuluje najpóźniej do końca dnia poprzedzającego przegląd; fachowiec może anulować
  również w dniu przeglądu.
- Kalendarz fachowca uwzględnia godziny pracy, przerwy, dni wolne i urlopy; terminy rezerwacji
  nie mogą nakładać się na siebie.

### Pliki i struktura

Ścieżki aplikacji poniżej są względne wobec katalogu projektu `C:\xampp\htdocs\kontroladomu`;
nie tworzyć aplikacji wewnątrz `instrukcje\kontroladomu-agenci`.

- `app/Domain/Accounts/`, `app/Domain/Specialists/`, `app/Domain/Localities/` — moduły domenowe
  (na Etapie 0 tworzyć wyłącznie te, które mają już odpowiedzialność w tym etapie).
- `app/Actions/` — pojedyncze operacje; `app/Enums/` — typy i statusy; `app/Services/` —
  integracje zewnętrzne, w tym `GeocodingService`; `app/Http/Requests/`, `app/Policies/` —
  walidacja i autoryzacja.
- `app/Models/Setting.php`, `app/Models/TerytLocality.php`; migracje w `database/migrations/`;
  import w `app/Console/Commands/ImportTerytLocalities.php`; dane importu nie trafiają do repozytorium,
  jeżeli licencja/warunki dystrybucji źródła na to nie pozwalają.
- `resources/views/layouts/`, `resources/views/components/`, `resources/css/`,
  `resources/js/`, `lang/pl/` — bazowy layout, komponenty, paleta, zasoby i polskie teksty.
- `tests/Feature/`, `tests/Unit/`; `.env.example`, `phpunit.xml`/konfiguracja Pest,
  `docs/DOKUMENTACJA_TECHNICZNA.md`, `CHANGELOG.md`.
- Użytkownikowe zaświadczenia i kopie zapasowe przechowywać poza `public/`; dostęp do dokumentów
  wyłącznie przez autoryzowany kontroler. Nie dodawać danych ani sekretów do Git.

### Pakiety

Plan został zaakceptowany. Laravel 12 i pozycje poniżej są wyborem właściciela w
`CLAUDE.md`/ADR-001–003; wprowadzać zależności w zakresie zaakceptowanego stosu i etapów.

| Potrzeba | Wbudowane w Laravel / szkielet | Do dodania zgodnie z zatwierdzonym stosem |
|---|---|---|
| Routing, Blade, Eloquent, migracje, walidacja, CSRF, sesje, szyfrowanie, reset hasła, potwierdzenie e-mail, Policies/Gates, rate limiting, mail, storage, harmonogram, kolejka i cache z bazą | Laravel 12; użyć mechanizmów frameworka, nie dublować ich pakietami. Szkielet Laravel zawiera Vite i jego plugin integracyjny. | — |
| Interaktywny interfejs | Blade i integracja Vite | `livewire/livewire` 3; Alpine.js jest dostarczane z Livewire 3 — nie dodawać osobnej kopii bez wykazanej potrzeby. Tailwind CSS 4 i `@tailwindcss/vite` do zasobów UI. |
| Logowanie, reset hasła, weryfikacja e-mail, 2FA | Podstawy uwierzytelniania są w Laravelu | `laravel/fortify` — backend 2FA TOTP i przepływy uwierzytelniania; interfejs w Blade/Livewire. |
| Role, audyt, kopie | Framework dostarcza autoryzację, ale nie kompletne role, dziennik audytu ani backup aplikacji | `spatie/laravel-permission`, `spatie/laravel-activitylog`, `spatie/laravel-backup`; wybrać tylko używane moduły i sprawdzić wymagania home.pl dla backupu. |
| Jakość i testy | Laravelowy szkielet ma PHPUnit oraz zwykle Laravel Pint; sprawdzić manifest wygenerowanego szkieletu | `pestphp/pest` i `pestphp/pest-plugin-laravel`; `larastan/larastan` (poziom 6+). |
| Turnstile | HTTP Client Laravel | Bez paczki: własna mała integracja z API weryfikacji Cloudflare, żeby uniknąć zależności od niezatwierdzonego wrappera. |
| Mapa, kalendarz, PDF | — | Odroczyć: Leaflet w Etapie 1; wyłącznie darmowe moduły FullCalendar w Etapie 2; `barryvdh/laravel-dompdf` przy protokołach/PDF w Etapie 4. |

Vite/Tailwind budować lokalnie; na home.pl wdrażać gotowe zasoby bez Node.js. Każda przyszła
zależność spoza zatwierdzonego stosu wymaga osobnego uzasadnienia w ADR (w tym licencji,
daty ostatniego wydania i oceny, czy da się bez niej).

### Kolejność i zależności podzadań

Każdy punkt jest osobnym podzadaniem. Poniższy status odzwierciedla bieżącą realizację Etapu 0.

| Kolejność | Podzadanie i wynik | Zależności | Status |
|---|---|---|
| 0.1 | Zatwierdzić ten plan; potwierdzić katalog aplikacji, tryb PHP i dostępność MySQL/MariaDB zgodnej z wymaganiami Laravel 12. | Brak; bramka przed wszystkimi pracami. | Gotowe |
| 0.2 | Przygotować PHP 8.3 obok XAMPP bez podmiany istniejącego PHP; sprawdzić Composer wskazujący na właściwy `php.exe` i wymagane rozszerzenia PHP (m.in. PDO MySQL, OpenSSL, Mbstring, Ctype, Fileinfo, XML, Curl, ZIP). | 0.1 | Gotowe |
| 0.3 | Utworzyć repozytorium Git w katalogu aplikacji, skonfigurować prywatny remote/gałęzie i `.gitignore`; przygotować `.env.example` bez sekretów. | 0.2 | Częściowo — repo i `.gitignore` gotowe; brak remote i danych tożsamości Git. |
| 0.4 | Utworzyć szkielet Laravel 12 i skonfigurować połączenie z lokalną bazą, UTC do zapisu czasu, język `pl` oraz mechanizmy cache/kolejki oparte na bazie. Zweryfikować instalację testem uruchomienia. | 0.3 | Gotowe — Laravel 12, MariaDB, locale `pl`, UTC oraz sesje/cache/kolejka z bazą są skonfigurowane. |
| 0.5 | Dodać uzgodnione narzędzia jakości/testów, uruchomić pusty zestaw testów i ustalić bramki Pint/Larastan w projekcie. | 0.4 | Gotowe — 2 testy Pest, Pint i Larastan przechodzą. |
| 0.6 | Utworzyć minimalną strukturę domenową, konwencje Actions/Enums/Requests/Policies oraz bazowe layouty, komponenty i paletę; nie dodawać jeszcze ekranów funkcji z dalszych etapów. | 0.4; interfejs po decyzji UX w ramach Etapu 0. | Gotowe — layouty (publiczny, auth, panel), 22 komponenty, paleta, struktura domenowa (zadanie 002). |
| 0.7 | Przygotować konta/uwierzytelnianie, 2FA dla admina i fachowca, role, Turnstile, nagłówki, limity prób i dziennik aktywności; domyślne ustawienia admina przez Artisan. | 0.5–0.6 | Gotowe — zadanie 002, audyt zatwierdzony; luki RODO i checklista wdrożeniowa zostają przed produkcją. |
| 0.8 | Dodać projekt tabeli `settings`, szyfrowanie sekretów SMTP i bezpieczne ładowanie ustawień do konfiguracji; nie zapisywać wartości sekretów w repozytorium. | 0.7 | Do zrobienia |
| 0.9 | Przygotować import TERYT z Artisan i `GeocodingService` z limitem Nominatim 1 zapytanie/s oraz cache w bazie; dodać testy importu, kodów wiodących zer i limitu. | 0.4–0.5 | Do zrobienia |
| 0.10 | Skonfigurować kopie i kolejkę zgodnie z ograniczeniami hostingu; sprawdzić lokalnie wykonanie zadania kolejki i backup oraz przygotować instrukcję wdrożenia. | 0.4; zgodność z home.pl potwierdzić przed wdrożeniem testowym z Etapu 2. | Do zrobienia |
| 0.11 | Uzupełnić instrukcję lokalnego uruchomienia i wdrożenia, sprawdzić czysty checkout, testy, Pint i Larastan; przygotować CHANGELOG, tag v0.1.0 i prezentację „Sprawdź sam” po odbiorze. | 0.2–0.10 | Częściowo — README i dokumentacja techniczna uzupełnione; czysty checkout, CHANGELOG, tag i odbiór pozostają do zrobienia. |

### Ryzyka i zabezpieczenia

- **PHP/XAMPP:** XAMPP ma PHP 8.2, a projekt ma używać PHP 8.3. Przypadkowe uruchomienie
  Composera/Artisana niewłaściwym `php.exe` da pozornie poprawne, lecz niewiarygodne testy;
  nie podmieniać PHP XAMPP ani nie konfigurować jego Apache bez osobnej zgody. Lokalny sposób
  uruchomienia PHP 8.3 zapisano w ADR-004.
- **home.pl:** przed etapem wdrożenia potwierdzić dostępną wersję PHP i rozszerzenia, wersję
  MySQL/MariaDB, limity czasu/pamięci i uprawnienia do katalogów. Brak Redis/stałych procesów
  oznacza kolejkę i cache w bazie oraz cron; cron może opóźniać maile, a backup pakietu może
  wymagać narzędzi/ścieżek niedostępnych na hostingu.
- **Pliki i bezpieczeństwo:** dokumenty weryfikacyjne są wrażliwe; dysk prywatny, walidacja MIME
  i rozmiaru, autoryzowane pobieranie, brak publicznych URL-i. W Etapie 0 testować też
  `APP_DEBUG=false` i nie logować haseł, tokenów ani treści dokumentów.
- **NIP i okres próbny:** egzekwowanie „jeden NIP — jeden okres” wymaga trwałego rozpoznania NIP-u
  także po usunięciu konta. Przed implementacją tej reguły trzeba ustalić z prawnikiem podstawę
  i okres przechowywania NIP-u lub jego pseudonimizowanego identyfikatora; to nie blokuje Etapu 0.
- **TERYT/Nominatim:** sprawdzić aktualny format i warunki użycia danych GUS przed importem;
  respektować limit Nominatim i cache. Nie zakładać, że publiczny geokoder obsłuży ruch produkcyjny.
- **Kopie:** wymaganie kopii poza serwerem wymaga osobnego miejsca docelowego i danych dostępowych;
  nie umieszczać sekretów w Git. Odtworzenie kopii powinno zostać przetestowane, nie tylko jej
  utworzenie.

### Pytania do właściciela

Na tym etapie brak pytań blokujących; decyzje biznesowe wymienione w zakresie są uwzględnione.
Jedyny późniejszy punkt prawny: przed wdrożeniem okresu próbnego potwierdzić z prawnikiem
podstawę i czas przechowywania NIP-u/identyfikatora po usunięciu konta, aby utrzymać zasadę
jednego okresu próbnego bez bezterminowego przechowywania danych.

## UX
Pierwsza strona startowa informuje po polsku o usłudze i prowadzi do przyszłych obszarów
właścicieli, fachowców oraz logowania. Pełne ekrany funkcji pozostają poza aktualnym zakresem.

## Backend
Laravel 12 i lokalna baza MariaDB 10.11 są przygotowane. Logowanie, role i pozostałe
funkcje backendowe Etapu 0 są w kolejce realizacji.

## Frontend
Dodano publiczny layout Blade, bazowy komponent przycisku, polskie teksty oraz paletę
Tailwind CSS 4. Kompilacja Vite została sprawdzona.

## Testy
Pest zawiera test jednostkowy i test strony głównej. Konfiguracja testów używa SQLite
in-memory. Ostatnie sprawdzenie: 2 testy / 3 asercje zaliczone; Pint bez zmian do poprawy;
Larastan bez błędów; `npm run build` przechodzi.

## Audyt bezpieczeństwa
Audyt bezpieczeństwa będzie wykonany po implementacji mechanizmów uwierzytelniania,
autoryzacji i przechowywania wrażliwych dokumentów.

## Podsumowanie (koordynator)
Etap 0 został zaakceptowany i jest realizowany. Aktualnie działa szkielet Laravel 12,
połączenie z lokalną MariaDB, testy Pest, analiza Larastan bez błędów, kontrola Pint oraz
build Vite. Strona startowa została otwarta w przeglądarce na widoku mobilnym i desktopowym.
Przed zakończeniem etapu należy dokończyć podzadania 0.3–0.11, w tym pozostałe mechanizmy
fundamentu, test czystego checkoutu, CHANGELOG, tag i odbiór. Composer poprawnie waliduje
manifest i lockfile; pozostaje oczekiwane ostrzeżenie o nieustalonej licencji projektu.
