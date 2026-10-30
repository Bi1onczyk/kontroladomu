# Przepisy i terminy przeglądów — dane do tabeli `inspection_rules`

> STATUS: robocze zestawienie z 29.10.2026. **Przed uruchomieniem serwisu do weryfikacji
> przez prawnika lub rzeczoznawcę budowlanego.** Agenci NIE dopisują tu nic bez źródła.
> Pole `verified_at` w tabeli ustawiamy dopiero po weryfikacji.

## Podstawy prawne

- Ustawa z 7 lipca 1994 r. — Prawo budowlane, **art. 62** (okresowe kontrole) i **art. 62a** (protokół).
- Rozporządzenie MSWiA z 7 czerwca 2010 r. w sprawie ochrony przeciwpożarowej budynków, innych obiektów
  budowlanych i terenów, **§ 34** (usuwanie zanieczyszczeń z przewodów kominowych).
- Ustawa z 13 września 1996 r. o utrzymaniu czystości i porządku w gminach (szamba, oczyszczalnie,
  częstotliwość opróżniania określają uchwały gmin).

## Kontrole okresowe (art. 62 Prawa budowlanego)

| Zakres | Częstotliwość | Kto wykonuje | Uwagi |
|---|---|---|---|
| Instalacja gazowa | min. 1× w roku | osoba z uprawnieniami budowlanymi w specjalności instalacyjnej lub świadectwem kwalifikacyjnym (art. 62 ust. 5) | obowiązuje też domy jednorodzinne |
| Przewody kominowe (dymowe, spalinowe, wentylacyjne) | min. 1× w roku | mistrz kominiarski (dymowe i grawitacyjne) lub osoba z uprawnieniami budowlanymi (art. 62 ust. 6) | od 18.09.2023 protokół jako **e-protokół w CEEB** (art. 62a ust. 5) |
| Elementy budynku narażone na wpływy atmosferyczne | min. 1× w roku | uprawnienia budowlane | **nie dotyczy** domów jednorodzinnych (art. 62 ust. 2) — zweryfikować pełną listę wyłączeń |
| Ogólny stan techniczny, estetyka, otoczenie | min. 1× na 5 lat | uprawnienia budowlane | |
| Instalacja elektryczna i piorunochronna (w tym rezystancja izolacji, uziemienia) | min. 1× na 5 lat | uprawnienia budowlane lub świadectwo kwalifikacyjne | |
| Obiekty wielkopowierzchniowe (>2000 m² zabudowy, dach >1000 m²) | 2× w roku: do 31 maja i do 30 listopada | uprawnienia budowlane | |

## Czyszczenie przewodów (§ 34 rozporządzenia MSWiA) — przypomnienia dodatkowe

| Przewody | Częstotliwość |
|---|---|
| od palenisk na paliwo stałe | co najmniej raz na 3 miesiące |
| od palenisk na paliwo płynne i gazowe | co najmniej raz na 6 miesięcy |
| wentylacyjne | co najmniej raz w roku |
| od palenisk zakładów gastronomicznych | co najmniej raz w miesiącu |

Uwaga: w domach jednorodzinnych i budownictwie zagrodowym czyszczenie może wykonać sam użytkownik
(§ 34 ust. 4), ale **kontrola** okresowa nadal wymaga uprawnionej osoby. Interfejs musi rozróżniać
"czyszczenie" i "kontrolę".

## Przegląd ekologiczny — szamba i przydomowe oczyszczalnie

Brak jednego ustawowego interwału przeglądu. Częstotliwość opróżniania szamb i wywozu osadów
określa **regulamin utrzymania czystości danej gminy**, a dla oczyszczalni także instrukcja producenta.
W systemie: domyślny interwał konfigurowalny przez właściciela (propozycja: 12 miesięcy),
z dopiskiem "Sprawdź regulamin swojej gminy". Do ustalenia z właścicielem projektu.

## Tekst ostrzeżenia przy ręcznej zmianie interwału (do weryfikacji)

> Zmieniasz termin przypomnienia na dłuższy niż wynika z przepisów ({przepis}: {interwał}).
> Przypomnienie to tylko pomoc — za terminowe wykonanie kontroli odpowiada właściciel lub
> zarządca obiektu. Czy na pewno chcesz zmienić termin?

## Do sprawdzenia przed startem
- [ ] pełna lista wyłączeń z art. 62 ust. 2 (budynki jednorodzinne, rekreacji indywidualnej, gospodarcze)
- [ ] czy c-KOB (cyfrowa książka obiektu budowlanego) wymaga integracji lub informacji w protokołach
- [ ] zakres uprawnień do przeglądu szamb/oczyszczalni (czy w ogóle wymagane)
- [ ] aktualny tekst jednolity Prawa budowlanego na dzień uruchomienia
