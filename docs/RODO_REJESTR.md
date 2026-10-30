# Rejestr danych osobowych (roboczy)

> Prowadzi audytor-bezpieczenstwa. Podstawa do polityki prywatności. Do weryfikacji przez prawnika.
> Administrator danych: Dariusz Magdziak, ul. Modrzewiowa 6, 62-069 Dąbrówka, NIP 6931171824.

| Dane | Kogo dotyczą | Cel | Podstawa (art. 6 RODO) | Okres przechowywania | Kto ma dostęp |
|---|---|---|---|---|---|
| imię, nazwisko, e-mail, telefon | klient | realizacja rezerwacji | 6.1.b | do 3 lat po ostatniej rezerwacji (do ustalenia) | fachowiec danej rezerwacji, admin |
| adres nieruchomości, współrzędne | klient | dojazd, wyszukiwanie | 6.1.b | jw. | jw. |
| dane do faktury | klient | wystawienie faktury przez fachowca | 6.1.b / 6.1.c | jw. | fachowiec, admin |
| zaświadczenia o uprawnieniach | fachowiec | weryfikacja | 6.1.b / 6.1.f | czas trwania konta + 1 rok | admin |
| adres IP, logi | wszyscy | bezpieczeństwo | 6.1.f | 90 dni (dziennik działań administratorów: 12 miesięcy, patrz niżej) | admin |

## Konta i uwierzytelnianie (zadanie 002, audyt 30.10.2026)

Tabela `users` i tabele pomocnicze. Kolumna "Stan" mówi, czy mechanizm z kolumny "Okres" jest już zaimplementowany w kodzie.

| Dane (tabela.kolumna) | Kogo dotyczą | Cel | Podstawa (art. 6 RODO) | Okres przechowywania | Kto ma dostęp | Stan |
|---|---|---|---|---|---|---|
| `users.name` (imię i nazwisko) | właściciel, fachowiec, pracownik, admin | prowadzenie konta, kontakt, personalizacja pulpitu | 6.1.b | do usunięcia konta; po usunięciu tylko dane niezbędne do obrony roszczeń (propozycja: do 3 lat, 6.1.f, do potwierdzenia prawnika) | użytkownik, admin | brak usuwania konta na żądanie (do zrobienia) |
| `users.email` | wszyscy | login, wysyłka maili transakcyjnych (potwierdzenie, reset hasła, powiadomienia o zmianach), kontakt | 6.1.b | jak wyżej; konta niepotwierdzone usuwane po 7 dniach | użytkownik, admin | `accounts:prune-unverified` działa |
| `users.password` (skrót bcrypt) | wszyscy | uwierzytelnianie | 6.1.b, art. 32 | do usunięcia konta | tylko aplikacja (ukryte w `$hidden`) | tak |
| `users.remember_token` | wszyscy | "Zapamiętaj mnie" (ciasteczko do ok. 400 dni) | 6.1.b (funkcja wybrana przez użytkownika) | do wylogowania, zmiany/resetu hasła lub usunięcia konta | tylko aplikacja | tak |
| `users.email_verified_at` | wszyscy | dowód potwierdzenia adresu | 6.1.b, 6.1.f (zapobieganie nadużyciom) | do usunięcia konta | admin | tak |
| `users.nip`, `users.company_name` | fachowiec (często osoba fizyczna prowadząca działalność, więc NIP to dane osobowe) | identyfikacja przedsiębiorcy, przyszły okres próbny | 6.1.b; (okres próbny po NIP: osobna podstawa do ustalenia, patrz ADR-010) | do usunięcia konta; identyfikator wykorzystanego okresu próbnego po usunięciu: do decyzji z prawnikiem | admin, (publicznie w profilu: dopiero w zadaniu profili) | NIP nie jest unikalny i nie ujawnia innych kont (zweryfikowane) |
| `users.terms_accepted_at`, `terms_version`, `privacy_accepted_at`, `privacy_version` | wszyscy | dowód akceptacji regulaminu i zapoznania z polityką (rozliczalność, art. 5 ust. 2) | 6.1.b, 6.1.c (obowiązek informacyjny), 6.1.f (dowód) | do usunięcia konta + okres przedawnienia roszczeń (do ustalenia) | admin | tak, czas w UTC |
| `users.marketing_consent_at`, `marketing_consent_version` | wszyscy | zgoda na informacje handlowe (osobna, nieobowiązkowa; NULL = brak zgody) | 6.1.a | do wycofania zgody; po wycofaniu zachować dowód udzielenia i wycofania przez okres przedawnienia | admin | BRAK ekranu wycofania zgody (art. 7 ust. 3: wycofanie ma być tak łatwe jak udzielenie) |
| `users.banned_at` | wszyscy | blokada konta (DSA art. 17: uzasadnienie decyzji w zadaniu admina/Etapie 6) | 6.1.c (DSA), 6.1.f | do ustalenia w Etapie 6 | admin | tylko kolumna i komunikat |
| `users.two_factor_secret`, `two_factor_recovery_codes` (zaszyfrowane `APP_KEY`), `two_factor_confirmed_at`, `two_factor_reminder_until` | wszyscy (2FA obowiązkowe: fachowiec, admin) | weryfikacja dwuetapowa | 6.1.f, art. 32 RODO, 6.1.b | do wyłączenia 2FA lub usunięcia konta; reset przez admina je kasuje | tylko aplikacja (`$hidden`) | tak |
| rola (`model_has_roles`, `roles`) | wszyscy | autoryzacja | 6.1.b | do usunięcia konta (Spatie czyści powiązania przy `delete()`) | admin | tak |
| `sessions` (id, `user_id`, `ip_address`, `user_agent`, payload) | zalogowani i odwiedzający | utrzymanie sesji, bezpieczeństwo | 6.1.b (sesja zalogowanego), 6.1.f | 120 minut bezczynności; wiersz usuwa też zmiana/reset hasła i reset 2FA | tylko aplikacja, administrator bazy | wiersze nie są kasowane przy usunięciu konta, wygasają same |
| `password_reset_tokens` (e-mail, skrót tokenu) | wszyscy | reset hasła | 6.1.b | token 60 minut; komenda `auth:clear-resets` codziennie | tylko aplikacja | tak |
| `activity_log` log `security` (zdarzenia kont: rejestracja, logowanie, wylogowanie, zmiany hasła/2FA; w `properties`: IP, user-agent, rola, `remember`, `marketing_consent`) | wszyscy | bezpieczeństwo, rozliczalność, wykrywanie nadużyć | 6.1.f | 90 dni (`activity:prune`) | admin | tak; NIE zapisuje haseł, kodów, sekretów, tokenów ani adresów e-mail z nieudanych logowań |
| `activity_log` log `security_admin` (utworzenie admina, reset 2FA; w `properties`: powód, operator systemu, host) | użytkownicy objęci działaniem admina, administratorzy | rozliczalność działań uprzywilejowanych, DSA/art. 32 | 6.1.f | 12 miesięcy (propozycja, do potwierdzenia prawnika; w polityce prywatności jest jeszcze "do 90 dni") | admin | tak; pole "powód" jest tekstem wolnym: nie wpisywać tam danych wrażliwych |
| `jobs`, `failed_jobs` (zserializowane powiadomienia: adres e-mail, w mailu resetu także token) | wszyscy | wysyłka maili przez kolejkę | 6.1.b | `jobs`: do wysłania; `failed_jobs`: 14 dni (`queue:prune-failed --hours=336`) | administrator bazy | tak |
| `cache` (klucze limiterów): `login-failures:{IP}` (jawny IP, 15 min), skróty `md5` kluczy limiterów zawierających e-mail i IP (do 1 h), `2fa-fail:{user_id}` (15 min), `register-attempt:{sha1(e-mail)}` (1 h) | wszyscy | ograniczanie nadużyć (rate limiting) | 6.1.f | od 15 minut do 1 godziny | tylko aplikacja | tak |
| ciasteczka: sesja (`*_session`), `XSRF-TOKEN`, `remember_web_*` (tylko po wyborze "Zapamiętaj mnie") | wszyscy | sesja, ochrona CSRF | niezbędne (art. 173 PT), bez zgody | sesja do zamknięcia/120 min; "zapamiętaj" do ok. 400 dni | przeglądarka użytkownika | patrz uwaga o fladze Secure (audyt S5) |
| Cloudflare Turnstile (adres IP, user-agent, sygnały przeglądarki wysyłane do Cloudflare; skrypt `challenges.cloudflare.com`; serwer wysyła do Cloudflare token i IP) | odwiedzający formularze rejestracji, resetu hasła, logowania (po 3 błędach) | ochrona przed botami | 6.1.f (bezpieczeństwo); Cloudflare jako podmiot przetwarzający, transfer poza EOG (USA, podstawa do ustalenia: DPF/SCC) | po stronie Cloudflare, zgodnie z jego umową | Cloudflare, Inc. | ładuje się przed jakimkolwiek wyborem w banerze cookies: do oceny prawnika (czy "ściśle niezbędne"); w polityce nadal "[do uzupełnienia]" |
| Have I Been Pwned (`Password::uncompromised()`): 5 pierwszych znaków skrótu SHA-1 hasła wysyłane na `api.pwnedpasswords.com` przy rejestracji, resecie i zmianie hasła | wszyscy | blokada haseł z wycieków | 6.1.f, art. 32 | brak zapisu po naszej stronie | zewnętrzny serwis (adres IP serwera, nie użytkownika) | k-anonimowość; opisane w polityce prywatności |
| Dostawca poczty (SMTP, do wyboru na produkcji): adres e-mail, treść maili transakcyjnych | wszyscy | wysyłka maili | 6.1.b | wg dostawcy | dostawca poczty (podmiot przetwarzający, umowa powierzenia) | dostawca nie jest jeszcze wybrany; na produkcji NIE używać `MAIL_MAILER=log` (tokeny trafiłyby do `storage/logs`) |
| Logi serwera WWW / `storage/logs/laravel.log` | wszyscy | diagnostyka błędów | 6.1.f | do 14 dni (do skonfigurowania rotacji `LOG_STACK=daily`) | administrator serwera | w adresie linku resetu hasła jest e-mail i token (GET): patrz audyt N7 |

### Luki RODO do domknięcia przed wdrożeniem (poza zakresem zadania 002)
1. Eksport i usunięcie konta z panelu (CLAUDE.md §9.1) — brak; w tym kasowanie powiązanych wierszy `sessions` i anonimizacja `activity_log.causer_id`.
2. Wycofanie zgody marketingowej z panelu i wpis w dzienniku.
3. Baner cookies/zgód (CLAUDE.md §9.2) — obecnie serwis używa tylko cookies niezbędnych, ale Turnstile ładuje zasoby Cloudflare na stronach auth bez wyboru użytkownika.
4. Uzupełnienie polityki prywatności: dane Cloudflare i mechanizm transferu, okres 12 miesięcy dla dziennika działań admina, dostawca poczty.
5. Procedura odpowiedzi na żądania osób (art. 15-22) i rejestr naruszeń.

<!-- uzupełniać przy każdym nowym polu -->
