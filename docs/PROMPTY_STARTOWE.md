# Prompty startowe — kopiuj i wklejaj do Claude w VS Code

Wklejaj po kolei. Nie przechodź do następnego, dopóki poprzedni nie jest zakończony
i nie sprawdziłeś wyniku w przeglądarce.

---

## 0. Pierwsze uruchomienie (raz)

```
Przeczytaj CLAUDE.md, docs/SPECYFIKACJA.md, docs/PLAN_ETAPOW.md, docs/PRZEPISY.md
oraz definicje agentów w .claude/agents/. Działasz jako koordynator zespołu.

Nie pisz jeszcze kodu. Zrób trzy rzeczy:
1. Streść mi w 10 punktach, jak rozumiesz projekt.
2. Wypisz wszystkie niejasności i sprzeczności, które znalazłeś w dokumentach.
3. Podaj, co muszę zainstalować na Windows (XAMPP z PHP 8.3, Composer, Node.js, Git)
   i jak sprawdzić, czy działa — krok po kroku, dla osoby znającej tylko podstawy PHP.
```

## 1. Etap 0 — fundament

```
Zaczynamy Etap 0 z docs/PLAN_ETAPOW.md.
1. Utwórz plik zadania docs/zadania/001-fundament.md z szablonu.
2. Poproś agenta architekt o strukturę katalogów, listę paczek (z uzasadnieniem w docs/DECYZJE.md)
   i podział Etapu 0 na mniejsze zadania 001a, 001b...
3. Pokaż mi plan i poczekaj na moją akceptację, zanim backend zacznie instalować.
```

Po akceptacji:
```
Realizuj zadanie 001a zgodnie ze standardowym przepływem z CLAUDE.md (rozdział 6).
Po każdym agencie pokaż mi jednym zdaniem, co zrobił. Na koniec daj mi listę "Sprawdź sam".
```

## 2. Każda kolejna funkcja (szablon)

```
Następne zadanie: [NAZWA, np. "Profil publiczny fachowca" ze SPECYFIKACJI pkt 4].
Utwórz docs/zadania/NNN-[nazwa].md i przeprowadź pełny przepływ:
architekt → ux-designer → backend → frontend → tester → audytor-bezpieczenstwa.
Jeśli któryś agent ma pytania biznesowe lub prawne — zatrzymaj się i zapytaj mnie.
Na koniec zaktualizuj dokumentację techniczną i CHANGELOG, zrób commit na gałęzi feature.
```

## 3. Naprawa błędu

```
Błąd: [opis, co kliknąłem, co się stało, treść błędu / zrzut ekranu].
Najpierw ustal przyczynę (nie zgaduj — przeczytaj logi w storage/logs/laravel.log).
Napisz test, który odtwarza błąd, popraw go, uruchom wszystkie testy.
Wyjaśnij mi prostymi słowami, co było nie tak.
```

## 4. Zmiana wyglądu

```
Poproś ux-designer o ocenę ekranu [adres]: czy wygląda jak zrobiony przez AI, co poprawić,
czy jest czytelny na telefonie. Pokaż mi propozycje, a po mojej akceptacji niech frontend je wdroży.
```

## 5. Przegląd bezpieczeństwa przed wdrożeniem

```
Przygotowujemy wdrożenie na home.pl. Poproś audytor-bezpieczenstwa o pełny audyt całej aplikacji
(nie tylko ostatnich zmian) według jego listy kontrolnej, z raportem w docs/audyty/.
Poprawki KRYTYCZNE i WYSOKIE zleć backend/frontend, potem ponowny audyt tych punktów.
```

## 6. Wdrożenie na home.pl

```
Przeprowadź mnie krok po kroku przez wdrożenie na home.pl według rozdziału "Wdrożenie"
w docs/DOKUMENTACJA_TECHNICZNA.md: kopia zapasowa, paczka, wgranie, .env produkcyjny,
katalog public/, migracje, cron, Cloudflare. Przy każdym kroku napisz, jak sprawdzić, że się udało.
Niczego nie wykonuj na serwerze bez mojego potwierdzenia.
```

## 7. Utrzymanie (raz w miesiącu)

```
Miesięczny przegląd: composer audit, npm audit, dostępne aktualizacje Laravela i paczek,
wynik testów, rozmiar bazy i kopii, błędy w logach z ostatniego miesiąca.
Zaproponuj, co zaktualizować, i oceń ryzyko każdej aktualizacji.
```

## 8. Przekazanie programiście

```
Przygotuj docs/PRZEKAZANIE.md dla zewnętrznego programisty: architektura, najważniejsze decyzje
(z DECYZJE.md), znane problemy i dług techniczny, jak uruchomić lokalnie, jak wdrożyć,
gdzie są testy, jak działają agenci i pliki zadań.
```

## Dobre nawyki
- Jedno zadanie na raz. Krótkie zadania = mniej błędów.
- Zawsze proś o "Sprawdź sam" i naprawdę klikaj.
- Gdy agent "odpływa" od zasad, napisz: "Wróć do CLAUDE.md, rozdział X".
- Po dłuższej przerwie zacznij od: "Przeczytaj CHANGELOG.md i ostatni plik w docs/zadania — gdzie skończyliśmy?"
