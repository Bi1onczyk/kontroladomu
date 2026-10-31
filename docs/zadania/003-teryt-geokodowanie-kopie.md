# Zadanie 003: TERYT, geokodowanie i kopie zapasowe

- **Status:** gotowe (30.10.2026)
- **Etap:** Etap 0 — Fundament (v0.1.0), ostatnie podzadania
- **Gałąź:** feature/teryt-geokodowanie-kopie
- **Utworzono:** 2026-10-30

## Cel (koordynator)
Domknąć Etap 0: (1) baza miejscowości TERYT (także wsie, z gminą i powiatem) importowana komendą
artisan, do autouzupełniania w wyszukiwarce (`docs/SPECYFIKACJA.md` §3); (2) `GeocodingService`
(Nominatim, limit 1 zapytanie/s, cache w bazie, ADR-002); (3) kopie zapasowe `spatie/laravel-backup`
(baza codziennie, pliki co tydzień, retencja 14 dni, zgodnie z `CLAUDE.md` §5.6); (4) tabela `settings`
(jeśli jeszcze jej nie ma) i rozdziały „Wdrożenie” oraz „Kopie zapasowe” w `docs/DOKUMENTACJA_TECHNICZNA.md`.

## Zakres
- W zakresie: model i migracje miejscowości, komenda importu TERYT (źródło i licencja do sprawdzenia
  przez architekta), `GeocodingService` z cache i limitem zapytań, endpoint autouzupełniania miejscowości
  (bez widoku wyszukiwarki), konfiguracja i harmonogram kopii, testy Pest, dokumentacja.
- Poza zakresem: wyszukiwarka, mapa i profile fachowców (Etap 1), kopia poza serwerem
  (miejsce docelowe do ustalenia z właścicielem), eksport/usunięcie konta (RODO, osobne zadanie).

## Architekt
Data: 2026-10-30. Decyzje formalne: ADR-012 … ADR-015 w `docs/DECYZJE.md`. Stan wyjściowy: w repozytorium NIE ma
tabeli `settings`, nie ma `spatie/laravel-backup`, w `routes/console.php` jest tylko kolejka i sprzątanie (zadanie 002).
Laravel zainstalowany: v12.70.2. Tabela `cache_locks` istnieje (potrzebna do blokady limitu Nominatim).

### Podział na podzadania (całość > 1 dzień)
- **3A** Model i import TERYT + endpoint autouzupełniania (ok. 1 dzień).
- **3B** `GeocodingService` + cache (ok. 0,5 dnia).
- **3C** Tabela `settings` + serwis `Settings` (ok. 0,25 dnia; zależność dla 3A: data stanu TERYT).
- **3D** Kopie zapasowe + harmonogram + rozdziały dokumentacji (ok. 0,5 dnia).
Kolejność: 3C → 3A → 3B → 3D (szczegóły w „Kolejność”).

### 1. Model danych

#### 1.1 `teryt_municipalities` (gminy, z pliku TERC)
Denormalizujemy nazwy powiatu i województwa do gminy: ok. 2500 wierszy, wyświetlanie etykiety „gm. X, pow. Y, woj. Z”
bez trzech złączeń. Brak osobnych tabel powiatów i województw (YAGNI; nie edytujemy ich, odświeżamy importem).

| Kolumna | Typ | Uwagi |
|---|---|---|
| `id` | bigint unsigned PK | |
| `code` | char(7) | kod TERYT gminy = WOJ(2)+POW(2)+GMI(2)+RODZ_GMI(1); **UNIQUE** |
| `name` | varchar(100) | nazwa gminy, np. „Dopiewo” |
| `type_code` | char(1) | RODZ_GMI: 1 miejska, 2 wiejska, 3 miejsko-wiejska, 4 miasto w gm. m-w, 5 obszar wiejski gm. m-w, 8 dzielnica Warszawy, 9 delegatura (do potwierdzenia na pliku) |
| `county_code` | char(4) | WOJ+POW |
| `county_name` | varchar(100) | np. „poznański” |
| `voivodeship_code` | char(2) | |
| `voivodeship_name` | varchar(50) | np. „wielkopolskie” |
| `name_search` | varchar(100) | nazwa po normalizacji (pkt 1.4), do dopasowania „dabrowka dopiewo” |
| `created_at`, `updated_at` | timestamp | |

Indeksy: UNIQUE(`code`), INDEX(`voivodeship_code`, `county_code`). Klucze obce: brak (tabela źródłowa).

#### 1.2 `teryt_localities` (miejscowości, z pliku SIMC)
Ok. 101,9 tys. wierszy wg GUS (stan od 01.01.2026: 43 641 wsi, 34 764 części miejscowości, 1026 miast). Rozmiar
z indeksami szacunkowo 20–30 MB. Importujemy WSZYSTKO, w tym części miejscowości (osiedla, przysiółki), bo adres
nieruchomości może wskazywać na część; autouzupełnianie domyślnie je ukrywa (`include_parts`).

| Kolumna | Typ | Uwagi |
|---|---|---|
| `id` | bigint unsigned PK | |
| `simc` | char(7) | identyfikator SIMC (pole SYM); **UNIQUE**; to jest „kod TERYT miejscowości” z reguł projektu i klucz w `properties.teryt_simc` (przyszłe zadanie) |
| `parent_simc` | char(7) NULL | SYMPOD: miejscowość podstawowa dla części miejscowości; dla samodzielnych równa `simc` albo NULL (importer zapisuje NULL gdy SYMPOD = SYM) |
| `municipality_id` | bigint unsigned | FK → `teryt_municipalities.id`, `onDelete restrict` (nie kasujemy gmin) |
| `name` | varchar(100) | NAZWA, ze wszystkimi polskimi znakami (najdłuższa wg GUS: 40 znaków) |
| `name_search` | varchar(100) | nazwa znormalizowana (pkt 1.4) |
| `kind_code` | char(2) | RM z SIMC (01 wieś, 96 miasto, 00 część miejscowości itd.; lista kodów do potwierdzenia na prawdziwym pliku) |
| `kind` | varchar(20) | wartość Enum `LocalityKind` (`city`, `village`, `colony`, `hamlet`, `settlement`, `forest_settlement`, `district`, `part`, `other`); mapowanie kodu → enum w jednym miejscu |
| `is_part` | boolean, default false | część miejscowości (RM 00, 99 lub SYMPOD ≠ SYM); filtr „bez części” |
| `slug` | varchar(160) | do adresów SEO (`dabrowka-gm-dopiewo`); **UNIQUE** |
| `lat` | decimal(10,7) NULL | **SIMC nie zawiera współrzędnych**; wypełniane leniwie (pkt 3) lub ręcznie |
| `lng` | decimal(10,7) NULL | j.w. |
| `coordinates_source` | varchar(20) NULL | `nominatim` / `manual` / `import` |
| `geocoded_at` | timestamp NULL | kiedy ustalono współrzędne |
| `is_active` | boolean, default true | `false` gdy miejscowość zniknęła z nowszego pliku SIMC (nie kasujemy: nieruchomości mogą się do niej odwoływać) |
| `created_at`, `updated_at` | timestamp | |

Indeksy:
- UNIQUE(`simc`), UNIQUE(`slug`);
- **INDEX `teryt_localities_search_idx` (`name_search`, `is_part`)** — dla `WHERE name_search LIKE 'abc%'` (dopasowanie prefiksowe korzysta z indeksu B-tree; dopasowanie „%abc%” NIE korzysta, więc go nie używamy);
- INDEX(`municipality_id`) (tworzy go FK); INDEX(`parent_simc`).
- Indeksu na (`lat`,`lng`) NIE dodajemy tutaj: wyszukiwanie odległości (bounding box) dotyczy fachowców i nieruchomości, nie słownika miejscowości. `lat/lng` w tej tabeli służą jako środek promienia po wyborze miejscowości.

Slug: `Str::slug(name)`; przy kolizji dopisujemy `-gm-<gmina>`; przy dalszej kolizji `-<simc>`. Generowany przy imporcie, NIE zmieniany przy aktualizacji (stabilne adresy SEO).

#### 1.3 `geocode_cache` (cache geokodowania adresów)
**RODO / minimalizacja:** nie zapisujemy tekstu adresu ani `display_name` z Nominatim, tylko skrót SHA-256 znormalizowanego
zapytania i wynik liczbowy. Pełny adres domu może być daną osobową; skrót + współrzędne nie pozwalają odtworzyć adresu.
Współrzędne konkretnej nieruchomości i tak są przechowywane w tabeli nieruchomości (z podstawą prawną tam opisaną).

| Kolumna | Typ | Uwagi |
|---|---|---|
| `id` | bigint unsigned PK | |
| `query_hash` | char(64) | SHA-256 z `normalize(street, number, postal_code, city, simc)`; **UNIQUE** |
| `status` | varchar(12) | `found` / `not_found` (błędy sieci NIE są cache’owane) |
| `lat` | decimal(10,7) NULL | NULL przy `not_found` |
| `lng` | decimal(10,7) NULL | |
| `precision` | varchar(12) NULL | `address` / `street` / `locality` (ocena z `addresstype` Nominatim) |
| `provider` | varchar(20) | `nominatim` (pole pod ewentualnego dostawcę zastępczego) |
| `expires_at` | timestamp | `found`: +180 dni; `not_found`: +7 dni (konfigurowalne) |
| `created_at`, `updated_at` | timestamp | |

Indeksy: UNIQUE(`query_hash`), INDEX(`expires_at`) (sprzątanie: `geocode:prune` codziennie, harmonogram). Brak kluczy obcych.
Wygasły wpis jest ignorowany przy odczycie i nadpisywany przy następnym trafieniu (`upsert`).

#### 1.4 Wyszukiwanie bez wrażliwości na polskie znaki (decyzja)
Nie polegamy na collation MySQL (`utf8mb4_polish_ci` traktuje „ą” jako osobną literę, a `utf8mb4_0900_ai_ci` nie istnieje
w MariaDB 10.6; test SQLite miałby inne zachowanie). Zamiast tego **kolumna `name_search`** liczona w PHP jednym helperem
`App\Domain\Geo\Support\SearchText::normalize(string): string`:
1. `mb_strtolower`, 2. `Str::ascii()` (zamienia ą ć ę ł ń ó ś ź ż, „ł” → „l”), 3. wszystko poza `[a-z0-9]` → spacja,
4. zwinięcie wielu spacji, `trim`. Przykład: „Łódź-Bałuty” → „lodz baluty”.
Ten sam helper normalizuje zapytanie użytkownika, więc „dabrowka”, „Dąbrówka” i „DĄBRÓWKA” dają ten sam wynik. Działa
identycznie na MySQL, MariaDB i SQLite (testy).

#### 1.5 `settings` (NIE istnieje — do utworzenia w 3C)
| Kolumna | Typ | Uwagi |
|---|---|---|
| `id` | bigint unsigned PK | |
| `group` | varchar(50) | np. `mail`, `teryt`, `geocoding`; do grupowania w panelu admina |
| `key` | varchar(100) | pełny klucz kropkowany, np. `mail.host`, `teryt.simc_state_date`; **UNIQUE** |
| `value` | text NULL | wartość jawna |
| `secret_value` | text NULL | wartość wrażliwa; w modelu `casts: ['secret_value' => 'encrypted']` (klucz = `APP_KEY`) |
| `updated_by` | bigint unsigned NULL | FK → `users.id`, `onDelete nullOnDelete` |
| `created_at`, `updated_at` | timestamp | |

Dwie kolumny zamiast flagi `is_encrypted`: cast `encrypted` Eloquenta działa na całej kolumnie, więc sekrety muszą mieć osobną.
Dostęp wyłącznie przez serwis `App\Domain\Settings\SettingsRepository` (`get`, `getSecret`, `put`, `putSecret`, `forget`),
z cache całej tabeli (`Cache::rememberForever('settings.all')`, unieważniany przy zapisie). Sekrety nigdy nie trafiają do
`activity_log` (log zapisuje tylko, że klucz się zmienił). Zmiana `APP_KEY` unieważnia odczyt sekretów (to samo ryzyko co 2FA, ADR-005).
**W tym zadaniu** `settings` służy do zapisu daty stanu TERYT (`teryt.simc_state_date`, `teryt.terc_state_date`, `teryt.imported_at`).
Ładowanie SMTP do `config('mail')` w ServiceProviderze + ekran admina to osobne zadanie (panel admina); tu tylko fundament.

#### 1.6 Diagram ER
Zaktualizowany w `docs/DOKUMENTACJA_TECHNICZNA.md` (rozdział 4, fragment „Dane geograficzne i ustawienia”).

### 2. Źródło danych TERYT (SIMC + TERC)

**Potwierdzone w sieci (30.10.2026):**
- Dane to rejestr TERYT GUS: portal eTeryt, „Pliki pełne” (bezpośrednie pobieranie bez logowania, osobny przycisk dla SIMC w wersjach:
  podstawowa/urzędowa, adresowa, statystyczna; wybór „stanu danych” datą) i „Pliki aktualizacyjne”. Alternatywa: usługa sieciowa TERYT API
  (api.stat.gov.pl; wymaga rejestracji konta i hasła, protokół SOAP — nie używamy).
- Katalog SIMC obowiązujący od 01.01.2026 zawiera 101 865 miejscowości. Identyfikatory SIMC są stałe, nazwy i podział administracyjny aktualizowane okresowo.
- Od 17.06.2027 pliki aktualizacyjne dostaną dodatkowy atrybut „zmiana jednoznaczna/niejednoznaczna” (ustawa z 21.11.2025, Dz. U. poz. 1792). My używamy
  plików PEŁNYCH, więc to nas nie dotyczy, ale importer ma ignorować nieznane kolumny.
- Nazwy gmin, powiatów i województw NIE ma w SIMC; wynikają z pliku **TERC** (stąd dwa pliki wejściowe).

**NIE udało się potwierdzić (jawnie):**
1. **Licencji i warunków ponownego wykorzystania danych TERYT.** Strony eTeryt nie podają licencji (stopka: „Copyright © 1995-2016 GUS”, co nie jest licencją).
   Ogólna podstawa to ustawa z 11.08.2021 o otwartych danych i ponownym wykorzystywaniu informacji sektora publicznego (zasadniczo bezpłatnie, z zastrzeżeniem
   praw do baz danych). Nie twierdzimy, że to „CC BY” ani „domena publiczna”. **Działanie:** pytanie do właściciela (pyt. 1), który ma wysłać zapytanie do GUS
   (formularz „Pytanie do GUS”, infolinia 22 279 99 99) — treść zapytania w pkt „Pytania”. Do czasu odpowiedzi: dane importujemy na własny użytek aplikacji (słownik
   adresowy), NIE publikujemy ich jako pobieralnego zbioru, NIE commitujemy plików GUS do repozytorium, a w stopce/polityce dajemy „Źródło danych: rejestr TERYT, GUS”.
2. **Dokładnej struktury plików** (nazwy kolumn, kodowanie, separator, dostępność CSV obok XML). Strona „Struktury plików” nie dała się odczytać w analizie.
   Z pamięci i zgodnie z narzędziami społeczności (django-teryt czyta XML z tagami WOJ, POW, GMI, RODZ, NAZWA) SIMC zawiera pola: `WOJ, POW, GMI, RODZ_GMI, RM, MZ, NAZWA, SYM, SYMPOD, STAN_NA`,
   a TERC: `WOJ, POW, GMI, RODZ, NAZWA, NAZDOD, STAN_NA`. **To założenie trzeba zweryfikować na prawdziwym pliku w kroku 0** (kolejność prac). Importer czyta kolumny
   po nazwach z nagłówka, nie po pozycji, i przerywa z czytelnym błędem, gdy brakuje wymaganej kolumny.
3. **Czy home.pl udostępnia SSH** (potrzebne do `php artisan` na serwerze) oraz limity `memory_limit`/`max_execution_time` dla PHP CLI i crona. Nie sprawdzono (wymaga dostępu do panelu).

**Sposób pobrania (decyzja):** pobranie ręczne przez właściciela z eTeryt (formularz ASP.NET bez stałego adresu URL, więc automatyczne pobieranie
przez cron jest kruche i niepotrzebne: dane zmieniają się kilka razy w roku). Właściciel wgrywa rozpakowane pliki (CSV albo XML) do
`storage/app/private/teryt-import/` (poza `public/`, FTP). Komenda:

```
php artisan teryt:import --terc=teryt-import/TERC.csv --simc=teryt-import/SIMC.csv [--dry-run] [--chunk=1000]
```
Działanie: (1) TERC: ~2500 gmin → `upsert` po `code`; (2) SIMC: strumieniowo (`SplFileObject`/`fgetcsv` albo `XMLReader`, nigdy `file()`/`simplexml_load_file` całości),
porcje po 1000 wierszy w jednej transakcji, `DB::connection()->disableQueryLog()`, `upsert` po `simc` (aktualizuje nazwę, gminę, rodzaj; NIE nadpisuje `lat/lng/slug`);
(3) miejscowości nieobecne w pliku: `is_active=false` (tylko gdy plik zawiera >90% dotychczasowych wierszy, zabezpieczenie przed obciętym plikiem);
(4) zapis dat stanu do `settings`; (5) pasek postępu i podsumowanie (dodano/zmieniono/wyłączono). Pamięć: stała, rzędu kilku MB (jedna porcja w RAM).
Czas: 100 tys. wierszy w porcjach po 1000 to ~100 zapytań `INSERT … ON DUPLICATE KEY UPDATE`; szacunek 10–40 s na MySQL (do zmierzenia; brak pomiaru).
Dla pewności komenda ma opcję `--resume-from=<simc>` i `--max-seconds=N` (przerywa po czasie z kodem 0 i wskazuje, od czego wznowić),
bo limit czasu na home.pl nie jest potwierdzony.

**Plan awaryjny (jeśli home.pl nie ma SSH lub blokuje długi proces):** import wykonać LOKALNIE (XAMPP/MariaDB), a tabele `teryt_municipalities` i `teryt_localities`
wgrać na produkcję przez phpMyAdmin (eksport SQL ok. 15–25 MB, w razie limitu wgrywania — podział na części lub kompresja gzip). To działa bez żadnego
zadania w tle. Seeder nie jest używany (dane nie mogą być w repozytorium przed wyjaśnieniem licencji).

**Współrzędne:** SIMC ich nie ma. Mamy dwie ścieżki: (a) leniwie, przy pierwszym wyborze miejscowości, `GeocodingService::geocodeLocality()` (jedno zapytanie
Nominatim na miejscowość, zapis w `teryt_localities.lat/lng`; po kilku miesiącach działania pokryte będą miejscowości faktycznie używane); (b) hurtowo zewnętrznym
zbiorem punktów (np. Państwowy Rejestr Granic / punkty adresowe z Geoportalu) — **niezweryfikowane** (licencja, format), więc poza zakresem; zapisane jako opcja
w ADR-012. Hurtowe geokodowanie 100 tys. miejscowości przez publiczny Nominatim jest niedopuszczalne (polityka OSMF: bulk „not encouraged”, jednorazowo tylko z cache,
jeden wątek; cyklicznie max 4 zapytania/min).

### 3. Projekt `GeocodingService`

Polityka Nominatim (potwierdzona na operations.osmfoundation.org/policies/nominatim, 30.10.2026): maks. **1 zapytanie/s łącznie dla całej aplikacji**
(suma ruchu wszystkich użytkowników); **poprawny User-Agent lub Referer identyfikujący aplikację** (domyślne nagłówki bibliotek HTTP są niedopuszczalne);
zalecane cache; powtarzanie tych samych zapytań grozi blokadą; **zakaz implementowania autouzupełniania po stronie klienta na API publicznego Nominatim**;
dane na licencji ODbL → atrybucja „© OpenStreetMap contributors” przy mapie i przy współrzędnych pochodzących z Nominatim. Polityka może się zmienić bez uprzedzenia.
**Wniosek architektoniczny:** autouzupełnianie miejscowości obsługujemy WYŁĄCZNIE z lokalnej bazy TERYT (pkt 4); Nominatim jest wołany tylko po zatwierdzeniu wyboru/adresu.

**Klasy (`app/Domain/Geo/`):**
- `Contracts/GeocodingProvider` — interfejs `search(AddressQuery): ProviderResult` (wymienny dostawca, ADR-002 zakłada możliwą zmianę).
- `Services/NominatimProvider` — wywołanie HTTP (`Http::withUserAgent(...)->timeout(5)->connectTimeout(3)->acceptJson()`), bez `retry`.
- `Services/GeocodingService` — publiczne API aplikacji: `geocodeAddress(AddressQuery): GeocodeResult`, `geocodeLocality(TerytLocality): ?Coordinates`.
- `Data/AddressQuery` (readonly DTO: `street`, `houseNumber`, `postalCode`, `city`, `simc`), `Data/GeocodeResult` (`status` Enum `GeocodeStatus::Found|NotFound|Unavailable`, `lat`, `lng`, `precision`, `fromCache`), `Data/Coordinates`.
- `Support/NominatimThrottle` — blokada 1 zapytania/s (poniżej).
- Konfiguracja `config/geocoding.php`: `provider`, `base_url` (`NOMINATIM_URL`, domyślnie `https://nominatim.openstreetmap.org`, zmiana na własny serwer bez zmiany kodu), `contact` (`NOMINATIM_CONTACT_EMAIL`, **wymagane na produkcji**), `timeout`, `ttl_found_days=180`, `ttl_not_found_days=7`, `min_interval_ms=1100`, `cooldown_seconds=300`.

**Przepływ `geocodeAddress`:**
1. Walidacja wejścia i normalizacja (`SearchText::normalize` dla pól tekstowych) → `query_hash`.
2. Odczyt `geocode_cache` (niewygasły) → zwrot z `fromCache=true`, **bez żadnego ruchu sieciowego**.
3. Sprawdzenie „wyłącznika”: klucz `geocoding:down` w cache (ustawiany po awarii) → zwróć `Unavailable` od razu.
4. `NominatimThrottle::run(callable)`: `Cache::lock('nominatim', 10)->block(5)`; w sekcji krytycznej odczyt `nominatim:last_at` (mikrosekundy), `usleep` do upływu `min_interval_ms` od ostatniego zapytania, wykonanie zapytania, zapis `last_at`. Lock w `cache_locks` (driver `database`, bez Redisa, działa między procesami PHP). Gdy nie uda się uzyskać blokady w 5 s → `Unavailable` (żądanie webowe nie wisi dłużej).
5. Zapytanie: `GET /search?format=jsonv2&limit=1&countrycodes=pl&accept-language=pl&addressdetails=0` + parametry strukturalne `street="{numer} {ulica}"`, `city`, `postalcode`. Gdy zero wyników: ponowna próba z samym `city`+`postalcode` (dokładność `locality`) — to drugie zapytanie też przechodzi przez throttle. Bez parametru `q` z wolnym tekstem użytkownika.
6. Walidacja odpowiedzi: współrzędne liczbowe, w prostokącie Polski (lat 48,9–54,9; lng 14,0–24,2), inaczej traktuj jako `NotFound`. `precision` z `addresstype` (`house|building` → `address`, `road|street` → `street`, reszta → `locality`).
7. Zapis do `geocode_cache` (`found` lub `not_found`).

**Zachowanie przy awarii:** timeout / połączenie / HTTP 5xx → `Unavailable`, ustaw `geocoding:down` na `cooldown_seconds` (domyślnie 5 min, żeby nie bombardować usługi i nie spowalniać stron);
HTTP 429 / 403 → `Unavailable` i cooldown 30 min oraz wpis `Log::warning` (jedna linia na okno cooldownu, nie na każde żądanie); HTTP 4xx inne → `Unavailable` + log. Błędy NIE są cache’owane jako „nie znaleziono”.
Serwis NIGDY nie rzuca wyjątku do UI. Zachowanie wywołującego (Action zapisu nieruchomości, przyszłe zadanie): przy `Unavailable` zapisz nieruchomość bez współrzędnych, ustaw
`geocoding_pending`, a dopełnienie robi zadanie kolejki `GeocodeProperty` (ponawia z backoffem 5/15/60 min, max 5 prób; kolejka `database` + cron, ADR-003); do czasu — użyj środka miejscowości
(`teryt_localities.lat/lng`), a jeśli i tego brak, UX pokazuje ręczne ustawienie pinezki. Wyszukiwarka fachowców w razie braku współrzędnych miejscowości zwraca wyniki po gminie/TERYT bez sortowania po odległości.

**Prywatność:** do Nominatim wysyłamy wyłącznie: ulicę, numer, kod pocztowy, miejscowość, kraj. Nigdy: imię i nazwisko, e-mail, telefon, ID użytkownika, IP klienta (IP widoczne dla Nominatim to IP serwera).
User-Agent: `kontroladomu.pl/{wersja} (+https://kontroladomu.pl; {NOMINATIM_CONTACT_EMAIL})`. Logi aplikacji nie zawierają adresu, tylko `query_hash`. Funkcja „Użyj mojej lokalizacji” korzysta z przeglądarki, nie z Nominatim (bez geokodowania odwrotnego).
Wpis do `docs/RODO_REJESTR.md`: odbiorca danych (OSM Foundation / Nominatim), zakres (adres), cel, brak profilowania — **zadanie dla koordynatora/audytora**.

**Testy (dla testera):** `Http::fake()` dla sukcesu, braku wyniku, 429, 500, timeoutu, współrzędnych poza Polską; cache hit bez żądania HTTP; sprawdzenie nagłówka `User-Agent`; sprawdzenie, że żądanie
nie zawiera danych użytkownika; test throttlingu na `Carbon::setTestNow` / odstępie zapisanym w cache (bez prawdziwego `usleep` w teście — przez wstrzykiwany `Sleeper`).

### 4. Kontrakt endpointu autouzupełniania miejscowości

- **Metoda i ścieżka:** `GET /miejscowosci/szukaj` (trasa `localities.search`, w `routes/web.php`). Zwykła trasa web bez sesji CSRF (GET); wersja API (`/api/v1/localities`) zostanie dodana w Etapie PWA jako ten sam kontroler/Action pod innym prefiksem.
- **Dostęp:** publiczny (gość też wyszukuje). Middleware: `throttle:locality-search` (RateLimiter w `AppServiceProvider`): **60 żądań/min na IP** (typowe pisanie z debounce 250 ms to ~4/s przez krótki czas — limit 60/min z `Limit::perMinute(60)` + dodatkowo burst `perSecond(8)`). Przekroczenie → 429 z `Retry-After` i JSON `{"message": "Zbyt wiele zapytań. Spróbuj za chwilę."}`.
- **Parametry (Form Request `SearchLocalitiesRequest`, `app/Http/Requests/`):**
  | Parametr | Typ | Reguła | Domyślnie |
  |---|---|---|---|
  | `q` | string | wymagany, min 2 i max 60 znaków po `trim`; dozwolone litery (Unicode), cyfry, spacja, `-`, `.`, `,`, `'` | — |
  | `limit` | int | 1–15 | 10 |
  | `include_parts` | bool | opcjonalny | `false` |
  Błąd walidacji → 422 w standardowym formacie Laravela.
- **Działanie:** `q` → `SearchText::normalize`. Dopasowanie: `name_search LIKE 'q%'` (znaki `%` `_` `\` w `q` są escapowane, wzorzec parametryzowany, bez `whereRaw`).
  Gdy `q` zawiera spację i wyników jest mniej niż `limit`, dodatkowo: pierwszy człon jako prefiks nazwy, a reszta jako prefiks nazwy gminy lub powiatu (`teryt_municipalities.name_search`/`county_name`), co pozwala wpisać „dabrowka dopiewo”.
  Filtry: `is_active = 1`, `is_part = 0` (chyba że `include_parts`). Sortowanie: dokładne dopasowanie nazwy najpierw, potem miasta przed wsiami, potem alfabetycznie po `name_search`, potem po nazwie gminy. Zapytanie z `with('municipality')`, limit stosowany w SQL; bez N+1.
- **Odpowiedź 200** (`Content-Type: application/json`, `Cache-Control: public, max-age=3600`; zapytania są powtarzalne, a dane zmieniają się rzadko):
  ```json
  {
    "data": [
      {
        "simc": "0950123",
        "name": "Dąbrówka",
        "label": "Dąbrówka, gm. Dopiewo, pow. poznański, woj. wielkopolskie",
        "kind": "village",
        "kind_label": "wieś",
        "slug": "dabrowka-gm-dopiewo",
        "municipality": "Dopiewo",
        "county": "poznański",
        "voivodeship": "wielkopolskie",
        "lat": 52.3,
        "lng": 16.6,
        "has_coordinates": true
      }
    ]
  }
  ```
  (wartości przykładowe, nie z bazy). Brak wyników → `{"data": []}` i 200. `lat/lng` mogą być `null` (miejscowość jeszcze nie zgeokodowana); klient wtedy po wyborze woła akcję serwerową, która uzupełni współrzędne (`ResolveLocalityCoordinates`), a nie Nominatim bezpośrednio z przeglądarki.
  Nazwy rodzajów (`kind_label`) z `lang/pl/geo.php`. Nigdy nie zwracamy `id` wewnętrznych, tylko `simc` i `slug`.
- **Logi:** tekst `q` nie jest logowany. Autoryzacja: brak (dane publiczne); Policy nie dotyczy.
- **Wydajność:** zapytanie prefiksowe po indeksie `teryt_localities_search_idx`, zwrot ≤15 wierszy; dla 2-literowych prefiksów (np. „kr”, kilka tysięcy wierszy) sortowanie w pamięci jest akceptowalne (sprawdzić `EXPLAIN` w teście ręcznym). Cloudflare może cache’ować odpowiedzi (nagłówek `Cache-Control`).

### 5. Kopie zapasowe — `spatie/laravel-backup`

**Wersja (sprawdzone w Packagist API, 30.10.2026):** `spatie/laravel-backup` **10.3.3** (14.09.2026), licencja MIT, wymaga `php ^8.3`, `illuminate/* ^12.40|^13.0`, `ext-zip`,
`spatie/db-dumper ^4.0`, `spatie/laravel-signal-aware-command ^2.1`, `spatie/temporary-directory ^2.3`. Zainstalowany Laravel 12.70.2 spełnia `^12.40`.
Uwaga na pułapkę: wydania 10.0.0–10.1.1 wymagały PHP ^8.4 (README nadal tak twierdzi); od **10.2.0** (19.03.2026) wymóg to znów PHP ^8.3. Constraint w `composer.json`: **`^10.3`**
(linia 9.4.x też działa na PHP 8.3 i Laravel 12.40+, ale 10.x ma bieżące poprawki). Wymagane rozszerzenie PHP **zip** (sprawdzić lokalnie w PHP 8.3 z WinGet i na home.pl: `php -m | findstr zip`).

**Konfiguracja (`config/backup.php`, po `vendor:publish --tag=backup-config`):**
- `backup.name` = `kontroladomu`; `source.databases` = `[env('DB_CONNECTION')]`.
- `source.files.include` = `storage/app/private` (dokumenty, protokoły, zdjęcia) + **`.env`** (zawiera `APP_KEY`; jego utrata = bezpowrotna utrata sekretów 2FA i ustawień z `settings`, ADR-005) — archiwum jest **szyfrowane**;
  `exclude` = `vendor`, `node_modules`, `storage/framework`, `storage/logs`, `storage/app/backups`, `storage/app/private/teryt-import` (pliki wejściowe TERYT), `public/build` (odtwarzalne z gita), `.git`.
- `backup.password` = `env('BACKUP_ARCHIVE_PASSWORD')`, `encryption` = `default` (AES-256 przez ZipArchive). Hasło trzymać poza serwerem (menedżer haseł); bez niego kopii nie otworzy nikt, także właściciel.
- `destination.disks` = `array_filter(explode(',', env('BACKUP_DISKS', 'backups')))`. Nowy dysk `backups` w `config/filesystems.php`: `driver local`, `root storage_path('app/backups')` (poza `public/`, NIE na dysku `public`). Kopia poza serwerem = drugi dysk dopisany do `BACKUP_DISKS` (opcje w pyt. 3).
- `dump`: `dump_binary_path` z `.env` (`DB_DUMP_BINARY_PATH`, na Windows XAMPP: `C:\xampp\mysql\bin`; lokalna MariaDB 3307 ma własną ścieżkę), `use_single_transaction` = true, `timeout` = 300 s, `add_extra_option` dla MariaDB w razie potrzeby.
- `cleanup.strategy` = `DefaultStrategy`, **`keep_all_backups_for_days = 14`**, pozostałe `keep_daily/weekly/monthly/yearly` = 0 (czyli po 14 dniach usuwane — zgodnie z CLAUDE.md §5.6), `delete_oldest_backups_when_using_more_megabytes_than` = wartość z pyt. 4 (domyślnie 3000 MB, ale musi być mniejsza niż limit dysku home.pl, do sprawdzenia).
- `notifications`: tylko kanał `mail` na `env('BACKUP_NOTIFY_EMAIL')` dla zdarzeń: `BackupHasFailed`, `CleanupHasFailed`, `UnhealthyBackupWasFound`; zdarzenia „Successful” wyłączone (szum). Powiadomienia przez kolejkę (`queue`), ustawić nadawcę z `config('mail.from')`.
- `monitor_backups`: `max_age_in_days = 2` dla bazy, `maximum_storage_in_megabytes` jak wyżej.

**Harmonogram (`routes/console.php`, dopisać obok istniejących wpisów; cron nadal tylko `schedule:run` co minutę, ADR-003):**
| Zadanie | Kiedy | Uwagi |
|---|---|---|
| `backup:run --only-db` | codziennie 02:00 | `->withoutOverlapping(120)`; `->onFailure` zapisuje log |
| `backup:run --only-files` | niedziela 02:30 | j.w.; pliki + `.env` |
| `backup:clean` | codziennie 02:50 | retencja 14 dni |
| `backup:monitor` | codziennie 04:00 | alarm gdy brak świeżej kopii |
| `geocode:prune` | codziennie 03:40 | usuwa wygasłe wiersze `geocode_cache` |
Godzina w harmonogramie jest w strefie `config('app.timezone')` (obecnie UTC, `config/app.php`); 02:00 UTC = 03:00/04:00 w Polsce — akceptowalne (ruch minimalny). Nie przestawiamy `app.timezone` (CLAUDE.md §7.9: baza w UTC).
`schedule:run` wykonuje zadania synchronicznie w jednym procesie crona: długi `backup:run` opóźni w tej minucie `queue:work` (ma `withoutOverlapping`, więc kolejne wywołanie go pomija) — przy małej bazie to kilka–kilkadziesiąt sekund, akceptowalne w nocy.

**Przed migracją produkcyjną (CLAUDE.md §5.5):** ręcznie `php artisan backup:run --only-db` — opisać w rozdziale „Wdrożenie”.

**Niepotwierdzone (do sprawdzenia na koncie home.pl, krok „Weryfikacja hostingu”):** (1) czy dostępny binarny `mysqldump` oraz czy PHP może uruchamiać procesy (`proc_open`/`exec` — spatie używa Symfony Process); (2) limit `max_execution_time`/czasu crona i pamięci; (3) limit miejsca na dysku i limit rozmiaru plików;
(4) dostęp SSH. **Plan B, gdy `mysqldump`/`proc_open` są zablokowane:** czysto-PHP-owy zrzut bazy (np. `ifsnop/mysqldump-php`, MIT — wymaga osobnego ADR i NIE jest dodawany teraz) albo korzystanie z wbudowanych kopii panelu home.pl plus ręczny eksport; obie opcje wymagają decyzji właściciela.

### Pliki

Nowe (ścieżki, bez kodu):
- `database/migrations/2026_10_30_200001_create_settings_table.php`
- `database/migrations/2026_10_30_200002_create_teryt_municipalities_table.php`
- `database/migrations/2026_10_30_200003_create_teryt_localities_table.php`
- `database/migrations/2026_10_30_200004_create_geocode_cache_table.php`
- `app/Domain/Settings/Models/Setting.php`, `app/Domain/Settings/SettingsRepository.php`
- `app/Domain/Geo/Models/TerytMunicipality.php`, `app/Domain/Geo/Models/TerytLocality.php`, `app/Domain/Geo/Models/GeocodeCacheEntry.php`
- `app/Domain/Geo/Enums/LocalityKind.php`, `app/Domain/Geo/Enums/GeocodeStatus.php`, `app/Domain/Geo/Enums/GeocodePrecision.php`
- `app/Domain/Geo/Actions/ImportTerytData.php` (logika importu), `app/Domain/Geo/Actions/SearchLocalities.php`, `app/Domain/Geo/Actions/ResolveLocalityCoordinates.php`
- `app/Domain/Geo/Contracts/GeocodingProvider.php`, `app/Domain/Geo/Services/NominatimProvider.php`, `app/Domain/Geo/Services/GeocodingService.php`, `app/Domain/Geo/Support/NominatimThrottle.php`, `app/Domain/Geo/Support/SearchText.php`
- `app/Domain/Geo/Data/AddressQuery.php`, `GeocodeResult.php`, `Coordinates.php`, `TerytRow` (DTO wiersza pliku)
- `app/Console/Commands/ImportTerytCommand.php` (`teryt:import`), `app/Console/Commands/PruneGeocodeCacheCommand.php` (`geocode:prune`)
- `app/Http/Controllers/LocalitySearchController.php`, `app/Http/Requests/SearchLocalitiesRequest.php`, `app/Http/Resources/LocalityResource.php`
- `config/geocoding.php`, `config/backup.php` (publikowany)
- `lang/pl/geo.php` (nazwy rodzajów miejscowości, komunikaty błędów)
- `database/factories/TerytLocalityFactory.php`, `TerytMunicipalityFactory.php`; `tests/Fixtures/teryt/{TERC,SIMC}.csv` (SYNTETYCZNE, ok. 30 wierszy, z polskimi znakami, częścią miejscowości, kolizją nazw „Dąbrówka”)
- Testy: `tests/Feature/Geo/{ImportTerytTest,LocalitySearchTest,GeocodingServiceTest}.php`, `tests/Unit/Geo/SearchTextTest.php`, `tests/Feature/Backup/BackupScheduleTest.php`, `tests/Feature/Settings/SettingsRepositoryTest.php`

Zmieniane:
- `composer.json` / `composer.lock` (`spatie/laravel-backup:^10.3`)
- `routes/web.php` (trasa `localities.search`), `routes/console.php` (harmonogram)
- `app/Providers/AppServiceProvider.php` (RateLimiter `locality-search`)
- `config/filesystems.php` (dysk `backups`), `.env.example`, `.env.production.example` (`NOMINATIM_URL`, `NOMINATIM_CONTACT_EMAIL`, `BACKUP_*`, `DB_DUMP_BINARY_PATH`)
- `app/Support/Security/ProductionConfigGuard.php` (na produkcji wymaga `NOMINATIM_CONTACT_EMAIL`, `BACKUP_ARCHIVE_PASSWORD`, `BACKUP_NOTIFY_EMAIL`)
- `.gitignore` (`storage/app/private/teryt-import/`, `storage/app/backups/`)
- `docs/DOKUMENTACJA_TECHNICZNA.md` (diagram ER — już zaktualizowany; rozdziały „Wdrożenie”, „Kopie zapasowe”, „Harmonogram i kolejki”), `docs/RODO_REJESTR.md` (Nominatim jako odbiorca; TERYT jako źródło), `CHANGELOG.md`, `docs/DECYZJE.md` (ADR-012…015 — już dopisane).

### Kolejność
0. **Krok 0 (właściciel + backend, ok. 30 min, blokuje 3A):** pobrać z eTeryt „Pliki pełne” SIMC (wersja podstawowa/urzędowa) i TERC, rozpakować, sprawdzić nagłówek, kodowanie, separator oraz kody RM; wynik wpisać w sekcji „Backend”. Równolegle właściciel wysyła pytanie licencyjne do GUS (pyt. 1) i sprawdza w panelu home.pl pkt „Niepotwierdzone” z sekcji 5 (`mysqldump`, `proc_open`, zip, SSH, limity).
1. **3C `settings`** (migracja, model, repozytorium, testy z szyfrowaniem: w bazie nie ma jawnego tekstu sekretu).
2. **3A migracje TERYT → enumy i `SearchText` (z testami jednostkowymi) → `ImportTerytData` + komenda na fixture → `SearchLocalities` + kontroler + rate limiter → test importu prawdziwego pliku lokalnie (pomiar czasu i pamięci, zapis w sekcji Backend).**
3. **3B `geocode_cache`, `config/geocoding.php`, provider, throttle, `GeocodingService`, `ResolveLocalityCoordinates`, `geocode:prune` + testy z `Http::fake()`.**
4. **3D backup:** `composer require spatie/laravel-backup:^10.3` (przez `composer83.cmd`), publikacja configu, dysk `backups`, harmonogram, `.env.*`, test konfiguracji; uruchomienie `backup:run --only-db` lokalnie (wymaga `mysqldump` z XAMPP/MariaDB i `ext-zip` w PHP 8.3 — sprawdzić!).
5. Dokumentacja (Wdrożenie, Kopie zapasowe, ER, RODO_REJESTR), potem tester, audytor (dotyka plików, zewnętrznego API, haseł szyfrowania — przegląd obowiązkowy).
Obowiązują komendy przez PHP 8.3: `& $php83 artisan …` i `composer83.cmd` (docs/DOKUMENTACJA_TECHNICZNA.md §2).

### Ryzyka
| # | Ryzyko | Skutek | Środek zaradczy |
|---|---|---|---|
| R1 | **Licencja TERYT niepotwierdzona** | spór prawny przy publikacji/redystrybucji | pytanie do GUS (pyt. 1); nie commitować plików; atrybucja w stopce; dane używane wewnętrznie jako słownik |
| R2 | Struktura pliku SIMC/TERC inna niż zakładam | import nie działa | krok 0; czytanie po nazwach kolumn; czytelne błędy; fixture odzwierciedla realny nagłówek po kroku 0 |
| R3 | Brak SSH / limity czasu na home.pl | import niemożliwy na serwerze | `--max-seconds`/`--resume-from`; plan awaryjny: import lokalny + zrzut tabel przez phpMyAdmin |
| R4 | Brak `mysqldump` lub `proc_open` na home.pl | `backup:run` zawodzi | weryfikacja hostingu przed wdrożeniem; plan B (PHP-owy dumper, osobny ADR) |
| R5 | Nominatim zablokuje serwer (429/403) | brak współrzędnych nowych adresów | throttle 1/s, cache, `geocoding:down`, User-Agent z kontaktem, kolejka z backoffem; wyjście: własny Nominatim lub płatny dostawca (ADR-002); polityka może się zmienić |
| R6 | Wolne wyszukiwanie przy krótkich prefiksach | opóźnienia autouzupełniania | indeks prefiksowy, `limit`, min 2 znaki, Cache-Control, rate limit; w razie potrzeby podniesienie minimum do 3 znaków |
| R7 | Dysk home.pl za mały na kopie (zip bazy + plików × 14 dni) | zapełniony hosting, awaria aplikacji | limit `delete_oldest_backups…`, monitor; kopia poza serwerem; pomiar rozmiaru po pierwszej kopii |
| R8 | Kopia w tej samej lokalizacji co aplikacja | włamanie/awaria dysku niszczy też kopie | kopia poza serwerem (pyt. 3) jest wymagana przed produkcją; do czasu — ręczne pobieranie tygodniowe |
| R9 | Hasło archiwum zgubione albo `.env` w kopii niezaszyfrowanej | brak możliwości odzyskania / wyciek sekretów | szyfrowanie AES-256 obowiązkowe, `ProductionConfigGuard` wymaga hasła, instrukcja przechowywania hasła w menedżerze haseł; test przywracania co kwartał |
| R10 | Zmiana `APP_KEY` unieważnia `settings.secret_value` i 2FA | utrata konfiguracji SMTP i 2FA | zapisane w dokumentacji; `.env` jest w kopii (szyfrowanej) |
| R11 | Geokodowanie przy zapisie nieruchomości blokuje żądanie | wolne formularze | geokodowanie w kolejce; wywołanie synchroniczne tylko z limitem 5 s |
| R12 | RODO: adres w cache i w żądaniu do zewnętrznej usługi | brak podstawy/informacji | tylko skrót + liczby w cache; wpis w rejestrze przetwarzania i polityce prywatności (Nominatim jako odbiorca); brak danych osobowych w zapytaniu |
| R13 | Zip PHP 8.3 lokalnie bez `ext-zip` | `composer require` odmówi | sprawdzić `php -m` przed instalacją; włączyć w `php.ini` PHP 8.3 (nie w XAMPP) |
| R14 | Duplikaty nazw miejscowości (np. „Dąbrówka” ×ok. 100) | użytkownik wybiera złą | etykieta z gminą i powiatem, slug z gminą, sortowanie; UX decyduje o prezentacji |
| R15 | Obcięty plik SIMC dezaktywuje większość miejscowości | wyszukiwarka pusta | próg 90% wierszy przed `is_active=false`, `--dry-run`, kopia bazy przed importem produkcyjnym |

### Pytania do właściciela (z domyślnymi odpowiedziami)
1. **Licencja TERYT.** Czy wyślesz zapytanie do GUS („Pytanie do GUS” lub infolinia 22 279 99 99): „Czy dane z rejestru TERYT (pliki pełne SIMC i TERC) mogą być
   wykorzystywane komercyjnie w serwisie internetowym jako słownik miejscowości do autouzupełniania, na jakiej licencji i z jaką atrybucją?”.
   **Domyślnie:** tak, wyślesz; do odpowiedzi używamy danych wewnętrznie, z podaniem źródła „GUS, rejestr TERYT”, bez udostępniania zbioru.
2. **Gdzie zrobić import produkcyjny?** **Domyślnie:** lokalnie + zrzut tabel przez phpMyAdmin (działa bez SSH); jeśli home.pl daje SSH, przechodzimy na `teryt:import` na serwerze.
3. **Kopia poza serwerem — dokąd?** Opcje: (a) ręczne pobieranie tygodniowej kopii przez Ciebie (0 zł, 0 nowych paczek), (b) drugi serwer FTP/SFTP (paczka `league/flysystem-sftp-v3`, MIT), (c) magazyn zgodny z S3, np. Backblaze B2 lub Cloudflare R2 (paczka `league/flysystem-aws-s3-v3`, MIT, kilka zł/mies.).
   **Domyślnie:** (a) na start (kopie lokalne w `storage/app/backups` + ręczne pobranie), a przed uruchomieniem produkcji wybierasz (b) lub (c); dodanie paczki to osobny wpis w DECYZJE.
4. **Ile miejsca masz na koncie home.pl?** (limit dysku i bazy). **Domyślnie:** zakładamy 3000 MB na kopie; po pierwszej kopii zmierzymy realny rozmiar i skorygujemy limit.
5. **Adres e-mail do alarmów o kopiach i kontaktowy dla Nominatim.** **Domyślnie:** ten sam adres administratora (np. adres z konta właściciela), po jednym w `.env` (`BACKUP_NOTIFY_EMAIL`, `NOMINATIM_CONTACT_EMAIL`).
6. **Hasło archiwum kopii** (min. 20 znaków) — wygenerujesz i zapiszesz w menedżerze haseł poza serwerem? **Domyślnie:** tak; backend wygeneruje przykład w dokumentacji, ale nie w repozytorium.
7. **Czy części miejscowości (osiedla, przysiółki) mają być widoczne w autouzupełnianiu?** **Domyślnie:** nie (tylko miasta, wsie, kolonie, osady); dostępne po przełączniku `include_parts`, bo adres w przysiółku jest wpisywany w polu „ulica/adres”.
8. **Co zrobić, gdy Nominatim nie znajdzie adresu?** **Domyślnie:** użyj środka miejscowości i pokaż klientowi „Pozycja przybliżona, możesz ją poprawić na mapie” (decyzja UX; nie blokuje zapisu).

## UX
<!-- Przepływ, ekrany (desktop/mobile), stany, teksty, kryteria akceptacji UX -->

## Backend
Data: 2026-10-30. Kolejność wykonania: krok 0, 3C, 3A, 3B, 3D. Bez commitów. Decyzje formalne i odstępstwa od projektu: ADR-016 w `docs/DECYZJE.md`.

### Decyzje właściciela z 30.10.2026 (zapisane)
- **(a) Adresy e-mail jeszcze nieznane.** Dwie osobne zmienne, obie puste w `.env.example` i `.env.production.example`, z komentarzem po polsku: `NOMINATIM_CONTACT_EMAIL` (kontakt do User-Agent) i `BACKUP_NOTIFICATION_EMAIL` (alarmy o kopiach).
  - Puste `BACKUP_NOTIFICATION_EMAIL`: kopie działają, powiadomienia pakietu są wyłączone (kanały `[]`, odbiorcy `[]`). Niepoprawny adres jest ignorowany tak samo. Sprawdzanie ręczne: `php artisan backup:list`.
  - Puste `NOMINATIM_CONTACT_EMAIL`: `GeocodingService` NIE wysyła żadnych zapytań do publicznego Nominatim (zwraca `Unavailable` z powodem `not_configured`, jedna linia ostrzeżenia na dobę w logu). Wyniki z cache nadal działają. Własny serwer Nominatim (`NOMINATIM_URL` z innym hostem) działa bez kontaktu.
  - `ProductionConfigGuard`: adresów NIE wymaga (aplikacja startuje), ale wymaga `BACKUP_ARCHIVE_PASSWORD` min. 20 znaków (kopia plików zawiera `.env` z `APP_KEY`). Dopisane testy w `AuditFixesTest` (+ bazowa konfiguracja w `ReAuditFixesTest`).
- **(b) Kopia poza serwerem:** na razie ręczne pobieranie z `storage/app/backups`. Dysk docelowy to lista `BACKUP_DISKS`, więc drugi dysk (SFTP/S3) dopisze się później po osobnym wpisie w DECYZJE. Żadnych paczek SFTP/S3.
- **(c) Licencja TERYT:** zapytanie do GUS wysyła właściciel (`docs/GUS_ZAPYTANIE_LICENCJA.md`). Po każdym imporcie zapisuje się `teryt.source = "GUS, rejestr TERYT"` (tekst z `lang/pl/geo.php`, klucz `data_source`). Plików GUS nie ma w repozytorium.
- **(d) Pozostałe pytania:** domyślne odpowiedzi architekta (części miejscowości domyślnie ukryte, `include_parts` je pokazuje; 3000 MB na kopie; brak wyniku Nominatim = środek miejscowości po stronie wywołującego).

### Krok 0: prawdziwe pliki GUS (sprawdzone)
Pliki udało się pobrać automatycznie (formularz ASP.NET „Pliki pełne”, wersja urzędowa, stan 30.10.2026; pole daty przyjmuje format `30 października 2026`). Archiwa zawierają CSV i XML. Importer i komenda zostały uruchomione na PRAWDZIWYCH plikach (nie tylko na fixturze).
- Format CSV: **UTF-8 z BOM**, separator **średnik**, końce linii CRLF, pola bez cudzysłowów. Dwa wiersze SIMC mają niezamknięte `"` w nazwie (`Mazewo Dworskie"B"`), dlatego `fgetcsv` odpada.
- TERC: `WOJ;POW;GMI;RODZ;NAZWA;NAZWA_DOD;STAN_NA`; 4361 wierszy: województwa (POW puste), powiaty (GMI puste), **3964 gminy**. Gmina miejsko-wiejska ma trzy wiersze (RODZ 3, 4, 5). Nazwy województw wielkimi literami (zapisujemy małymi).
- SIMC: `WOJ;POW;GMI;RODZ_GMI;RM;MZ;NAZWA;SYM;SYMPOD;STAN_NA`; **101 865 miejscowości** (zgodnie z danymi GUS), SYM unikalne, najdłuższa nazwa 44 znaki. Klucz gminy w SIMC = WOJ+POW+GMI+RODZ_GMI i zawsze występuje w TERC (RODZ_GMI w SIMC: 1, 2, 4, 5, 8, 9; nigdy 3). Wszystkie wiersze SIMC znalazły gminę (0 pominiętych).
- Słownik RM (plik WMRODZ): 00 część, 01 wieś, 02 kolonia, 03 przysiółek, 04 osada, 05 osada leśna, 06 osiedle, 07 schronisko turystyczne, 95 dzielnica (Warszawa), 96 miasto, 98 delegatura, 99 część miasta. Rozkład po imporcie: miasto 1026, wieś 43 641, kolonia 3353, przysiółek 4367, osada 5785, osada leśna 2102, dzielnica/delegatura 37, część 41 523, inne 31 (osiedle, schronisko).
- **Uwaga:** kolonie, przysiółki i osady mogą mieć `SYMPOD` ≠ `SYM` (są częścią innej wsi). Zgodnie z projektem (`is_part` = RM 00/99 lub SYMPOD ≠ SYM) są wtedy ukryte w domyślnym autouzupełnianiu: ukrytych jest 47 388, widocznych 54 477 (z 101 865).
- Warszawskie dzielnice i delegatury (RM 95/98, np. „Poznań-Grunwald”) nie są częściami, więc pojawiają się w podpowiedziach jako rodzaj `district`. Do decyzji UX, czy je ukrywać.

### Pomiary importu (lokalna MariaDB 10.11.19, PHP 8.3, 30.10.2026)
- Pierwszy import 101 865 miejscowości + 3964 gmin: ok. **9 s**; kolejny (aktualizacja): ok. 8 s. Szczytowe zużycie pamięci ok. **80 MB** (limit CLI PHP 8.3 tutaj: 128 MB; ok. 20 MB to framework, ok. 20 MB zbiór widzianych SIMC do wyłączania brakujących, reszta bufory porcji). `--dry-run` (bez zapisu): 3 s, ok. 42 MB.
- Rozmiar tabeli `teryt_localities` z indeksami: ok. 34 MB.
- Znaleziona i usunięta wada: obiekty Carbon w bindingach `upsert` powodowały liniowy wzrost pamięci (ok. 0,8 MB na porcję); teraz daty są tekstem (`toDateTimeString()`), zużycie ustabilizowane.
- Wyszukiwanie na pełnych danych (MariaDB): prefiks 2-literowy „kr” 24 ms, „dabrowka” 2 ms, „Dąbrówka dopiewo” 4 ms; `EXPLAIN` używa `teryt_localities_search_idx` (zakres ok. 1900 wierszy, sortowanie w pamięci).
- NIE zmierzono na home.pl (brak dostępu): limit pamięci i czasu PHP CLI, SSH. Plan awaryjny (import lokalny + zrzut przez phpMyAdmin) pozostaje w mocy; `--max-seconds`/`--resume-from` działają (przetestowane na fixturze).

### Migracje (4, dodające; wykonane na lokalnej bazie `kontroladomu`, testy używają SQLite w pamięci)
- `2026_10_30_200001_create_settings_table` (3C)
- `2026_10_30_200002_create_teryt_municipalities_table` (+ kolumna `county_name_search`, odstęp od projektu)
- `2026_10_30_200003_create_teryt_localities_table` (indeks `teryt_localities_search_idx` na (`name_search`, `is_part`))
- `2026_10_30_200004_create_geocode_cache_table` (tylko skrót SHA-256 i liczby, bez tekstu adresu)

### Nowe i zmienione pliki
**3C settings:** `app/Domain/Settings/Models/Setting.php` (cast `encrypted` na `secret_value`, sekret w `$hidden`), `app/Domain/Settings/SettingsRepository.php` (`get`, `getSecret`, `put`, `putSecret`, `forget`, `flush`; cache `settings.all` tylko z wartościami jawnymi, sekret nie trafia do cache; zły `APP_KEY` = zwrot wartości domyślnej, nie wyjątek), `tests/Feature/Settings/SettingsRepositoryTest.php`.

**3A TERYT:** `app/Domain/Geo/{Models/TerytMunicipality,Models/TerytLocality,Enums/LocalityKind,Support/SearchText,Support/TerytCsvReader,Exceptions/TerytImportException,Data/TerytImportResult,Actions/ImportTerytData,Actions/SearchLocalities}.php`, `app/Console/Commands/ImportTerytCommand.php`, `app/Http/{Controllers/LocalitySearchController,Requests/SearchLocalitiesRequest,Resources/LocalityResource}.php`, `lang/pl/geo.php`, fabryki `database/factories/Teryt{Municipality,Locality}Factory.php`, fixtury `tests/Fixtures/teryt/{TERC,SIMC}.csv` (SYNTETYCZNE, 12 i 24 wiersze, format GUS), `.gitattributes` (`-text` dla fixtur), testy `tests/Unit/Geo/SearchTextTest.php`, `tests/Feature/Geo/{ImportTerytTest,LocalitySearchTest}.php`.

**3B geokodowanie:** `config/geocoding.php`, `app/Domain/Geo/{Models/GeocodeCacheEntry,Enums/GeocodeStatus,Enums/GeocodePrecision,Contracts/GeocodingProvider,Data/{AddressQuery,Coordinates,GeocodeResult,ProviderResult},Services/{NominatimProvider,GeocodingService},Support/{NominatimThrottle,Sleeper},Actions/ResolveLocalityCoordinates}.php`, `app/Console/Commands/PruneGeocodeCacheCommand.php`, `tests/Feature/Geo/GeocodingServiceTest.php`.

**3D kopie:** `composer.json`/`composer.lock` (`spatie/laravel-backup ^10.3` = 10.3.3, MIT), `config/backup.php` (publikowany i dostosowany), `config/filesystems.php` (dysk `backups`), `config/database.php` (`dump` w połączeniach mysql/mariadb), `tests/Feature/Backup/BackupScheduleTest.php`.

**Wspólne:** `app/Providers/AppServiceProvider.php` (bind `GeocodingProvider`, limiter `locality-search`), `routes/web.php`, `routes/console.php`, `app/Support/Security/ProductionConfigGuard.php`, `.env.example`, `.env.production.example`, `.gitignore` (`storage/app/private/teryt-import/`, `storage/app/backups/`), `phpunit.xml` (puste zmienne NOMINATIM/BACKUP w testach), `tests/Pest.php` (helper `importTerytFixture()`), `tests/Feature/Accounts/{AuditFixesTest,ReAuditFixesTest}.php` (strażnik), `docs/DECYZJE.md` (ADR-016).

### Komendy i harmonogram (godziny w UTC; cron nadal tylko `schedule:run` co minutę)
| Komenda | Kiedy | Uwagi |
|---|---|---|
| `php artisan teryt:import --terc=... --simc=... [--dry-run] [--chunk=1000] [--resume-from=SIMC] [--max-seconds=N]` | ręcznie | pliki CSV w `storage/app/private/teryt-import/` lub ścieżka bezwzględna; wypisuje podsumowanie i szczytową pamięć |
| `backup:run --only-db` | codziennie 02:00 | `withoutOverlapping(120)`, `onFailure` loguje |
| `backup:run --only-files` | niedziela 02:30 | pliki użytkowników + `.env`, szyfrowane |
| `backup:clean` | codziennie 02:50 | retencja 14 dni |
| `backup:monitor` | codziennie 04:00 | alarm gdy najnowsza kopia starsza niż 2 dni (mail tylko gdy ustawiono adres) |
| `geocode:prune` | codziennie 03:40 | usuwa wygasłe wpisy `geocode_cache` |

### Zmienne `.env` (nowe)
`NOMINATIM_URL`, `NOMINATIM_CONTACT_EMAIL` (puste), `BACKUP_NOTIFICATION_EMAIL` (puste), `BACKUP_ARCHIVE_PASSWORD` (na produkcji wymagane, min. 20 znaków; lokalnie puste = bez szyfrowania), `BACKUP_DISKS` (domyślnie `backups`), `BACKUP_MAX_STORAGE_MB` (3000), `DB_DUMP_BINARY_PATH` (katalog z `mysqldump`/`mariadb-dump`; lokalnie ustawiony w moim `.env` na katalog `bin` lokalnej MariaDB 10.11, hasło archiwum lokalne testowe). `phpunit.xml` zeruje je w testach.

### Kopia zapasowa: sprawdzona ręcznie lokalnie
`backup:run --only-db` (2,6 MB, 2,6 s, zrzut `mariadb-dump` pełnej bazy z danymi TERYT), `backup:run --only-files`, `backup:list`, `backup:clean`, `backup:monitor`: wszystko poprawne. Archiwa szyfrowane AES-256 (ZipArchive, kod 259): odczyt złym hasłem zwraca błąd, dobrym działa; kopia plików zawiera `.env` i `storage/app/private`, a NIE zawiera `teryt-import`. Pliki lokalnych kopii zostały w `storage/app/backups/` (ignorowane przez Git, zawierają zrzut lokalnej bazy: można usunąć).
NIE sprawdzono na home.pl: `mysqldump`, `proc_open`, `ext-zip`, limit miejsca, czas działania crona (patrz „Niepotwierdzone” w sekcji Architekt, pkt 5). Połączenie `mysql` użyje `mysqldump`, lokalne `mariadb` używa `mariadb-dump` (db-dumper 4.1.1).

### Endpoint autouzupełniania (kontrakt zgodny z sekcją Architekt, pkt 4)
`GET /miejscowosci/szukaj?q=...&limit=10&include_parts=0` (trasa `localities.search`), publiczny, JSON `{"data":[...]}`; pola: `simc, name, label, kind, kind_label, slug, municipality, county, voivodeship, lat, lng, has_coordinates` (bez `id`).
- Błędy: 422 w JSON zawsze (także bez nagłówka `Accept`), 429 z `Retry-After` i `{"message":"Zbyt wiele zapytań. Spróbuj za chwilę."}` (60/min i 8/s na IP).
- Nagłówek `Cache-Control: public, max-age=3600`; trasa bez sesji i ciasteczek (test sprawdza brak `Set-Cookie`).
- `q` normalizowane tak jak `name_search`; `q` ze spacją dopasowuje też „miejscowość + początek gminy/powiatu” („dabrowka dopiewo”, „Dąbrówka poznański”).
- `ResolveLocalityCoordinates::handle(TerytLocality)` uzupełnia `lat/lng` leniwie; **nie ma jeszcze trasy ani przycisku, który ją wywołuje** (brak ekranu wyszukiwarki; to wchodzi razem z Etapem 1).

### Co musi zrobić frontend / następne zadania
- Pole „miejscowość” z debounce ok. 250 ms, min. 2 znaki, wyświetlanie `label`; obsłużyć 422 i 429 (komunikat „Spróbuj za chwilę”); `kind_label` pomaga odróżnić „wieś” od „miasto”.
- Gdy `has_coordinates` = false, po wyborze wywołać akcję serwerową opartą o `ResolveLocalityCoordinates` (trzeba dodać trasę/Action w zadaniu wyszukiwarki). Przeglądarka NIE woła Nominatim.
- Atrybucja przy mapie: „© współtwórcy OpenStreetMap” (`lang/pl/geo.php`, klucz `osm_attribution`); źródło słownika: „GUS, rejestr TERYT” (stopka / polityka prywatności).
- Zadanie dla koordynatora: wpisy w `docs/RODO_REJESTR.md` (Nominatim jako odbiorca danych adresowych; TERYT jako źródło), rozdziały „Wdrożenie” i „Kopie zapasowe” w `docs/DOKUMENTACJA_TECHNICZNA.md`, `CHANGELOG.md`; w diagramie ER dopisać kolumnę `teryt_municipalities.county_name_search`.

### Wyniki kontroli (PHP 8.3.36)
- `php artisan test`: **509 testów, wszystkie zielone** (3211 asercji; w tym nowe: Settings 7, SearchText/LocalityKind 22, import TERYT 20, wyszukiwarka 26, geokodowanie 27, kopie/harmonogram 16, strażnik +3).
- `./vendor/bin/pint --test`: passed.
- `./vendor/bin/phpstan analyse --no-progress --memory-limit=1G`: No errors (poziom 6). Uwaga: bez `--memory-limit` proces roboczy PHPStan przekracza domyślne 128 MB i się przewraca; uruchamiaj z flagą.

### Znane ograniczenia i ryzyka
- Importer nie czyta XML (tylko CSV, wystarcza: oba formaty są w tym samym archiwum GUS).
- Format zapytania Nominatim dla wsi (`county=powiat poznański`, `state=województwo wielkopolskie`) jest oparty na konwencji nazw OSM i **NIE był sprawdzony na żywym Nominatim** (brak adresu kontaktowego, nie wysyłałem prawdziwych zapytań). Przy `NotFound` współrzędne miejscowości zostają puste (brak awaryjnego zapytania bez powiatu, bo dawałoby błędną wieś o tej samej nazwie).
- Wszystkie testy geokodowania używają `Http::fake()`; żadnego zapytania do prawdziwego Nominatim nie wykonano.
- Dezaktywacja brakujących miejscowości trzyma w pamięci zbiór kodów SIMC z pliku (ok. 20 MB); limit CLI na home.pl nieznany.
- Powiadomienia mailowe o kopiach nie były testowane wysyłką (adres nieznany); sprawdzono tylko konfigurację dla pustego, poprawnego i niepoprawnego adresu.
- Kopia poza serwerem wymaga ręcznego pobierania (R8 z sekcji Ryzyka pozostaje otwarte do decyzji przed produkcją).
- Zawieszony proces `python` w tle z mojej pomyłki w poleceniu (puste wejście) mógł pozostać w systemie; jest nieszkodliwy.

### Jak sprawdzić (dla właściciela)
1. W PowerShell: `& $php83 artisan test` (ma być 509 zielonych); lokalna baza MariaDB musi działać na porcie 3307.
2. `& $php83 artisan migrate`, potem `& $php83 artisan teryt:import --terc=teryt-import/TERC.csv --simc=teryt-import/SIMC.csv --dry-run` (pliki CSV z eTeryt wgrane do `storage/app/private/teryt-import/`; na tym komputerze już tam są).
3. `& $php83 artisan serve`, w przeglądarce: `http://127.0.0.1:8000/miejscowosci/szukaj?q=dabrowka` (po imporcie lista Dąbrówek z gminą i powiatem); `?q=dąbrówka dopiewo`, `?q=a` (błąd 422).
4. `& $php83 artisan backup:run --only-db`, potem `& $php83 artisan backup:list` (plik zip pojawi się w `storage/app/backups/kontroladomu/`).

### Poprawki po testach
- W-1: skrypt Composera `phpstan` (`composer phpstan`) z limitem pamięci 1 GB; opisany w `docs/DOKUMENTACJA_TECHNICZNA.md`.
- DROBNY-1: `NominatimProvider::isConfigured()` porównuje host bez wielkości liter.
- DROBNY-2: `GeocodingService` po zdobyciu blokady limitu ponownie sprawdza cache (bez zapytania do usługi); zapis przez `upsert`, a błąd zapisu jest logowany i nie wychodzi na zewnątrz.
- DROBNY-3: importer waliduje SYMPOD, RM, NAZWA (1-100 znaków) i STAN_NA (RRRR-MM-DD); błędny wiersz przerywa import wyjątkiem z numerem linii (jak uszkodzone wiersze CSV).
- DROBNY-4: `--chunk` i `--max-seconds` muszą być liczbami całkowitymi dodatnimi, inaczej komunikat po polsku i kod 1.
- DROBNY-5: komunikat błędu importu informuje, że ponowne uruchomienie jest bezpieczne (upsert); `--resume-from` służy do wznowienia po limicie czasu.
- DROBNY-6: PHPDoc `SettingsRepository::get` zgodny z zachowaniem (domyślna wartość tylko dla braku klucza lub NULL).
- Testy DROBNY-1 i -2 odblokowane (zdjęte `skip`).

## Frontend
<!-- Widoki, komponenty, jak sprawdzić w przeglądarce -->

## Testy
Data: 2026-10-30. Tester. PHP 8.3.36 (WinGet). Kodu produkcyjnego nie zmieniano, commitów nie robiono. Dopisano tylko pliki testów (lista niżej).

### Wynik
| Narzędzie | Wynik |
|---|---|
| `php artisan test` | **657 zielonych, 2 pominięte** (3601 asercji, ok. 37 s). Przed moimi testami backend zgłaszał 509, więc dopisano 150 testów. |
| `./vendor/bin/pint --test` | passed (po sformatowaniu moich nowych plików testowych) |
| `./vendor/bin/phpstan analyse --no-progress --memory-limit=1G` | No errors (poziom 6) |
| `./vendor/bin/phpstan analyse --no-progress` (bez flagi, po `clear-result-cache`) | **PADA**: „PHPStan process crashed because it reached configured PHP memory limit: 128M” |

Dwa testy pominięte (`->skip(...)`) opisują usterki DROBNY-1 i DROBNY-2 poniżej. Sprawdziłem, że po zdjęciu `skip` oba padają (zachowanie docelowe nie jest spełnione), więc nie są „pustymi” testami.

**PHPStan i pamięć (nie jest skutkiem zadania 003).** Wymóg `--memory-limit=1G` istniał już w commicie 9416b33 (`docs/DOKUMENTACJA_TECHNICZNA.md`, wiersz o PHPStan), a `phpstan.neon` ani `composer.json` nie zawierają limitu. PHPStan nie ma opcji pamięci w `phpstan.neon` (tylko flaga CLI), więc ustawienie w neon nie zadziała. Proponuję (architekt/koordynator, ja nie zmieniałem): w `composer.json` w `scripts` dopisać `"phpstan": "phpstan analyse --no-progress --memory-limit=1G"` i w `CLAUDE.md` §11 oraz w „Definicji gotowe” zmienić komendę na `composer phpstan` albo dopisać flagę. Bez tego punkt „kod przechodzi phpstan” z CLAUDE.md §11 dosłownie nie przechodzi na czystej instalacji. Wynik uruchomienia z cache bywa zielony bez flagi, co maskuje problem.

### Pokrycie kryteriów akceptacji z sekcji Architekt
| Kryterium | Pokrycie | Uwagi |
|---|---|---|
| Model/migracje TERYT, UNIQUE simc/code/slug, indeks `name_search` | tak (import, fabryki) | indeks i EXPLAIN sprawdzał backend ręcznie na MariaDB; testy automatyczne działają na SQLite i tego nie widzą |
| Normalizacja polskich znaków (pkt 1.4) | tak | `SearchTextTest` (22) + moje: ł, ó, ń, ż, ź, myślnik, wielkie litery, wielokrotne spacje |
| Import: idempotentny, nie nadpisuje slugów/lat/lng, tryb próbny, 90%, `is_active`, `--max-seconds`, `--resume-from` | tak | dopisane: `created_at` i `id` bez zmian, zmiana nazwy gminy odświeża kolumny wyszukiwania, wznowienie = ten sam stan co import jednorazowy, powtórne wznowienie bezpieczne, dry-run liczy wyłączenia nie wyłączając, wznowienie nie wyłącza |
| Uszkodzone wiersze CSV | tak (nowe) | SYM pusty / za krótki / za długi / litery / spacja; za mało pól; spacje w linii; Windows-1250; pusty plik; sam nagłówek; XML; przecinki; LF bez końcowego newline; puste linie; nieznane kolumny i inna kolejność; duplikat SYM; transakcja po błędzie |
| Import: pamięć stała | tak (nowe) | 20 tys. wierszy: przyrost szczytu pamięci < 40 MB, czas < 30 s (SQLite); pomiar na 101 865 wierszach zrobił backend (80 MB szczyt, ok. 9 s) |
| Endpoint: kontrakt, 422, 429, Cache-Control, brak ciasteczek | tak | dopisane: 1 znak po trim, tablica w `q`, same znaki interpunkcji, emoji i znaki sterujące, 60 vs 61 liter wielobajtowych, granice `limit` (1, 15, puste, -1, 1.5, 16, 1e1, przepełnienie), `include_parts` 0/1 także w trybie „miejscowość gmina”, części miast, brak dubli, limit w drugim kroku, deterministyczna kolejność, 405 dla POST/PUT/PATCH/DELETE, limit per IP i odnowienie po sekundzie |
| Bezpieczeństwo wejścia | tak (nowe) | `' OR 1=1 --`, `'; DROP TABLE ...`, `UNION SELECT`, `<script>`, `<img onerror>`, `%_%`, ukośnik, bajt zerowy: 422 lub pusta lista, tabela nienaruszona |
| N+1 w endpoincie | tak (nowe) | `Model::preventLazyLoading(true)` przy 7 wynikach i w drugim kroku wyszukiwania; liczba zapytań ≤ 4 niezależnie od limitu |
| Wydajność | częściowo | 5000 miejscowości (SQLite): trzy zapytania łącznie < 500 ms. Pomiar na pełnych danych (MariaDB) tylko backend (24 ms dla „kr”). 1000 fachowców: nie dotyczy tego zadania |
| Geokodowanie: cache, 1/s, User-Agent, brak danych osobowych | tak | `GeocodingServiceTest` (27) |
| not_found vs błąd sieci | tak (nowe) | błąd sieci nie trafia do cache, po wygaśnięciu przerwy ponowne zapytanie znajduje adres; „nie znaleziono” ważne 7 dni (6 dni z cache, 8 dni nowe zapytanie); gdy zapytanie awaryjne zawiedzie po „nie znaleziono”, nic się nie zapisuje; 400/404/418/502/503; 7 postaci niepoprawnej odpowiedzi 200; granice prostokąta Polski i 0,0 |
| Wyścig blokady | częściowo | zajęta blokada = brak zapytania, brak wpisu, brak zapytania awaryjnego; po zwolnieniu działa; blokada zwolniona po sukcesie i po wyjątku dostawcy; nieudane zapytanie też zajmuje limit 1/s. Prawdziwej równoległości (dwa procesy) nie da się odtworzyć w Pest; patrz DROBNY-2 |
| Brak kontaktu = brak zapytań | tak | dopisane: 5 wywołań = 0 zapytań i 1 ostrzeżenie, kontakt z CRLF traktowany jak brak (wstrzyknięcie nagłówka) |
| Prywatność w logach | tak | wyjątek dostawcy: log bez ulicy, miejscowości i kodu |
| Wieś o tej samej nazwie w kilku powiatach | tak (nowe) | osobne wpisy w cache i osobne zapytania |
| Settings: sekret nie w cache/logach | tak (nowe) | log (Log::spy), `activity_log`, kolumny tabeli, cache, `toArray/toJson/(string)`, zmieniony szyfrogram, sekret i wartość jawna tego samego klucza niezależne, `forget`, emoji i 10 tys. znaków, FK `updated_by` zerowany po usunięciu użytkownika |
| Kopie: harmonogram, retencja 14 dni, dysk poza `public/`, szyfrowanie | tak | dopisane: poprawny adres włącza maile, niepoprawny/pusty/CRLF wyłącza; strażnik: 19 znaków odrzuca, 20 przyjmuje; `.env.*.example` bez haseł i e-maili; `.gitignore`; brak plików TERYT/zip/sql w `public/` |
| Uprawnienia | tak | endpoint publiczny: gość i zalogowany dostają to samo (200), brak tras wykonujących geokodowanie z poziomu użytkownika. `ResolveLocalityCoordinates` nie jest jeszcze podpięta do trasy (brak do testowania uprawnień) |
| Maile i kolejki | nie dotyczy | brak wysyłki w tym zadaniu; powiadomienia kopii sprawdzone tylko konfiguracyjnie (adres nieznany) |

### Nowe pliki testów
- `tests/Feature/Geo/LocalitySearchEdgeCasesTest.php` (54 przypadki)
- `tests/Feature/Geo/ImportTerytEdgeCasesTest.php` (31)
- `tests/Feature/Geo/GeocodingEdgeCasesTest.php` (41, w tym 2 pominięte)
- `tests/Feature/Settings/SettingsSecretLeakTest.php` (12)
- `tests/Feature/Backup/BackupConfigEdgeCasesTest.php` (12)

### Znalezione błędy
Blokerów nie znaleziono.

| ID | Priorytet | Kto poprawia | Opis |
|---|---|---|---|
| W-1 | WAŻNY | architekt + koordynator | `phpstan analyse` bez `--memory-limit=1G` pada na czystym cache (128 MB), a CLAUDE.md §11 podaje komendę bez flagi. Nie wynika z zadania 003 (wymóg znany od 002). Propozycja: skrypt Composera `phpstan` i poprawka §11. |
| DROBNY-1 | DROBNY | backend | `NominatimProvider::isConfigured()` porównuje host z `nominatim.openstreetmap.org` z uwzględnieniem wielkości liter. `NOMINATIM_URL=https://NOMINATIM.OpenStreetMap.org` bez kontaktu wysyła zapytania do publicznego serwera, czyli łamie zasadę „brak kontaktu = brak zapytań”. Poprawka: `strtolower(parse_url(..., PHP_URL_HOST))`. Test czeka (pominięty). |
| DROBNY-2 | DROBNY | backend | `GeocodingService` nie sprawdza cache ponownie po zdobyciu blokady: dwa równoległe żądania o ten sam nowy adres wyślą dwa zapytania do Nominatim (polityka OSMF zniechęca do powtórzeń). Dodatkowo `store()` (`updateOrCreate`) wykonuje się po zwolnieniu blokady, więc w rzadkim wyścigu dwóch zapisów tego samego skrótu może wyjść wyjątek `UNIQUE` poza serwis, mimo zapisu „serwis nigdy nie rzuca” (nie odtworzyłem, wynika z czytania kodu). Poprawka: ponowny `fromCache` wewnątrz sekcji krytycznej oraz `try/catch` wokół zapisu lub `upsert`. Test czeka (pominięty). |
| DROBNY-3 | DROBNY | backend | Importer waliduje tylko SYM. Pola `SYMPOD`, `RM`, `STAN_NA`, `NAZWA` nie są sprawdzane: przesunięta kolumna (`0900002;X` w SYM) jest przyjęta jako `SYMPOD = X`; pusta nazwa jest przyjęta; za długa wartość w `parent_simc` (char 7) lub nazwa > 100 znaków da na MySQL w trybie ścisłym surowy `QueryException` zamiast `TerytImportException` (SQLite w testach tego nie pokaże; nie sprawdzałem na MariaDB). Dodatkowe pola na końcu wiersza są po cichu ucinane. Dla prawdziwych plików GUS (sprawdzonych przez backend) problem nie występuje. |
| DROBNY-4 | DROBNY | backend | `teryt:import --chunk=abc` daje porcję 1 (100 tys. transakcji), a `--max-seconds=abc` lub ujemne zatrzymuje po pierwszej porcji. Brak walidacji opcji w komendzie (kod 2 z czytelnym błędem). |
| DROBNY-5 | DROBNY | backend | Błąd w środku pliku SIMC zostawia zatwierdzone wcześniejsze porcje (bez dat stanu i bez wyłączania brakujących; zachowanie przetestowane). Komunikat błędu nie mówi, że ponowne uruchomienie jest bezpieczne (`upsert`). Wystarczy zdanie w komunikacie. |
| DROBNY-6 | DROBNY | backend (opis) | PHPDoc `SettingsRepository::get` mówi „albo `$default`, gdy klucza brak lub wartość jest pusta”, a kod zwraca pusty tekst `''` (tylko `null` daje domyślną). Poprawić opis albo kod. |
| INFO-1 | do wiadomości frontendu | frontend | `include_parts` przyjmuje tylko `0` i `1` (reguła `boolean` Laravela odrzuca `true`/`false`/`on` kodem 422). Pole wyszukiwarki musi wysyłać `1`/`0`. |
| INFO-2 | do wiadomości | backend/audytor | Testy automatyczne działają na SQLite w pamięci. Nie są sprawdzane na MariaDB/MySQL: indeks prefiksowy, tryb ścisły kolumn, zachowanie `upsert` z duplikatami w jednej porcji, `Cache::lock` w tabeli `cache_locks`. Format zapytania Nominatim dla wsi (`county`, `state`) nie był sprawdzony na żywej usłudze (żaden test nie łączy się z siecią). |

### Sprawdź sam (dla właściciela projektu)
Wszystkie komendy w PowerShell, w katalogu `C:\xampp\htdocs\kontroladomu`. Najpierw ustaw PHP 8.3 (jak w `docs/DOKUMENTACJA_TECHNICZNA.md`, rozdział 2): `$php83 = "$env:LOCALAPPDATA\Microsoft\WinGet\Packages\PHP.PHP.NTS.8.3_Microsoft.Winget.Source_8wekyb3d8bbwe\php.exe"`. Uruchom MariaDB (port 3307) w XAMPP.
1. Testy: `& $php83 artisan test`. Na końcu ma być „657 passed” i „2 skipped” (te dwa to znane drobne usterki z tabeli wyżej, nie awaria).
2. Styl kodu: `& $php83 vendor\bin\pint --test` (ma być „passed”). Analiza: `& $php83 vendor\bin\phpstan analyse --no-progress --memory-limit=1G` (ma być „No errors”). Bez flagi `--memory-limit` PHPStan może się wywrócić; to znany problem W-1.
3. Baza: `& $php83 artisan migrate` (dodaje 4 tabele; niczego nie kasuje).
4. Podgląd importu bez zapisu: `& $php83 artisan teryt:import --terc=teryt-import/TERC.csv --simc=teryt-import/SIMC.csv --dry-run` (pliki CSV z eTeryt leżą w `storage\app\private\teryt-import`). Ma napisać „TRYB PRÓBNY” i podać liczby: 101 865 miejscowości, 3964 gminy.
5. Właściwy import (jeśli jeszcze nie był): to samo polecenie bez `--dry-run`. Ma trwać ok. 10 s i napisać „Import zakończony.”. Uruchom drugi raz: liczby „dodano” mają być 0 (import można powtarzać).
6. Wyszukiwarka w przeglądarce (po `& $php83 artisan serve`, adres `http://127.0.0.1:8000`): `/miejscowosci/szukaj?q=dabrowka` (lista Dąbrówek z gminą i powiatem), `?q=Dąbrówka dopiewo` (zawężone do gminy Dopiewo), `?q=dabrowka&limit=3` (3 wyniki), `?q=a` (błąd 422 z komunikatem), `?q=abc%3Cscript%3E` (błąd 422, nic się nie wykonuje).
7. Limit zapytań: w pasku adresu odśwież szybko `/miejscowosci/szukaj?q=ko` kilkanaście razy w ciągu sekundy. Po ok. 8 odświeżeniach ma się pojawić komunikat „Zbyt wiele zapytań. Spróbuj za chwilę.” (kod 429), po chwili znów działa.
8. Kopia zapasowa bazy: `& $php83 artisan backup:run --only-db`, potem `& $php83 artisan backup:list`. Ma pokazać świeżą kopię (zip w `storage\app\backups\kontroladomu`), z hasłem z Twojego `.env`; bez `BACKUP_NOTIFICATION_EMAIL` nie wyśle maila i to jest zamierzone.
9. Harmonogram: `& $php83 artisan schedule:list`. Mają być wpisy `backup:run --only-db` (codziennie 02:00 UTC), `backup:run --only-files` (niedziela 02:30), `backup:clean`, `backup:monitor`, `geocode:prune`.
10. Geokodowanie bez adresu kontaktowego: `NOMINATIM_CONTACT_EMAIL` w `.env` zostaw puste. Aplikacja ma nie wysyłać żadnych zapytań do Nominatim (tego nie widać w przeglądarce, pilnują tego testy). Gdy podasz swój adres e-mail, geokodowanie włączy się dopiero przy zapisie nieruchomości (przyszłe zadanie).

**Werdykt testera:** brak blokerów; zadanie może iść do audytora bezpieczeństwa. Zalecam przed oznaczeniem „gotowe” poprawić W-1 (to jedno zdanie w CLAUDE.md i jeden skrypt) i DROBNY-1 (jedna linijka). Pozostałe drobne mogą poczekać.

## Audyt bezpieczeństwa
Data: 30.10.2026. Audytor bezpieczeństwa. Zakres: niezacommitowane zmiany na gałęzi `feature/teryt-geokodowanie-kopie` (endpoint `/miejscowosci/szukaj`, `teryt:import`, geokodowanie, `settings`, `spatie/laravel-backup`, harmonogram, pliki `.env.*.example`, `.gitignore`, CSP). Kodu nie zmieniałem. Sprawdzone: odczyt kodu, `composer audit` (PHP 8.3.36): brak znanych podatności; `npm audit`: 0 podatności; `composer licenses`: `spatie/laravel-backup` 10.3.3 (wydana 14.09.2026), `spatie/db-dumper` 4.1.1, `spatie/laravel-signal-aware-command` 2.1.2, `spatie/temporary-directory` 2.4.0, wszystkie MIT; `composer.json` wymaga `ext-zip` (do sprawdzenia na home.pl, ADR-014). Na lokalnej bazie z pełnym TERYT (101 865 miejscowości) zmierzyłem trudne zapytania dwuczłonowe: "a a" 20 ms, "k k" 26 ms, "a zzzz" 32 ms, "s zz" 2 ms (bez problemu wydajności). `backup:monitor` i `backup:list` działają, archiwum bazy ma szyfrowanie (nazwy plików w środku są widoczne), `.env`, archiwa i pliki TERYT nie są śledzone przez Git (`git ls-files` czysty, `.gitignore` poprawny).

### Co sprawdziłem i jest w porządku (bez ustaleń)
- Wstrzyknięcia: `SearchLocalities` używa bindowania; `orderByRaw` ma parametr `?` albo stałą bez danych użytkownika; zapytanie jest normalizowane do `[a-z0-9 ]` przed `LIKE`, więc `%`, `_`, `\` nie występują; walidacja `q` (2-60 znaków, lista dozwolonych znaków `\p{L}\p{N} -.,'`), `limit` 1-15, `include_parts` boolean; błąd walidacji zawsze 422 JSON (bez przekierowania).
- Endpoint: brak sesji i ciasteczek (`withoutMiddleware`), więc `Cache-Control: public` nie może współdzielić cudzych danych sesji; globalny `SecurityHeaders` nadal działa (CSP, nosniff, Referrer-Policy, frame-ancestors `none`); limity 60/min i 8/s na IP (IP z `TrustProxies`, strażnik produkcyjny wymusza `TRUSTED_PROXIES`, więc za Cloudflare nie ma wspólnego licznika dla wszystkich); zwracane dane to publiczny rejestr (bez `id`, bez danych osobowych); tekst zapytania nie jest logowany.
- CSP `connect-src 'self'` wystarcza dla autouzupełniania (`ContentSecurityPolicy.php:43`). Przeglądarka nie łączy się z Nominatim.
- Import: tylko CLI, brak trasy webowej, pliki w `storage/app/private/teryt-import/` (poza `public/`, ignorowane przez Git i wyłączone z kopii); ścieżka względna idzie przez dysk `local` (Flysystem blokuje `..`), bezwzględna wymaga uprawnień operatora; parser nie używa `unserialize`/`eval`/`simplexml`, tylko `fgets` + `explode` + walidacja SYM/SYMPOD/RM/NAZWA/STAN_NA; zabezpieczenie 90% przed wyłączeniem miejscowości z obciętego pliku; zapis przez `upsert` w transakcjach.
- Geokodowanie: do usługi trafiają tylko pola adresowe (struktura zapytania bez `q`), bez ID, e-maila, telefonu; w logach tylko klasa wyjątku, przyczyna i skrót `query_hash`; brak kontaktu = brak zapytań (publiczny host); bez ponowień (`retry`); limit 1/s przez `Cache::lock`; wyłącznik awarii; wynik spoza Polski odrzucany.
- `settings`: `secret_value` z castem `encrypted`, w `$hidden`, poza cache, bez dziennika; błąd odszyfrowania nie zdradza treści.
- Kopie: dysk `backups` w `storage/app/backups` (poza `public/`), szyfrowanie AES-256 przy ustawionym haśle (`config/backup.php:202`), strażnik produkcyjny wymaga hasła min. 20 znaków (`ProductionConfigGuard.php:73-75`), `db-dumper` przekazuje hasło bazy przez plik `--defaults-extra-file`, nie w argumentach procesu; retencja 14 dni; `.env.example` i `.env.production.example` bez sekretów (wartości puste).

### Tabela ustaleń
| # | Waga | Plik:linia | Problem i scenariusz | Poprawka | Agent |
|---|---|---|---|---|---|
| 1 | ŚREDNIA | `config/backup.php:17`, `:316-322`, `:218` | Przy pustym `BACKUP_NOTIFICATION_EMAIL` (decyzja właściciela, ADR-016) nikt nie dostaje alarmu o nieudanej kopii. Dodatkowo `backup:monitor` sprawdza tylko wiek NAJNOWSZEJ kopii (`MaximumAgeInDays => 2`), a kopia bazy powstaje codziennie, więc awaria tygodniowej kopii plików (zawiera `.env` z `APP_KEY` i `storage/app/private`) nie wywoła nawet alarmu monitora; `onFailure` w `routes/console.php` tylko zapisuje wiersz w logu. Scenariusz: kopia plików przestaje działać (brak miejsca, limit czasu, zmiana hasła), po awarii dysku `APP_KEY` jest nie do odzyskania, a zaszyfrowane 2FA i sekrety `settings` stają się nieczytelne. | Nie bloker zadania 003, ale WARUNEK WDROŻENIA: przed produkcją ustalić `BACKUP_NOTIFICATION_EMAIL` (dodać do listy kontrolnej wdrożenia), a do tego czasu raz w tygodniu wykonywać `backup:list` i sprawdzać, że istnieje kopia plików z ostatnich 7 dni. Rozważyć `verify_backup => true`. Poprawkę wiekową dla kopii plików można dodać osobnym wpisem monitora z innym dyskiem/nazwą albo własnym sprawdzeniem w harmonogramie (zadanie przy panelu admina). | backend + koordynator |
| 2 | ŚREDNIA | `database/migrations/2026_10_30_200004_create_geocode_cache_table.php:11`, `app/Domain/Geo/Services/GeocodingService.php:171-190`, `config/geocoding.php` (`ttl_found_days`) | Komentarz "z samego skrótu i współrzędnych nie da się odtworzyć adresu" jest nieprawdziwy: SHA-256 z krótkiego, przewidywalnego tekstu (ulica + numer + miejscowość z TERYT) łamie się słownikiem, a współrzędne wskazują konkretny budynek (odwrotne geokodowanie daje adres). Po zapisaniu nieruchomości (kolejne zadanie) wiersz cache będzie więc danymi adresowymi powiązanymi z miejscem zamieszkania, trzymanymi 180 dni i NIE kasowanymi przy usunięciu konta (art. 17 RODO). W zadaniu 003 geokodowane są wyłącznie miejscowości (nie dane osobowe), więc dziś nie ma naruszenia. | Przed podpięciem geokodowania adresów nieruchomości: (a) poprawić komentarz i ADR-013 ("pseudonimizacja", nie "anonimizacja"); (b) liczyć skrót jako `hash_hmac('sha256', ..., config('app.key'))` (słownik wymaga wtedy klucza); (c) dla adresów z ulicą skrócić TTL (propozycja 30 dni) albo nie zapisywać wyniku z numerem domu, tylko zaokrąglone współrzędne; (d) wpis w rejestrze RODO (niżej). | backend |
| 3 | ŚREDNIA | `docs/RODO_REJESTR.md` (brak wpisów) | Brak wpisów o: Nominatim (odbiorca adresów), TERYT (źródło), `geocode_cache`, `settings`, kopiach zapasowych (już teraz zawierają pełny zrzut `users`, `sessions`, `activity_log`, `jobs` i `.env`) oraz o nowym limiterze w `cache`. Brak informacji, że usunięte dane żyją w kopiach do 14 dni (art. 17 i art. 13/14, polityka prywatności). Treść wpisów poniżej (nie edytowałem pliku, zgodnie z poleceniem). | Koordynator wkleja wpisy z sekcji "Proponowane wpisy do RODO_REJESTR.md" przed tagiem wersji; prawnik weryfikuje podstawę i transfer dla OSMF; uzupełnić politykę prywatności. | koordynator |
| 4 | NISKA | `app/Domain/Geo/Services/NominatimProvider.php:49-53`, `config/geocoding.php` (`base_url`) | `NOMINATIM_URL` nie jest ograniczony do `https`, klient HTTP Laravela domyślnie podąża za przekierowaniami, a rozmiar odpowiedzi nie jest ograniczony. SSRF z danych użytkownika NIE istnieje (adres tylko z `.env`), ale błędna wartość `http://...` wyśle adresy klientów jawnym tekstem, a przejęty/źle skonfigurowany serwer Nominatim mógłby przekierować serwer aplikacji do adresu wewnętrznego lub zwrócić ogromną odpowiedź. | `->withoutRedirecting()` w żądaniu; w `ProductionConfigGuard` wymagać schematu `https` dla `NOMINATIM_URL` (wyjątek: pusty/lokalny); opcjonalnie odrzucać odpowiedzi o `Content-Length` powyżej kilkudziesięciu KB. | backend |
| 5 | NISKA | `app/Domain/Geo/Support/NominatimThrottle.php:34-39`, `app/Domain/Geo/Actions/ResolveLocalityCoordinates.php` | Dziś żadna trasa nie wywołuje geokodowania (zgodnie z dokumentacją), więc brak podatności. Ale gdy zadanie wyszukiwarki podepnie `ResolveLocalityCoordinates` pod publiczną trasę wykonywaną synchronicznie, jedno żądanie może trzymać proces PHP do ok. 6-12 s (oczekiwanie na blokadę do 5 s + odstęp 1,1 s + HTTP do 5 s). Anonimowy atakujący, wybierając miejscowości bez współrzędnych (ok. 100 tys.), wyczerpie procesy PHP i szybko wywoła 429/403 z OSMF, czyli 30-minutową blokadę geokodowania dla wszystkich (`blocked_cooldown_seconds`). | Ustalić jako zasadę dla zadania wyszukiwarki: geokodowanie wyłącznie w zadaniu kolejki (`ShouldBeUnique` po `simc`), nigdy w żądaniu webowym; trasa wyzwalająca ma rate limiting i Turnstile dla gościa. Dopisać do pliku zadania wyszukiwarki jako kryterium audytu. | architekt |
| 6 | NISKA | `config/backup.php:329-334` | Martwy wpis z szablonu Spatie ("name of the second app", dyski `local` i `s3`). Dziś `backup:monitor` go pomija, ale to konfiguracja wprowadzająca w błąd i potencjalnie wywołująca błąd monitora po późniejszym dodaniu dysku S3. | Usunąć drugi element tablicy `monitor_backups`. | backend |
| 7 | NISKA | `config/backup.php:20`, `:184`, `:319`; `ProductionConfigGuard.php:73` | `BACKUP_DISKS` może wskazać dowolny dysk; wpisanie `public` zapisałoby archiwa w `storage/app/public`, dostępnym z sieci po `storage:link` (nazwy plików to przewidywalne znaczniki czasu). Strażnik sprawdza tylko długość hasła. Szyfrowanie ogranicza skutki, ale kopia `.env` w publicznym katalogu to zła praktyka. | Strażnik produkcyjny odrzuca `BACKUP_DISKS` zawierające `public`; lub biała lista (`backups`). | backend |
| 8 | NISKA | `config/backup.php:195` | W czasie tworzenia kopii niezaszyfrowany zrzut bazy leży w `storage/app/backup-temp/`. Po przerwaniu procesu (limit czasu crona, restart hostingu) może tam zostać jawny plik `.sql` z danymi wszystkich użytkowników. Katalog nie jest w `public/`, więc ryzyko jest niskie, ale to jedyne miejsce, gdzie dane są nieszyfrowane. Dodatkowo nazwy plików w archiwum ZIP (np. `.env`) są widoczne bez hasła (to ograniczenie ZipArchive AES, nie błąd konfiguracji). | Dopisać do dokumentacji wdrożenia: po awarii kopii sprawdzić i wyczyścić `storage/app/backup-temp`; dodać zadanie harmonogramu usuwające zawartość starszą niż 1 dzień; uprawnienia katalogu 700 na hostingu. | backend |
| 9 | NISKA | `routes/web.php:34-35`, `app/Providers/AppServiceProvider.php:117-125` | Limit per IP chroni przed pojedynczym klientem, ale odpowiedź `public` nie powstrzyma rozproszonego ataku zmieniającego `q` (każda wartość omija cache). Pomiar: najcięższe znalezione zapytania 20-32 ms, więc 8 req/s/IP to nie jest realne zagrożenie, ale limiter zapisuje do tabeli `cache` (po 2 wpisy na żądanie). Endpoint pozwala też wyczerpująco "wyskrobać" cały zbiór TERYT (101 tys. wierszy) zapytaniami dwuliterowymi, co ma znaczenie, dopóki licencja GUS jest niewyjaśniona (ADR-012). | Reguła Cloudflare rate limiting dla `/miejscowosci/*` (np. 100/min na IP) i cache w Cloudflare dla tej ścieżki (odpowiedź jest `public`); odpowiedź GUS w sprawie licencji przed uruchomieniem publicznym. | koordynator |
| 10 | NISKA | `app/Domain/Settings/SettingsRepository.php:49`, `Setting.php:25` | `put()` zapisuje wartość jawną dla dowolnego klucza; błąd wywołującego (np. `put('mail.password', ...)` zamiast `putSecret`) zapisze hasło otwartym tekstem i do cache `settings.all`. `$fillable` zawiera `secret_value` i `updated_by`: bezpieczne, dopóki żaden formularz nie robi `Setting::create($request->all())`. Zmiany ustawień nie trafiają jeszcze do dziennika aktywności admina (wymóg CLAUDE.md §8, ale panel admina to osobne zadanie). | W zadaniu panelu admina: jawna lista kluczy sekretnych (`mail.password` itd.), dla których `put()` rzuca wyjątek; zapis przez Form Request z listą dozwolonych kluczy; wpis w `activity_log` (sam fakt zmiany klucza, bez wartości). | backend |
| 11 | INFO | `app/Http/Resources/LocalityResource.php`, frontend (zadanie 4+) | Nazwy w odpowiedzi pochodzą z pliku GUS (zaufane źródło, ale to dane zewnętrzne). JSON jest bezpieczny, XSS powstałby dopiero po wstawieniu `label` jako HTML. | Frontend: tylko `x-text`/`textContent`, nigdy `x-html`/`innerHTML`; `include_parts` wysyłać jako `1`/`0`. | frontend |
| 12 | INFO | `app/Support/Security/ContentSecurityPolicy.php:41` | `img-src 'self' data:` zablokuje kafelki mapy OpenStreetMap, a po ich dodaniu przeglądarka użytkownika (adres IP, Referer `strict-origin`) zacznie łączyć się z serwerami OSM: to kolejny odbiorca danych, do rejestru RODO i polityki prywatności. | Przy zadaniu z mapą: dodać do `img-src` dokładnie hosty kafelków (nie `*`), dopisać odbiorcę w rejestrze, atrybucja "© współtwórcy OpenStreetMap". | frontend |

### Proponowane wpisy do `docs/RODO_REJESTR.md` (do wklejenia przez koordynatora)
Nowy rozdział `## Geokodowanie, TERYT, ustawienia i kopie zapasowe (zadanie 003, audyt 30.10.2026)` z tabelą w układzie rozdziału "Konta i uwierzytelnianie" (Dane | Kogo dotyczą | Cel | Podstawa | Okres | Kto ma dostęp | Stan):

| Dane (tabela / system) | Kogo dotyczą | Cel | Podstawa (art. 6 RODO) | Okres przechowywania | Kto ma dostęp | Stan |
|---|---|---|---|---|---|---|
| Nominatim (OpenStreetMap Foundation, `nominatim.openstreetmap.org`): ulica, numer domu, kod pocztowy, miejscowość, powiat, województwo, kraj (PL) adresu nieruchomości wysyłane przez serwer; dodatkowo adres IP serwera aplikacji i nagłówek User-Agent z adresem e-mail kontaktowym administratora serwisu (nie użytkownika). Nie wysyłamy imienia, e-maila, telefonu ani ID użytkownika | klient / właściciel nieruchomości (od zadania nieruchomości); w zadaniu 003 wysyłane są tylko nazwy miejscowości z TERYT (nie dane osobowe) | ustalenie współrzędnych adresu do wyszukiwarki i dojazdu | 6.1.b (adres wpisany przez klienta w celu realizacji usługi) lub 6.1.f; OSMF jako odbiorca (samodzielny administrator, bez umowy powierzenia: do oceny prawnika); siedziba OSMF poza EOG (Wielka Brytania): transfer, podstawa do potwierdzenia przez prawnika (decyzja o adekwatności UE-UK) | po stronie OSMF wg jej polityki (logi serwerowe); po naszej stronie patrz `geocode_cache` | OSMF; po naszej stronie aplikacja | NIE uruchomione: do czasu ustawienia `NOMINATIM_CONTACT_EMAIL` aplikacja nie wysyła zapytań. Wymaga informacji w polityce prywatności (odbiorca i transfer, art. 13 ust. 1 lit. e-f) |
| `geocode_cache` (`query_hash` SHA-256 znormalizowanego adresu, `lat`, `lng`, `precision`, `status`, `expires_at`) | klient / właściciel nieruchomości | uniknięcie powtórnych zapytań do Nominatim (limit 1/s, polityka OSMF), wydajność | 6.1.f | trafienie 180 dni, "nie znaleziono" 7 dni, potem `geocode:prune` (codziennie 03:40); proponowane skrócenie do 30 dni dla adresów z numerem domu | tylko aplikacja, administrator bazy | to dane pseudonimizowane, nie zanonimizowane (współrzędne wskazują budynek, skrót łamie się słownikiem, patrz ustalenie 2); NIE są kasowane przy usunięciu konta, więc przy zadaniu usuwania konta uwzględnić albo skrócić TTL |
| `teryt_municipalities`, `teryt_localities` (nazwy miejscowości, gmin, powiatów, województw, kody TERYT/SIMC, współrzędne środka miejscowości) | brak (nie dotyczą osób) | autouzupełnianie miejscowości, SEO stron lokalnych | nie dotyczy (dane nieosobowe); źródło: GUS, rejestr TERYT, licencja w trakcie wyjaśniania (`docs/GUS_ZAPYTANIE_LICENCJA.md`), do odpowiedzi oznaczenie "GUS, rejestr TERYT" (`teryt.source`) | do kolejnego importu (kilka razy w roku), miejscowości nieobecne w pliku są wyłączane, nie kasowane | publicznie (endpoint `/miejscowosci/szukaj`) | wpis informacyjny, żeby nikt nie traktował tych tabel jako danych osobowych |
| `settings` (`value`, `secret_value` szyfrowane `APP_KEY`, `updated_by` = ID administratora, który zmienił ustawienie) | administrator (tylko `updated_by`); sekrety dotyczą konfiguracji serwisu (np. hasło SMTP) | konfiguracja serwisu edytowana z panelu admina, daty importu TERYT | 6.1.f | do zmiany/usunięcia ustawienia; `updated_by` zerowane po usunięciu konta administratora (FK `nullOnDelete`) | administrator, aplikacja | sekrety w kopiach zapasowych są nieczytelne bez `APP_KEY`, a `APP_KEY` jest w `.env` w kopii plików (obie chronione hasłem archiwum) |
| Kopie zapasowe (`spatie/laravel-backup`): codzienny pełny zrzut bazy (wszystkie tabele z tego rejestru: `users`, `sessions`, `activity_log`, `jobs`, `cache` itd.), tygodniowa kopia plików (`storage/app/private`, w przyszłości zaświadczenia i protokoły) wraz z `.env` (sekrety, `APP_KEY`) | wszyscy, których dane są w systemie | ciągłość działania i odtwarzanie po awarii (art. 32 ust. 1 lit. c) | 6.1.f, art. 32 | 14 dni (`keep_all_backups_for_days = 14`; limit 3000 MB może skrócić), czyszczenie `backup:clean` codziennie 02:50 UTC; kopia pobrana ręcznie poza serwer podlega temu samemu okresowi (usuwać po 14 dniach) | administrator serwera, właściciel (hasło archiwum min. 20 znaków trzymane poza serwerem); archiwa szyfrowane AES-256, nazwy plików w archiwum widoczne | dane usunięte na żądanie (art. 17) pozostają w kopiach do 14 dni: wpisać do polityki prywatności; po odtworzeniu z kopii ponownie usunąć konta zlikwidowane po dacie kopii; kopie na dysku komputera właściciela tylko na szyfrowanym dysku; kopie własne home.pl (panel) mają niezależną retencję, do ustalenia |
| `cache` / `cache_locks`: klucze limitera `locality-search` (skrót md5 z nazwy limitera i adresu IP, okno 1 min), `nominatim:last_at`, `geocoding:down`, `geocoding:logged:*` | odwiedzający | ograniczanie nadużyć (rate limiting), przestrzeganie limitu 1/s Nominatim | 6.1.f | od 1 sekundy do 1 godziny (limiter), przerwa awaryjna 5-30 min | tylko aplikacja | dopisać do istniejącego wiersza o kluczach limiterów; pozostałe klucze nie zawierają danych osobowych |
| Logi (`storage/logs/laravel.log`): zdarzenia geokodowania zawierają tylko przyczynę, klasę wyjątku i `query_hash`; endpoint wyszukiwania nie loguje zapytań | wszyscy | diagnostyka | 6.1.f | jak istniejący wiersz o logach (do 14 dni, rotacja `LOG_STACK=daily`) | administrator serwera | adresy nie trafiają do logów (sprawdzone w kodzie i testem) |

### Werdykt
Brak ustaleń KRYTYCZNYCH i WYSOKICH. Trzy ustalenia ŚREDNIE (1 alarmy kopii, 2 charakter cache geokodowania, 3 uzupełnienie rejestru RODO) i osiem NISKICH/INFO nie blokują zadania, ale:
- przed tagiem wersji dla zadania 003: koordynator wkleja wpisy do `docs/RODO_REJESTR.md` (#3) i dopisuje do listy kontrolnej wdrożenia ustalenie `BACKUP_NOTIFICATION_EMAIL` oraz ręczne sprawdzanie `backup:list` (#1);
- przed podpięciem geokodowania adresów nieruchomości (zadanie nieruchomości): zrealizować #2, #4, #5 (to warunki dla tamtego audytu);
- przy okazji (jedna linijka każde): #6, #7, #4 `withoutRedirecting()`.

**ZATWIERDZONE** (bez blokerów; ustalenia ŚREDNIE do zamknięcia według powyższych terminów).

## Podsumowanie (koordynator)

Zadanie zamknięte 30.10.2026. Przepływ: architekt → backend → tester → poprawki → audytor (ZATWIERDZONE, brak ustaleń
krytycznych i wysokich). Kontrole: 659 testów zielonych, Pint bez uwag, `composer phpstan` bez błędów.
Wpisy RODO z audytu wklejone do `docs/RODO_REJESTR.md`, checklista wdrożenia i CHANGELOG uzupełnione.

Na później: ustalenia audytu 2, 4, 5 (warunki przed zadaniem z nieruchomościami), 6, 7, 8, 9, 10; adresy e-mail alarmów i Nominatim;
odpowiedź GUS w sprawie licencji; sprawdzenie parametrów home.pl i prawdziwego Nominatim.
