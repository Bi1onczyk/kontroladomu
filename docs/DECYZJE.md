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
