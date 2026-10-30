# kontroladomu.pl — instrukcje główne projektu

> Ten plik czyta Claude Code przy każdej sesji. Jest to "konstytucja" projektu.
> Zasady z tego pliku mają pierwszeństwo przed domysłami agentów.
> Szczegóły funkcji: `docs/SPECYFIKACJA.md`. Kolejność prac: `docs/PLAN_ETAPOW.md`.

## 1. Czym jest projekt

kontroladomu.pl to polska platforma łącząca **właścicieli i zarządców nieruchomości**
ze **specjalistami od okresowych przeglądów**:

- przegląd kominiarski (przewody dymowe, spalinowe, wentylacyjne),
- przegląd instalacji elektrycznej i piorunochronnej,
- przegląd ogólnobudowlany (roczny i pięcioletni),
- przegląd instalacji gazowej,
- przegląd ekologiczny = **szamba (zbiorniki bezodpływowe) i przydomowe oczyszczalnie ścieków**.

Wzorzec funkcjonalny: ZnanyLekarz.pl (wyszukiwarka → profil specjalisty → rezerwacja terminu → opinia).
Serwis **nie pośredniczy w płatnościach** za przeglądy. Rozliczenie zawsze odbywa się bezpośrednio
między fachowcem a klientem.

Właściciel serwisu (administrator danych):
Dariusz Magdziak, ul. Modrzewiowa 6, 62-069 Dąbrówka, NIP 6931171824.

## 2. Osoba, z którą pracujesz

Właściciel projektu zna **tylko podstawy PHP**. Dlatego:

1. Przy każdej zmianie napisz po polsku 2–5 zdań: co zostało zrobione, dlaczego, jak to sprawdzić.
2. Nie zakładaj wiedzy o Laravelu. Gdy użytkownik ma coś uruchomić, podaj dokładną komendę.
3. Gdy decyzja jest biznesowa albo prawna (a nie techniczna), **zapytaj**, zamiast zgadywać.
4. Kod ma być czytelny dla innego programisty, który kiedyś zrobi audyt. Prostota > spryt.

## 3. Stos technologiczny (nie zmieniaj bez wpisu w `docs/DECYZJE.md`)

| Warstwa | Wybór | Uzasadnienie |
|---|---|---|
| Język | PHP 8.3 | wspierany przez XAMPP i home.pl |
| Framework | Laravel 12 | wybór właściciela |
| Baza | MySQL 8 / MariaDB 10.6+ | XAMPP lokalnie, MySQL na home.pl |
| Widoki | Blade + Livewire 3 + Alpine.js | brak osobnego SPA = prostsze utrzymanie |
| Style | Tailwind CSS 4 (build przez Vite) | budowane lokalnie, na serwer trafiają gotowe pliki |
| Mapy | Leaflet + OpenStreetMap | darmowe |
| Geokodowanie | Nominatim (z cache w bazie) + baza miejscowości TERYT (GUS) | darmowe; obsługa wsi |
| Kalendarz | FullCalendar (licencja MIT, tylko darmowe moduły) | |
| PDF | barryvdh/laravel-dompdf | działa na hostingu współdzielonym |
| Role | spatie/laravel-permission | |
| Logi aktywności | spatie/laravel-activitylog | |
| Kopie zapasowe | spatie/laravel-backup | baza + pliki użytkowników |
| Autoryzacja + 2FA | Laravel Fortify (2FA TOTP) | |
| Captcha | Cloudflare Turnstile | darmowa, bez śledzenia jak reCAPTCHA |
| Kolejki | driver `database` + cron | home.pl nie daje Redisa ani procesów w tle |
| Testy | Pest | |
| Jakość kodu | Laravel Pint, Larastan (poziom 6+) | |
| PWA (etap końcowy) | manifest + service worker | aplikacja instalowana z przeglądarki |

Nie dodawaj nowej paczki Composer/npm bez uzasadnienia w `docs/DECYZJE.md`
(nazwa, licencja, data ostatniego wydania, czy da się bez niej).

## 4. Środowiska

- **Lokalnie:** XAMPP na Windows. Projekt w `C:\xampp\htdocs\kontroladomu`, w XAMPP ustaw
  VirtualHost `kontroladomu.test` wskazujący na katalog `public/`. Mail lokalnie → Mailpit
  lub driver `log`.
- **Produkcja:** home.pl, hosting współdzielony. Wymagania, które każdy agent musi respektować:
  - katalog główny domeny wskazuje na `public/` (nie wolno wystawiać całego projektu),
  - brak stałych procesów w tle → kolejka obsługiwana przez cron co minutę:
    `php artisan schedule:run` a w harmonogramie `queue:work --stop-when-empty --max-time=50`,
  - brak Redisa → cache i sesje w `database` lub `file`,
  - pliki budowane (Vite) commitujemy jako wynik `npm run build` albo wgrywamy w paczce wdrożeniowej,
  - przed DDoS chroni **Cloudflare** (darmowy plan) postawiony przed domeną.
- Instrukcja przeniesienia na home.pl ma być w `docs/DOKUMENTACJA_TECHNICZNA.md` (rozdział "Wdrożenie").

## 5. Git, wersje i kopie zapasowe

1. Repozytorium Git od pierwszego dnia, zdalne prywatne repo na GitHubie.
2. Gałęzie: `main` (to, co działa na produkcji), `develop` (bieżąca praca), `feature/<krótka-nazwa>`.
3. Commity po polsku, w trybie rozkazującym, np. `Dodaj formularz rezerwacji terminu`.
4. Każdy zakończony etap z `docs/PLAN_ETAPOW.md` = tag wersji `v0.X.0` + wpis w `CHANGELOG.md`.
5. Przed każdą migracją na produkcji: kopia bazy (`php artisan backup:run --only-db`).
6. Kopie automatyczne: codziennie baza, co tydzień pliki; przechowywanie 14 dni; kopia także poza
   serwerem (np. drugi dysk FTP/chmura — do ustalenia z właścicielem).
7. Nigdy nie commituj `.env`, haseł, kluczy API ani plików wgranych przez użytkowników.

## 6. Zespół agentów i sposób współpracy

W `.claude/agents/` są zdefiniowani specjaliści:

| Agent | Odpowiada za |
|---|---|
| `architekt` | struktura aplikacji, model danych, decyzje techniczne, podział zadań |
| `ux-designer` | przepływy użytkownika, makiety, teksty interfejsu, dostępność, wygląd "ludzki" |
| `backend` | modele, migracje, logika, kontrolery, kolejki, maile, PDF, API |
| `frontend` | widoki Blade, komponenty Livewire, Tailwind, mapy, kalendarz, PWA |
| `tester` | testy Pest, scenariusze ręczne, regresja |
| `audytor-bezpieczenstwa` | przegląd kodu pod kątem OWASP, RODO, konfiguracji serwera |

**Ważne o komunikacji:** agenci nie rozmawiają ze sobą bezpośrednio. Główna sesja Claude
działa jako **koordynator** i przekazuje pracę między nimi. "Rozmowa" agentów odbywa się
przez pliki w `docs/zadania/`. Każde zadanie ma jeden plik (szablon: `docs/zadania/_SZABLON.md`),
do którego kolejni agenci dopisują swoje sekcje.

### Standardowy przepływ jednej funkcji

```
koordynator tworzy docs/zadania/NNN-nazwa.md
   → architekt: model danych, pliki do zmiany, ryzyka
   → ux-designer: przepływ ekranów, teksty, stany (pusty / błąd / sukces)
   → backend: implementacja + migracje
   → frontend: widoki
   → tester: testy automatyczne + lista kroków do ręcznego sprawdzenia
   → audytor-bezpieczenstwa: przegląd; blokery wracają do backend/frontend
   → koordynator: aktualizuje dokumentację, CHANGELOG, podsumowuje użytkownikowi
```

Małe poprawki (literówka, kolor przycisku) nie wymagają całego przepływu.
Wszystko, co dotyka logowania, uprawnień, danych osobowych, plików lub formularzy,
**zawsze** przechodzi przez audytora.

### Zasady koordynatora

- Zanim delegujesz, przeczytaj plik zadania i przekaż agentowi jego ścieżkę.
- Jedno zadanie naraz. Nie zaczynaj następnego, dopóki tester i audytor nie dali zielonego światła.
- Gdy agenci się nie zgadzają, rozstrzyga architekt, a decyzję zapisuje w `docs/DECYZJE.md`.
- Po zakończeniu zadania zaktualizuj `docs/DOKUMENTACJA_TECHNICZNA.md`.

## 7. Standardy kodu

1. PSR-12, formatowanie Laravel Pint. Nazwy klas, metod i zmiennych **po angielsku**,
   komentarze i dokumentacja **po polsku**, teksty interfejsu przez pliki `lang/pl/*.php`.
2. Cienkie kontrolery. Logika w klasach `app/Actions/` (jedna akcja = jedna klasa, np.
   `AcceptBookingRequest`) albo `app/Services/` dla większych obszarów.
3. Walidacja tylko przez Form Requesty (`app/Http/Requests/`).
4. Uprawnienia tylko przez Policies i Gates. Nigdy `if ($user->id == 1)`.
5. Każda klasa i każda publiczna metoda ma komentarz PHPDoc po polsku: co robi i dlaczego.
6. Statusy i typy jako PHP Enums (`app/Enums/`), np. `InspectionType`, `BookingStatus`.
7. Zapytania do bazy wyłącznie przez Eloquent / Query Builder z parametrami.
   `DB::raw` i `whereRaw` tylko z bindowaniem i komentarzem, dlaczego są konieczne.
8. Żadnych zapytań w pętli (N+1) — używaj `with()`. Tester to sprawdza.
9. Daty w bazie w UTC, wyświetlanie w `Europe/Warsaw`, format polski (`29.10.2026`).
10. Kwoty w groszach jako `integer`, nigdy `float`.
11. Struktura katalogów modułowa w obrębie Laravela, np.
    `app/Domain/Bookings/`, `app/Domain/Inspections/`, `app/Domain/Properties/` — ustala architekt.

## 8. Bezpieczeństwo (obowiązkowe, nienegocjowalne)

- Ochrona CSRF na wszystkich formularzach (domyślna w Laravelu — nie wyłączać).
- Escapowanie wyjścia: tylko `{{ }}` w Blade. `{!! !!}` zabronione, chyba że treść przeszła przez
  HTML Purifier — z komentarzem.
- SQL injection: patrz punkt 7.7. Brak sklejania SQL z danych użytkownika.
- Mass assignment: każdy model ma jawne `$fillable`. Nigdy `$guarded = []`.
- Rate limiting: logowanie, rejestracja, reset hasła, rezerwacja, wyszukiwarka, formularz kontaktowy.
- Hasła: domyślny bcrypt/argon Laravela, minimum 12 znaków, sprawdzenie wycieku (`Password::uncompromised()`).
- 2FA **obowiązkowe** dla administratora i właściciela konta fachowca; opcjonalne dla pozostałych.
- Turnstile na rejestracji, rezerwacji bez konta, formularzu kontaktowym, resecie hasła.
- Pliki od użytkowników (zaświadczenia, protokoły, zdjęcia): walidacja typu po zawartości (MIME),
  limit rozmiaru, zapis poza `public/` na dysku `local`, wydawanie przez kontroler z autoryzacją,
  losowe nazwy plików.
- Nagłówki: CSP, HSTS, X-Frame-Options, Referrer-Policy, Permissions-Policy (middleware).
- `APP_DEBUG=false` na produkcji. Błędy logowane, nie wyświetlane.
- Dziennik aktywności administratora (kto, co, kiedy, z jakiego IP).
- DDoS: Cloudflare + rate limiting w aplikacji. Nie obiecuj użytkownikowi ochrony 100%.

## 9. Prawo — zasady dla wszystkich agentów

1. **RODO:** minimalizacja danych, podstawa prawna każdego pola, zgody marketingowe oddzielnie
   i nieobowiązkowe, prawo do eksportu i usunięcia danych z panelu, okres przechowywania opisany.
2. **Cookies:** baner z wyborem przed załadowaniem czegokolwiek nieniezbędnego
   (domyślnie tylko niezbędne; analityka dopiero po zgodzie).
3. **Opinie (dyrektywa Omnibus):** opinię może wystawić tylko zalogowany klient po przeglądzie
   oznaczonym jako wykonany. Na stronie informacja, jak weryfikujemy opinie.
   Fachowiec może odpowiedzieć na opinię i ją zgłosić, ale nie może jej usunąć.
4. **DSA (platformy pośredniczące):** mechanizm zgłaszania nielegalnych treści, uzasadnienie
   decyzji o banie / usunięciu treści, punkt kontaktowy.
5. **Wyróżnienia płatne** muszą być oznaczone jako "Promowane" w wynikach wyszukiwania.
6. **Protokół kominiarski:** od 18.09.2023 protokół z okresowej kontroli przewodów kominowych
   sporządza się elektronicznie w systemie CEEB (e-protokół). Nasz system **nie zastępuje** CEEB.
   Fachowiec wpisuje numer e-protokołu z CEEB i może dołączyć jego PDF; nasz PDF jest kopią informacyjną.
   W interfejsie musi to być jasno napisane.
7. Terminy przeglądów pochodzą z tabeli konfigurowalnej `inspection_rules` (seeder z `docs/PRZEPISY.md`),
   **nie** z wartości wpisanych na sztywno w kodzie. Każda reguła ma pole `legal_basis` i `verified_at`.
8. Regulaminy i polityki generowane przez agentów są **szkicami**. W stopce każdego szkicu dodaj
   komentarz `<!-- DO WERYFIKACJI PRZEZ PRAWNIKA -->`.

## 10. Wygląd: ma wyglądać jak zrobione przez człowieka

Szczegóły w `.claude/agents/ux-designer.md`. Najważniejsze zakazy:
- żadnych fioletowo-niebieskich gradientów, "szklanych" kart, emoji w interfejsie,
- żadnych ogólnikowych haseł typu "Zrewolucjonizuj sposób, w jaki...",
- żadnych trzech identycznych kafelków z ikonką jako "sekcji korzyści" na każdej podstronie,
- teksty konkretne, po polsku, językiem urzędnika, który jest uprzejmy (np. "Kominiarz z uprawnieniami,
  wolny termin we wtorek"),
- kolorystyka: zieleń i biel, poczucie zaufania i porządku (paleta w pliku agenta UX).

## 11. Definicja "gotowe"

Zadanie jest skończone, gdy:
- [ ] kod przechodzi `php artisan test`, `./vendor/bin/pint --test`, `./vendor/bin/phpstan analyse`,
- [ ] audytor nie zgłosił blokerów,
- [ ] widok działa na telefonie (szerokość 375 px) i na komputerze,
- [ ] teksty są w `lang/pl`, bez literówek,
- [ ] `docs/DOKUMENTACJA_TECHNICZNA.md` i `CHANGELOG.md` są zaktualizowane,
- [ ] użytkownik dostał krótkie podsumowanie i instrukcję, jak to sprawdzić w przeglądarce.

## 12. Czego nie robić

- Nie usuwaj danych, migracji ani plików bez wyraźnej zgody użytkownika.
- Nie uruchamiaj `migrate:fresh` ani `db:wipe` poza bazą testową.
- Nie zmieniaj stosu technologicznego "przy okazji".
- Nie implementuj płatności za przeglądy. Płatności za abonament fachowca to osobny, późny etap.
- Nie wymyślaj przepisów. Jeśli czegoś nie ma w `docs/PRZEPISY.md`, zapytaj.
