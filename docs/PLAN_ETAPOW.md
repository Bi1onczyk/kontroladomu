# Plan etapów

Każdy etap kończy się tagiem Git, wpisem w `CHANGELOG.md` i krótką prezentacją dla właściciela
("Sprawdź sam"). Nie zaczynamy etapu, dopóki poprzedni nie jest zatwierdzony przez właściciela.

## Etap 0 — Fundament (v0.1.0)
- Instalacja Laravel 12 na XAMPP, Git + GitHub, `.env.example`, Pint, Larastan, Pest.
- Struktura katalogów (architekt), Enums, tabela `settings`, layouty, komponenty bazowe, paleta.
- Logowanie, rejestracja, reset hasła, weryfikacja e-mail, 2FA, role, Turnstile.
- Nagłówki bezpieczeństwa, rate limiting, dziennik aktywności, kopie zapasowe.
- Baza miejscowości TERYT (import komendą artisan) + `GeocodingService`.
- `docs/DOKUMENTACJA_TECHNICZNA.md` — rozdziały: uruchomienie lokalne, struktura, wdrożenie.

## Etap 1 — MVP: wyszukiwarka i profil (v0.2.0)
- Profil fachowca (edycja + strona publiczna), cennik, punkt startowy, promień, zaświadczenia.
- Weryfikacja fachowca w panelu admina + alert w panelu fachowca.
- Wyszukiwarka z mapą, wyniki, sortowanie, filtry, przyjazne adresy.

## Etap 2 — MVP: rezerwacja i kalendarz (v0.3.0)
- Godziny pracy, czas trwania przeglądów, siatka wolnych terminów.
- Rezerwacja z kontem i bez (potwierdzenie mailem), dane do faktury.
- Panel fachowca: Dziś, Kalendarz, Prośby, akceptacja/odrzucenie, maile.
- Trasa w Mapach Google.
- Admin: użytkownicy, ban z uzasadnieniem, ustawienia SMTP + mail testowy.
- **Pierwszy pełny audyt bezpieczeństwa + pierwsze wdrożenie testowe na home.pl.**

## Etap 3 — Właściciel nieruchomości (v0.4.0)
- Nieruchomości, `inspection_rules`, wyliczanie terminów, ręczna zmiana z potwierdzeniem.
- Przypomnienia mailowe (cron + kolejka), wypisanie się.
- Historia przeglądów.

## Etap 4 — Protokoły i opinie (v0.5.0)
- Formularze protokołów dla każdego rodzaju przeglądu, PDF, wgranie skanu, numer CEEB.
- Opinie, odpowiedzi, zgłoszenia, moderacja.

## Etap 5 — Firmy i statystyki (v0.6.0)
- Subkonta pracowników, przypisywanie przeglądów, "Mój dzień".
- Statystyki i szacowane przychody, eksport CSV.

## Etap 6 — Admin i treści (v0.7.0)
- Tickety wewnętrzne, słowniki, strony statyczne, FAQ, regulaminy (szkice do prawnika), cookies, DSA.
- Pola pod abonament i wyróżnienia, oznaczenie "Promowane".

## Etap 7 — Start produkcyjny (v1.0.0)
- Pełny audyt, testy wydajności, Cloudflare, kopie poza serwerem, monitoring błędów (logi + mail do admina).
- Weryfikacja prawna `docs/PRZEPISY.md` i regulaminów.

## Etap 8 — PWA (v1.1.0)
- Manifest, ikony, service worker, tryb offline "Mój dzień", powiadomienia push (opcjonalnie).
