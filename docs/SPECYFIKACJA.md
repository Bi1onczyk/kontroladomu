# Specyfikacja funkcjonalna — kontroladomu.pl

Wersja 1.0 (29.10.2026). Źródło prawdy o tym, CO ma robić serwis. JAK — decyduje architekt.
Zmiany w tym pliku tylko po akceptacji właściciela projektu.

## 1. Role użytkowników

| Rola | Opis | Rejestracja |
|---|---|---|
| Gość | przegląda, wyszukuje, rezerwuje bez konta (potwierdzenie e-mailem) | nie |
| Właściciel (`owner`) | właściciel lub zarządca nieruchomości | opcjonalna |
| Fachowiec (`specialist`) | właściciel konta: JDG lub firma | tak; podaje NIP, a profil weryfikuje ręcznie administrator |
| Pracownik (`technician`) | subkonto pracownika firmy fachowca | zakłada fachowiec |
| Administrator (`admin`) | jeden poziom uprawnień | zakładany komendą artisan |

Jedna osoba może mieć konto właściciela i fachowca (np. kominiarz z własnym domem) — rozdzielone panele.

## 2. Słowniki

**Rodzaje przeglądów:** kominiarski, elektryczny (z piorunochronną), ogólnobudowlany roczny,
ogólnobudowlany pięcioletni, gazowy, ekologiczny (szambo / przydomowa oczyszczalnia ścieków).

**Rodzaje nieruchomości:** dom jednorodzinny, mieszkanie, lokal użytkowy, budynek wielorodzinny
(zarządca/wspólnota), obiekt wielkopowierzchniowy (>2000 m² zabudowy lub dach >1000 m²).

**Dodatkowe cechy nieruchomości** (wpływają na terminy): rodzaj paliwa (stałe / płynne / gaz /
brak spalania), czy jest instalacja gazowa, czy jest szambo / oczyszczalnia, powierzchnia.

## 3. Wyszukiwarka (strona główna i `/szukaj`)

- Pola: rodzaj przeglądu, rodzaj nieruchomości, miejscowość (autouzupełnianie z bazy TERYT,
  także wsie, z gminą i powiatem) lub "Użyj mojej lokalizacji".
- Wynik: fachowcy, którzy (a) wykonują dany rodzaj przeglądu, (b) obsługują dany typ nieruchomości,
  (c) obejmują lokalizację swoim promieniem dojazdu i (d) nie są zbanowani. Status weryfikacji
  nie wyklucza z wyników — profile zweryfikowane i oczekujące na weryfikację są widoczne,
  a ich status musi być jasno oznaczony.
- Sortowanie: trafność (domyślnie: promowani na górze z oznaczeniem, potem odległość i ocena),
  odległość, ocena, cena, najbliższy wolny termin.
- Filtry: wolny termin w ciągu 7 dni, firma / osoba. Nie ma filtra ukrywającego fachowców
  oczekujących na weryfikację.
- Widok: lista + mapa Leaflet (na telefonie przełącznik). Cena "od" liczona z cennika i odległości.
- Przyjazne adresy SEO: `/kominiarz/krakow`, `/przeglad-elektryczny/dabrowka-gm-dopiewo`.

## 4. Profil publiczny fachowca (`/fachowiec/{slug}`)

Zdjęcie/logo, nazwa, znaczek "Zweryfikowany" z datą weryfikacji albo czytelna informacja
o oczekiwaniu na weryfikację, opis, rodzaje przeglądów,
typy nieruchomości, cennik, obszar działania na mapie (okrąg), certyfikaty i uprawnienia
(miniatury + rodzaj + data ważności; pełny skan widoczny publicznie tylko, jeśli fachowiec wyrazi zgodę),
opinie ze średnią, siatka wolnych terminów, przycisk "Umów przegląd".

## 5. Rezerwacja

1. Klient wybiera termin z siatki fachowca.
2. Formularz: imię i nazwisko, adres nieruchomości (z mapą do poprawienia pinezki), e-mail, telefon,
   rodzaj nieruchomości, uwagi; opcjonalnie dane do faktury (nazwa, NIP, adres).
   Zalogowany właściciel wybiera nieruchomość z listy.
3. Gość potwierdza rezerwację linkiem z maila (ważny 24 h). Bez potwierdzenia prośba nie trafia do fachowca.
4. Fachowiec akceptuje lub odrzuca (powód obowiązkowy przy odrzuceniu, z listy + pole tekstowe).
5. Klient dostaje mail o decyzji; może anulować rezerwację do końca dnia poprzedzającego
   dzień przeglądu (niezależnie od godziny). Fachowiec może anulować rezerwację także w dniu
   przeglądu, na przykład po uzgodnieniu tego telefonicznie z klientem.
6. Po terminie fachowiec oznacza "Wykonano" → wystawia protokół → przegląd trafia do historii
   nieruchomości i aktualizuje datę ostatniego przeglądu.
7. Zalogowany klient dostaje prośbę o opinię. Gość dostaje propozycję założenia konta, żeby ją wystawić
   (konto łączy się z rezerwacją po e-mailu).

## 6. Panel właściciela nieruchomości

- Lista nieruchomości (adres, typ, cechy, pinezka na mapie).
- Dla każdej nieruchomości i rodzaju przeglądu: data ostatniego przeglądu, data następnego (liczona
  automatycznie z `inspection_rules`), status (aktualny / zbliża się / po terminie).
- Ręczna zmiana interwału: możliwa, ale z oknem potwierdzenia pokazującym termin wynikający
  z przepisów i ostrzeżeniem o odpowiedzialności (tekst w pliku przepisów). Zmiana zapisywana w historii.
- Historia przeglądów z protokołami PDF do pobrania.
- Przypomnienia e-mail: 30 dni i 7 dni przed terminem (można wyłączyć dla danej nieruchomości).
  W mailu przycisk "Znajdź fachowca" z wypełnioną wyszukiwarką.
- Moje rezerwacje, moje opinie, ustawienia konta, eksport danych (RODO), usunięcie konta.

## 7. Panel fachowca (NAJWAŻNIEJSZY — maksymalnie prosty)

Menu (max 5): **Dziś**, **Kalendarz**, **Prośby**, **Historia i statystyki**, **Profil i ustawienia**.

- **Dziś:** lista przeglądów w kolejności trasy, mapa z numerowanymi pinezkami, przycisk
  "Otwórz trasę w Mapach Google" (od punktu startowego przez wszystkie adresy), przełącznik dnia
  (wczoraj / jutro / wybór daty). Przy każdym przeglądzie: adres, telefon (klik = dzwoń), uwagi, przycisk
  "Wykonano".
- **Kalendarz:** widok dzień / tydzień / miesiąc, prośby (oczekujące) w innym kolorze, akceptacja
  i odrzucenie z poziomu kalendarza. Fachowiec ustawia godziny pracy, przerwy, dni wolne i urlopy
  oraz czas trwania przeglądu dla każdego rodzaju.
- **Prośby:** lista oczekujących z licznikiem w menu; Akceptuj / Odrzuć / Zaproponuj inny termin.
- **Protokół:** po "Wykonano" formularz protokołu dla danego rodzaju przeglądu (pola zgodne z art. 62a
  Prawa budowlanego) → PDF. Alternatywnie wgranie własnego skanu PDF. Dla kominiarskiego dodatkowo
  pole "Numer e-protokołu CEEB" i informacja, że obowiązujący protokół sporządza się w CEEB.
- **Historia i statystyki:** liczba wykonanych przeglądów i **szacowany** przychód w miesiącu / roku
  (z cennika + dojazd), wykres miesięczny, podział na rodzaje przeglądów, eksport CSV.
- **Profil i ustawienia:** dane, opis, zdjęcie, rodzaje przeglądów, typy nieruchomości, **cennik**
  (cena za rodzaj przeglądu × typ nieruchomości + stawka za km dojazdu, opcjonalnie km gratis),
  **punkt startowy** (siedziba / dom — pinezka), promień działania, zaświadczenia, pracownicy.
- **Zaświadczenia i weryfikacja:** fachowiec podaje NIP i wgrywa skany zaświadczeń (rodzaj, numer,
  data ważności); administrator ręcznie sprawdza profil i dokumenty, po czym zatwierdza albo odrzuca
  weryfikację. Do czasu weryfikacji fachowiec jest widoczny w wyszukiwarce ze statusem "Oczekuje na
  weryfikację". W panelu fachowca jest żółty alert o oczekiwaniu i lista brakujących dokumentów.
  30 dni przed wygaśnięciem uprawnienia — mail i alert.
- **Pracownicy (firma):** dodanie pracownika (imię, e-mail → zaproszenie), przypisanie rodzajów przeglądów
  i uprawnień (pracownik ma własne zaświadczenia do weryfikacji), przypisywanie przeglądów pracownikom.
- **Subkonto pracownika:** tylko "Mój dzień" (lista, mapa, trasa) i "Wykonano / Wystaw protokół".

## 8. Opinie

Ocena 1–5 + tekst (min. 20 znaków). Tylko zalogowany klient, tylko po rezerwacji `completed`,
jedna opinia na rezerwację, do 60 dni od przeglądu. Fachowiec może odpowiedzieć publicznie i zgłosić
opinię do admina. Strona "Jak weryfikujemy opinie".

## 9. Panel administratora

- Pulpit: nowe konta do weryfikacji, zgłoszenia treści, statystyki serwisu.
- **Weryfikacja fachowców:** podgląd zaświadczeń, zatwierdzenie (znaczek), odrzucenie z uzasadnieniem
  (mail do fachowca), cofnięcie weryfikacji.
- **Użytkownicy:** wyszukiwanie, podgląd, ban czasowy / stały z uzasadnieniem (DSA), odbanowanie.
- **Moderacja:** zgłoszone opinie i profile.
- **Tickety wewnętrzne:** tytuł, opis, priorytet, status (nowy / w toku / czeka / zamknięty), przypisanie
  do admina lub "programista", komentarze, załączniki. (Wszyscy admini mają ten sam poziom uprawnień;
  "programista" to admin z oznaczeniem.)
- **Słowniki i reguły:** rodzaje przeglądów, typy nieruchomości, `inspection_rules` (terminy z podstawą prawną).
- **Ustawienia:** dane SMTP (host, port, szyfrowanie, login, hasło, nadawca) + "Wyślij test",
  terminy przypomnień, teksty stron statycznych, okres darmowy dla fachowców, ceny wyróżnień.
- **Dziennik aktywności** administratorów.
- **Kopie zapasowe:** lista kopii, przycisk "Utwórz kopię teraz".

## 10. Model biznesowy (przygotowanie, nie implementacja płatności w MVP)

- Właściciele: zawsze za darmo.
- Fachowcy: każdy NIP może skorzystać z jednego 6-miesięcznego okresu darmowego, liczonego od
  pierwszej rejestracji tego NIP-u. Kolejne konto z tym samym NIP-em nie otrzymuje ponownie okresu
  próbnego. Po okresie próbnym obowiązuje abonament. W MVP tylko pola w bazie: `plan`,
  `trial_ends_at`, `featured_until`; płatności za abonament nie są wdrażane.
- Wyróżnienie (promowanie w wynikach) — w MVP nadawane ręcznie przez admina, oznaczane "Promowane".

## 11. Strony statyczne

Strona główna, Jak to działa (dla właścicieli / dla fachowców), Przepisy i terminy przeglądów
(poradnik — także SEO), Cennik dla fachowców, FAQ, Kontakt (formularz + dane firmy), Regulamin,
Polityka prywatności, Polityka cookies, Jak weryfikujemy fachowców i opinie, Zgłoś nielegalną treść,
Mapa strony, strona 404/500 w stylu serwisu.

## 12. Wymagania niefunkcjonalne

- Czas odpowiedzi stron < 1 s na hostingu, Lighthouse ≥ 90 (wydajność, dostępność, SEO) na telefonie.
- WCAG 2.1 AA.
- Działa w ostatnich 2 wersjach Chrome, Firefox, Safari, Edge oraz Chrome na Androidzie 10+.
- Wszystkie maile w szablonie serwisu, z danymi firmy w stopce.
- PWA na końcu projektu.
